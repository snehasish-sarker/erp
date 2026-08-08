<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Operations\OperationsRuntimeStateService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class RecordQueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function handle(OperationsRuntimeStateService $runtimeStateService): void
    {
        $runtimeStateService->touch('queue.heartbeat', [
            'queue' => $this->queue ?? 'default',
        ]);
    }
}