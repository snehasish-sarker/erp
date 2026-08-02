<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

final class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('suppliers.view');
    }

    public function view(
        User $user,
        Supplier $supplier,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            supplier: $supplier,
        ) && $user->can('suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('suppliers.create');
    }

    public function update(
        User $user,
        Supplier $supplier,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            supplier: $supplier,
        ) && $user->can('suppliers.update');
    }

    public function delete(
        User $user,
        Supplier $supplier,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            supplier: $supplier,
        ) && $user->can('suppliers.delete');
    }

    private function belongsToSameTenant(
        User $user,
        Supplier $supplier,
    ): bool {
        return (int) $user->tenant_id
            === (int) $supplier->tenant_id;
    }
}