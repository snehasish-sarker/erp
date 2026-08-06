<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;

final class SalesInvoicePolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->can('sales_invoices.view');
    }

    public function view(
        User $user,
        SalesInvoice $salesInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            salesInvoice: $salesInvoice,
        ) && $user->can('sales_invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sales_invoices.create');
    }

    public function update(
        User $user,
        SalesInvoice $salesInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            salesInvoice: $salesInvoice,
        )
            && $salesInvoice->canBeEdited()
            && $user->can('sales_invoices.create');
    }

    public function delete(
        User $user,
        SalesInvoice $salesInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            salesInvoice: $salesInvoice,
        )
            && $salesInvoice->canBeDeleted()
            && $user->can('sales_invoices.create');
    }

    public function post(
        User $user,
        SalesInvoice $salesInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            salesInvoice: $salesInvoice,
        )
            && $salesInvoice->canBePosted()
            && $user->can('sales_invoices.post');
    }

    public function reverse(
        User $user,
        SalesInvoice $salesInvoice,
    ): bool {
        return $this->canAccess(
            user: $user,
            salesInvoice: $salesInvoice,
        )
            && $salesInvoice->canBeReversed()
            && $user->can('sales_invoices.reverse');
    }

    public function print(
        User $user,
        SalesInvoice $salesInvoice,
    ): bool {
        return $this->view(
            user: $user,
            salesInvoice: $salesInvoice,
        );
    }

    private function canAccess(
        User $user,
        SalesInvoice $salesInvoice,
    ): bool {
        return (int) $user->tenant_id
            === (int) $salesInvoice->tenant_id
            && $this->branchAccessService
                ->accessibleBranches(
                    user: $user,
                    activeOnly: false,
                )
                ->contains(
                    'id',
                    (int) $salesInvoice->branch_id,
                );
    }
}