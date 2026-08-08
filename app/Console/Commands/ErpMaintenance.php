<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

final class ErpMaintenance extends Command
{
    protected $signature = 'erp:maintenance {action : status, enable, or disable} {--retry=60 : Retry-After value} {--refresh=15 : Maintenance refresh interval} {--secret= : Optional maintenance bypass secret}';

    protected $description = 'Inspect or change ERP maintenance mode from the server console.';

    public function handle(): int
    {
        $action = strtolower(trim((string) $this->argument('action')));
        if ($action === 'status') {
            $this->line(app()->isDownForMaintenance() ? 'Maintenance mode: enabled' : 'Maintenance mode: disabled');

            return self::SUCCESS;
        }
        if ($action === 'disable') {
            $exitCode = Artisan::call('up');
            $this->line(Artisan::output());

            return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
        }
        if ($action !== 'enable') {
            $this->error('Action must be status, enable, or disable.');

            return self::INVALID;
        }

        $parameters = [
            '--retry' => max(1, (int) $this->option('retry')),
            '--refresh' => max(1, (int) $this->option('refresh')),
        ];
        $secret = trim((string) $this->option('secret'));
        if ($secret !== '') {
            $parameters['--secret'] = $secret;
        }
        $exitCode = Artisan::call('down', $parameters);
        $this->line(Artisan::output());

        return $exitCode === 0 ? self::SUCCESS : self::FAILURE;
    }
}
