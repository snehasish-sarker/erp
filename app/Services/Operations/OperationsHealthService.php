<?php

declare(strict_types=1);

namespace App\Services\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class OperationsHealthService
{
    public function __construct(
        private readonly OperationsRuntimeStateService $runtimeStateService,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $checks = [];

        $this->attempt(
            $checks,
            'database.connection',
            'Database connection',
            true,
            function (): array {
                DB::connection()->getPdo();

                return [true, 'Database connection is available.'];
            },
        );

        $this->attempt(
            $checks,
            'cache.roundtrip',
            'Cache read/write',
            true,
            function (): array {
                $key = 'operations-health-'.Str::uuid()->toString();
                Cache::put($key, 'ok', 30);
                $passed = Cache::get($key) === 'ok';
                Cache::forget($key);

                return [$passed, $passed ? 'Cache read/write roundtrip succeeded.' : 'Cache read/write roundtrip failed.'];
            },
        );

        $queueDriver = (string) config('queue.default', 'sync');
        $production = app()->environment('production');
        $checks[] = $this->result(
    'queue.driver',
    'Queue connection',
    !$production || $queueDriver !== 'sync',
    true,
    $production && $queueDriver === 'sync'
        ? 'Production requires a durable asynchronous queue connection.'
        : 'Queue connection: '.$queueDriver.'.',
);

        $queuedJobs = Schema::hasTable('jobs')
            ? (int) DB::table('jobs')->count()
            : 0;
        $queueWarning = max(1, (int) config('operations.health.queued_job_warning', 500));
        $checks[] = $this->result(
            'queue.depth',
            'Queued job depth',
            $queuedJobs < $queueWarning,
            false,
            sprintf('%d queued job(s); warning threshold is %d.', $queuedJobs, $queueWarning),
        );

        $failedJobs = Schema::hasTable('failed_jobs')
            ? (int) DB::table('failed_jobs')->count()
            : 0;
        $failedWarning = max(1, (int) config('operations.health.failed_job_warning', 1));
        $checks[] = $this->result(
            'queue.failed_jobs',
            'Failed queue jobs',
            $failedJobs < $failedWarning,
            false,
            sprintf('%d failed job(s) are currently recorded.', $failedJobs),
        );

        $scheduler = $this->runtimeStateService->find('scheduler.heartbeat');
        $schedulerStale = max(1, (int) config('operations.health.scheduler_stale_minutes', 3));
        $schedulerFresh = $scheduler?->touched_at !== null
            && $scheduler->touched_at->greaterThanOrEqualTo(now()->subMinutes($schedulerStale));
        $checks[] = $this->result(
            'scheduler.heartbeat',
            'Scheduler heartbeat',
            $schedulerFresh,
            true,
            $scheduler?->touched_at === null
                ? 'No scheduler heartbeat has been recorded.'
                : 'Last scheduler heartbeat: '.$scheduler->touched_at->toIso8601String().'.',
        );

        if ($queueDriver !== 'sync') {
            $worker = $this->runtimeStateService->find('queue.heartbeat');
            $workerStale = max(1, (int) config('operations.health.queue_stale_minutes', 10));
            $workerFresh = $worker?->touched_at !== null
                && $worker->touched_at->greaterThanOrEqualTo(now()->subMinutes($workerStale));
            $checks[] = $this->result(
                'queue.heartbeat',
                'Queue worker heartbeat',
                $workerFresh,
                true,
                $worker?->touched_at === null
                    ? 'No queue worker heartbeat has been processed.'
                    : 'Last queue worker heartbeat: '.$worker->touched_at->toIso8601String().'.',
            );
        }

        $storagePath = storage_path();
        $totalBytes = @disk_total_space($storagePath);
        $freeBytes = @disk_free_space($storagePath);
        $usedPercent = null;
        if (is_float($totalBytes) && $totalBytes > 0 && is_float($freeBytes)) {
            $usedPercent = round((($totalBytes - $freeBytes) / $totalBytes) * 100, 2);
        }
        $storageWarning = min(99, max(1, (int) config('operations.health.storage_warning_percent', 85)));
        $checks[] = $this->result(
            'storage.capacity',
            'Storage capacity',
            $usedPercent === null || $usedPercent < $storageWarning,
            true,
            $usedPercent === null
                ? 'Storage capacity could not be measured.'
                : sprintf('Storage usage is %.2f%%; warning threshold is %d%%.', $usedPercent, $storageWarning),
        );

        $checks[] = $this->result(
            'storage.writable',
            'Laravel storage write access',
            is_writable(storage_path()) && is_writable(storage_path('framework')),
            true,
            'Laravel storage and framework directories must be writable.',
        );

        $checks[] = $this->result(
            'application.maintenance',
            'Maintenance mode',
            !app()->isDownForMaintenance(),
            false,
            app()->isDownForMaintenance()
                ? 'Application is currently in maintenance mode.'
                : 'Application is accepting normal traffic.',
        );

        foreach (['pdo', 'openssl', 'mbstring', 'json'] as $extension) {
            $checks[] = $this->result(
                'php.extension.'.$extension,
                'PHP extension: '.$extension,
                extension_loaded($extension),
                true,
                extension_loaded($extension)
                    ? 'Extension is loaded.'
                    : 'Required PHP extension is missing.',
            );
        }

        $checks[] = $this->result(
            'php.extension.zip',
            'PHP extension: zip',
            class_exists('ZipArchive'),
            false,
            class_exists('ZipArchive')
                ? 'ZipArchive is available for XLSX exports.'
                : 'ZipArchive is unavailable; XLSX generation will fail.',
        );

        $criticalFailures = count(array_filter(
            $checks,
            static fn (array $check): bool => $check['status'] === 'failed' && $check['critical'] === true,
        ));
        $warnings = count(array_filter(
            $checks,
            static fn (array $check): bool => $check['status'] === 'failed' && $check['critical'] === false,
        ));

        return [
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
            'environment' => app()->environment(),
            'summary' => [
                'checks' => count($checks),
                'passed' => count(array_filter($checks, static fn (array $check): bool => $check['status'] === 'passed')),
                'critical_failures' => $criticalFailures,
                'warnings' => $warnings,
                'healthy' => $criticalFailures === 0,
            ],
            'checks' => $checks,
            'metrics' => [
                'queue_driver' => $queueDriver,
                'queued_jobs' => $queuedJobs,
                'failed_jobs' => $failedJobs,
                'storage_used_percent' => $usedPercent,
                'php_version' => PHP_VERSION,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $checks
     * @param callable():array{0:bool,1:string} $callback
     */
    private function attempt(
        array &$checks,
        string $key,
        string $label,
        bool $critical,
        callable $callback,
    ): void {
        try {
            [$passed, $message] = $callback();
            $checks[] = $this->result($key, $label, $passed, $critical, $message);
        } catch (Throwable $exception) {
            report($exception);
            $checks[] = $this->result($key, $label, false, $critical, 'Check failed with an internal error.');
        }
    }

    /** @return array<string, mixed> */
    private function result(
        string $key,
        string $label,
        bool $passed,
        bool $critical,
        string $message,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $passed ? 'passed' : 'failed',
            'critical' => $critical,
            'message' => $message,
        ];
    }
}
