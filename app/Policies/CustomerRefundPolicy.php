<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerRefund;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\CustomerSettlementStatusRegistry;

final class CustomerRefundPolicy
{
    public function __construct(private readonly BranchAccessService $branchAccessService, private readonly CustomerSettlementStatusRegistry $statusRegistry)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('customer_refunds.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customer_refunds.create');
    }

    public function view(User $user, CustomerRefund $document): bool
    {
        return $this->access($user, $document) && $user->can('customer_refunds.view');
    }

    public function update(User $user, CustomerRefund $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->isEditable($document->status) && $user->can('customer_refunds.update');
    }

    public function delete(User $user, CustomerRefund $document): bool
    {
        return $this->access($user, $document) && $document->canBeDeleted() && $user->can('customer_refunds.delete');
    }

    public function submit(User $user, CustomerRefund $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canSubmit($document->status) && $user->can('customer_refunds.submit');
    }

    public function returnToDraft(User $user, CustomerRefund $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canReturnToDraft($document->status) && $user->can('customer_refunds.submit');
    }

    public function approve(User $user, CustomerRefund $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canApprove($document->status) && $user->can('customer_refunds.approve');
    }

    public function post(User $user, CustomerRefund $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canPost($document->status) && $user->can('customer_refunds.post');
    }

    public function cancel(User $user, CustomerRefund $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canCancel($document->status) && $user->can('customer_refunds.cancel');
    }

    public function reverse(User $user, CustomerRefund $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canReverse($document->status) && $user->can('customer_refunds.reverse');
    }

    public function print(User $user, CustomerRefund $document): bool
    {
        return $this->view($user, $document);
    }

    private function access(User $user, CustomerRefund $document): bool
    {
        return (int) $user->tenant_id === (int) $document->tenant_id && $this->branchAccessService->accessibleBranches($user, false)->contains('id', (int) $document->branch_id);
    }
}