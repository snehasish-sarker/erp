<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PurchaseReturn;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Purchasing\PurchaseReturnStatusRegistry;

final class PurchaseReturnPolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
        private readonly PurchaseReturnStatusRegistry $statusRegistry,
    ) {
    }

    public function viewAny(
        User $user,
    ): bool {
        return $user->can(
            'purchase_returns.view',
        );
    }

    public function view(
        User $user,
        PurchaseReturn $purchaseReturn,
    ): bool {
        return $this->canAccess(
            user: $user,
            purchaseReturn: $purchaseReturn,
        )
            && $user->can(
                'purchase_returns.view',
            );
    }

    public function create(
        User $user,
    ): bool {
        return $user->can(
            'purchase_returns.create',
        );
    }

    public function update(
        User $user,
        PurchaseReturn $purchaseReturn,
    ): bool {
        return $this->canAccess(
            user: $user,
            purchaseReturn: $purchaseReturn,
        )
            && $this->statusRegistry
                ->isEditable(
                    $purchaseReturn->status,
                )
            && $user->can(
                'purchase_returns.update',
            );
    }

    public function delete(
        User $user,
        PurchaseReturn $purchaseReturn,
    ): bool {
        return $this->canAccess(
            user: $user,
            purchaseReturn: $purchaseReturn,
        )
            && $purchaseReturn->canBeDeleted()
            && $user->can(
                'purchase_returns.delete',
            );
    }

    public function submit(
        User $user,
        PurchaseReturn $purchaseReturn,
    ): bool {
        return $this->canAccess(
            user: $user,
            purchaseReturn: $purchaseReturn,
        )
            && $this->statusRegistry
                ->canSubmit(
                    $purchaseReturn->status,
                )
            && $user->can(
                'purchase_returns.submit',
            );
    }

    public function returnToDraft(
        User $user,
        PurchaseReturn $purchaseReturn,
    ): bool {
        return $this->canAccess(
            user: $user,
            purchaseReturn: $purchaseReturn,
        )
            && $this->statusRegistry
                ->canReturnToDraft(
                    $purchaseReturn->status,
                )
            && $user->can(
                'purchase_returns.submit',
            );
    }

    public function approve(
        User $user,
        PurchaseReturn $purchaseReturn,
    ): bool {
        return $this->canAccess(
            user: $user,
            purchaseReturn: $purchaseReturn,
        )
            && $this->statusRegistry
                ->canApprove(
                    $purchaseReturn->status,
                )
            && $user->can(
                'purchase_returns.approve',
            );
    }

    public function post(
        User $user,
        PurchaseReturn $purchaseReturn,
    ): bool {
        return $this->canAccess(
            user: $user,
            purchaseReturn: $purchaseReturn,
        )
            && $this->statusRegistry
                ->canPost(
                    $purchaseReturn->status,
                )
            && $user->can(
                'purchase_returns.post',
            );
    }

    public function reverse(
        User $user,
        PurchaseReturn $purchaseReturn,
    ): bool {
        return $this->canAccess(
            user: $user,
            purchaseReturn: $purchaseReturn,
        )
            && $this->statusRegistry
                ->canReverse(
                    $purchaseReturn->status,
                )
            && $user->can(
                'purchase_returns.reverse',
            );
    }

    public function cancel(
        User $user,
        PurchaseReturn $purchaseReturn,
    ): bool {
        return $this->canAccess(
            user: $user,
            purchaseReturn: $purchaseReturn,
        )
            && $this->statusRegistry
                ->canCancel(
                    $purchaseReturn->status,
                )
            && $user->can(
                'purchase_returns.cancel',
            );
    }

    private function canAccess(
        User $user,
        PurchaseReturn $purchaseReturn,
    ): bool {
        if (
            (int) $user->tenant_id
            !== (int) $purchaseReturn->tenant_id
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
                (int) $purchaseReturn->branch_id,
            );
    }
}