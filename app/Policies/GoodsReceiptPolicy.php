<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GoodsReceipt;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;

final class GoodsReceiptPolicy
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function viewAny(User $user): bool
    {
        return $user->can(
            'goods_receipts.view',
        );
    }

    public function view(
        User $user,
        GoodsReceipt $goodsReceipt,
    ): bool {
        return $this->sameTenant(
            user: $user,
            goodsReceipt: $goodsReceipt,
        )
            && $this->canAccessBranch(
                user: $user,
                goodsReceipt: $goodsReceipt,
            )
            && $user->can(
                'goods_receipts.view',
            );
    }

    public function create(User $user): bool
    {
        return $user->can(
            'goods_receipts.create',
        );
    }

    public function update(
        User $user,
        GoodsReceipt $goodsReceipt,
    ): bool {
        return $this->sameTenant(
            user: $user,
            goodsReceipt: $goodsReceipt,
        )
            && $this->canAccessBranch(
                user: $user,
                goodsReceipt: $goodsReceipt,
            )
            && $goodsReceipt->canBeEdited()
            && $user->can(
                'goods_receipts.update',
            );
    }

    public function post(
        User $user,
        GoodsReceipt $goodsReceipt,
    ): bool {
        return $this->sameTenant(
            user: $user,
            goodsReceipt: $goodsReceipt,
        )
            && $this->canAccessBranch(
                user: $user,
                goodsReceipt: $goodsReceipt,
            )
            && $goodsReceipt->canBePosted()
            && $user->can(
                'goods_receipts.post',
            );
    }

    public function reverse(
        User $user,
        GoodsReceipt $goodsReceipt,
    ): bool {
        return $this->sameTenant(
            user: $user,
            goodsReceipt: $goodsReceipt,
        )
            && $this->canAccessBranch(
                user: $user,
                goodsReceipt: $goodsReceipt,
            )
            && $goodsReceipt->canBeReversed()
            && $user->can(
                'goods_receipts.reverse',
            );
    }

    private function sameTenant(
        User $user,
        GoodsReceipt $goodsReceipt,
    ): bool {
        return (int) $user->tenant_id
            === (int) $goodsReceipt->tenant_id;
    }

    private function canAccessBranch(
        User $user,
        GoodsReceipt $goodsReceipt,
    ): bool {
        return $this->branchAccessService
            ->accessibleBranches(
                user: $user,
                activeOnly: false,
            )
            ->contains(
                'id',
                (int) $goodsReceipt->branch_id,
            );
    }

    public function delete(
        User $user,
        GoodsReceipt $goodsReceipt,
    ): bool {
        return $this->sameTenant(
            user: $user,
            goodsReceipt: $goodsReceipt,
        )
            && $this->canAccessBranch(
                user: $user,
                goodsReceipt: $goodsReceipt,
            )
            && $goodsReceipt->canBeDeleted()
            && $user->can(
                'goods_receipts.delete',
            );
    }
}