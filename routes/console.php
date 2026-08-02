<?php

declare(strict_types=1);

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
    'exports:expire --limit=500',
)
    ->dailyAt('02:00')
    ->withoutOverlapping(30);

Schedule::command(
    'notifications:prune --limit=5000',
)
    ->dailyAt('02:30')
    ->withoutOverlapping(30);