<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class TreasuryAdjustmentRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'min:1'],
            'bank_account_id' => ['required', 'integer', 'min:1'],
            'offset_account_id' => ['required', 'integer', 'min:1', 'different:bank_account_id'],
            'bank_statement_line_id' => ['nullable', 'integer', 'min:1'],
            'adjustment_type' => ['required', Rule::in(['bank_charge', 'bank_interest', 'other_debit', 'other_credit'])],
            'adjustment_date' => ['required', 'date_format:Y-m-d'],
            'posting_date' => ['required', 'date_format:Y-m-d'],
            'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'decimal:0,8'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999999.999999', 'decimal:0,6'],
            'reference' => ['nullable', 'string', 'max:160'],
            'description' => ['required', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'adjustment_type' => trim((string) $this->input('adjustment_type', '')),
            'adjustment_date' => trim((string) $this->input('adjustment_date', '')),
            'posting_date' => trim((string) $this->input('posting_date', '')),
            'currency_code' => strtoupper(trim((string) $this->input('currency_code', ''))),
            'exchange_rate' => trim((string) $this->input('exchange_rate', '1')),
            'amount' => trim((string) $this->input('amount', '')),
            'reference' => $this->nullableText('reference'),
            'description' => trim((string) $this->input('description', '')),
            'bank_statement_line_id' => $this->filled('bank_statement_line_id')
                ? $this->input('bank_statement_line_id')
                : null,
        ]);
    }

    private function nullableText(string $field): ?string
    {
        $value = trim((string) $this->input($field, ''));

        return $value === '' ? null : $value;
    }
}
