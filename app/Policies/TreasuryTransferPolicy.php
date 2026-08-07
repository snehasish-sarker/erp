<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TreasuryTransfer;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;

final class TreasuryTransferPolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('treasury_transfers.view');
    }

    public function view(User $user, TreasuryTransfer $transfer): bool
    {
        return $user->can('treasury_transfers.view') && $this->canAccess($user, $transfer);
    }

    public function create(User $user): bool
    {
        return $user->can('treasury_transfers.create');
    }

    public function update(User $user, TreasuryTransfer $transfer): bool
    {
        return $user->can('treasury_transfers.update')
            && $transfer->canBeEdited()
            && $this->canAccess($user, $transfer);
    }

    public function delete(User $user, TreasuryTransfer $transfer): bool
    {
        return $user->can('treasury_transfers.delete')
            && $transfer->canBeDeleted()
            && $this->canAccess($user, $transfer);
    }

    public function submit(User $user, TreasuryTransfer $transfer): bool
    {
        return $user->can('treasury_transfers.submit')
            && $transfer->isDraft()
            && $this->canAccess($user, $transfer);
    }

    public function returnToDraft(User $user, TreasuryTransfer $transfer): bool
    {
        return $user->can('treasury_transfers.update')
            && $transfer->isSubmitted()
            && $this->canAccess($user, $transfer);
    }

    public function approve(User $user, TreasuryTransfer $transfer): bool
    {
        return $user->can('treasury_transfers.approve')
            && $transfer->isSubmitted()
            && $this->canAccess($user, $transfer);
    }

    public function post(User $user, TreasuryTransfer $transfer): bool
    {
        return $user->can('treasury_transfers.post')
            && $transfer->isApproved()
            && $this->canAccess($user, $transfer);
    }

    public function cancel(User $user, TreasuryTransfer $transfer): bool
    {
        return $user->can('treasury_transfers.cancel')
            && in_array($transfer->status, ['draft', 'submitted', 'approved'], true)
            && $this->canAccess($user, $transfer);
    }

    public function reverse(User $user, TreasuryTransfer $transfer): bool
    {
        return $user->can('treasury_transfers.reverse')
            && $transfer->isPosted()
            && $this->canAccess($user, $transfer);
    }

    private function canAccess(User $user, TreasuryTransfer $transfer): bool
    {
        if ((int) $user->tenant_id !== (int) $transfer->tenant_id) {
            return false;
        }

        $branches = $this->branchAccessService->accessibleBranches($user, false);

        return $branches->contains('id', (int) $transfer->source_branch_id)
            && $branches->contains('id', (int) $transfer->destination_branch_id);
    }
}
