<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\AuditLog;
use App\Models\ProductionAcceptanceRun;
use App\Models\ReleaseCandidate;
use App\Models\SystemBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class OperationsDashboardService
{
    public function __construct(
        private readonly OperationsHealthService $healthService,
        private readonly DatabasePerformanceDiagnosticsService $performanceService,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        $health = $this->healthService->run();
        $latestBackup = Schema::hasTable('system_backups')
            ? SystemBackup::query()->where('status', 'completed')->latest('completed_at')->first()
            : null;
        $latestAcceptance = Schema::hasTable('production_acceptance_runs')
            ? ProductionAcceptanceRun::query()->latest('id')->first()
            : null;
        $latestReleaseCandidate = Schema::hasTable('release_candidates')
            ? ReleaseCandidate::query()->latest('frozen_at')->latest('id')->first()
            : null;

        return [
            'health' => $health,
            'performance' => $this->performanceService->run(),
            'backup' => $latestBackup instanceof SystemBackup ? [
                'id' => (int) $latestBackup->getKey(),
                'filename' => $latestBackup->filename,
                'size_bytes' => $latestBackup->size_bytes,
                'verification_status' => $latestBackup->verification_status,
                'completed_at' => $latestBackup->completed_at?->toIso8601String(),
                'verified_at' => $latestBackup->verified_at?->toIso8601String(),
            ] : null,
            'acceptance' => $latestAcceptance instanceof ProductionAcceptanceRun ? [
                'id' => (int) $latestAcceptance->getKey(),
                'status' => $latestAcceptance->status,
                'blocking_failures' => (int) $latestAcceptance->blocking_failures,
                'completed_at' => $latestAcceptance->completed_at?->toIso8601String(),
            ] : null,
            'release_candidate' => $latestReleaseCandidate instanceof ReleaseCandidate ? [
                'id' => (int) $latestReleaseCandidate->getKey(),
                'version' => $latestReleaseCandidate->version,
                'status' => $latestReleaseCandidate->status,
                'verification_status' => $latestReleaseCandidate->verification_status,
                'frozen_at' => $latestReleaseCandidate->frozen_at?->toIso8601String(),
                'verified_at' => $latestReleaseCandidate->verified_at?->toIso8601String(),
            ] : null,
            'queue' => [
                'queued' => Schema::hasTable('jobs') ? (int) DB::table('jobs')->count() : 0,
                'failed' => Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0,
            ],
            'recent_operations_audit' => Schema::hasTable('audit_logs')
                ? AuditLog::query()
                    ->whereIn('event', [
                        'system_backup_verified',
                        'failed_job_retried',
                        'failed_job_forgotten',
                    ])
                    ->latest('created_at')
                    ->limit(12)
                    ->get(['id', 'event', 'actor_name', 'subject_label', 'created_at'])
                    ->map(static fn (AuditLog $log): array => [
                        'id' => (int) $log->getKey(),
                        'event' => $log->event,
                        'actor_name' => $log->actor_name,
                        'subject_label' => $log->subject_label,
                        'created_at' => $log->created_at?->toIso8601String(),
                    ])->values()->all()
                : [],
        ];
    }
}
