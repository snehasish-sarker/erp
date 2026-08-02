<?php

declare(strict_types=1);

namespace App\Http\Requests\Purchasing;

use App\Models\GoodsReceipt;
use Illuminate\Foundation\Http\FormRequest;

final class ReverseGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $goodsReceipt = $this->route(
            'goodsReceipt',
        );

        return $goodsReceipt instanceof GoodsReceipt
            && $this->user()?->can(
                'reverse',
                $goodsReceipt,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'reversal_reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input(
            'reversal_reason',
        );

        $this->merge([
            'reversal_reason' =>
                is_string($reason)
                    ? trim($reason)
                    : $reason,
        ]);
    }
}