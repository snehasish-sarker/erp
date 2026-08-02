<?php

declare(strict_types=1);

namespace App\Http\Requests\Users;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.view') === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)
            ->tenant()
            ->getKey();

        return [
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')
                    ->where(
                        static fn (Builder $query): Builder =>
                            $query
                                ->where('tenant_id', $tenantId)
                                ->whereNull('deleted_at'),
                    ),
            ],
            'role_id' => [
                'nullable',
                'integer',
                Rule::exists('roles', 'id')
                    ->where(
                        static fn (Builder $query): Builder =>
                            $query
                                ->where('tenant_id', $tenantId)
                                ->where('guard_name', 'web'),
                    ),
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                    'suspended',
                    'archived',
                ]),
            ],
            'sort' => [
                'nullable',
                'string',
                Rule::in([
                    'name',
                    'email',
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
            'branch_id' => $this->filled('branch_id')
                ? $this->integer('branch_id')
                : null,
            'role_id' => $this->filled('role_id')
                ? $this->integer('role_id')
                : null,
            'status' => $this->nullableTrimmedString('status'),
            'sort' => $this->nullableTrimmedString('sort'),
            'direction' => $this->nullableTrimmedString('direction'),
        ]);
    }

    private function nullableTrimmedString(string $key): ?string
    {
        $value = trim(
            $this->string($key)->toString(),
        );

        return $value === '' ? null : $value;
    }
}