<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreSaasPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('platform')->check();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('saas_plans', 'code'),
            ],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'billing_currency_code' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],
            'currency_scale' => [
                'required',
                'integer',
                'min:0',
                'max:4',
            ],
            'monthly_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'annual_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'status' => [
                'required',
                'string',
                Rule::in(['active', 'inactive']),
            ],
            'is_default' => ['required', 'boolean'],
            'sort_order' => [
                'required',
                'integer',
                'min:0',
                'max:100000',
            ],
            'entitlements' => ['required', 'array', 'min:1'],
            'entitlements.*.feature_key' => [
                'required',
                'string',
                'distinct',
                Rule::exists('saas_features', 'key')
                    ->where('status', 'active'),
            ],
            'entitlements.*.enabled' => ['required', 'boolean'],
            'entitlements.*.limit_value' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->boolean('is_default')
                    && $this->input('status') !== 'active'
                ) {
                    $validator->errors()->add(
                        'is_default',
                        'The default SaaS plan must be active.',
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::lower(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'description' => $this->nullableString('description'),
            'billing_currency_code' => Str::upper(
                trim((string) $this->input('billing_currency_code', 'BDT')),
            ),
            'currency_scale' => $this->filled('currency_scale')
                ? $this->input('currency_scale')
                : 2,
            'monthly_price' => $this->nullableNumericString('monthly_price'),
            'annual_price' => $this->nullableNumericString('annual_price'),
            'status' => Str::lower(trim((string) $this->input('status'))),
            'is_default' => $this->boolean('is_default'),
            'sort_order' => $this->filled('sort_order')
                ? $this->input('sort_order')
                : 0,
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

    private function nullableNumericString(string $field): ?string
    {
        if (!$this->filled($field)) {
            return null;
        }

        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }
}
