<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

final class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(
        User $user,
        Role $role,
    ): bool {
        return $this->belongsToSameTenant($user, $role)
            && $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(
        User $user,
        Role $role,
    ): bool {
        return $this->belongsToSameTenant($user, $role)
            && $user->can('roles.update');
    }

    public function assignPermissions(
        User $user,
        Role $role,
    ): bool {
        return $this->belongsToSameTenant($user, $role)
            && $user->can('roles.assign_permissions');
    }

    public function delete(
        User $user,
        Role $role,
    ): bool {
        return $this->belongsToSameTenant($user, $role)
            && $user->can('roles.delete');
    }

    private function belongsToSameTenant(
        User $user,
        Role $role,
    ): bool {
        return (int) $user->tenant_id
            === (int) $role->tenant_id;
    }
}