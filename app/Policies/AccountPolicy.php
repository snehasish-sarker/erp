<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

final class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounts.view');
    }

    public function view(
        User $user,
        Account $account,
    ): bool {
        return (int) $user->tenant_id
                === (int) $account->tenant_id
            && $user->can('accounts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('accounts.create');
    }

    public function update(
        User $user,
        Account $account,
    ): bool {
        return (int) $user->tenant_id
                === (int) $account->tenant_id
            && $user->can('accounts.update');
    }

    public function delete(
        User $user,
        Account $account,
    ): bool {
        return (int) $user->tenant_id
                === (int) $account->tenant_id
            && $user->can('accounts.delete');
    }
}