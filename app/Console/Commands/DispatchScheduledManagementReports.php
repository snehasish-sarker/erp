<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Management\ManagementReportScheduleService;
use Illuminate\Console\Command;

final class DispatchScheduledManagementReports extends Command
{
    protected $signature = 'management:dispatch-scheduled-reports {--limit=100 : Maximum due schedules to process}';

    protected $description = 'Queue due management report exports.';

    public function handle(ManagementReportScheduleService $service): int
    {
        $result = $service->dispatchDue((int) $this->option('limit'));
        $this->info(sprintf(
            'Processed %d schedule(s): %d queued, %d skipped, %d failed.',
            $result['processed'],
            $result['queued'],
            $result['skipped'],
            $result['failed'],
        ));

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}