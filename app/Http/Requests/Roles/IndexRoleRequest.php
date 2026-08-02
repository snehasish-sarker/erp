<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.view') === true;
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
            'type' => [
                'nullable',
                'string',
                Rule::in([
                    'system',
                    'custom',
                ]),
            ],
            'sort' => [
                'nullable',
                'string',
                Rule::in([
                    'name',
                    'users_count',
                    'permissions_count',
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
            'type' => $this->nullableTrimmedString('type'),
            'sort' => $this->nullableTrimmedString('sort'),
            'direction' => $this->nullableTrimmedString(
                'direction',
            ),
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