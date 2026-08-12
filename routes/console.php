<?php

declare(strict_types=1);

use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command(
    'inspire',
    function (): void {
        $this->comment(
            Inspiring::quote(),
        );
    },
)->purpose('Display an inspiring quote');

Schedule::command(
    'operations:heartbeat scheduler',
)
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::job(
    new RecordQueueHeartbeat(),
)
    ->everyFiveMinutes()
    ->withoutOverlapping(10);

Schedule::command(
    'exports:expire --limit=500',
)
    ->dailyAt('02:00')
    ->withoutOverlapping(30);

Schedule::command(
    'notifications:prune --limit=5000',
)
    ->dailyAt('02:30')
    ->withoutOverlapping(30);

Schedule::command(
    'management:dispatch-scheduled-reports --limit=100',
)
    ->hourly()
    ->withoutOverlapping(55);

Schedule::command(
    'erp:backup:create --scheduled --verify',
)
    ->dailyAt(
        (string) config(
            'operations.backups.schedule_time',
            '01:00',
        ),
    )
    ->when(
        static fn (): bool =>
            (bool) config(
                'operations.backups.enabled',
                false,
            ),
    )
    ->withoutOverlapping(120);

Schedule::command(
    'erp:backup:prune',
)
    ->dailyAt('02:15')
    ->when(
        static fn (): bool =>
            (bool) config(
                'operations.backups.enabled',
                false,
            ),
    )
    ->withoutOverlapping(30);

Schedule::command(
    'erp:storage:cleanup',
)
    ->dailyAt(
        (string) config(
            'operations.cleanup.schedule_time',
            '03:00',
        ),
    )
    ->withoutOverlapping(30);
Schedule::command(
    sprintf(
        'saas:subscriptions:process --limit=%d',
        max(
            1,
            (int) config(
                'saas.subscription.lifecycle_batch_limit',
                500,
            ),
        ),
    ),
)
    ->hourly()
    ->withoutOverlapping(55);