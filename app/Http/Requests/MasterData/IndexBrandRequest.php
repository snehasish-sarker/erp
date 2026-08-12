<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'brands.view',
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
                'max:120',
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
                    'slug',
                    'sort_order',
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

            'status' => $this->nullableString(
                'status',
                lowercase: true,
            ),

            'sort' => $this->filled('sort')
                ? trim(
                    (string) $this->input('sort'),
                )
                : 'sort_order',

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