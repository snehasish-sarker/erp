<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

final class ExtendTenantTrialRequest extends FormRequest
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
            'days' => [
                'required',
                'integer',
                'min:1',
                'max:90',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'days' => $this->filled('days')
                ? (int) $this->input('days')
                : 7,
        ]);
    }
}