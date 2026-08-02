<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('users.view');
    }

    public function view(
        User $actor,
        User $managedUser,
    ): bool {
        return $this->belongsToSameTenant($actor, $managedUser)
            && $actor->can('users.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('users.create');
    }

    public function update(
        User $actor,
        User $managedUser,
    ): bool {
        return $this->belongsToSameTenant($actor, $managedUser)
            && $actor->can('users.update');
    }

    public function delete(
        User $actor,
        User $managedUser,
    ): bool {
        return $this->belongsToSameTenant($actor, $managedUser)
            && $actor->can('users.delete');
    }

    public function changeStatus(
        User $actor,
        User $managedUser,
    ): bool {
        return $this->belongsToSameTenant($actor, $managedUser)
            && $actor->can('users.change_status');
    }

    public function resetPassword(
        User $actor,
        User $managedUser,
    ): bool {
        return $this->belongsToSameTenant($actor, $managedUser)
            && $actor->can('users.reset_password');
    }

    private function belongsToSameTenant(
        User $actor,
        User $managedUser,
    ): bool {
        return (int) $actor->tenant_id
            === (int) $managedUser->tenant_id;
    }
}