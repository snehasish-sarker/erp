<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerCreditNote;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;

final class CustomerCreditNotePolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('sales_returns.view');
    }

    public function view(
        User $user,
        CustomerCreditNote $creditNote,
    ): bool {
        return $this->canAccess($user, $creditNote)
            && $user->can('sales_returns.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sales_returns.create');
    }

    public function update(
        User $user,
        CustomerCreditNote $creditNote,
    ): bool {
        return $this->canAccess($user, $creditNote)
            && $creditNote->canBeEdited()
            && $user->can('sales_returns.create');
    }

    public function delete(
        User $user,
        CustomerCreditNote $creditNote,
    ): bool {
        return $this->canAccess($user, $creditNote)
            && $creditNote->canBeDeleted()
            && $user->can('sales_returns.create');
    }

    public function submit(
        User $user,
        CustomerCreditNote $creditNote,
    ): bool {
        return $this->canAccess($user, $creditNote)
            && $creditNote->canBeSubmitted()
            && $user->can('sales_returns.create');
    }

    public function returnToDraft(
        User $user,
        CustomerCreditNote $creditNote,
    ): bool {
        return $this->canAccess($user, $creditNote)
            && $creditNote->canReturnToDraft()
            && $user->can('sales_returns.approve');
    }

    public function approve(
        User $user,
        CustomerCreditNote $creditNote,
    ): bool {
        return $this->canAccess($user, $creditNote)
            && $creditNote->canBeApproved()
            && $user->can('sales_returns.approve');
    }

    public function cancel(
        User $user,
        CustomerCreditNote $creditNote,
    ): bool {
        return $this->canAccess($user, $creditNote)
            && $creditNote->canBeCancelled()
            && $user->can('sales_returns.create');
    }

    public function post(
        User $user,
        CustomerCreditNote $creditNote,
    ): bool {
        return $this->canAccess($user, $creditNote)
            && $creditNote->canBePosted()
            && $user->can('sales_returns.post');
    }

    public function reverse(
        User $user,
        CustomerCreditNote $creditNote,
    ): bool {
        return $this->canAccess($user, $creditNote)
            && $creditNote->canBeReversed()
            && $user->can('sales_returns.reverse');
    }

    public function print(
        User $user,
        CustomerCreditNote $creditNote,
    ): bool {
        return $this->view($user, $creditNote);
    }

    private function canAccess(
        User $user,
        CustomerCreditNote $creditNote,
    ): bool {
        return (int) $user->tenant_id
            === (int) $creditNote->tenant_id
            && $this->branchAccessService
                ->accessibleBranches(
                    user: $user,
                    activeOnly: false,
                )
                ->contains('id', (int) $creditNote->branch_id);
    }
}