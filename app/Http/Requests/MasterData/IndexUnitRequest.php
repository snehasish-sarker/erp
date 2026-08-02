<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Support\MasterData\UnitCategoryRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'units.view',
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
                'max:100',
            ],

            'category' => [
                'nullable',
                'string',
                Rule::in(
                    app(
                        UnitCategoryRegistry::class,
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
                    'category',
                    'allow_decimal',
                    'decimal_places',
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

            'category' => trim(
                (string) $this->input('category'),
            ),

            'status' => trim(
                (string) $this->input('status'),
            ),

            'sort' => trim(
                (string) $this->input('sort'),
            ),

            'direction' => trim(
                (string) $this->input('direction'),
            ),
        ]);
    }
}