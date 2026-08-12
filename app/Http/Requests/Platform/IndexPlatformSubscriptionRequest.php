<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class IndexPlatformSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('platform')->check();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:160',
            ],
            'saas_plan_id' => [
                'nullable',
                'integer',
                Rule::exists('saas_plans', 'id')
                    ->where(
                        static fn (Builder $query): Builder => $query
                            ->whereNull('deleted_at'),
                    ),
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'trial',
                    'active',
                    'past_due',
                    'suspended',
                    'cancelled',
                    'no_subscription',
                ]),
            ],
            'expiry' => [
                'nullable',
                'string',
                Rule::in([
                    'expiring_soon',
                    'expired',
                    'indefinite',
                ]),
            ],
            'sort' => [
                'required',
                'string',
                Rule::in([
                    'tenant_name',
                    'company_code',
                    'package',
                    'subscription_status',
                    'billing_cycle',
                    'trial_ends_at',
                    'current_period_starts_at',
                    'current_period_ends_at',
                    'grace_ends_at',
                    'expiry',
                ]),
            ],
            'direction' => [
                'required',
                'string',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],
            'per_page' => [
                'required',
                'integer',
                Rule::in([
                    10,
                    25,
                    50,
                    100,
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->nullableString('search'),
            'saas_plan_id' => $this->filled('saas_plan_id')
                ? $this->input('saas_plan_id')
                : null,
            'status' => $this->nullableLowercaseString('status'),
            'expiry' => $this->nullableLowercaseString('expiry'),
            'sort' => $this->filled('sort')
                ? trim((string) $this->input('sort'))
                : 'tenant_name',
            'direction' => $this->filled('direction')
                ? Str::lower(trim((string) $this->input('direction')))
                : 'asc',
            'per_page' => $this->filled('per_page')
                ? $this->input('per_page')
                : 25,
        ]);
    }

    private function nullableString(string $field): ?string
    {
        if (!$this->filled($field)) {
            return null;
        }

        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }

    private function nullableLowercaseString(string $field): ?string
    {
        $value = $this->nullableString($field);

        return $value === null ? null : Str::lower($value);
    }
}
