<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Models\Product;
use App\Support\MasterData\ProductTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UpdateProductRequest extends FormRequest
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
        $canViewCost = $this->user()?->can(
            'products.view_cost',
        ) === true;

        return [
            'product_category_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'brand_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'base_unit_id' => [
                'required',
                'integer',
                'min:1',
            ],

            'name' => [
                'required',
                'string',
                'max:160',
            ],

            'sku' => [
                'required',
                'string',
                'max:80',
                'regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/',
            ],

            'slug' => [
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:120',
            ],

            'product_type' => [
                'required',
                'string',
                Rule::in(
                    app(
                        ProductTypeRegistry::class,
                    )->keys(),
                ),
            ],

            'description' => [
                'nullable',
                'string',
                'max:4000',
            ],

            'cost_price' => [
                Rule::excludeIf(!$canViewCost),
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'selling_price' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999999999.999999',
                'decimal:0,6',
            ],

            'is_purchasable' => [
                'required',
                'boolean',
            ],

            'is_sellable' => [
                'required',
                'boolean',
            ],

            'status' => [
                'required',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = trim(
            (string) $this->input('slug'),
        );

        $this->merge([
            'product_category_id' => $this->input(
                'product_category_id',
            ),

            'brand_id' => $this->filled('brand_id')
                ? $this->input('brand_id')
                : null,

            'base_unit_id' => $this->input(
                'base_unit_id',
            ),

            'name' => trim(
                (string) $this->input('name'),
            ),

            'sku' => mb_strtoupper(
                trim(
                    (string) $this->input('sku'),
                ),
            ),

            'slug' => $slug !== ''
                ? Str::slug($slug)
                : null,

            'barcode' => $this->filled('barcode')
                ? trim(
                    (string) $this->input('barcode'),
                )
                : null,

            'product_type' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'product_type',
                        'stock',
                    ),
                ),
            ),

            'description' => $this->filled(
                'description',
            )
                ? trim(
                    (string) $this->input(
                        'description',
                    ),
                )
                : null,

            'is_purchasable' => $this->boolean(
                'is_purchasable',
            ),

            'is_sellable' => $this->boolean(
                'is_sellable',
            ),

            'status' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'status',
                        'active',
                    ),
                ),
            ),
        ]);
    }
}