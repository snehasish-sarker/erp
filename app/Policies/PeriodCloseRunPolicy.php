<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PeriodCloseRun;
use App\Models\User;

final class PeriodCloseRunPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('financial_control.view');
    }

    public function view(User $user, PeriodCloseRun $run): bool
    {
        return (int) $user->tenant_id === (int) $run->tenant_id
            && $user->can('financial_control.view');
    }

    public function prepare(User $user): bool
    {
        return $user->can('period_close.prepare');
    }

    public function close(User $user): bool
    {
        return $user->can('period_close.close');
    }

    public function reopen(User $user): bool
    {
        return $user->can('period_close.reopen');
    }
}
