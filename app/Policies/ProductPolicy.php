<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

final class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('products.view');
    }

    public function view(
        User $user,
        Product $product,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            product: $product,
        ) && $user->can('products.view');
    }

    public function create(User $user): bool
    {
        return $user->can('products.create');
    }

    public function update(
        User $user,
        Product $product,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            product: $product,
        ) && $user->can('products.update');
    }

    public function delete(
        User $user,
        Product $product,
    ): bool {
        return $this->belongsToSameTenant(
            user: $user,
            product: $product,
        ) && $user->can('products.delete');
    }

    private function belongsToSameTenant(
        User $user,
        Product $product,
    ): bool {
        return (int) $user->tenant_id
            === (int) $product->tenant_id;
    }
}