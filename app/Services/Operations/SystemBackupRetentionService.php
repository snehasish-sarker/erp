<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\SystemBackup;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SystemBackupRetentionService
{
    /** @return array{pruned:int,kept:int} */
    public function prune(): array
    {
        $retentionDays = max(1, (int) config('operations.backups.retention_days', 14));
        $minimumKeep = max(1, (int) config('operations.backups.minimum_keep', 7));
        $protectedIds = SystemBackup::query()
            ->where('status', 'completed')
            ->orderByDesc('completed_at')
            ->limit($minimumKeep)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $candidates = SystemBackup::query()
            ->where('status', 'completed')
            ->where('completed_at', '<', now()->subDays($retentionDays))
            ->when($protectedIds !== [], static fn ($query) => $query->whereNotIn('id', $protectedIds))
            ->orderBy('completed_at')
            ->get();

        $pruned = 0;
        foreach ($candidates as $backup) {
            if (!$backup instanceof SystemBackup) {
                continue;
            }
            try {
                if (Storage::disk($backup->disk)->exists($backup->path)) {
                    Storage::disk($backup->disk)->delete($backup->path);
                }
                $backup->forceFill([
                    'status' => 'pruned',
                    'pruned_at' => now(),
                ])->save();
                $pruned++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return [
            'pruned' => $pruned,
            'kept' => (int) SystemBackup::query()->where('status', 'completed')->count(),
        ];
    }
}
