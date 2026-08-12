<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ApplyTenantSubscriptionQuickActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('platform')->check();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'action' => [
                'required',
                'string',
                Rule::in([
                    'extend_trial_7',
                    'extend_trial_14',
                    'extend_trial_30',
                    'extend_month',
                    'extend_year',
                    'renew_monthly',
                    'renew_annual',
                    'activate_indefinite',
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'action' => Str::lower(
                trim((string) $this->input('action')),
            ),
        ]);
    }
}
