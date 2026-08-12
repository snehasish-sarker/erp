<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserNotification;
use App\Services\Organisation\BranchAccessService;
use App\Services\Saas\SaasEntitlementService;
use App\Support\Notifications\UserNotificationPresenter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

final class HandleInertiaRequests extends Middleware
{
    /**
     * @var string
     */
    protected $rootView = 'app';

    public function __construct(
        private readonly UserNotificationPresenter $notificationPresenter,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
        private readonly SaasEntitlementService $saasEntitlementService,
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

            /*
             * Ziggy route information is shared through Inertia so that the
             * same configuration is available to browser rendering and SSR.
             *
             * The current request URL is required during SSR because there
             * is no browser window/location object on the Node side.
             */
            'ziggy' => fn (): array => [
                ...(new Ziggy())->toArray(),

                'location' => $request->url(),
            ],

            'auth' => $this->getAuthData(
                $request,
            ),

            'saas' => fn (): array =>
                $this->getSaasData($request),

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
    private function getAuthData(
        Request $request,
    ): array {
        $user = $request->user();

        if (!$user instanceof User) {
            return $this->emptyAuthData();
        }

        $tenant = $user
            ->tenant()
            ->first();

        /*
         * Public authentication routes such as /login do not run through
         * tenant.context middleware.
         *
         * It is possible for an authenticated session to reach one of these
         * routes temporarily, for example immediately after login or when
         * navigating back to the login URL.
         *
         * Do not resolve tenant-team roles, permissions, branch access, or
         * tenant-scoped resources until TenantContext has been initialized
         * by SetTenantContext.
         */
        if ($this->tenantContext->id() === null) {
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

                'roles' => [],
                'permissions' => [],

                'branch_access' => [
                    'mode' => 'none',
                    'can_access_all' => false,
                    'assigned_branch' => null,
                ],
            ];
        }

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
            ->assignedBranch(
                $user,
            );

        $canAccessAllBranches = $this
            ->branchAccessService
            ->hasCompanyWideAccess(
                $user,
            );

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
     *     subscription: array{
     *         status: string,
     *         trial_ends_at: string|null,
     *         current_period_ends_at: string|null,
     *         grace_ends_at: string|null,
     *         plan: array{
     *             id: int,
     *             code: string,
     *             name: string
     *         }
     *     }|null,
     *     features: list<string>,
     *     limits: array<string, int|null>
     * }
     */
    private function getSaasData(Request $request): array
    {
        $user = $request->user();

        if (
            !$user instanceof User
            || $this->tenantContext->id() === null
        ) {
            return $this->emptySaasData();
        }

        $tenant = $this->tenantContext->tenant();
        $subscription = $this->saasEntitlementService
            ->subscription($tenant);

        $plan = $subscription?->plan;

        return [
            'subscription' => $subscription === null || $plan === null
                ? null
                : [
                    'status' => $subscription->status,
                    'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                    'current_period_ends_at' => $subscription->current_period_ends_at?->toIso8601String(),
                    'grace_ends_at' => $subscription->grace_ends_at?->toIso8601String(),
                    'plan' => [
                        'id' => (int) $plan->getKey(),
                        'code' => $plan->code,
                        'name' => $plan->name,
                    ],
                ],

            'features' => $this->saasEntitlementService
                ->enabledFeatureKeys($tenant),

            'limits' => $this->saasEntitlementService
                ->limits($tenant),
        ];
    }

    /**
     * @return array{
     *     subscription: null,
     *     features: list<string>,
     *     limits: array<string, int|null>
     * }
     */
    private function emptySaasData(): array
    {
        return [
            'subscription' => null,
            'features' => [],
            'limits' => [],
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

        /*
         * Notifications are tenant-scoped.
         *
         * Public authentication routes do not have an initialized tenant
         * context, so never query tenant-scoped notifications from them.
         */
        if (
            !$user instanceof User
            || $this->tenantContext->id() === null
        ) {
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
                    ->present(
                        $notification,
                    ),
            )
            ->values()
            ->all();

        return [
            'unread_count' => $unreadCount,
            'items' => $items,
        ];
    }

    /**
     * @return array{
     *     user: null,
     *     tenant: null,
     *     roles: list<string>,
     *     permissions: list<string>,
     *     branch_access: array{
     *         mode: string,
     *         can_access_all: bool,
     *         assigned_branch: null
     *     }
     * }
     */
    private function emptyAuthData(): array
    {
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
}