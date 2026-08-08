<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operations\FailedJobOperationsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ForgetFailedQueueJob extends Command
{
    protected $signature = 'erp:queue:forget {uuid : Failed-job UUID} {--force : Confirm permanent removal of the failed-job record}';

    protected $description = 'Forget one failed ERP queue job from the trusted server console.';

    public function handle(FailedJobOperationsService $service): int
    {
        if (!$this->option('force')) {
            $this->error('Use --force after confirming that the failed-job record is no longer required.');

            return self::INVALID;
        }

        $uuid = trim((string) $this->argument('uuid'));
        try {
            $service->forget($uuid);
            Log::warning('ERP failed queue job forgotten by console operator.', ['failed_job_uuid' => $uuid]);
            $this->info('Failed-job record was removed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
