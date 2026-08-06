<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CustomerStatementRequest extends FormRequest
{
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
            'customer_id' => [
                'nullable',
                'integer',
                'min:1',
            ],
            'branch_id' => [
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
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
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
            'customer_id' => $this->nullableId('customer_id'),
            'branch_id' => $this->nullableId('branch_id'),
            'currency_code' => $this->filled('currency_code')
                ? mb_strtoupper(
                    trim((string) $this->input('currency_code')),
                )
                : null,
            'date_from' => $this->nullableString('date_from'),
            'date_to' => $this->nullableString('date_to'),
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