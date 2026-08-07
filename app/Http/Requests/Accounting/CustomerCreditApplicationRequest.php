<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

abstract class CustomerCreditApplicationRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return['branch_id' => ['required', 'integer', 'min:1'], 'customer_id' => ['required', 'integer', 'min:1'], 'application_date' => ['required', 'date_format:Y-m-d'], 'posting_date' => ['required', 'date_format:Y-m-d'], 'currency_code' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'], 'reason' => ['required', 'string', 'max:500'], 'notes' => ['nullable', 'string', 'max:4000'], 'lines' => ['required', 'array', 'min:1', 'max:500'], 'lines.*.receivable_open_item_id' => ['required', 'integer', 'min:1'], 'lines.*.credit_open_item_id' => ['required', 'integer', 'min:1'], 'lines.*.amount' => ['required', 'numeric', 'gt:0', 'max:99999999999999.999999', 'decimal:0,6'],];
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines');
        $this->merge(['currency_code' => strtoupper(trim((string) $this->input('currency_code', ''))), 'reason' => trim((string) $this->input('reason', '')), 'notes' => $this->nullableText('notes'), 'lines' => is_array($lines) ? array_values($lines): $lines,]);
    }

    private function nullableText(string $field): ? string
    {
        $value = trim((string) $this->input($field, ''));
        return $value === '' ? null: $value;
    }
}
