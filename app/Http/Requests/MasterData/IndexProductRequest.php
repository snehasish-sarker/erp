<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Support\MasterData\ProductTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'products.view',
        ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $sortOptions = [
            'name',
            'sku',
            'product_type',
            'selling_price',
            'status',
            'created_at',
        ];

        if (
            $this->user()?->can(
                'products.view_cost',
            ) === true
        ) {
            $sortOptions[] = 'cost_price';
        }

        return [
            'search' => [
                'nullable',
                'string',
                'max:160',
            ],

            'product_category_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'brand_id' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'product_type' => [
                'nullable',
                'string',
                Rule::in(
                    app(
                        ProductTypeRegistry::class,
                    )->keys(),
                ),
            ],

            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],

            'sort' => [
                'nullable',
                'string',
                Rule::in($sortOptions),
            ],

            'direction' => [
                'nullable',
                'string',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                Rule::in([
                    10,
                    25,
                    50,
                    100,
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => trim(
                (string) $this->input('search'),
            ),

            'product_category_id' => $this->filled(
                'product_category_id',
            )
                ? $this->input('product_category_id')
                : null,

            'brand_id' => $this->filled('brand_id')
                ? $this->input('brand_id')
                : null,

            'product_type' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'product_type',
                    ),
                ),
            ),

            'status' => mb_strtolower(
                trim(
                    (string) $this->input('status'),
                ),
            ),

            'sort' => trim(
                (string) $this->input('sort'),
            ),

            'direction' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'direction',
                    ),
                ),
            ),
        ]);
    }
}