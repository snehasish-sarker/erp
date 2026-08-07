<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerArAdjustment;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\CustomerSettlementStatusRegistry;

final class CustomerArAdjustmentPolicy
{
    public function __construct(private readonly BranchAccessService $branchAccessService, private readonly CustomerSettlementStatusRegistry $statusRegistry)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('customer_ar_adjustments.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customer_ar_adjustments.create');
    }

    public function view(User $user, CustomerArAdjustment $document): bool
    {
        return $this->access($user, $document) && $user->can('customer_ar_adjustments.view');
    }

    public function update(User $user, CustomerArAdjustment $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->isEditable($document->status) && $user->can('customer_ar_adjustments.update');
    }

    public function delete(User $user, CustomerArAdjustment $document): bool
    {
        return $this->access($user, $document) && $document->canBeDeleted() && $user->can('customer_ar_adjustments.delete');
    }

    public function submit(User $user, CustomerArAdjustment $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canSubmit($document->status) && $user->can('customer_ar_adjustments.submit');
    }

    public function returnToDraft(User $user, CustomerArAdjustment $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canReturnToDraft($document->status) && $user->can('customer_ar_adjustments.submit');
    }

    public function approve(User $user, CustomerArAdjustment $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canApprove($document->status) && $user->can('customer_ar_adjustments.approve');
    }

    public function post(User $user, CustomerArAdjustment $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canPost($document->status) && $user->can('customer_ar_adjustments.post');
    }

    public function cancel(User $user, CustomerArAdjustment $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canCancel($document->status) && $user->can('customer_ar_adjustments.cancel');
    }

    public function reverse(User $user, CustomerArAdjustment $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canReverse($document->status) && $user->can('customer_ar_adjustments.reverse');
    }

    private function access(User $user, CustomerArAdjustment $document): bool
    {
        return (int) $user->tenant_id === (int) $document->tenant_id && $this->branchAccessService->accessibleBranches($user, false)->contains('id', (int) $document->branch_id);
    }
}