<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ExportRequest;
use App\Models\Tenant;
use App\Models\TenantFile;
use App\Services\Auditing\AuditLogService;
use App\Services\Files\TenantFileStorageService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ExpireExportRequests extends Command
{
    /**
     * @var string
     */
    protected $signature =
        'exports:expire
        {--limit=500 : Maximum exports to expire per tenant}';

    /**
     * @var string
     */
    protected $description =
        'Expire completed export files after their retention period.';

    public function handle(
        TenantContext $tenantContext,
        TenantFileStorageService $fileStorageService,
        AuditLogService $auditLogService,
    ): int {
        $limit = max(
            1,
            (int) $this->option('limit'),
        );

        $expiredCount = 0;
        $failedTenantCount = 0;

        Tenant::query()
            ->select([
                'id',
                'name',
                'code',
                'status',
                'timezone',
            ])
            ->orderBy('id')
            ->chunkById(
                50,

                function (
                    $tenants,
                ) use (
                    $tenantContext,
                    $fileStorageService,
                    $auditLogService,
                    $limit,
                    &$expiredCount,
                    &$failedTenantCount,
                ): void {
                    foreach ($tenants as $tenant) {
                        if (!$tenant instanceof Tenant) {
                            continue;
                        }

                        $tenantContext->set($tenant);

                        try {
                            $exportIds =
                                ExportRequest::query()
                                    ->where(
                                        'status',
                                        'completed',
                                    )
                                    ->whereNotNull(
                                        'expires_at',
                                    )
                                    ->where(
                                        'expires_at',
                                        '<=',
                                        now(),
                                    )
                                    ->orderBy('id')
                                    ->limit($limit)
                                    ->pluck('id');

                            foreach (
                                $exportIds
                                as $exportId
                            ) {
                                $expired =
                                    $this->expireOne(
                                        exportRequestId:
                                            (int) $exportId,

                                        fileStorageService:
                                            $fileStorageService,

                                        auditLogService:
                                            $auditLogService,
                                    );

                                if ($expired) {
                                    $expiredCount++;
                                }
                            }
                        } catch (
                            Throwable $exception
                        ) {
                            $failedTenantCount++;
                            report($exception);

                            $this->error(
                                sprintf(
                                    'Failed to expire exports for tenant %s (%d).',
                                    $tenant->code,
                                    $tenant->getKey(),
                                ),
                            );
                        } finally {
                            $tenantContext->clear();
                        }
                    }
                },
            );

        $this->info(
            sprintf(
                'Expired %d export request(s).',
                $expiredCount,
            ),
        );

        return $failedTenantCount === 0
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function expireOne(
        int $exportRequestId,
        TenantFileStorageService $fileStorageService,
        AuditLogService $auditLogService,
    ): bool {
        return DB::transaction(
            function () use (
                $exportRequestId,
                $fileStorageService,
                $auditLogService,
            ): bool {
                $exportRequest =
                    ExportRequest::query()
                        ->whereKey(
                            $exportRequestId,
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    !$exportRequest
                        instanceof ExportRequest
                    || $exportRequest->status
                        !== 'completed'
                    || $exportRequest->expires_at
                        === null
                    || $exportRequest
                        ->expires_at
                        ->isFuture()
                ) {
                    return false;
                }

                $tenantFile =
                    $exportRequest->file;

                if (
                    $tenantFile instanceof TenantFile
                    && $tenantFile->isActive()
                ) {
                    $fileStorageService->detach(
                        $tenantFile,
                    );

                    $fileStorageService->delete(
                        $tenantFile,
                    );
                }

                $exportRequest->tenant_file_id =
                    null;

                $exportRequest->status =
                    'expired';

                $exportRequest->saveQuietly();

                $auditLogService
                    ->recordCustomEvent(
                        subject: $exportRequest,
                        event: 'export_expired',

                        oldValues: [
                            'status' =>
                                'completed',
                        ],

                        newValues: [
                            'status' =>
                                'expired',

                            'tenant_file_id' =>
                                null,
                        ],
                    );

                return true;
            },
            attempts: 5,
        );
    }
}