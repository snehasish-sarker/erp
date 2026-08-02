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
                'nullable',
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
                    (string) $this->input('direction'),
                ),
            ),
        ]);
    }
}