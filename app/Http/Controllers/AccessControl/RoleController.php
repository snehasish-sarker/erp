<?php

declare(strict_types=1);

namespace App\Http\Controllers\AccessControl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\IndexRoleRequest;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\SyncRolePermissionsRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Models\User;
use App\Services\AccessControl\RoleService;
use App\Support\Responses\CommonResponseService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class RoleController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly RoleService $roleService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexRoleRequest $request,
    ): Response {
        Gate::authorize('viewAny', Role::class);

        $validated = $request->validated();

        $search = (string) ($validated['search'] ?? '');
        $type = (string) ($validated['type'] ?? '');
        $sort = (string) ($validated['sort'] ?? 'name');
        $direction = (string) (
            $validated['direction'] ?? 'asc'
        );
        $perPage = (int) ($validated['per_page'] ?? 25);

        $tenantId = (int) $this->tenantContext
            ->tenant()
            ->getKey();

        $pivotTable = (string) config(
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

        $userMorphClass = (new User())->getMorphClass();

        $roles = Role::query()
            ->select('roles.*')
            ->selectSub(
                static function (
                    QueryBuilder $query,
                ) use (
                    $pivotTable,
                    $teamKey,
                    $roleKey,
                    $tenantId,
                    $userMorphClass,
                ): void {
                    $query
                        ->from($pivotTable)
                        ->selectRaw('count(*)')
                        ->whereColumn(
                            "{$pivotTable}.{$roleKey}",
                            'roles.id',
                        )
                        ->where(
                            "{$pivotTable}.{$teamKey}",
                            $tenantId,
                        )
                        ->where(
                            "{$pivotTable}.model_type",
                            $userMorphClass,
                        );
                },
                'users_count',
            )
            ->withCount('permissions')
            ->where('tenant_id', $tenantId)
            ->where('guard_name', 'web')
            ->when(
                $search !== '',
                static fn (Builder $query): Builder =>
                    $query->where(
                        'name',
                        'like',
                        "%{$search}%",
                    ),
            )
            ->when(
                $type === 'system',
                static fn (Builder $query): Builder =>
                    $query->whereIn(
                        'name',
                        RoleService::SYSTEM_ROLE_NAMES,
                    ),
            )
            ->when(
                $type === 'custom',
                static fn (Builder $query): Builder =>
                    $query->whereNotIn(
                        'name',
                        RoleService::SYSTEM_ROLE_NAMES,
                    ),
            )
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Roles/Index',
            [
                'roles' => [
                    'data' => $roles
                        ->getCollection()
                        ->map(
                            fn (Role $role): array =>
                                $this->roleListData($role),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $roles->currentPage(),

                        'last_page' =>
                            $roles->lastPage(),

                        'per_page' =>
                            $roles->perPage(),

                        'from' =>
                            $roles->firstItem(),

                        'to' =>
                            $roles->lastItem(),

                        'total' =>
                            $roles->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'type' => $type,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],
            ],
        );
    }

    public function create(): Response
    {
        Gate::authorize('create', Role::class);

        return Inertia::render('Roles/Create');
    }

    public function store(
        StoreRoleRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('create', Role::class);

        $role = $this->roleService->create(
            (string) $request->validated('name'),
        );

        $user = $request->user();

        $redirectTo = $user instanceof User
            && $user->can('roles.view')
                ? route('roles.edit', $role)
                : route('dashboard');

        return $this->responseService->success(
            message: 'Role created successfully.',
            data: [
                'id' => (int) $role->getKey(),
            ],
            redirectTo: $redirectTo,
        );
    }

    public function edit(
        Request $request,
        Role $role,
    ): Response {
        Gate::authorize('view', $role);

        $role->load('permissions:id,name');

        $user = $request->user();

        abort_unless(
            $user instanceof User,
            HttpResponse::HTTP_UNAUTHORIZED,
        );

        return Inertia::render(
            'Roles/Edit',
            [
                'role' => $this->roleEditData($role),

                'permissionGroups' =>
                    $this->permissionGroups(),

                'abilities' => [
                    'update_details' =>
                        $user->can('update', $role),

                    'assign_permissions' =>
                        $user->can(
                            'assignPermissions',
                            $role,
                        ),
                ],
            ],
        );
    }

    public function update(
        UpdateRoleRequest $request,
        Role $role,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $role);

        $role = $this->roleService->updateName(
            role: $role,
            name: (string) $request->validated('name'),
        );

        return $this->responseService->success(
            message: 'Role updated successfully.',
            data: [
                'id' => (int) $role->getKey(),
                'name' => $role->name,
            ],
            redirectTo: route('roles.edit', $role),
        );
    }

    public function syncPermissions(
        SyncRolePermissionsRequest $request,
        Role $role,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'assignPermissions',
            $role,
        );

        /** @var list<int|string> $validatedPermissionIds */
        $validatedPermissionIds = $request->validated(
            'permission_ids',
        );

        $permissionIds = array_values(
            array_map(
                static fn (
                    int|string $permissionId,
                ): int => (int) $permissionId,
                $validatedPermissionIds,
            ),
        );

        $role = $this->roleService->syncPermissions(
            role: $role,
            permissionIds: $permissionIds,
        );

        return $this->responseService->success(
            message: 'Role permissions updated successfully.',
            data: [
                'id' => (int) $role->getKey(),
            ],
            redirectTo: route('roles.edit', $role),
        );
    }

    public function destroy(
        Role $role,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('delete', $role);

        $this->roleService->delete($role);

        return $this->responseService->success(
            message: 'Role deleted successfully.',
            redirectTo: route('roles.index'),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     is_system: bool,
     *     is_tenant_owner: bool,
     *     users_count: int,
     *     permissions_count: int,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function roleListData(Role $role): array
    {
        return [
            'id' => (int) $role->getKey(),
            'name' => $role->name,

            'is_system' =>
                $this->roleService->isSystemRole($role),

            'is_tenant_owner' =>
                $this->roleService
                    ->isTenantOwnerRole($role),

            'users_count' => (int) (
                $role->getAttribute('users_count') ?? 0
            ),

            'permissions_count' => (int) (
                $role->getAttribute(
                    'permissions_count',
                ) ?? 0
            ),

            'created_at' =>
                $role->created_at?->toIso8601String(),

            'updated_at' =>
                $role->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     is_system: bool,
     *     is_tenant_owner: bool,
     *     permission_ids: list<int>
     * }
     */
    private function roleEditData(Role $role): array
    {
        return [
            'id' => (int) $role->getKey(),
            'name' => $role->name,

            'is_system' =>
                $this->roleService->isSystemRole($role),

            'is_tenant_owner' =>
                $this->roleService
                    ->isTenantOwnerRole($role),

            'permission_ids' => $role->permissions
                ->pluck('id')
                ->map(
                    static fn (
                        int|string $permissionId,
                    ): int => (int) $permissionId,
                )
                ->sort()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array{
     *     key: string,
     *     label: string,
     *     permissions: list<array{
     *         id: int,
     *         name: string,
     *         label: string
     *     }>
     * }>
     */
    private function permissionGroups(): array
    {
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ]);

        /**
         * @var array<string, array{
         *     key: string,
         *     label: string,
         *     permissions: list<array{
         *         id: int,
         *         name: string,
         *         label: string
         *     }>
         * }> $groups
         */
        $groups = [];

        foreach ($permissions as $permission) {
            $groupKey = Str::before(
                $permission->name,
                '.',
            );

            $action = Str::after(
                $permission->name,
                '.',
            );

            if (!array_key_exists($groupKey, $groups)) {
                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'label' => Str::headline($groupKey),
                    'permissions' => [],
                ];
            }

            $groups[$groupKey]['permissions'][] = [
                'id' => (int) $permission->getKey(),
                'name' => $permission->name,
                'label' => Str::headline($action),
            ];
        }

        return collect($groups)
            ->sortBy('label')
            ->values()
            ->all();
    }
}