<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreProductWarehouseSettingRequest extends FormRequest
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

            'warehouse_id' => [
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

            'minimum_stock' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'reorder_level' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'maximum_stock' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'bin_location' => [
                'nullable',
                'string',
                'max:120',
            ],

            'allow_negative_stock' => [
                'required',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'branch_id' => $this->input('branch_id'),

            'warehouse_id' =>
                $this->input('warehouse_id'),

            'status' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'status',
                        'active',
                    ),
                ),
            ),

            'minimum_stock' => trim(
                (string) $this->input(
                    'minimum_stock',
                    '0',
                ),
            ),

            'reorder_level' => trim(
                (string) $this->input(
                    'reorder_level',
                    '0',
                ),
            ),

            'maximum_stock' => $this->filled(
                'maximum_stock',
            )
                ? trim(
                    (string) $this->input(
                        'maximum_stock',
                    ),
                )
                : null,

            'bin_location' => $this->filled(
                'bin_location',
            )
                ? trim(
                    (string) $this->input(
                        'bin_location',
                    ),
                )
                : null,

            'allow_negative_stock' => $this->boolean(
                'allow_negative_stock',
            ),
        ]);
    }
}