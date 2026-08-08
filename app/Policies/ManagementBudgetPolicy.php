<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ManagementBudget;
use App\Models\User;

final class ManagementBudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('management_budgets.view');
    }

    public function view(User $user, ManagementBudget $budget): bool
    {
        return $user->can('management_budgets.view')
            && (int) $user->tenant_id === (int) $budget->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->can('management_budgets.create');
    }

    public function update(User $user, ManagementBudget $budget): bool
    {
        return $budget->isDraft()
            && $user->can('management_budgets.update')
            && (int) $user->tenant_id === (int) $budget->tenant_id;
    }

    public function delete(User $user, ManagementBudget $budget): bool
    {
        return $budget->isDraft()
            && $user->can('management_budgets.delete')
            && (int) $user->tenant_id === (int) $budget->tenant_id;
    }

    public function approve(User $user, ManagementBudget $budget): bool
    {
        return $budget->isDraft()
            && $user->can('management_budgets.approve')
            && (int) $user->tenant_id === (int) $budget->tenant_id;
    }

    public function reopen(User $user, ManagementBudget $budget): bool
    {
        return $budget->isApproved()
            && $user->can('management_budgets.reopen')
            && (int) $user->tenant_id === (int) $budget->tenant_id;
    }
}
