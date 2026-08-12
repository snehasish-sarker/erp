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
                'required',
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
                'required',
                'string',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],

            'per_page' => [
                'required',
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
            'search' => $this->nullableString(
                'search',
            ),

            'supplier_type' => $this->nullableString(
                'supplier_type',
                lowercase: true,
            ),

            'status' => $this->nullableString(
                'status',
                lowercase: true,
            ),

            'sort' => $this->filled('sort')
                ? trim(
                    (string) $this->input('sort'),
                )
                : 'name',

            'direction' => $this->filled('direction')
                ? mb_strtolower(
                    trim(
                        (string) $this->input(
                            'direction',
                        ),
                    ),
                )
                : 'asc',

            'per_page' => $this->filled('per_page')
                ? $this->input('per_page')
                : 25,
        ]);
    }

    private function nullableString(
        string $field,
        bool $lowercase = false,
    ): ?string {
        if (!$this->filled($field)) {
            return null;
        }

        $value = trim(
            (string) $this->input($field),
        );

        if ($value === '') {
            return null;
        }

        return $lowercase
            ? mb_strtolower($value)
            : $value;
    }
}