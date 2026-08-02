<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\GoodsReceipt;

final class StoreGoodsReceiptRequest extends GoodsReceiptRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'create',
            GoodsReceipt::class,
        ) === true;
    }
}