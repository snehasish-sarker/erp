<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

final class RecordSaasManualPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('platform')->check();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'reference' => [
                'nullable',
                'string',
                'max:150',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reference' => $this->nullableString('reference'),
            'notes' => $this->nullableString('notes'),
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
}
