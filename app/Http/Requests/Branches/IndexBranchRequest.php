<?php

declare(strict_types=1);

namespace App\Http\Requests\Branches;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('branches.view') === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                    'archived',
                ]),
            ],
            'sort' => [
                'nullable',
                'string',
                Rule::in([
                    'name',
                    'code',
                    'status',
                    'created_at',
                ]),
            ],
            'direction' => [
                'nullable',
                'string',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],
            'per_page' => [
                'nullable',
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
            'search' => $this->nullableTrimmedString('search'),
            'status' => $this->nullableTrimmedString('status'),
            'sort' => $this->nullableTrimmedString('sort'),
            'direction' => $this->nullableTrimmedString('direction'),
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