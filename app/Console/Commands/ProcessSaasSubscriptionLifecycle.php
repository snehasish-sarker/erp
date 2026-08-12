<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Saas\SaasSubscriptionLifecycleService;
use Illuminate\Console\Command;

final class ProcessSaasSubscriptionLifecycle extends Command
{
    /**
     * @var string
     */
    protected $signature = 'saas:subscriptions:process
        {--limit= : Maximum subscriptions to process in this run}';

    /**
     * @var string
     */
    protected $description =
        'Process SaaS trial expiry, grace periods, past-due status, and automatic suspension.';

    public function handle(
        SaasSubscriptionLifecycleService $lifecycleService,
    ): int {
        $configuredLimit = max(
            1,
            (int) config(
                'saas.subscription.lifecycle_batch_limit',
                500,
            ),
        );

        $limitOption = $this->option('limit');

        $limit = is_numeric($limitOption)
            ? max(1, min((int) $limitOption, 5000))
            : $configuredLimit;

        $summary = $lifecycleService
            ->processDueSubscriptions($limit);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Examined', $summary['examined']],
                ['Updated', $summary['updated']],
                ['Trials initialized', $summary['trial_initialized']],
                ['Moved to past due', $summary['past_due']],
                ['Suspended', $summary['suspended']],
            ],
        );

        return self::SUCCESS;
    }
}