<?php

declare(strict_types=1);

namespace App\Http\Requests\Operations;

use Illuminate\Foundation\Http\FormRequest;

final class StoreReleaseCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}