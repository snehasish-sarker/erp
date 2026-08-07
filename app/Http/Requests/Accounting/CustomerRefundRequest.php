<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class CustomerRefundRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return['branch_id' => ['required', 'integer', 'min:1'], 'customer_id' => ['required', 'integer', 'min:1'], 'refund_account_id' => ['required', 'integer', 'min:1'], 'refund_date' => ['required', 'date_format:Y-m-d'], 'posting_date' => ['required', 'date_format:Y-m-d'], 'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'], 'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:999999999999.99999999', 'decimal:0,8'], 'refund_method' => ['required', Rule::in(['cash', 'bank_transfer', 'cheque', 'mobile_financial_service', 'other'])], 'refund_reference' => ['nullable', 'string', 'max:160'], 'cheque_number' => ['nullable', 'string', 'max:100'], 'cheque_date' => ['nullable', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'max:500'], 'notes' => ['nullable', 'string', 'max:4000'], 'allocations' => ['required', 'array', 'min:1', 'max:500'], 'allocations.*.credit_open_item_id' => ['required', 'integer', 'min:1', 'distinct'], 'allocations.*.amount' => ['required', 'numeric', 'gt:0', 'max:99999999999999.999999', 'decimal:0,6'],];
    }

    protected function prepareForValidation(): void
    {
        $allocations = $this->input('allocations');
        $this->merge(['currency_code' => strtoupper(trim((string) $this->input('currency_code', ''))), 'refund_method' => trim((string) $this->input('refund_method', '')), 'refund_reference' => $this->nullableText('refund_reference'), 'cheque_number' => $this->nullableText('cheque_number'), 'cheque_date' => $this->nullableText('cheque_date'), 'reason' => trim((string) $this->input('reason', '')), 'notes' => $this->nullableText('notes'), 'allocations' => is_array($allocations) ? array_values($allocations): $allocations,]);
    }

    private function nullableText(string $field): ? string
    {
        $value = trim((string) $this->input($field, ''));
        return $value === '' ? null: $value;
    }
}
