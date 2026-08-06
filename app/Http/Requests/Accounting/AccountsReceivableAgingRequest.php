<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AccountsReceivableAgingRequest extends FormRequest
{
    /** @var list<string> */
    private const SORTS = [
        'customer_name',
        'total_receivable',
        'unapplied_credit',
        'net_outstanding',
        'ledger_balance',
        'difference',
        'current',
        'days_1_30',
        'days_31_60',
        'days_61_90',
        'days_91_120',
        'days_over_120',
    ];

    public function authorize(): bool
    {
        return $this->user()?->can(
            'reports.receivables',
        ) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'as_of_date' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'branch_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'customer_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'currency_code' => [
                'nullable',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],
            'search' => [
                'nullable',
                'string',
                'max:160',
            ],
            'sort' => [
                'required',
                'string',
                Rule::in(self::SORTS),
            ],
            'direction' => [
                'required',
                'string',
                Rule::in(['asc', 'desc']),
            ],
            'per_page' => [
                'required',
                'integer',
                Rule::in([10, 15, 25, 50, 100]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'as_of_date' => $this->nullableString('as_of_date'),
            'branch_id' => $this->nullableId('branch_id'),
            'customer_id' => $this->nullableId('customer_id'),
            'currency_code' => $this->filled('currency_code')
                ? mb_strtoupper(
                    trim((string) $this->input('currency_code')),
                )
                : null,
            'search' => $this->nullableString('search'),
            'sort' => $this->filled('sort')
                ? mb_strtolower(trim((string) $this->input('sort')))
                : 'net_outstanding',
            'direction' => $this->filled('direction')
                ? mb_strtolower(trim((string) $this->input('direction')))
                : 'desc',
            'per_page' => $this->filled('per_page')
                ? $this->input('per_page')
                : 25,
        ]);
    }

    private function nullableString(string $field): ?string
    {
        if (!$this->filled($field)) {
            return null;
        }

        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }

    private function nullableId(string $field): int|string|null
    {
        if (!$this->filled($field)) {
            return null;
        }

        $value = $this->input($field);

        if (is_int($value)) {
            return $value;
        }

        return is_string($value)
            ? trim($value)
            : $value;
    }
}