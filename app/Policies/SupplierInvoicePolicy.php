<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Purchasing\SupplierInvoiceStatusRegistry;

final class SupplierInvoicePolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
        private readonly SupplierInvoiceStatusRegistry $statusRegistry,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('supplier_invoices.view');
    }

    public function view(
        User $user,
        SupplierInvoice $supplierInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierInvoice: $supplierInvoice,
        ) && $user->can('supplier_invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->can('supplier_invoices.create');
    }

    public function update(
        User $user,
        SupplierInvoice $supplierInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierInvoice: $supplierInvoice,
        )
            && $supplierInvoice->canBeEdited()
            && $user->can('supplier_invoices.update');
    }

    public function delete(
        User $user,
        SupplierInvoice $supplierInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierInvoice: $supplierInvoice,
        )
            && $supplierInvoice->canBeDeleted()
            && $user->can('supplier_invoices.delete');
    }

    public function validate(
        User $user,
        SupplierInvoice $supplierInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierInvoice: $supplierInvoice,
        )
            && $this->statusRegistry->canValidate(
                $supplierInvoice->status,
            )
            && $user->can('supplier_invoices.validate');
    }

    public function returnToDraft(
        User $user,
        SupplierInvoice $supplierInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierInvoice: $supplierInvoice,
        )
            && $this->statusRegistry->canReturnToDraft(
                $supplierInvoice->status,
            )
            && $user->can('supplier_invoices.validate');
    }

    public function approve(
        User $user,
        SupplierInvoice $supplierInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierInvoice: $supplierInvoice,
        )
            && $this->statusRegistry->canApprove(
                $supplierInvoice->status,
            )
            && $user->can('supplier_invoices.approve');
    }

    public function dispute(
        User $user,
        SupplierInvoice $supplierInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierInvoice: $supplierInvoice,
        )
            && $this->statusRegistry->canDispute(
                $supplierInvoice->status,
            )
            && $user->can('supplier_invoices.dispute');
    }

    public function cancel(
        User $user,
        SupplierInvoice $supplierInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierInvoice: $supplierInvoice,
        )
            && $this->statusRegistry->canCancel(
                $supplierInvoice->status,
            )
            && $user->can('supplier_invoices.cancel');
    }

    public function post(
        User $user,
        SupplierInvoice $supplierInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierInvoice: $supplierInvoice,
        )
            && $this->statusRegistry->canPost(
                $supplierInvoice->status,
            )
            && $user->can('supplier_invoices.post');
    }

    public function reverse(
        User $user,
        SupplierInvoice $supplierInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            supplierInvoice: $supplierInvoice,
        )
            && $this->statusRegistry->canReverse(
                $supplierInvoice->status,
            )
            && $user->can('supplier_invoices.reverse');
    }

    private function canAccess(
        User $user,
        SupplierInvoice $supplierInvoice,
    ): bool {
        if (
            (int) $user->tenant_id
            !== (int) $supplierInvoice->tenant_id
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
                (int) $supplierInvoice->branch_id,
            );
    }
}