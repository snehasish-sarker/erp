<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\PurchaseReturn;

final class UpdatePurchaseReturnRequest extends PurchaseReturnRequest
{
    public function authorize(): bool
    {
        $purchaseReturn = $this->route(
            'purchaseReturn',
        );

        return $purchaseReturn
                instanceof PurchaseReturn
            && $this->user()?->can(
                'update',
                $purchaseReturn,
            ) === true;
    }
}