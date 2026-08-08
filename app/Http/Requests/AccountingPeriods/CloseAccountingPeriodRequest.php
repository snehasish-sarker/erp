<?php

declare(strict_types=1);

namespace App\Http\Requests\AccountingPeriods;

use App\Models\AccountingPeriod;
use Illuminate\Foundation\Http\FormRequest;

final class CloseAccountingPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $accountingPeriod = $this->route(
            'accountingPeriod',
        );

        return $accountingPeriod
                instanceof AccountingPeriod
            && $this->user()?->can(
                'close',
                $accountingPeriod,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }
}