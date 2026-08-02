<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UpdateCompanySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'company_settings.update',
        ) === true;
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
            'currency_code' => [
                'required',
                'string',
                'size:3',
                'alpha:ascii',
            ],
            'timezone' => [
                'required',
                'string',
                'max:100',
                Rule::in(DateTimeZone::listIdentifiers()),
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
                'max:1000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(
                $this->string('name')->toString(),
            ),
            'currency_code' => Str::upper(
                trim(
                    $this->string('currency_code')->toString(),
                ),
            ),
            'timezone' => trim(
                $this->string('timezone')->toString(),
            ),
            'email' => $this->nullableTrimmedString('email'),
            'phone' => $this->nullableTrimmedString('phone'),
            'address' => $this->nullableTrimmedString('address'),
        ]);
    }

    private function nullableTrimmedString(
        string $key,
    ): ?string {
        $value = trim(
            $this->string($key)->toString(),
        );

        return $value === '' ? null : $value;
    }
}