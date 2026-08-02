<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use App\Services\AccessControl\RoleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

final class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            && $this->user()?->can(
                'update',
                $role,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $role = $this->route('role');

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
                    )
                    ->ignore(
                        $role instanceof Role
                            ? $role->getKey()
                            : null,
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