<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class FinancialStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('financial_statements.view') === true
            || $this->user()?->can('financial_control.view') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'as_of_date' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'comparison' => ['nullable', Rule::in(['none', 'previous_period', 'previous_year'])],
            'method' => ['nullable', Rule::in(['direct', 'indirect'])],
        ];
    }
}
