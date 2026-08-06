<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;

final class SalesOrderPolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->can(
            'sales_orders.view',
        );
    }

    public function view(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->canAccess(
            $user,
            $salesOrder,
        )
            && $user->can(
                'sales_orders.view',
            );
    }

    public function create(User $user): bool
    {
        return $user->can(
            'sales_orders.create',
        );
    }

    public function update(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->canAccess(
            $user,
            $salesOrder,
        )
            && $salesOrder->isDraft()
            && $user->can(
                'sales_orders.update',
            );
    }

    public function delete(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->canAccess(
            $user,
            $salesOrder,
        )
            && $salesOrder->isDraft()
            && !$salesOrder->hasAllocations()
            && !$salesOrder->hasDispatches()
            && !$salesOrder->hasInvoices()
            && $user->can(
                'sales_orders.delete',
            );
    }

    public function submit(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->canAccess(
            $user,
            $salesOrder,
        )
            && $salesOrder->isDraft()
            && $user->can(
                'sales_orders.submit',
            );
    }

    public function returnToDraft(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->canAccess(
            $user,
            $salesOrder,
        )
            && $salesOrder->isSubmitted()
            && $user->can(
                'sales_orders.update',
            );
    }

    public function approve(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->canAccess(
            $user,
            $salesOrder,
        )
            && $salesOrder->isSubmitted()
            && $user->can(
                'sales_orders.approve',
            );
    }

    public function viewAllocation(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->canAccess(
            $user,
            $salesOrder,
        )
            && $salesOrder->isApproved()
            && $user->can(
                'sales_orders.allocate',
            );
    }

    public function allocate(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->canAccess(
            $user,
            $salesOrder,
        )
            && $salesOrder->isAllocatable()
            && !$salesOrder->hasDispatches()
            && !$salesOrder->hasInvoices()
            && $user->can(
                'sales_orders.allocate',
            );
    }

    public function releaseAllocation(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->allocate(
            $user,
            $salesOrder,
        )
            && $salesOrder
                ->hasActiveAllocation();
    }

    public function cancel(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->canAccess(
            $user,
            $salesOrder,
        )
            && in_array(
                $salesOrder->status,
                [
                    'draft',
                    'submitted',
                    'approved',
                    'partially_allocated',
                    'allocated',
                ],
                true,
            )
            && !$salesOrder->hasAllocations()
            && !$salesOrder->hasDispatches()
            && !$salesOrder->hasInvoices()
            && $user->can(
                'sales_orders.cancel',
            );
    }

    public function overridePrice(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->canAccess(
            $user,
            $salesOrder,
        )
            && $salesOrder->isDraft()
            && $user->can(
                'sales_orders.override_price',
            );
    }

    public function overrideDiscount(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->canAccess(
            $user,
            $salesOrder,
        )
            && $salesOrder->isDraft()
            && $user->can(
                'sales_orders.override_discount',
            );
    }

    private function canAccess(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->belongsToSameTenant(
            $user,
            $salesOrder,
        )
            && $this->canAccessBranch(
                $user,
                $salesOrder,
            );
    }

    private function belongsToSameTenant(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return (int) $user->tenant_id
            === (int) $salesOrder->tenant_id;
    }

    private function canAccessBranch(
        User $user,
        SalesOrder $salesOrder,
    ): bool {
        return $this->branchAccessService
            ->accessibleBranches(
                user: $user,
                activeOnly: false,
            )
            ->contains(
                'id',
                (int) $salesOrder
                    ->branch_id,
            );
    }
}