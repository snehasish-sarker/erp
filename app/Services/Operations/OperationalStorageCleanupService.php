<?php

declare(strict_types=1);

namespace App\Services\Operations;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class OperationalStorageCleanupService
{
    public function __construct(
        private readonly SystemBackupRetentionService $backupRetentionService,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(bool $dryRun = false): array
    {
        $expiredExports = (int) DB::table('export_requests')
            ->where('status', 'completed')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->count();

        $temporaryFiles = $this->temporaryBackupFiles($dryRun);
        $backupResult = ['pruned' => 0, 'kept' => 0];
        $exportExitCode = null;

        if (!$dryRun) {
            $exportExitCode = Artisan::call('exports:expire', ['--limit' => 500]);
            $backupResult = $this->backupRetentionService->prune();
        }

        return [
            'dry_run' => $dryRun,
            'expired_exports_detected' => $expiredExports,
            'export_expire_exit_code' => $exportExitCode,
            'temporary_backup_files' => $temporaryFiles,
            'backup_retention' => $backupResult,
        ];
    }

    /** @return array{detected:int,deleted:int} */
    private function temporaryBackupFiles(bool $dryRun): array
    {
        $disk = (string) config('operations.backups.disk', 'operations_private');
        $directory = trim((string) config('operations.backups.directory', 'backups'), '/');
        $hours = max(1, (int) config('operations.cleanup.temporary_file_hours', 24));
        $detected = 0;
        $deleted = 0;

        try {
            $storage = Storage::disk($disk);
            foreach ($storage->files($directory) as $file) {
                if (!str_ends_with($file, '.part.sql')) {
                    continue;
                }
                if ($storage->lastModified($file) > now()->subHours($hours)->getTimestamp()) {
                    continue;
                }
                $detected++;
                if (!$dryRun && $storage->delete($file)) {
                    $deleted++;
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return ['detected' => $detected, 'deleted' => $deleted];
    }
}
