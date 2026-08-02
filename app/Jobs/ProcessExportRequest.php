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

            $definition = $exportRegistry->get(
                $exportRequest->export_type,
            );

            $filters =
                $definition->validateFilters(
                    $exportRequest->filters ?? [],
                );

            $totalRows =
                $definition->totalRows(
                    $filters,
                );

            $temporaryPath =
                $this->createTemporaryFile();

            $rowsExported = $this->writeCsv(
                path: $temporaryPath,
                definition: $definition,
                filters: $filters,
                totalRows: $totalRows,
            );

            $requester = User::withTrashed()
                ->where(
                    'tenant_id',
                    $this->tenantId,
                )
                ->whereKey(
                    $exportRequest
                        ->requested_by_user_id,
                )
                ->first();

            $fileName = $this->fileName(
                exportRequest: $exportRequest,
                tenant: $tenant,
            );

            $uploadedFile = new UploadedFile(
                $temporaryPath,
                $fileName,
                'text/csv',
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
                $definition->rows($filters)
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
                static fn (
                    string|int|float|null $value,
                ): string|int|float =>
                    $value ?? '',

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

        return Str::slug(
            $exportRequest->name,
        )."-{$timestamp}.csv";
    }
}