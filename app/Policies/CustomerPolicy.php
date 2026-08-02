<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

final class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(
        User $user,
        Customer $customer,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            customer: $customer,
        ) && $user->can('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(
        User $user,
        Customer $customer,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            customer: $customer,
        ) && $user->can('customers.update');
    }

    public function delete(
        User $user,
        Customer $customer,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            customer: $customer,
        ) && $user->can('customers.delete');
    }

    public function viewBalance(
        User $user,
        Customer $customer,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            customer: $customer,
        ) && $user->can(
            'customers.view_balance',
        );
    }

    public function overrideCreditLimit(
        User $user,
        Customer $customer,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            customer: $customer,
        ) && $user->can(
            'customers.override_credit_limit',
        );
    }

    private function belongsToSameTenant(
        User $user,
        Customer $customer,
    ): bool {
        return (int) $user->tenant_id
            === (int) $customer->tenant_id;
    }
}