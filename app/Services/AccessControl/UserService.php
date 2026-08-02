<?php

declare(strict_types=1);

namespace App\Services\AccessControl;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

final class UserService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    /**
     * @param array{
     *     branch_id: int|null,
     *     name: string,
     *     email: string,
     *     password: string,
     *     status: string
     * } $attributes
     * @param list<int> $roleIds
     */
    public function create(
        array $attributes,
        array $roleIds,
    ): User {
        return DB::transaction(
            function () use (
                $attributes,
                $roleIds,
            ): User {
                $user = new User($attributes);

                $user->tenant_id = $this->tenantContext
                    ->tenant()
                    ->getKey();

                $user->save();

                $user->syncRoles(
                    $this->tenantRoles($roleIds)->all(),
                );

                return $user->refresh();
            },
        );
    }

    /**
     * @param array{
     *     branch_id: int|null,
     *     name: string,
     *     email: string
     * } $attributes
     * @param list<int> $roleIds
     */
    public function update(
        User $user,
        array $attributes,
        array $roleIds,
    ): User {
        return DB::transaction(
            function () use (
                $user,
                $attributes,
                $roleIds,
            ): User {
                $this->assertTenantOwnerContinuity(
                    user: $user,
                    newStatus: $user->status,
                    newRoleIds: $roleIds,
                );

                $user->fill($attributes);
                $user->save();

                $user->syncRoles(
                    $this->tenantRoles($roleIds)->all(),
                );

                return $user->refresh();
            },
        );
    }

    public function changeStatus(
        User $user,
        User $actor,
        string $status,
    ): User {
        return DB::transaction(
            function () use (
                $user,
                $actor,
                $status,
            ): User {
                if (
                    $user->is($actor)
                    && $status !== 'active'
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'You cannot deactivate, suspend, or archive your own account.',
                        ],
                    ]);
                }

                $this->assertTenantOwnerContinuity(
                    user: $user,
                    newStatus: $status,
                    newRoleIds: $this->currentRoleIds($user),
                );

                $user->status = $status;
                $user->save();

                if ($status !== 'active') {
                    $this->clearSessions($user);
                }

                return $user->refresh();
            },
        );
    }

    public function resetPassword(
        User $user,
        User $actor,
        string $password,
    ): User {
        return DB::transaction(
            function () use (
                $user,
                $actor,
                $password,
            ): User {
                if ($user->is($actor)) {
                    throw ValidationException::withMessages([
                        'password' => [
                            'Use the personal password-change screen to update your own password.',
                        ],
                    ]);
                }

                $user->password = $password;
                $user->save();

                $this->clearSessions($user);

                return $user->refresh();
            },
        );
    }

    public function delete(
        User $user,
        User $actor,
    ): void {
        DB::transaction(
            function () use (
                $user,
                $actor,
            ): void {
                if ($user->is($actor)) {
                    throw ValidationException::withMessages([
                        'user' => [
                            'You cannot delete your own account.',
                        ],
                    ]);
                }

                $this->assertTenantOwnerContinuity(
                    user: $user,
                    newStatus: 'archived',
                    newRoleIds: [],
                );

                $user->status = 'archived';
                $user->save();

                $this->clearSessions($user);
                $user->syncRoles([]);
                $user->delete();
            },
        );
    }

    /**
     * @param list<int> $roleIds
     * @return Collection<int, Role>
     */
    private function tenantRoles(array $roleIds): Collection
    {
        $tenantId = (int) $this->tenantContext
            ->tenant()
            ->getKey();

        $roles = Role::query()
            ->where('tenant_id', $tenantId)
            ->where('guard_name', 'web')
            ->whereIn('id', $roleIds)
            ->get();

        if ($roles->count() !== count(array_unique($roleIds))) {
            throw ValidationException::withMessages([
                'role_ids' => [
                    'One or more selected roles are invalid for this tenant.',
                ],
            ]);
        }

        return $roles;
    }

    /**
     * @param list<int> $newRoleIds
     */
    private function assertTenantOwnerContinuity(
        User $user,
        string $newStatus,
        array $newRoleIds,
    ): void {
        $tenantOwnerRole = Role::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('guard_name', 'web')
            ->where('name', 'Tenant Owner')
            ->first();

        if (!$tenantOwnerRole instanceof Role) {
            return;
        }

        $tenantOwnerRoleId = (int) $tenantOwnerRole->getKey();

        if (!in_array(
            $tenantOwnerRoleId,
            $this->currentRoleIds($user),
            true,
        )) {
            return;
        }

        $remainsActiveOwner = $newStatus === 'active'
            && in_array(
                $tenantOwnerRoleId,
                $newRoleIds,
                true,
            );

        if ($remainsActiveOwner) {
            return;
        }

        $otherActiveOwnerExists = User::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 'active')
            ->where('id', '!=', $user->getKey())
            ->whereHas(
                'roles',
                static fn (Builder $query): Builder =>
                    $query->whereKey($tenantOwnerRoleId),
            )
            ->exists();

        if ($otherActiveOwnerExists) {
            return;
        }

        throw ValidationException::withMessages([
            'role_ids' => [
                'The tenant must retain at least one active Tenant Owner.',
            ],
        ]);
    }

    /**
     * @return list<int>
     */
    private function currentRoleIds(User $user): array
    {
        return $user->roles()
            ->pluck('roles.id')
            ->map(
                static fn (int|string $roleId): int =>
                    (int) $roleId,
            )
            ->values()
            ->all();
    }

    private function clearSessions(User $user): void
    {
        DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->delete();
    }
}