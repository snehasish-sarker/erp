<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\SystemBackup;
use App\Services\Auditing\AuditLogService;
use App\Services\Operations\DatabaseBackupService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SystemBackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backupService,
        private readonly AuditLogService $auditLogService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SystemBackup::class);
        $perPage = max(10, min(100, (int) $request->integer('per_page', 25)));
        $paginator = SystemBackup::query()
            ->with(['requestedBy:id,name', 'requestedTenant:id,code,name'])
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Operations/Backups/Index', [
            'backups' => [
                'data' => $paginator->getCollection()->map(
                    static fn (SystemBackup $backup): array => [
                        'id' => (int) $backup->getKey(),
                        'filename' => $backup->filename,
                        'scope' => $backup->scope,
                        'initiated_by' => $backup->initiated_by,
                        'size_bytes' => $backup->size_bytes,
                        'checksum_sha256' => $backup->checksum_sha256,
                        'status' => $backup->status,
                        'verification_status' => $backup->verification_status,
                        'verification_message' => $backup->verification_message,
                        'started_at' => $backup->started_at?->toIso8601String(),
                        'completed_at' => $backup->completed_at?->toIso8601String(),
                        'verified_at' => $backup->verified_at?->toIso8601String(),
                        'pruned_at' => $backup->pruned_at?->toIso8601String(),
                        'requested_by' => $backup->requestedBy === null ? null : [
                            'id' => (int) $backup->requestedBy->getKey(),
                            'name' => $backup->requestedBy->name,
                        ],
                        'requested_tenant' => $backup->requestedTenant === null ? null : [
                            'id' => (int) $backup->requestedTenant->getKey(),
                            'code' => $backup->requestedTenant->code,
                            'name' => $backup->requestedTenant->name,
                        ],
                        'can_verify' => Gate::allows('verify', $backup),
                    ],
                )->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
            'configuration' => [
                'enabled' => (bool) config('operations.backups.enabled', false),
                'retention_days' => (int) config('operations.backups.retention_days', 14),
                'minimum_keep' => (int) config('operations.backups.minimum_keep', 7),
                'schedule_time' => (string) config('operations.backups.schedule_time', '01:00'),
            ],
        ]);
    }

    public function verify(SystemBackup $systemBackup): JsonResponse|RedirectResponse
    {
        Gate::authorize('verify', $systemBackup);
        $oldStatus = $systemBackup->verification_status;
        $result = $this->backupService->verify($systemBackup);

        $this->auditLogService->recordCustomEvent(
            subject: $systemBackup,
            event: 'system_backup_verified',
            oldValues: ['verification_status' => $oldStatus],
            newValues: ['verification_status' => $result['passed'] ? 'passed' : 'failed'],
        );

        return $result['passed']
            ? $this->responseService->success($result['message'])
            : $this->responseService->error($result['message'], code: 'BACKUP_VERIFICATION_FAILED');
    }
}
