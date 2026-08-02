<?php

declare(strict_types=1);

namespace App\Http\Requests\Files;

use App\Support\Files\TenantFileCategoryRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTenantFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'files.upload',
        ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'category' => [
                'required',
                'string',

                Rule::in(
                    app(
                        TenantFileCategoryRegistry::class,
                    )->keys(),
                ),
            ],

            /*
             * This is the global request ceiling. The service applies the
             * smaller category-specific limit after inspecting the file.
             */
            'file' => [
                'required',
                'file',
                'max:102400',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category' => trim(
                (string) $this->input('category'),
            ),
        ]);
    }
}