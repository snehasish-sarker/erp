<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

use Illuminate\Foundation\Http\FormRequest;

abstract class ManagementBudgetRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'min:1'],
            'fiscal_year_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1', 'max:2000'],
            'lines.*.account_id' => ['required', 'integer', 'min:1'],
            'lines.*.month_number' => ['required', 'integer', 'between:1,12'],
            'lines.*.amount' => ['required', 'numeric', 'min:0', 'max:99999999999999.999999'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
