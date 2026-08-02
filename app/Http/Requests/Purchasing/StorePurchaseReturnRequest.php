<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\PurchaseReturn;

final class StorePurchaseReturnRequest extends PurchaseReturnRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            PurchaseReturn::class,
        ) === true;
    }
}