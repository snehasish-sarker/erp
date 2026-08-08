<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreManagementReportScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('management_report_schedules.create') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'report_type' => ['required', Rule::in([
                'management_branch_profitability',
                'management_budget_vs_actual',
                'management_product_profitability',
                'management_customer_profitability',
                'management_supplier_spend',
                'management_gross_margin',
            ])],
            'format' => ['required', Rule::in(['csv', 'xlsx'])],
            'frequency' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'run_day' => ['nullable', 'integer', 'between:1,28'],
            'run_time' => ['required', 'date_format:H:i'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'budget_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:10', 'max:500'],
            'date_window_days' => ['nullable', 'integer', 'min:1', 'max:366'],
        ];
    }
}