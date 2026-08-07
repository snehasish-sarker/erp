<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

abstract class TreasuryTransferRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'source_branch_id' => ['required', 'integer', 'min:1'],
            'destination_branch_id' => ['required', 'integer', 'min:1'],
            'source_account_id' => ['required', 'integer', 'min:1', 'different:destination_account_id'],
            'destination_account_id' => ['required', 'integer', 'min:1', 'different:source_account_id'],
            'transfer_date' => ['required', 'date_format:Y-m-d'],
            'posting_date' => ['required', 'date_format:Y-m-d'],
            'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'decimal:0,8'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999999.999999', 'decimal:0,6'],
            'reference' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'transfer_date' => $this->trimmed('transfer_date'),
            'posting_date' => $this->trimmed('posting_date'),
            'currency_code' => strtoupper((string) $this->trimmed('currency_code')),
            'exchange_rate' => trim((string) $this->input('exchange_rate', '1')),
            'amount' => trim((string) $this->input('amount', '')),
            'reference' => $this->nullableText('reference'),
            'notes' => $this->nullableText('notes'),
        ]);
    }

    private function trimmed(string $field): mixed
    {
        $value = $this->input($field);

        return is_string($value) ? trim($value) : $value;
    }

    private function nullableText(string $field): ?string
    {
        $value = $this->input($field);

        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
