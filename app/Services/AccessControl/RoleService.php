<?php

declare(strict_types=1);

namespace App\Services\AccessControl;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RoleService
{
    public const TENANT_OWNER_ROLE = 'Tenant Owner';

    /**
     * @var list<string>
     */
    public const SYSTEM_ROLE_NAMES = [
        self::TENANT_OWNER_ROLE,
        'System Administrator',
        'Branch Manager',
        'Procurement Manager',
        'Warehouse Manager',
        'Sales Manager',
        'Accountant',
        'Auditor',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {
    }

    public function create(string $name): Role
    {
        return DB::transaction(
            function () use ($name): Role {
                $role = Role::query()->create([
                    'tenant_id' => $this->tenantContext
                        ->tenant()
                        ->getKey(),
                    'name' => $name,
                    'guard_name' => 'web',
                ]);

                $this->permissionRegistrar
                    ->forgetCachedPermissions();

                return $role;
            },
        );
    }

    public function updateName(
        Role $role,
        string $name,
    ): Role {
        $this->ensureRoleIsCustom($role);

        return DB::transaction(
            function () use (
                $role,
                $name,
            ): Role {
                $role->name = $name;
                $role->save();

                $this->permissionRegistrar
                    ->forgetCachedPermissions();

                return $role->refresh();
            },
        );
    }

    /**
     * @param list<int> $permissionIds
     */
    public function syncPermissions(
        Role $role,
        array $permissionIds,
    ): Role {
        if ($this->isTenantOwnerRole($role)) {
            throw ValidationException::withMessages([
                'permission_ids' => [
                    'Tenant Owner permissions are managed by the system and cannot be changed.',
                ],
            ]);
        }

        return DB::transaction(
            function () use (
                $role,
                $permissionIds,
            ): Role {
                $permissions = $this->permissions(
                    $permissionIds,
                );

                $role->syncPermissions($permissions);

                $this->permissionRegistrar
                    ->forgetCachedPermissions();

                return $role->refresh();
            },
        );
    }

    public function delete(Role $role): void
    {
        $this->ensureRoleIsCustom($role);

        $tableName = (string) config(
            'permission.table_names.model_has_roles',
            'model_has_roles',
        );

        $teamKey = (string) config(
            'permission.column_names.team_foreign_key',
            'tenant_id',
        );

        $roleKey = (string) (
            config('permission.column_names.role_pivot_key')
            ?: 'role_id'
        );

        $assignedUserExists = DB::table($tableName)
            ->where(
                $teamKey,
                $this->tenantContext->tenant()->getKey(),
            )
            ->where(
                $roleKey,
                $role->getKey(),
            )
            ->where(
                'model_type',
                (new User())->getMorphClass(),
            )
            ->exists();

        if ($assignedUserExists) {
            throw ValidationException::withMessages([
                'role' => [
                    'The role cannot be deleted while users are assigned to it.',
                ],
            ]);
        }

        DB::transaction(
            function () use ($role): void {
                $role->delete();

                $this->permissionRegistrar
                    ->forgetCachedPermissions();
            },
        );
    }

    public function isSystemRole(Role $role): bool
    {
        return in_array(
            $role->name,
            self::SYSTEM_ROLE_NAMES,
            true,
        );
    }

    public function isTenantOwnerRole(Role $role): bool
    {
        return $role->name === self::TENANT_OWNER_ROLE;
    }

    private function ensureRoleIsCustom(Role $role): void
    {
        if (!$this->isSystemRole($role)) {
            return;
        }

        throw ValidationException::withMessages([
            'role' => [
                'Seeded system roles cannot be renamed or deleted.',
            ],
        ]);
    }

    /**
     * @param list<int> $permissionIds
     * @return Collection<int, Permission>
     */
    private function permissions(
        array $permissionIds,
    ): Collection {
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('id', $permissionIds)
            ->get();

        if (
            $permissions->count()
            !== count(array_unique($permissionIds))
        ) {
            throw ValidationException::withMessages([
                'permission_ids' => [
                    'One or more selected permissions are invalid.',
                ],
            ]);
        }

        return $permissions;
    }
}