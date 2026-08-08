<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Operations\OperationalStorageCleanupService;
use Illuminate\Console\Command;

final class CleanOperationalStorage extends Command
{
    protected $signature = 'erp:storage:cleanup {--dry-run : Report cleanup candidates without deleting anything}';

    protected $description = 'Expire export files, prune old backups, and remove stale temporary backup files.';

    public function handle(OperationalStorageCleanupService $cleanupService): int
    {
        $result = $cleanupService->run((bool) $this->option('dry-run'));
        $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
