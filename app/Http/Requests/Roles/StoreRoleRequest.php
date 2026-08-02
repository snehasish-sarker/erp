<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use App\Services\AccessControl\RoleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('roles.create') === true;
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
            'name' => [
                'required',
                'string',
                'max:125',
                Rule::notIn(
                    RoleService::SYSTEM_ROLE_NAMES,
                ),
                Rule::unique('roles', 'name')
                    ->where(
                        static fn (Builder $query): Builder =>
                            $query
                                ->where(
                                    'tenant_id',
                                    $tenantId,
                                )
                                ->where(
                                    'guard_name',
                                    'web',
                                ),
                    ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(
                $this->string('name')->toString(),
            ),
        ]);
    }
}