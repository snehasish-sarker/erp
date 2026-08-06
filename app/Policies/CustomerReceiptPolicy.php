<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CustomerReceipt;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\CustomerReceiptStatusRegistry;

final class CustomerReceiptPolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
        private readonly CustomerReceiptStatusRegistry $statusRegistry,
    ) {
    }

    public function viewAny(
        User $user,
    ): bool {
        return $user->can(
            'customer_receipts.view',
        );
    }

    public function view(
        User $user,
        CustomerReceipt $customerReceipt,
    ): bool {
        return $this->canAccess(
            user: $user,
            customerReceipt: $customerReceipt,
        )
            && $user->can(
                'customer_receipts.view',
            );
    }

    public function create(
        User $user,
    ): bool {
        return $user->can(
            'customer_receipts.create',
        );
    }

    public function update(
        User $user,
        CustomerReceipt $customerReceipt,
    ): bool {
        return $this->canAccess(
            user: $user,
            customerReceipt: $customerReceipt,
        )
            && $this->statusRegistry->isEditable(
                $customerReceipt->status,
            )
            && $user->can(
                'customer_receipts.update',
            );
    }

    public function delete(
        User $user,
        CustomerReceipt $customerReceipt,
    ): bool {
        return $this->canAccess(
            user: $user,
            customerReceipt: $customerReceipt,
        )
            && $customerReceipt->canBeDeleted()
            && $user->can(
                'customer_receipts.delete',
            );
    }

    public function submit(
        User $user,
        CustomerReceipt $customerReceipt,
    ): bool {
        return $this->canAccess(
            user: $user,
            customerReceipt: $customerReceipt,
        )
            && $this->statusRegistry->canSubmit(
                $customerReceipt->status,
            )
            && $user->can(
                'customer_receipts.submit',
            );
    }

    public function returnToDraft(
        User $user,
        CustomerReceipt $customerReceipt,
    ): bool {
        return $this->canAccess(
            user: $user,
            customerReceipt: $customerReceipt,
        )
            && $this->statusRegistry->canReturnToDraft(
                $customerReceipt->status,
            )
            && $user->can(
                'customer_receipts.submit',
            );
    }

    public function approve(
        User $user,
        CustomerReceipt $customerReceipt,
    ): bool {
        return $this->canAccess(
            user: $user,
            customerReceipt: $customerReceipt,
        )
            && $this->statusRegistry->canApprove(
                $customerReceipt->status,
            )
            && $user->can(
                'customer_receipts.approve',
            );
    }

    public function cancel(
        User $user,
        CustomerReceipt $customerReceipt,
    ): bool {
        return $this->canAccess(
            user: $user,
            customerReceipt: $customerReceipt,
        )
            && $this->statusRegistry->canCancel(
                $customerReceipt->status,
            )
            && $user->can(
                'customer_receipts.cancel',
            );
    }

    public function post(
        User $user,
        CustomerReceipt $customerReceipt,
    ): bool {
        return $this->canAccess(
            user: $user,
            customerReceipt: $customerReceipt,
        )
            && $this->statusRegistry->canPost(
                $customerReceipt->status,
            )
            && $user->can(
                'customer_receipts.post',
            );
    }

    public function print(
        User $user,
        CustomerReceipt $customerReceipt,
    ): bool {
        return $this->view(
            user: $user,
            customerReceipt: $customerReceipt,
        );
    }

    public function reverse(
        User $user,
        CustomerReceipt $customerReceipt,
    ): bool {
        return $this->canAccess(
            user: $user,
            customerReceipt: $customerReceipt,
        )
            && $this->statusRegistry->canReverse(
                $customerReceipt->status,
            )
            && $user->can(
                'customer_receipts.reverse',
            );
    }

    private function canAccess(
        User $user,
        CustomerReceipt $customerReceipt,
    ): bool {
        if (
            (int) $user->tenant_id
            !== (int) $customerReceipt->tenant_id
        ) {
            return false;
        }

        return $this->branchAccessService
            ->accessibleBranches(
                user: $user,
                activeOnly: false,
            )
            ->contains(
                'id',
                (int) $customerReceipt->branch_id,
            );
    }
}