<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;

final class PurchaseOrderPolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->can(
            'purchase_orders.view',
        );
    }

    public function view(
        User $user,
        PurchaseOrder $purchaseOrder,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            purchaseOrder: $purchaseOrder,
        )
            && $this->canAccessBranch(
                user: $user,
                purchaseOrder: $purchaseOrder,
            )
            && $user->can(
                'purchase_orders.view',
            );
    }

    public function create(User $user): bool
    {
        return $user->can(
            'purchase_orders.create',
        );
    }

    public function update(
        User $user,
        PurchaseOrder $purchaseOrder,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            purchaseOrder: $purchaseOrder,
        )
            && $this->canAccessBranch(
                user: $user,
                purchaseOrder: $purchaseOrder,
            )
            && $purchaseOrder->isDraft()
            && $user->can(
                'purchase_orders.update',
            );
    }

    public function delete(
        User $user,
        PurchaseOrder $purchaseOrder,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            purchaseOrder: $purchaseOrder,
        )
            && $this->canAccessBranch(
                user: $user,
                purchaseOrder: $purchaseOrder,
            )
            && $purchaseOrder->isDraft()
            && !$purchaseOrder->hasReceipts()
            && $user->can(
                'purchase_orders.delete',
            );
    }

    public function submit(
        User $user,
        PurchaseOrder $purchaseOrder,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            purchaseOrder: $purchaseOrder,
        )
            && $this->canAccessBranch(
                user: $user,
                purchaseOrder: $purchaseOrder,
            )
            && $purchaseOrder->isDraft()
            && $user->can(
                'purchase_orders.submit',
            );
    }

    public function returnToDraft(
        User $user,
        PurchaseOrder $purchaseOrder,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            purchaseOrder: $purchaseOrder,
        )
            && $this->canAccessBranch(
                user: $user,
                purchaseOrder: $purchaseOrder,
            )
            && $purchaseOrder->isSubmitted()
            && $user->can(
                'purchase_orders.update',
            );
    }

    public function approve(
        User $user,
        PurchaseOrder $purchaseOrder,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            purchaseOrder: $purchaseOrder,
        )
            && $this->canAccessBranch(
                user: $user,
                purchaseOrder: $purchaseOrder,
            )
            && $purchaseOrder->isSubmitted()
            && $user->can(
                'purchase_orders.approve',
            );
    }

    public function cancel(
        User $user,
        PurchaseOrder $purchaseOrder,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            purchaseOrder: $purchaseOrder,
        )
            && $this->canAccessBranch(
                user: $user,
                purchaseOrder: $purchaseOrder,
            )
            && in_array(
                $purchaseOrder->status,
                [
                    'draft',
                    'submitted',
                    'approved',
                    'partially_received',
                ],
                true,
            )
            && $user->can(
                'purchase_orders.cancel',
            );
    }

    private function belongsToSameTenant(
        User $user,
        PurchaseOrder $purchaseOrder,
    ): bool {
        return (int) $user->tenant_id
            === (int) $purchaseOrder->tenant_id;
    }

    private function canAccessBranch(
        User $user,
        PurchaseOrder $purchaseOrder,
    ): bool {
        return $this->branchAccessService
            ->accessibleBranches(
                user: $user,
                activeOnly: false,
            )
            ->contains(
                'id',
                (int) $purchaseOrder->branch_id,
            );
    }
}