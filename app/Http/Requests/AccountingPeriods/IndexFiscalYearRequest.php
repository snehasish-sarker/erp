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
                'nullable',
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