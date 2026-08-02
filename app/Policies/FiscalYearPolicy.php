<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FiscalYear;
use App\Models\User;

final class FiscalYearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting_periods.view');
    }

    public function view(
        User $user,
        FiscalYear $fiscalYear,
    ): bool {
        return $user->tenant_id === $fiscalYear->tenant_id
            && $user->can('accounting_periods.view');
    }

    public function create(User $user): bool
    {
        return $user->can(
            'accounting_periods.generate',
        );
    }
}