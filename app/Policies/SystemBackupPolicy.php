<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SystemBackup;
use App\Models\User;

final class SystemBackupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('operations.backups.view');
    }

    public function view(User $user, SystemBackup $backup): bool
    {
        return $user->can('operations.backups.view');
    }

    public function verify(User $user, SystemBackup $backup): bool
    {
        return $backup->isCompleted()
            && $user->can('operations.backups.verify');
    }
}
