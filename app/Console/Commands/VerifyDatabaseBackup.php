<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SystemBackup;
use App\Services\Operations\DatabaseBackupService;
use Illuminate\Console\Command;

final class VerifyDatabaseBackup extends Command
{
    protected $signature = 'erp:backup:verify {backup : System backup numeric ID}';

    protected $description = 'Verify an ERP database backup checksum and compressed SQL readability.';

    public function handle(DatabaseBackupService $backupService): int
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

        $result = $backupService->verify($backup);
        $this->line(($result['passed'] ? '[PASS] ' : '[FAIL] ').$result['message']);

        return $result['passed'] ? self::SUCCESS : self::FAILURE;
    }
}
