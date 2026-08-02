<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductCategory;
use App\Models\User;

final class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(
            'product_categories.view',
        );
    }

    public function view(
        User $user,
        ProductCategory $productCategory,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            productCategory: $productCategory,
        ) && $user->can(
            'product_categories.view',
        );
    }

    public function create(User $user): bool
    {
        return $user->can(
            'product_categories.create',
        );
    }

    public function update(
        User $user,
        ProductCategory $productCategory,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            productCategory: $productCategory,
        ) && $user->can(
            'product_categories.update',
        );
    }

    public function delete(
        User $user,
        ProductCategory $productCategory,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            productCategory: $productCategory,
        ) && $user->can(
            'product_categories.delete',
        );
    }

    private function belongsToSameTenant(
        User $user,
        ProductCategory $productCategory,
    ): bool {
        return (int) $user->tenant_id
            === (int) $productCategory->tenant_id;
    }
}