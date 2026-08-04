<?php

declare(strict_types=1);

namespace App\Services\Exports;

use App\Jobs\ProcessExportRequest;
use App\Models\ExportRequest;
use App\Models\User;
use App\Services\Auditing\AuditLogService;
use App\Support\Exports\ExportRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class ExportRequestService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ExportRegistry $exportRegistry,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function request(
        User $requester,
        string $exportType,
        array $filters = [],
        string $format = 'csv',
    ): ExportRequest {
        $tenant = $this->tenantContext->tenant();

        if (
            (int) $requester->tenant_id
            !== (int) $tenant->getKey()
        ) {
            throw new LogicException(
                'The export requester does not belong to the active tenant.',
            );
        }

        if ($requester->status !== 'active') {
            throw new AuthorizationException(
                'Inactive users cannot request exports.',
            );
        }

        if (!$requester->can('exports.create')) {
            throw new AuthorizationException(
                'You are not authorized to create exports.',
            );
        }

        $definition = $this->exportRegistry->get(
            $exportType,
        );

        if (
            !$requester->can(
                $definition->requiredPermission(),
            )
        ) {
            throw new AuthorizationException(
                'You are not authorized to export this data.',
            );
        }

        $format = mb_strtolower(
            trim($format),
        );

        if ($format !== 'csv') {
            throw ValidationException::withMessages([
                'format' => [
                    'The selected export format is not supported.',
                ],
            ]);
        }

        $validatedFilters =
            $definition->validateFilters(
                $filters,
                $requester,
            );

        return DB::transaction(
            function () use (
                $requester,
                $definition,
                $validatedFilters,
                $format,
                $tenant,
            ): ExportRequest {
                $exportRequest =
                    ExportRequest::query()->create([
                        'requested_by_user_id' =>
                            $requester->getKey(),

                        'tenant_file_id' => null,

                        'request_key' =>
                            Str::uuid()->toString(),

                        'name' =>
                            $definition->label()
                            .' Export',

                        'export_type' =>
                            $definition->key(),

                        'format' => $format,

                        'filters' =>
                            $validatedFilters === []
                                ? null
                                : $validatedFilters,

                        'status' => 'queued',
                        'progress_percent' => 0,
                        'rows_exported' => 0,
                        'error_code' => null,
                        'error_message' => null,
                        'queued_at' => now(),
                        'started_at' => null,
                        'completed_at' => null,
                        'failed_at' => null,
                        'cancelled_at' => null,
                        'expires_at' => null,
                    ]);

                ProcessExportRequest::dispatch(
                    (int) $tenant->getKey(),

                    (int) $exportRequest
                        ->getKey(),
                )->afterCommit();

                return $exportRequest;
            },
            attempts: 5,
        );
    }

    public function cancel(
        ExportRequest $exportRequest,
        User $actor,
    ): ExportRequest {
        return DB::transaction(
            function () use (
                $exportRequest,
                $actor,
            ): ExportRequest {
                $lockedExport =
                    ExportRequest::query()
                        ->whereKey(
                            $exportRequest->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    (int) $actor->tenant_id
                    !== (int) $lockedExport
                        ->tenant_id
                ) {
                    throw new LogicException(
                        'The export request belongs to another tenant.',
                    );
                }

                if (!$lockedExport->isQueued()) {
                    throw ValidationException::withMessages([
                        'export' => [
                            'Only queued exports can be cancelled.',
                        ],
                    ]);
                }

                $lockedExport->status = 'cancelled';
                $lockedExport->cancelled_at = now();
                $lockedExport->saveQuietly();

                $this->auditLogService
                    ->recordCustomEvent(
                        subject: $lockedExport,
                        event: 'export_cancelled',

                        oldValues: [
                            'status' => 'queued',
                        ],

                        newValues: [
                            'status' => 'cancelled',

                            'cancelled_at' =>
                                $lockedExport
                                    ->cancelled_at,
                        ],

                        metadata: [
                            'cancelled_by_user_id' =>
                                $actor->getKey(),
                        ],
                    );

                return $lockedExport->refresh();
            },
            attempts: 5,
        );
    }
}