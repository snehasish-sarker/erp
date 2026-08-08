<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SystemBackup;
use App\Services\Operations\DatabaseRestoreVerificationService;
use Illuminate\Console\Command;

final class CheckDatabaseRestoreReadiness extends Command
{
    protected $signature = 'erp:backup:restore-check {backup : System backup numeric ID}';

    protected $description = 'Verify that a backup is suitable for an isolated restore drill without executing a restore.';

    public function handle(DatabaseRestoreVerificationService $service): int
    {
        $id = (string) $this->argument('backup');
        if (!ctype_digit($id) || (int) $id < 1) {
            $this->error('Backup ID must be a positive integer.');

            return self::INVALID;
        }
        $backup = SystemBackup::query()->find((int) $id);
        if (!$backup instanceof SystemBackup) {
            $this->error('Backup record was not found.');

            return self::FAILURE;
        }

        $report = $service->inspect($backup);
        $this->line(($report['ready_for_restore_drill'] ? '[READY]' : '[BLOCKED]').' isolated restore drill');
        $this->line('Integrity: '.$report['integrity']['message']);
        $this->line('MySQL client: '.($report['mysql_client_available'] ? 'available' : 'missing'));
        foreach ($report['restore_drill'] as $index => $step) {
            $this->line(sprintf('%d. %s', $index + 1, $step));
        }
        $this->warn($report['safety']);

        return $report['ready_for_restore_drill'] ? self::SUCCESS : self::FAILURE;
    }
}
