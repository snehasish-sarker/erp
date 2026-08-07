<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CustomerArAdjustmentRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return['branch_id' => ['required', 'integer', 'min:1'], 'customer_id' => ['required', 'integer', 'min:1'], 'offset_account_id' => ['required', 'integer', 'min:1'], 'adjustment_date' => ['required', 'date_format:Y-m-d'], 'posting_date' => ['required', 'date_format:Y-m-d'], 'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'], 'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:999999999999.99999999', 'decimal:0,8'], 'direction' => ['required', Rule::in(['debit', 'credit'])], 'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999999.999999', 'decimal:0,6'], 'reason' => ['required', 'string', 'max:500'], 'notes' => ['nullable', 'string', 'max:4000'],];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['currency_code' => strtoupper(trim((string) $this->input('currency_code', ''))), 'direction' => trim((string) $this->input('direction', '')), 'reason' => trim((string) $this->input('reason', '')), 'notes' => ($notes = trim((string) $this->input('notes', ''))) === '' ? null: $notes,]);
    }
}
