<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class PreparePeriodCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('financial_control.view') === true
            && $this->user()?->can('period_close.prepare') === true;
    }

    public function rules(): array
    {
        return [];
    }
}
