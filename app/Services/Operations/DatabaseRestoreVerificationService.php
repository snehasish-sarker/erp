<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\SystemBackup;
use Symfony\Component\Process\ExecutableFinder;

final class DatabaseRestoreVerificationService
{
    public function __construct(
        private readonly DatabaseBackupService $backupService,
    ) {
    }

    /** @return array<string, mixed> */
    public function inspect(SystemBackup $backup): array
    {
        $integrity = $this->backupService->verify($backup);
        $mysqlBinary = (new ExecutableFinder())->find('mysql');

        return [
            'ready_for_restore_drill' => $integrity['passed'] && $mysqlBinary !== null,
            'integrity' => $integrity,
            'mysql_client_available' => $mysqlBinary !== null,
            'restore_drill' => [
                'Create an isolated disposable database with no production application traffic.',
                'Restore the verified SQL backup into only that disposable database.',
                'Run migration-status, critical-table, accounting-balance, and tenant-count checks.',
                'Record the restore duration and verification result.',
                'Drop the disposable restore database after verification.',
            ],
            'safety' => 'This command does not execute a restore and never targets the production database.',
        ];
    }
}
