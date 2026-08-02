<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

final class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('warehouses.view');
    }

    public function view(
        User $user,
        Warehouse $warehouse,
    ): bool {
        return $user->tenant_id === $warehouse->tenant_id
            && $user->can('warehouses.view');
    }

    public function create(User $user): bool
    {
        return $user->can('warehouses.create');
    }

    public function update(
        User $user,
        Warehouse $warehouse,
    ): bool {
        return $user->tenant_id === $warehouse->tenant_id
            && $user->can('warehouses.update');
    }

    public function delete(
        User $user,
        Warehouse $warehouse,
    ): bool {
        return $user->tenant_id === $warehouse->tenant_id
            && $user->can('warehouses.delete');
    }
}