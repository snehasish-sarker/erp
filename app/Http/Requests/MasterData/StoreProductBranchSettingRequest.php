<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProductBranchSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            && $this->user()?->can(
                'update',
                $product,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'branch_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'status' => [
                'required',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],

            'is_purchasable' => [
                'required',
                'boolean',
            ],

            'is_sellable' => [
                'required',
                'boolean',
            ],

            'selling_price' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->input('branch_id'),

            'status' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'status',
                        'active',
                    ),
                ),
            ),

            'is_purchasable' => $this->boolean(
                'is_purchasable',
            ),

            'is_sellable' => $this->boolean(
                'is_sellable',
            ),

            'selling_price' => $this->filled(
                'selling_price',
            )
                ? trim(
                    (string) $this->input(
                        'selling_price',
                    ),
                )
                : null,
        ]);
    }
}