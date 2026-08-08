<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operations\OperationsRuntimeStateService;
use Illuminate\Console\Command;

final class OperationsHeartbeat extends Command
{
    protected $signature = 'operations:heartbeat {component=scheduler : Runtime component name}';

    protected $description = 'Record an ERP operations runtime heartbeat.';

    public function handle(OperationsRuntimeStateService $runtimeStateService): int
    {
        $component = trim((string) $this->argument('component'));
        if ($component === '') {
            $this->error('Component name is required.');

            return self::INVALID;
        }

        $state = $runtimeStateService->touch($component.'.heartbeat', [
            'source' => 'artisan',
        ]);
        if ($state === null) {
            $this->error('Operations runtime-state table is not available. Run migrations first.');

            return self::FAILURE;
        }
        $this->info('Recorded '.$component.' heartbeat.');

        return self::SUCCESS;
    }
}
