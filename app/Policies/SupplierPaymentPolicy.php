<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\SupplierPaymentStatusRegistry;

final class SupplierPaymentPolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
        private readonly SupplierPaymentStatusRegistry $statusRegistry,
    ) {
    }

    public function viewAny(
        User $user,
    ): bool {
        return $user->can(
            'supplier_payments.view',
        );
    }

    public function view(
        User $user,
        SupplierPayment $supplierPayment,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierPayment: $supplierPayment,
        )
            && $user->can(
                'supplier_payments.view',
            );
    }

    public function create(
        User $user,
    ): bool {
        return $user->can(
            'supplier_payments.create',
        );
    }

    public function update(
        User $user,
        SupplierPayment $supplierPayment,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierPayment: $supplierPayment,
        )
            && $this->statusRegistry->isEditable(
                $supplierPayment->status,
            )
            && $user->can(
                'supplier_payments.update',
            );
    }

    public function delete(
        User $user,
        SupplierPayment $supplierPayment,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierPayment: $supplierPayment,
        )
            && $supplierPayment->canBeDeleted()
            && $user->can(
                'supplier_payments.delete',
            );
    }

    public function submit(
        User $user,
        SupplierPayment $supplierPayment,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierPayment: $supplierPayment,
        )
            && $this->statusRegistry->canSubmit(
                $supplierPayment->status,
            )
            && $user->can(
                'supplier_payments.submit',
            );
    }

    public function returnToDraft(
        User $user,
        SupplierPayment $supplierPayment,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierPayment: $supplierPayment,
        )
            && $this->statusRegistry->canReturnToDraft(
                $supplierPayment->status,
            )
            && $user->can(
                'supplier_payments.submit',
            );
    }

    public function approve(
        User $user,
        SupplierPayment $supplierPayment,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierPayment: $supplierPayment,
        )
            && $this->statusRegistry->canApprove(
                $supplierPayment->status,
            )
            && $user->can(
                'supplier_payments.approve',
            );
    }

    public function cancel(
        User $user,
        SupplierPayment $supplierPayment,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierPayment: $supplierPayment,
        )
            && $this->statusRegistry->canCancel(
                $supplierPayment->status,
            )
            && $user->can(
                'supplier_payments.cancel',
            );
    }

    public function post(
        User $user,
        SupplierPayment $supplierPayment,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierPayment: $supplierPayment,
        )
            && $this->statusRegistry->canPost(
                $supplierPayment->status,
            )
            && $user->can(
                'supplier_payments.post',
            );
    }

    public function reverse(
        User $user,
        SupplierPayment $supplierPayment,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierPayment: $supplierPayment,
        )
            && $this->statusRegistry->canReverse(
                $supplierPayment->status,
            )
            && $user->can(
                'supplier_payments.reverse',
            );
    }

    private function canAccess(
        User $user,
        SupplierPayment $supplierPayment,
    ): bool {
        if (
            (int) $user->tenant_id
            !== (int) $supplierPayment->tenant_id
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
                (int) $supplierPayment->branch_id,
            );
    }
}