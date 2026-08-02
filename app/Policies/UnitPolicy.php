<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

final class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('units.view');
    }

    public function view(
        User $user,
        Unit $unit,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            unit: $unit,
        ) && $user->can('units.view');
    }

    public function create(User $user): bool
    {
        return $user->can('units.create');
    }

    public function update(
        User $user,
        Unit $unit,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            unit: $unit,
        ) && $user->can('units.update');
    }

    public function delete(
        User $user,
        Unit $unit,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            unit: $unit,
        ) && $user->can('units.delete');
    }

    private function belongsToSameTenant(
        User $user,
        Unit $unit,
    ): bool {
        return (int) $user->tenant_id
            === (int) $unit->tenant_id;
    }
}