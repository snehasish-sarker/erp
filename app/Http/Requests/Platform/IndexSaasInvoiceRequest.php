<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class IndexSaasInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('platform')->check();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:160'],
            'status' => [
                'nullable',
                'string',
                Rule::in(['open', 'paid', 'void', 'uncollectible']),
            ],
            'sort' => [
                'required',
                'string',
                Rule::in([
                    'issued_at',
                    'due_at',
                    'invoice_number',
                    'status',
                    'total_minor',
                ]),
            ],
            'direction' => [
                'required',
                'string',
                Rule::in(['asc', 'desc']),
            ],
            'per_page' => [
                'required',
                'integer',
                Rule::in([10, 25, 50, 100]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => $this->nullableString('search'),
            'status' => $this->filled('status')
                ? Str::lower(trim((string) $this->input('status')))
                : null,
            'sort' => $this->filled('sort')
                ? trim((string) $this->input('sort'))
                : 'issued_at',
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
}
