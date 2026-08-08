<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operations\SystemBackupRetentionService;
use Illuminate\Console\Command;

final class PruneDatabaseBackups extends Command
{
    protected $signature = 'erp:backup:prune';

    protected $description = 'Prune ERP database backups according to configured retention rules.';

    public function handle(SystemBackupRetentionService $retentionService): int
    {
        $result = $retentionService->prune();
        $this->info(sprintf('Pruned %d backup(s); %d completed backup(s) remain.', $result['pruned'], $result['kept']));

        return self::SUCCESS;
    }
}
