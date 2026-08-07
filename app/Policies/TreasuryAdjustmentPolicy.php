<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TreasuryAdjustment;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;

final class TreasuryAdjustmentPolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('treasury_adjustments.view');
    }

    public function view(User $user, TreasuryAdjustment $adjustment): bool
    {
        return $user->can('treasury_adjustments.view') && $this->canAccess($user, $adjustment);
    }

    public function create(User $user): bool
    {
        return $user->can('treasury_adjustments.create');
    }

    public function update(User $user, TreasuryAdjustment $adjustment): bool
    {
        return $user->can('treasury_adjustments.update')
            && $adjustment->canBeEdited()
            && $this->canAccess($user, $adjustment);
    }

    public function delete(User $user, TreasuryAdjustment $adjustment): bool
    {
        return $user->can('treasury_adjustments.delete')
            && $adjustment->canBeDeleted()
            && $this->canAccess($user, $adjustment);
    }

    public function submit(User $user, TreasuryAdjustment $adjustment): bool
    {
        return $user->can('treasury_adjustments.submit')
            && $adjustment->isDraft()
            && $this->canAccess($user, $adjustment);
    }

    public function returnToDraft(User $user, TreasuryAdjustment $adjustment): bool
    {
        return $user->can('treasury_adjustments.update')
            && $adjustment->isSubmitted()
            && $this->canAccess($user, $adjustment);
    }

    public function approve(User $user, TreasuryAdjustment $adjustment): bool
    {
        return $user->can('treasury_adjustments.approve')
            && $adjustment->isSubmitted()
            && $this->canAccess($user, $adjustment);
    }

    public function post(User $user, TreasuryAdjustment $adjustment): bool
    {
        return $user->can('treasury_adjustments.post')
            && $adjustment->isApproved()
            && $this->canAccess($user, $adjustment);
    }

    public function cancel(User $user, TreasuryAdjustment $adjustment): bool
    {
        return $user->can('treasury_adjustments.cancel')
            && in_array($adjustment->status, ['draft', 'submitted', 'approved'], true)
            && $this->canAccess($user, $adjustment);
    }

    public function reverse(User $user, TreasuryAdjustment $adjustment): bool
    {
        return $user->can('treasury_adjustments.reverse')
            && $adjustment->isPosted()
            && $this->canAccess($user, $adjustment);
    }

    private function canAccess(User $user, TreasuryAdjustment $adjustment): bool
    {
        return (int) $user->tenant_id === (int) $adjustment->tenant_id
            && $this->branchAccessService
                ->accessibleBranches($user, false)
                ->contains('id', (int) $adjustment->branch_id);
    }
}
