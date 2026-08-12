<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class IndexPlatformSaasUsageRequest extends FormRequest
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
            'tenant_status' => [
                'nullable',
                'string',
                Rule::in([
                    'trial',
                    'active',
                    'suspended',
                    'past_due',
                    'cancelled',
                    'archived',
                ]),
            ],
            'subscription_status' => [
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
            'resource' => [
                'required',
                'string',
                Rule::in([
                    'all',
                    'users',
                    'branches',
                    'warehouses',
                    'products',
                    'storage',
                ]),
            ],
            'capacity' => [
                'nullable',
                'string',
                Rule::in([
                    'healthy',
                    'near_limit',
                    'at_limit',
                    'over_limit',
                ]),
            ],
            'sort' => [
                'required',
                'string',
                Rule::in([
                    'tenant_name',
                    'company_code',
                    'package',
                    'tenant_status',
                    'subscription_status',
                    'users_usage',
                    'branches_usage',
                    'warehouses_usage',
                    'products_usage',
                    'storage_usage',
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
            'tenant_status' => $this->nullableLowercaseString('tenant_status'),
            'subscription_status' => $this->nullableLowercaseString(
                'subscription_status',
            ),
            'resource' => $this->filled('resource')
                ? Str::lower(trim((string) $this->input('resource')))
                : 'all',
            'capacity' => $this->nullableLowercaseString('capacity'),
            'sort' => $this->filled('sort')
                ? Str::lower(trim((string) $this->input('sort')))
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
