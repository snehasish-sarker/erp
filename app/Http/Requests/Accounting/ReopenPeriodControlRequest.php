<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

final class ReopenPeriodControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('financial_control.view') === true
            && $this->user()?->can('period_close.reopen') === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}
