<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductionAcceptanceRun;
use App\Models\User;

final class ProductionAcceptanceRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('production_acceptance.view');
    }

    public function view(User $user, ProductionAcceptanceRun $run): bool
    {
        return (int) $user->tenant_id === (int) $run->tenant_id
            && $user->can('production_acceptance.view');
    }

    public function run(User $user): bool
    {
        return $user->can('production_acceptance.run');
    }
}
