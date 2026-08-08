<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexManagementBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('management_budgets.view') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'fiscal_year_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['draft', 'approved'])],
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }
}
