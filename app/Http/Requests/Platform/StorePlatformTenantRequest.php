<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;

final class StorePlatformTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('platform')->check();
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9][A-Z0-9_-]*$/',
                Rule::unique('tenants', 'code'),
                Rule::unique('tenants', 'slug'),
            ],
            'status' => [
                'required',
                'string',
                Rule::in([
                    'trial',
                    'active',
                ]),
            ],
            'currency_code' => [
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],
            'timezone' => [
                'required',
                'string',
                'timezone',
                'max:100',
            ],
            'email' => [
                'nullable',
                'string',
                'email:rfc',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],
            'address' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'admin_name' => [
                'required',
                'string',
                'max:255',
            ],
            'admin_email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
            ],
            'admin_password' => [
                'required',
                'string',
                'confirmed',
                Password::min(12)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $code = Str::upper(
            trim((string) $this->input('code')),
        );

        $this->merge([
            'name' => trim(
                (string) $this->input('name'),
            ),
            'code' => $code,
            'status' => Str::lower(
                trim((string) $this->input('status')),
            ),
            'currency_code' => Str::upper(
                trim(
                    (string) $this->input('currency_code'),
                ),
            ),
            'timezone' => trim(
                (string) $this->input('timezone'),
            ),
            'email' => $this->nullableLowercaseString('email'),
            'phone' => $this->nullableString('phone'),
            'address' => $this->nullableString('address'),
            'admin_name' => trim(
                (string) $this->input('admin_name'),
            ),
            'admin_email' => Str::lower(
                trim(
                    (string) $this->input('admin_email'),
                ),
            ),
        ]);
    }

    private function nullableString(string $field): ?string
    {
        if (!$this->filled($field)) {
            return null;
        }

        $value = trim(
            (string) $this->input($field),
        );

        return $value === ''
            ? null
            : $value;
    }

    private function nullableLowercaseString(
        string $field,
    ): ?string {
        $value = $this->nullableString($field);

        return $value === null
            ? null
            : Str::lower($value);
    }
}