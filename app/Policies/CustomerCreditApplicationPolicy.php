<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerCreditApplication;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\CustomerSettlementStatusRegistry;

final class CustomerCreditApplicationPolicy
{
    public function __construct(private readonly BranchAccessService $branchAccessService, private readonly CustomerSettlementStatusRegistry $statusRegistry,)
    {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('customer_credit_applications.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customer_credit_applications.create');
    }

    public function view(User $user, CustomerCreditApplication $document): bool
    {
        return $this->access($user, $document) && $user->can('customer_credit_applications.view');
    }

    public function update(User $user, CustomerCreditApplication $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->isEditable($document->status) && $user->can('customer_credit_applications.update');
    }

    public function delete(User $user, CustomerCreditApplication $document): bool
    {
        return $this->access($user, $document) && $document->canBeDeleted() && $user->can('customer_credit_applications.delete');
    }

    public function submit(User $user, CustomerCreditApplication $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canSubmit($document->status) && $user->can('customer_credit_applications.submit');
    }

    public function returnToDraft(User $user, CustomerCreditApplication $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canReturnToDraft($document->status) && $user->can('customer_credit_applications.submit');
    }

    public function approve(User $user, CustomerCreditApplication $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canApprove($document->status) && $user->can('customer_credit_applications.approve');
    }

    public function post(User $user, CustomerCreditApplication $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canPost($document->status) && $user->can('customer_credit_applications.post');
    }

    public function cancel(User $user, CustomerCreditApplication $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canCancel($document->status) && $user->can('customer_credit_applications.cancel');
    }

    public function reverse(User $user, CustomerCreditApplication $document): bool
    {
        return $this->access($user, $document) && $this->statusRegistry->canReverse($document->status) && $user->can('customer_credit_applications.reverse');
    }

    private function access(User $user, CustomerCreditApplication $document): bool
    {
        return (int) $user->tenant_id === (int) $document->tenant_id && $this->branchAccessService->accessibleBranches($user, false)->contains('id', (int) $document->branch_id);
    }
}