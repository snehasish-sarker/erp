<?php

declare(strict_types=1);

namespace App\Http\Requests\AccountingPeriods;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexFiscalYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'accounting_periods.view',
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

            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'active',
                    'closed',
                ]),
            ],

            'sort' => [
                'required',
                'string',
                Rule::in([
                    'name',
                    'code',
                    'start_date',
                    'end_date',
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
            ),

            'sort' => $this->filled('sort')
                ? trim(
                    (string) $this->input('sort'),
                )
                : 'start_date',

            'direction' => $this->filled('direction')
                ? mb_strtolower(
                    trim(
                        (string) $this->input(
                            'direction',
                        ),
                    ),
                )
                : 'desc',

            'per_page' => $this->filled('per_page')
                ? $this->input('per_page')
                : 25,
        ]);
    }

    private function nullableString(
        string $field,
    ): ?string {
        if (!$this->filled($field)) {
            return null;
        }

        $value = trim(
            (string) $this->input($field),
        );

        return $value === ''
            ? null
            : $value;
    }
}