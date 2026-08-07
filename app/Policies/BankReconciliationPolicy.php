<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BankReconciliation;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;

final class BankReconciliationPolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('bank_reconciliations.view');
    }

    public function view(User $user, BankReconciliation $reconciliation): bool
    {
        return $user->can('bank_reconciliations.view') && $this->canAccess($user, $reconciliation);
    }

    public function create(User $user): bool
    {
        return $user->can('bank_reconciliations.create');
    }

    public function match(User $user, BankReconciliation $reconciliation): bool
    {
        return $user->can('bank_reconciliations.match')
            && $reconciliation->isDraft()
            && $this->canAccess($user, $reconciliation);
    }

    public function complete(User $user, BankReconciliation $reconciliation): bool
    {
        return $user->can('bank_reconciliations.complete')
            && $reconciliation->isDraft()
            && $this->canAccess($user, $reconciliation);
    }

    public function reverse(User $user, BankReconciliation $reconciliation): bool
    {
        return $user->can('bank_reconciliations.reverse')
            && $reconciliation->isCompleted()
            && $this->canAccess($user, $reconciliation);
    }

    private function canAccess(User $user, BankReconciliation $reconciliation): bool
    {
        return (int) $user->tenant_id === (int) $reconciliation->tenant_id
            && $this->branchAccessService
                ->accessibleBranches($user, false)
                ->contains('id', (int) $reconciliation->branch_id);
    }
}
