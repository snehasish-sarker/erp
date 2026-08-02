<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AccountingPeriod;
use App\Models\User;

final class AccountingPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounting_periods.view');
    }

    public function view(
        User $user,
        AccountingPeriod $accountingPeriod,
    ): bool {
        return $user->tenant_id
                === $accountingPeriod->tenant_id
            && $user->can('accounting_periods.view');
    }

    public function close(
        User $user,
        AccountingPeriod $accountingPeriod,
    ): bool {
        return $user->tenant_id
                === $accountingPeriod->tenant_id
            && $user->can('accounting_periods.close');
    }

    public function reopen(
        User $user,
        AccountingPeriod $accountingPeriod,
    ): bool {
        return $user->tenant_id
                === $accountingPeriod->tenant_id
            && $user->can('accounting_periods.reopen');
    }
}