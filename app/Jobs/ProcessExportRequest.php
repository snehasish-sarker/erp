<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ExportRequest;
use App\Models\Tenant;
use App\Models\TenantFile;
use App\Models\User;
use App\Services\Auditing\AuditLogService;
use App\Services\Files\TenantFileStorageService;
use App\Support\Exports\ExportDefinition;
use App\Support\Exports\ExportRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Throwable;
use ZipArchive;

#[Tries(3)]
#[Timeout(1200)]
final class ProcessExportRequest implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $exportRequestId,
    ) {
        $queue = config(
            'exports.queue',
            'exports',
        );

        if (
            is_string($queue)
            && $queue !== ''
        ) {
            $this->onQueue($queue);
        }
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [
            60,
            300,
        ];
    }

    public function handle(
        TenantContext $tenantContext,
        ExportRegistry $exportRegistry,
        TenantFileStorageService $fileStorageService,
        AuditLogService $auditLogService,
    ): void {
        $tenant = Tenant::query()
            ->whereKey($this->tenantId)
            ->firstOrFail();

        if ($tenant->status !== 'active') {
            throw new LogicException(
                'Exports cannot be processed for an inactive tenant.',
            );
        }

        $tenantContext->set($tenant);

        $temporaryPath = null;
        $storedFile = null;

        try {
            $exportRequest = $this->startExport(
                auditLogService: $auditLogService,
                attempt: $this->attempts(),
            );

            if (
                !$exportRequest
                    instanceof ExportRequest
            ) {
                return;
            }

            $requester = User::query()
                ->where(
                    'tenant_id',
                    $this->tenantId,
                )
                ->whereKey(
                    $exportRequest
                        ->requested_by_user_id,
                )
                ->where(
                    'status',
                    'active',
                )
                ->first();

            if (!$requester instanceof User) {
                throw new LogicException(
                    'The export requester is no longer an active tenant user.',
                );
            }

            $definition = $exportRegistry->get(
                $exportRequest->export_type,
            );

            if (
                !$requester->can('exports.create')
                || !$requester->can(
                    $definition->requiredPermission(),
                )
            ) {
                throw new LogicException(
                    'The export requester no longer has permission to generate this export.',
                );
            }

            $filters =
                $definition->validateFilters(
                    $exportRequest->filters ?? [],
                    $requester,
                );

            $totalRows =
                $definition->totalRows(
                    $filters,
                    $requester,
                );

            $temporaryPath =
                $this->createTemporaryFile();

            $rowsExported = match ($exportRequest->format) {
                'csv' => $this->writeCsv(
                    path: $temporaryPath,
                    definition: $definition,
                    filters: $filters,
                    requester: $requester,
                    totalRows: $totalRows,
                ),
                'xlsx' => $this->writeXlsx(
                    path: $temporaryPath,
                    definition: $definition,
                    filters: $filters,
                    requester: $requester,
                    totalRows: $totalRows,
                ),
                default => throw new LogicException(
                    'The requested export format is unsupported.',
                ),
            };

            $fileName = $this->fileName(
                exportRequest: $exportRequest,
                tenant: $tenant,
            );

            $uploadedFile = new UploadedFile(
                $temporaryPath,
                $fileName,
                $this->mimeType($exportRequest->format),
                null,
                true,
            );

            $storedFile =
                $fileStorageService->store(
                    file: $uploadedFile,
                    category: 'export_result',
                    uploader: $requester,
                    attachable: $exportRequest,

                    metadata: [
                        'export_type' =>
                            $exportRequest
                                ->export_type,

                        'export_request_id' =>
                            $exportRequest
                                ->getKey(),

                        'rows_exported' =>
                            $rowsExported,
                    ],
                );

            $this->completeExport(
                tenantFile: $storedFile,
                rowsExported: $rowsExported,
                auditLogService: $auditLogService,
            );
        } catch (Throwable $exception) {
            if ($storedFile instanceof TenantFile) {
                try {
                    $fileStorageService->detach(
                        $storedFile,
                    );

                    $fileStorageService->delete(
                        $storedFile,
                    );
                } catch (
                    Throwable $cleanupException
                ) {
                    report($cleanupException);
                }
            }

            throw $exception;
        } finally {
            if (
                is_string($temporaryPath)
                && is_file($temporaryPath)
            ) {
                @unlink($temporaryPath);
            }

            $tenantContext->clear();
        }
    }

    public function failed(
        ?Throwable $exception,
    ): void {
        $tenantContext = app(
            TenantContext::class,
        );

        $tenant = Tenant::query()
            ->whereKey($this->tenantId)
            ->first();

        if (!$tenant instanceof Tenant) {
            return;
        }

        $tenantContext->set($tenant);

        try {
            DB::transaction(
                function () use (
                    $exception,
                ): void {
                    $exportRequest =
                        ExportRequest::query()
                            ->whereKey(
                                $this
                                    ->exportRequestId,
                            )
                            ->lockForUpdate()
                            ->first();

                    if (
                        !$exportRequest
                            instanceof ExportRequest
                    ) {
                        return;
                    }

                    if (
                        in_array(
                            $exportRequest->status,
                            [
                                'completed',
                                'cancelled',
                                'expired',
                            ],
                            true,
                        )
                    ) {
                        return;
                    }

                    $oldStatus =
                        $exportRequest->status;

                    $exportRequest->status =
                        'failed';

                    $exportRequest->failed_at =
                        now();

                    $exportRequest
                        ->progress_percent = 0;

                    $exportRequest->error_code =
                        $exception === null
                            ? 'EXPORT_FAILED'
                            : Str::upper(
                                Str::snake(
                                    class_basename(
                                        $exception,
                                    ),
                                ),
                            );

                    $exportRequest->error_message =
                        'The export could not be generated. Please retry the export.';

                    $exportRequest->saveQuietly();

                    app(AuditLogService::class)
                        ->recordCustomEvent(
                            subject:
                                $exportRequest,

                            event:
                                'export_failed',

                            oldValues: [
                                'status' =>
                                    $oldStatus,
                            ],

                            newValues: [
                                'status' =>
                                    'failed',

                                'failed_at' =>
                                    $exportRequest
                                        ->failed_at,
                            ],

                            metadata: [
                                'exception_class' =>
                                    $exception === null
                                        ? null
                                        : $exception::class,
                            ],
                        );
                },
                attempts: 5,
            );
        } finally {
            $tenantContext->clear();
        }
    }

    private function startExport(
        AuditLogService $auditLogService,
        int $attempt,
    ): ?ExportRequest {
        return DB::transaction(
            function () use (
                $auditLogService,
                $attempt,
            ): ?ExportRequest {
                $exportRequest =
                    ExportRequest::query()
                        ->whereKey(
                            $this->exportRequestId,
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    !$exportRequest
                        instanceof ExportRequest
                ) {
                    return null;
                }

                $canStart =
                    $exportRequest->status
                        === 'queued'
                    || (
                        $exportRequest->status
                            === 'processing'
                        && $attempt > 1
                    );

                if (!$canStart) {
                    return null;
                }

                $oldStatus =
                    $exportRequest->status;

                $exportRequest->status =
                    'processing';

                $exportRequest
                    ->progress_percent = 1;

                $exportRequest->rows_exported = 0;
                $exportRequest->started_at = now();
                $exportRequest->completed_at = null;
                $exportRequest->failed_at = null;
                $exportRequest->error_code = null;
                $exportRequest->error_message = null;

                $exportRequest->saveQuietly();

                $auditLogService
                    ->recordCustomEvent(
                        subject: $exportRequest,
                        event: 'export_started',

                        oldValues: [
                            'status' => $oldStatus,
                        ],

                        newValues: [
                            'status' => 'processing',

                            'started_at' =>
                                $exportRequest
                                    ->started_at,
                        ],

                        metadata: [
                            'attempt' => $attempt,

                            'requested_by_user_id' =>
                                $exportRequest
                                    ->requested_by_user_id,
                        ],
                    );

                return $exportRequest->refresh();
            },
            attempts: 5,
        );
    }

    private function createTemporaryFile(): string
    {
        $path = tempnam(
            sys_get_temp_dir(),
            'erp-export-',
        );

        if (
            !is_string($path)
            || $path === ''
        ) {
            throw new RuntimeException(
                'A temporary export file could not be created.',
            );
        }

        return $path;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function writeCsv(
        string $path,
        ExportDefinition $definition,
        array $filters,
        User $requester,
        int $totalRows,
    ): int {
        $stream = fopen($path, 'wb');

        if ($stream === false) {
            throw new RuntimeException(
                'The temporary export file could not be opened.',
            );
        }

        try {
            fwrite(
                $stream,
                "\xEF\xBB\xBF",
            );

            $this->writeCsvRow(
                stream: $stream,
                values: $definition->headings(),
            );

            $rowsExported = 0;
            $lastProgress = 1;

            foreach (
                $definition->rows(
                    $filters,
                    $requester,
                )
                as $model
            ) {
                $this->writeCsvRow(
                    stream: $stream,
                    values:
                        $definition->mapRow(
                            $model,
                        ),
                );

                $rowsExported++;

                $progress = $totalRows === 0
                    ? 95
                    : min(
                        95,
                        max(
                            1,
                            (int) floor(
                                (
                                    $rowsExported
                                    / $totalRows
                                ) * 95,
                            ),
                        ),
                    );

                if (
                    $progress
                        >= $lastProgress + 5
                    || $rowsExported
                        === $totalRows
                ) {
                    $this->updateProgress(
                        rowsExported:
                            $rowsExported,

                        progress: $progress,
                    );

                    $lastProgress = $progress;
                }
            }

            if ($rowsExported === 0) {
                $this->updateProgress(
                    rowsExported: 0,
                    progress: 95,
                );
            }

            return $rowsExported;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param resource $stream
     * @param list<string|int|float|null> $values
     */
    private function writeCsvRow(
        $stream,
        array $values,
    ): void {
        $written = fputcsv(
            $stream,

            array_map(
                fn (
                    string|int|float|null $value,
                ): string|int|float =>
                    $this->safeCsvValue($value),

                $values,
            ),

            ',',
            '"',
            '',
            "\n",
        );

        if ($written === false) {
            throw new RuntimeException(
                'An export row could not be written.',
            );
        }
    }

    private function safeCsvValue(
        string|int|float|null $value,
    ): string|int|float {
        if ($value === null) {
            return '';
        }

        if (!is_string($value)) {
            return $value;
        }

        $trimmed = ltrim($value);

        if ($trimmed === '') {
            return $value;
        }

        $firstCharacter = $trimmed[0];

        $isFormulaPrefix = in_array(
            $firstCharacter,
            [
                '=',
                '@',
                "\t",
                "\r",
            ],
            true,
        );

        $isSignedNonNumeric = in_array(
            $firstCharacter,
            [
                '+',
                '-',
            ],
            true,
        ) && !is_numeric($trimmed);

        return $isFormulaPrefix
            || $isSignedNonNumeric
                ? "'{$value}"
                : $value;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function writeXlsx(
        string $path,
        ExportDefinition $definition,
        array $filters,
        User $requester,
        int $totalRows,
    ): int {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException(
                'The PHP Zip extension is required to create Excel exports.',
            );
        }

        $sheetPath = tempnam(
            sys_get_temp_dir(),
            'erp-export-sheet-',
        );

        if (!is_string($sheetPath) || $sheetPath === '') {
            throw new RuntimeException(
                'A temporary Excel worksheet could not be created.',
            );
        }

        $stream = fopen($sheetPath, 'wb');

        if ($stream === false) {
            @unlink($sheetPath);

            throw new RuntimeException(
                'The temporary Excel worksheet could not be opened.',
            );
        }

        $rowsExported = 0;

        try {
            fwrite(
                $stream,
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                .'<sheetData>',
            );

            $rowNumber = 1;
            $this->writeXlsxRow(
                stream: $stream,
                rowNumber: $rowNumber,
                values: $definition->headings(),
                heading: true,
            );

            $lastProgress = 1;

            foreach ($definition->rows($filters, $requester) as $model) {
                $rowNumber++;

                $this->writeXlsxRow(
                    stream: $stream,
                    rowNumber: $rowNumber,
                    values: $definition->mapRow($model),
                    heading: false,
                );

                $rowsExported++;

                $progress = $totalRows === 0
                    ? 95
                    : min(
                        95,
                        max(
                            1,
                            (int) floor(
                                ($rowsExported / $totalRows) * 95,
                            ),
                        ),
                    );

                if (
                    $progress >= $lastProgress + 5
                    || $rowsExported === $totalRows
                ) {
                    $this->updateProgress(
                        rowsExported: $rowsExported,
                        progress: $progress,
                    );

                    $lastProgress = $progress;
                }
            }

            fwrite($stream, '</sheetData></worksheet>');
        } finally {
            fclose($stream);
        }

        if ($rowsExported === 0) {
            $this->updateProgress(
                rowsExported: 0,
                progress: 95,
            );
        }

        if (is_file($path)) {
            @unlink($path);
        }

        $zip = new ZipArchive();
        $opened = $zip->open(
            $path,
            ZipArchive::CREATE | ZipArchive::OVERWRITE,
        );

        if ($opened !== true) {
            @unlink($sheetPath);

            throw new RuntimeException(
                'The Excel export archive could not be created.',
            );
        }

        try {
            $zip->addFromString(
                '[Content_Types].xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
                .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
                .'</Types>',
            );

            $zip->addFromString(
                '_rels/.rels',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
                .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
                .'</Relationships>',
            );

            $zip->addFromString(
                'xl/workbook.xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets>'
                .'</workbook>',
            );

            $zip->addFromString(
                'xl/_rels/workbook.xml.rels',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                .'</Relationships>',
            );

            $zip->addFromString(
                'xl/styles.xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
                .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
                .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
                .'</styleSheet>',
            );

            $zip->addFromString(
                'docProps/core.xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">'
                .'<dc:creator>ERP</dc:creator><cp:lastModifiedBy>ERP</cp:lastModifiedBy>'
                .'</cp:coreProperties>',
            );

            $zip->addFromString(
                'docProps/app.xml',
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
                .'<Application>ERP</Application>'
                .'</Properties>',
            );

            if (!$zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml')) {
                throw new RuntimeException(
                    'The Excel worksheet could not be added to the export archive.',
                );
            }
        } finally {
            $zip->close();
            @unlink($sheetPath);
        }

        return $rowsExported;
    }

    /**
     * @param resource $stream
     * @param list<string|int|float|null> $values
     */
    private function writeXlsxRow(
        $stream,
        int $rowNumber,
        array $values,
        bool $heading,
    ): void {
        fwrite($stream, '<row r="'.$rowNumber.'">');

        foreach (array_values($values) as $columnIndex => $value) {
            $reference = $this->xlsxColumnName($columnIndex + 1).$rowNumber;

            if (is_int($value) || is_float($value)) {
                fwrite(
                    $stream,
                    '<c r="'.$reference.'"><v>'.$value.'</v></c>',
                );

                continue;
            }

            $text = $value === null ? '' : (string) $value;
            $numericValue = $heading
                ? null
                : $this->xlsxNumericValue($text);

            if ($numericValue !== null) {
                fwrite(
                    $stream,
                    '<c r="'.$reference.'"><v>'.$numericValue.'</v></c>',
                );

                continue;
            }

            fwrite(
                $stream,
                '<c r="'.$reference.'" t="inlineStr" s="'
                .($heading ? '1' : '0')
                .'"><is><t xml:space="preserve">'
                .htmlspecialchars(
                    $text,
                    ENT_XML1 | ENT_QUOTES,
                    'UTF-8',
                )
                .'</t></is></c>',
            );
        }

        fwrite($stream, '</row>');
    }


    private function xlsxNumericValue(string $value): ?string
    {
        $trimmed = trim($value);

        if (
            $trimmed === ''
            || preg_match(
                '/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/',
                $trimmed,
            ) !== 1
        ) {
            return null;
        }

        $unsigned = ltrim($trimmed, '-');

        if (
            strlen($unsigned) > 1
            && $unsigned[0] === '0'
            && !str_starts_with($unsigned, '0.')
        ) {
            return null;
        }

        return $trimmed;
    }

    private function xlsxColumnName(int $column): string
    {
        $name = '';

        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function updateProgress(
        int $rowsExported,
        int $progress,
    ): void {
        ExportRequest::query()
            ->whereKey(
                $this->exportRequestId,
            )
            ->where(
                'status',
                'processing',
            )
            ->update([
                'rows_exported' =>
                    $rowsExported,

                'progress_percent' =>
                    $progress,

                'updated_at' => now(),
            ]);
    }

    private function completeExport(
        TenantFile $tenantFile,
        int $rowsExported,
        AuditLogService $auditLogService,
    ): void {
        DB::transaction(
            function () use (
                $tenantFile,
                $rowsExported,
                $auditLogService,
            ): void {
                $exportRequest =
                    ExportRequest::query()
                        ->whereKey(
                            $this->exportRequestId,
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    !$exportRequest
                        ->isProcessing()
                ) {
                    throw new LogicException(
                        'Only a processing export can be completed.',
                    );
                }

                $retentionDays = max(
                    1,
                    (int) config(
                        'exports.retention_days',
                        7,
                    ),
                );

                $exportRequest->tenant_file_id =
                    $tenantFile->getKey();

                $exportRequest->status =
                    'completed';

                $exportRequest
                    ->progress_percent = 100;

                $exportRequest->rows_exported =
                    $rowsExported;

                $exportRequest->completed_at =
                    now();

                $exportRequest->expires_at =
                    now()->addDays(
                        $retentionDays,
                    );

                $exportRequest->saveQuietly();

                $auditLogService
                    ->recordCustomEvent(
                        subject: $exportRequest,
                        event: 'export_completed',

                        oldValues: [
                            'status' =>
                                'processing',
                        ],

                        newValues: [
                            'status' =>
                                'completed',

                            'progress_percent' =>
                                100,

                            'rows_exported' =>
                                $rowsExported,

                            'completed_at' =>
                                $exportRequest
                                    ->completed_at,

                            'expires_at' =>
                                $exportRequest
                                    ->expires_at,
                        ],

                        metadata: [
                            'tenant_file_id' =>
                                $tenantFile
                                    ->getKey(),
                        ],
                    );
            },
            attempts: 5,
        );
    }

    private function fileName(
        ExportRequest $exportRequest,
        Tenant $tenant,
    ): string {
        $timestamp = now()
            ->setTimezone(
                $tenant->timezone,
            )
            ->format('Ymd-His');

        $extension = $exportRequest->format === 'xlsx'
            ? 'xlsx'
            : 'csv';

        return Str::slug(
            $exportRequest->name,
        )."-{$timestamp}.{$extension}";
    }

    private function mimeType(string $format): string
    {
        return match ($format) {
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'text/csv',
        };
    }
}