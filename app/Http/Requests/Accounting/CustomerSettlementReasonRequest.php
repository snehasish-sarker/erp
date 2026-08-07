<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class CustomerSettlementReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return['reason' => ['required', 'string', 'max:500'], 'posting_date' => ['nullable', 'date_format:Y-m-d'],];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => trim((string) $this->input('reason', '')), 'posting_date' => ($date = trim((string) $this->input('posting_date', ''))) === '' ? null: $date,]);
    }
}
