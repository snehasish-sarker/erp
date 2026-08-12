<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateManualTenantSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('platform')->check();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'saas_plan_id' => [
                'required',
                'integer',
                Rule::exists('saas_plans', 'id')
                    ->where('status', 'active')
                    ->whereNull('deleted_at'),
            ],
            'billing_cycle' => [
                'required',
                'string',
                Rule::in(['monthly', 'annual']),
            ],
            'status' => [
                'required',
                'string',
                Rule::in([
                    'trial',
                    'active',
                    'past_due',
                    'suspended',
                    'cancelled',
                ]),
            ],
            'starts_at' => [
                'required',
                'date',
            ],
            'trial_ends_at' => [
                'nullable',
                'date',
                'after:starts_at',
            ],
            'current_period_starts_at' => [
                'nullable',
                'date',
                'required_with:current_period_ends_at',
            ],
            'current_period_ends_at' => [
                'nullable',
                'date',
                'after:current_period_starts_at',
            ],
            'past_due_at' => [
                'nullable',
                'date',
            ],
            'grace_ends_at' => [
                'nullable',
                'date',
                'after:past_due_at',
            ],
            'ends_at' => [
                'nullable',
                'date',
                'after_or_equal:starts_at',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $status = (string) $this->input('status');

                if (
                    $status === 'trial'
                    && !$this->filled('trial_ends_at')
                ) {
                    $validator->errors()->add(
                        'trial_ends_at',
                        'A trial end date is required for a trial subscription.',
                    );
                }

                if (
                    $status === 'past_due'
                    && !$this->filled('past_due_at')
                ) {
                    $validator->errors()->add(
                        'past_due_at',
                        'A past-due date is required for a past-due subscription.',
                    );
                }

                if (
                    $status === 'past_due'
                    && !$this->filled('grace_ends_at')
                ) {
                    $validator->errors()->add(
                        'grace_ends_at',
                        'A grace end date is required for a past-due subscription.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'billing_cycle' => Str::lower(
                trim((string) $this->input('billing_cycle', 'monthly')),
            ),
            'status' => Str::lower(
                trim((string) $this->input('status', 'trial')),
            ),
        ]);
    }
}
