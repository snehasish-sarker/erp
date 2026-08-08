<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;

final class ManagementReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('management_reports.view') === true
            || $this->user()?->can('management_dashboard.view') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'budget_id' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:10', 'max:500'],
        ];
    }
}