<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\Organisation\BranchAccessService;
use App\Support\Notifications\UserNotificationPresenter;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private readonly UserNotificationPresenter $notificationPresenter,
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'appName' => (string) config(
                'app.name',
                'Wholesale Distribution ERP',
            ),

            'auth' => $this->getAuthData($request),

            'headerNotifications' => fn (): array =>
                $this->getHeaderNotificationData(
                    $request,
                ),
        ];
    }

    /**
     * @return array{
     *     user: array{
     *         id: int,
     *         name: string,
     *         email: string,
     *         status: string,
     *         avatar: string|null
     *     }|null,
     *     tenant: array{
     *         id: int,
     *         name: string,
     *         code: string,
     *         slug: string,
     *         status: string,
     *         currency_code: string,
     *         timezone: string
     *     }|null,
     *     roles: list<string>,
     *     permissions: list<string>,
     *     branch_access: array{
     *         mode: string,
     *         can_access_all: bool,
     *         assigned_branch: array{
     *             id: int,
     *             name: string,
     *             code: string,
     *             status: string
     *         }|null
     *     }
     * }
     */
    private function getAuthData(Request $request): array
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return [
                'user' => null,
                'tenant' => null,
                'roles' => [],
                'permissions' => [],

                'branch_access' => [
                    'mode' => 'none',
                    'can_access_all' => false,
                    'assigned_branch' => null,
                ],
            ];
        }

        $tenant = $user->tenant()->first();

        /*
         * Ensure role and permission information is loaded using the active
         * tenant team context instead of any previously cached relationship.
         */
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');

        $roles = $user
            ->getRoleNames()
            ->sort()
            ->values()
            ->all();

        $permissions = $user
            ->getAllPermissions()
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $assignedBranch = $this
            ->branchAccessService
            ->assignedBranch($user);

        $canAccessAllBranches = $this
            ->branchAccessService
            ->hasCompanyWideAccess($user);

        return [
            'user' => [
                'id' => (int) $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'avatar' => null,
            ],

            'tenant' => $tenant === null
                ? null
                : [
                    'id' => (int) $tenant->getKey(),
                    'name' => $tenant->name,
                    'code' => $tenant->code,
                    'slug' => $tenant->slug,
                    'status' => $tenant->status,

                    'currency_code' =>
                        $tenant->currency_code,

                    'timezone' =>
                        $tenant->timezone,
                ],

            'roles' => $roles,
            'permissions' => $permissions,

            'branch_access' => [
                'mode' => $canAccessAllBranches
                    ? 'company'
                    : 'assigned',

                'can_access_all' =>
                    $canAccessAllBranches,

                'assigned_branch' =>
                    $assignedBranch === null
                        ? null
                        : [
                            'id' => (int) (
                                $assignedBranch->getKey()
                            ),

                            'name' =>
                                $assignedBranch->name,

                            'code' =>
                                $assignedBranch->code,

                            'status' =>
                                $assignedBranch->status,
                        ],
            ],
        ];
    }

    /**
     * @return array{
     *     unread_count: int,
     *     items: list<array<string, mixed>>
     * }
     */
    private function getHeaderNotificationData(
        Request $request,
    ): array {
        $user = $request->user();

        if (!$user instanceof User) {
            return [
                'unread_count' => 0,
                'items' => [],
            ];
        }

        $query = UserNotification::query()
            ->where(
                'recipient_user_id',
                $user->getKey(),
            );

        $unreadCount = (clone $query)
            ->whereNull('read_at')
            ->count();

        $limit = min(
            25,
            max(
                1,
                (int) config(
                    'erp-notifications.header_limit',
                    10,
                ),
            ),
        );

        $items = $query
            ->orderByRaw(
                'CASE WHEN read_at IS NULL THEN 0 ELSE 1 END',
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(
                fn (
                    UserNotification $notification,
                ): array => $this
                    ->notificationPresenter
                    ->present($notification),
            )
            ->values()
            ->all();

        return [
            'unread_count' => $unreadCount,
            'items' => $items,
        ];
    }
}