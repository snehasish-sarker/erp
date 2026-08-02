<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Support\MasterData\SupplierTypeRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'suppliers.view',
        ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:160',
            ],

            'supplier_type' => [
                'nullable',
                'string',
                Rule::in(
                    app(
                        SupplierTypeRegistry::class,
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
                Rule::in([
                    'name',
                    'code',
                    'supplier_type',
                    'payment_terms_days',
                    'status',
                    'created_at',
                ]),
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

            'supplier_type' => mb_strtolower(
                trim(
                    (string) $this->input(
                        'supplier_type',
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