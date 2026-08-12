<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class IndexPlatformSubscriptionHistoryRequest extends FormRequest
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
            'tenant_id' => [
                'nullable',
                'integer',
                Rule::exists('tenants', 'id'),
            ],
            'event' => [
                'nullable',
                'string',
                Rule::in([
                    'saas_plan_assigned',
                    'saas_subscription_manually_updated',
                    'saas_subscription_manually_activated',
                    'saas_subscription_manually_suspended',
                    'saas_subscription_quick_action_applied',
                    'saas_trial_extended',
                    'saas_subscription_past_due',
                    'saas_subscription_suspended',
                ]),
            ],
            'actor_type' => [
                'nullable',
                'string',
                Rule::in([
                    'platform_admin',
                    'system',
                ]),
            ],
            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
            ],
            'sort' => [
                'required',
                'string',
                Rule::in([
                    'created_at',
                    'tenant',
                    'event',
                    'actor',
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator): void {
                $dateFrom = $this->string('date_from')->toString();
                $dateTo = $this->string('date_to')->toString();

                if (
                    $dateFrom !== ''
                    && $dateTo !== ''
                    && $dateTo < $dateFrom
                ) {
                    $validator->errors()->add(
                        'date_to',
                        'The end date must be on or after the start date.',
                    );
                }
            },
        );
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->nullableString('search'),
            'tenant_id' => $this->filled('tenant_id')
                ? $this->input('tenant_id')
                : null,
            'event' => $this->nullableLowercaseString('event'),
            'actor_type' => $this->nullableLowercaseString('actor_type'),
            'date_from' => $this->nullableString('date_from'),
            'date_to' => $this->nullableString('date_to'),
            'sort' => $this->filled('sort')
                ? Str::lower(trim((string) $this->input('sort')))
                : 'created_at',
            'direction' => $this->filled('direction')
                ? Str::lower(trim((string) $this->input('direction')))
                : 'desc',
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
