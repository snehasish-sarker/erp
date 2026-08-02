<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\GoodsReceipt;

final class UpdateGoodsReceiptRequest extends GoodsReceiptRequest
{
    public function authorize(): bool
    {
        $goodsReceipt = $this->route(
            'goodsReceipt',
        );

        return $goodsReceipt instanceof GoodsReceipt
            && $this->user()?->can(
                'update',
                $goodsReceipt,
            ) === true;
    }
}