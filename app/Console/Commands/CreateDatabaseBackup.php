<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operations\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

final class CreateDatabaseBackup extends Command
{
    protected $signature = 'erp:backup:create {--scheduled : Mark the backup as scheduler initiated} {--verify : Verify immediately after creation}';

    protected $description = 'Create a compressed MySQL/MariaDB ERP database backup.';

    public function handle(DatabaseBackupService $backupService): int
    {
        if ($this->option('scheduled') && !(bool) config('operations.backups.enabled', false)) {
            $this->info('Scheduled ERP backups are disabled.');

            return self::SUCCESS;
        }

        try {
            $backup = $backupService->create($this->option('scheduled') ? 'scheduled' : 'manual');
            $this->info(sprintf('Backup #%d created: %s', $backup->getKey(), $backup->filename));
            if ($this->option('verify')) {
                $result = $backupService->verify($backup);
                $this->line(($result['passed'] ? '[PASS] ' : '[FAIL] ').$result['message']);

                return $result['passed'] ? self::SUCCESS : self::FAILURE;
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
