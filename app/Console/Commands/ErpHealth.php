<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operations\OperationsHealthService;
use Illuminate\Console\Command;

final class ErpHealth extends Command
{
    protected $signature = 'erp:health {--json : Output machine-readable JSON}';

    protected $description = 'Run live ERP application, queue, scheduler, cache, and storage health checks.';

    public function handle(OperationsHealthService $healthService): int
    {
        $report = $healthService->run();
        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return ($report['summary']['healthy'] ?? false) === true ? self::SUCCESS : self::FAILURE;
        }

        foreach ($report['checks'] as $check) {
            $marker = $check['status'] === 'passed' ? 'PASS' : ($check['critical'] ? 'FAIL' : 'WARN');
            $this->line(sprintf('[%s] %s: %s', $marker, $check['label'], $check['message']));
        }

        return ($report['summary']['healthy'] ?? false) === true ? self::SUCCESS : self::FAILURE;
    }
}
