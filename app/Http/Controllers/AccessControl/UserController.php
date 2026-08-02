<?php

declare(strict_types=1);

namespace App\Http\Controllers\AccessControl;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\ChangeUserStatusRequest;
use App\Http\Requests\Users\IndexUserRequest;
use App\Http\Requests\Users\ResetUserPasswordRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\AccessControl\UserService;
use App\Support\Responses\CommonResponseService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

final class UserController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly UserService $userService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(IndexUserRequest $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $validated = $request->validated();

        $search = (string) ($validated['search'] ?? '');

        $branchId = isset($validated['branch_id'])
            ? (int) $validated['branch_id']
            : null;

        $roleId = isset($validated['role_id'])
            ? (int) $validated['role_id']
            : null;

        $status = (string) ($validated['status'] ?? '');
        $sort = (string) ($validated['sort'] ?? 'name');
        $direction = (string) ($validated['direction'] ?? 'asc');
        $perPage = (int) ($validated['per_page'] ?? 25);

        $tenantId = (int) $this->tenantContext
            ->tenant()
            ->getKey();

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            HttpResponse::HTTP_UNAUTHORIZED,
        );

        $actorId = (int) $actor->getKey();

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'branch:id,name,code,status',
                'roles:id,name',
            ])
            ->when(
                $search !== '',
                static function (
                    Builder $query,
                ) use ($search): void {
                    $query->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            )
            ->when(
                $branchId !== null,
                static fn (Builder $query): Builder =>
                    $query->where('branch_id', $branchId),
            )
            ->when(
                $roleId !== null,
                static fn (Builder $query): Builder =>
                    $query->whereHas(
                        'roles',
                        static fn (Builder $roleQuery): Builder =>
                            $roleQuery->whereKey($roleId),
                    ),
            )
            ->when(
                $status !== '',
                static fn (Builder $query): Builder =>
                    $query->where('status', $status),
            )
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Users/Index',
            [
                'users' => [
                    'data' => $users
                        ->getCollection()
                        ->map(
                            fn (User $user): array =>
                                $this->userData(
                                    user: $user,
                                    actorId: $actorId,
                                ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' => $users->currentPage(),
                        'last_page' => $users->lastPage(),
                        'per_page' => $users->perPage(),
                        'from' => $users->firstItem(),
                        'to' => $users->lastItem(),
                        'total' => $users->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'branch_id' => $branchId,
                    'role_id' => $roleId,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'branchOptions' => $this->branchOptions(),
                'roleOptions' => $this->roleOptions(),
                'statusOptions' => $this->statusOptions(),
            ],
        );
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render(
            'Users/Create',
            [
                'branchOptions' => $this->branchOptions(),
                'roleOptions' => $this->roleOptions(),

                'statusOptions' => $this->statusOptions(
                    includeArchived: false,
                ),
            ],
        );
    }

    public function store(
        StoreUserRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('create', User::class);

        $validated = $request->validated();
        $roleIds = $this->extractRoleIds($validated);

        unset($validated['role_ids']);

        $user = $this->userService->create(
            attributes: $validated,
            roleIds: $roleIds,
        );

        return $this->responseService->success(
            message: 'User created successfully.',
            data: [
                'id' => (int) $user->getKey(),
            ],
            redirectTo: route('users.index'),
        );
    }

    public function edit(
        Request $request,
        User $managedUser,
    ): Response {
        Gate::authorize('update', $managedUser);

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            HttpResponse::HTTP_UNAUTHORIZED,
        );

        $managedUser->load([
            'branch:id,name,code,status',
            'roles:id,name',
        ]);

        return Inertia::render(
            'Users/Edit',
            [
                'managedUser' => $this->userData(
                    user: $managedUser,
                    actorId: (int) $actor->getKey(),
                ),

                'branchOptions' => $this->branchOptions(
                    currentBranchId: $managedUser->branch_id === null
                        ? null
                        : (int) $managedUser->branch_id,
                ),

                'roleOptions' => $this->roleOptions(),
            ],
        );
    }

    public function update(
        UpdateUserRequest $request,
        User $managedUser,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $managedUser);

        $validated = $request->validated();
        $roleIds = $this->extractRoleIds($validated);

        unset($validated['role_ids']);

        $user = $this->userService->update(
            user: $managedUser,
            attributes: $validated,
            roleIds: $roleIds,
        );

        return $this->responseService->success(
            message: 'User updated successfully.',
            data: [
                'id' => (int) $user->getKey(),
            ],
            redirectTo: route('users.index'),
        );
    }

    public function changeStatus(
        ChangeUserStatusRequest $request,
        User $managedUser,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('changeStatus', $managedUser);

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            HttpResponse::HTTP_UNAUTHORIZED,
        );

        $user = $this->userService->changeStatus(
            user: $managedUser,
            actor: $actor,
            status: (string) $request->validated('status'),
        );

        return $this->responseService->success(
            message: 'User status updated successfully.',
            data: [
                'id' => (int) $user->getKey(),
                'status' => $user->status,
            ],
            redirectTo: route('users.index'),
        );
    }

    public function resetPassword(
        ResetUserPasswordRequest $request,
        User $managedUser,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('resetPassword', $managedUser);

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            HttpResponse::HTTP_UNAUTHORIZED,
        );

        $this->userService->resetPassword(
            user: $managedUser,
            actor: $actor,
            password: (string) $request->validated('password'),
        );

        return $this->responseService->success(
            message: 'Password reset successfully. Existing sessions were signed out.',
            redirectTo: route('users.index'),
        );
    }

    public function destroy(
        Request $request,
        User $managedUser,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('delete', $managedUser);

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            HttpResponse::HTTP_UNAUTHORIZED,
        );

        $this->userService->delete(
            user: $managedUser,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'User deleted successfully.',
            redirectTo: route('users.index'),
        );
    }

    /**
     * @param array<string, mixed> $validated
     * @return list<int>
     */
    private function extractRoleIds(array $validated): array
    {
        /** @var list<int|string> $roleIds */
        $roleIds = $validated['role_ids'];

        return array_values(
            array_map(
                static fn (int|string $roleId): int =>
                    (int) $roleId,
                $roleIds,
            ),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     status: string,
     *     branch_id: int|null,
     *     branch: array{
     *         id: int,
     *         name: string,
     *         code: string,
     *         status: string
     *     }|null,
     *     roles: list<array{id: int, name: string}>,
     *     is_current_user: bool,
     *     is_tenant_owner: bool,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function userData(
        User $user,
        int $actorId,
    ): array {
        $branch = $user->branch;

        $roles = $user->roles
            ->sortBy('name')
            ->map(
                static fn (Role $role): array => [
                    'id' => (int) $role->getKey(),
                    'name' => $role->name,
                ],
            )
            ->values()
            ->all();

        return [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'status' => $user->status,

            'branch_id' => $user->branch_id === null
                ? null
                : (int) $user->branch_id,

            'branch' => $branch === null
                ? null
                : [
                    'id' => (int) $branch->getKey(),
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'status' => $branch->status,
                ],

            'roles' => $roles,

            'is_current_user' =>
                (int) $user->getKey() === $actorId,

            'is_tenant_owner' => collect($roles)->contains(
                static fn (array $role): bool =>
                    $role['name'] === 'Tenant Owner',
            ),

            'created_at' =>
                $user->created_at?->toIso8601String(),

            'updated_at' =>
                $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     status: string
     * }>
     */
    private function branchOptions(
        ?int $currentBranchId = null,
    ): array {
        return Branch::query()
            ->where(
                static function (
                    Builder $query,
                ) use ($currentBranchId): void {
                    $query->where(
                        'status',
                        '!=',
                        'archived',
                    );

                    if ($currentBranchId !== null) {
                        $query->orWhere(
                            'id',
                            $currentBranchId,
                        );
                    }
                },
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'status',
            ])
            ->map(
                static fn (Branch $branch): array => [
                    'id' => (int) $branch->getKey(),
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'status' => $branch->status,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function roleOptions(): array
    {
        $tenantId = (int) $this->tenantContext
            ->tenant()
            ->getKey();

        return Role::query()
            ->where('tenant_id', $tenantId)
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get([
                'id',
                'name',
            ])
            ->map(
                static fn (Role $role): array => [
                    'id' => (int) $role->getKey(),
                    'name' => $role->name,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(
        bool $includeArchived = true,
    ): array {
        $options = [
            [
                'value' => 'active',
                'label' => 'Active',
            ],
            [
                'value' => 'inactive',
                'label' => 'Inactive',
            ],
            [
                'value' => 'suspended',
                'label' => 'Suspended',
            ],
        ];

        if ($includeArchived) {
            $options[] = [
                'value' => 'archived',
                'label' => 'Archived',
            ];
        }

        return $options;
    }
}