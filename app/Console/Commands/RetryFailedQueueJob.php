<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operations\FailedJobOperationsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RetryFailedQueueJob extends Command
{
    protected $signature = 'erp:queue:retry {uuid : Failed-job UUID}';

    protected $description = 'Retry one failed ERP queue job from the trusted server console.';

    public function handle(FailedJobOperationsService $service): int
    {
        $uuid = trim((string) $this->argument('uuid'));
        try {
            $service->retry($uuid);
            Log::notice('ERP failed queue job retried by console operator.', ['failed_job_uuid' => $uuid]);
            $this->info('Failed job was returned to the queue.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
