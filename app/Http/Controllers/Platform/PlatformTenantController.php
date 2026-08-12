<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\IndexPlatformTenantRequest;
use App\Http\Requests\Platform\StorePlatformTenantRequest;
use App\Models\Branch;
use App\Models\PlatformAdmin;
use App\Models\SaasPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Platform\PlatformTenantLifecycleService;
use App\Services\Platform\PlatformTenantProvisioningService;
use App\Services\Saas\SaasSubscriptionLifecycleService;
use App\Support\Responses\CommonResponseService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformTenantController extends Controller
{
    public function __construct(
        private readonly PlatformTenantLifecycleService $lifecycleService,
        private readonly PlatformTenantProvisioningService $provisioningService,
        private readonly SaasSubscriptionLifecycleService $subscriptionLifecycleService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexPlatformTenantRequest $request,
    ): Response {
        $this->platformAdmin();

        $validated = $request->validated();

        $search = (string) ($validated['search'] ?? '');
        $status = (string) ($validated['status'] ?? '');
        $sort = (string) ($validated['sort'] ?? 'name');
        $direction = (string) ($validated['direction'] ?? 'asc');
        $perPage = (int) ($validated['per_page'] ?? 25);

        $tenants = Tenant::query()
            ->select('tenants.*')
            ->selectSub(
                User::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('users.tenant_id', 'tenants.id'),
                'users_count',
            )
            ->selectSub(
                Branch::query()
                    ->withoutGlobalScope('tenant')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('branches.tenant_id', 'tenants.id'),
                'branches_count',
            )
            ->selectSub(
                Warehouse::query()
                    ->withoutGlobalScope('tenant')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('warehouses.tenant_id', 'tenants.id'),
                'warehouses_count',
            )
            ->when(
                $search !== '',
                static function (Builder $query) use ($search): void {
                    $query->where(
                        static function (Builder $searchQuery) use ($search): void {
                            $searchQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        },
                    );
                },
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
            'Platform/Tenants/Index',
            [
                'tenants' => [
                    'data' => $tenants
                        ->getCollection()
                        ->map(
                            fn (Tenant $tenant): array =>
                                $this->tenantSummary($tenant),
                        )
                        ->values()
                        ->all(),
                    'meta' => [
                        'current_page' => $tenants->currentPage(),
                        'last_page' => $tenants->lastPage(),
                        'per_page' => $tenants->perPage(),
                        'from' => $tenants->firstItem(),
                        'to' => $tenants->lastItem(),
                        'total' => $tenants->total(),
                        'previous_page_url' => $tenants->previousPageUrl(),
                        'next_page_url' => $tenants->nextPageUrl(),
                    ],
                ],
                'filters' => [
                    'search' => $search,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],
                'statusOptions' => $this->statusOptions(),
            ],
        );
    }

    public function create(): Response
    {
        $this->platformAdmin();

        return Inertia::render(
            'Platform/Tenants/Create',
            [
                'defaults' => [
                    'status' => 'trial',
                    'currency_code' => 'BDT',
                    'timezone' => 'Asia/Dhaka',
                ],
            ],
        );
    }

    public function store(
        StorePlatformTenantRequest $request,
    ): JsonResponse|RedirectResponse {
        $platformAdmin = $this->platformAdmin();

        $tenant = $this->provisioningService->provision(
            attributes: $request->validated(),
            platformAdmin: $platformAdmin,
        );

        return $this->responseService->success(
            message: 'Tenant provisioned successfully.',
            data: $this->tenantDetails($tenant),
            redirectTo: route(
                'platform.tenants.show',
                $tenant,
            ),
            status: 201,
        );
    }

    public function show(
        Tenant $tenant,
    ): Response {
        $this->platformAdmin();

        $this->subscriptionLifecycleService
            ->synchronizeTenant($tenant);

        $tenant = $tenant->refresh();

        return Inertia::render(
            'Platform/Tenants/Show',
            [
                'tenant' => $this->tenantDetails($tenant),
                'subscription' => $this->subscriptionDetails($tenant),
                'planOptions' => $this->planOptions(),
            ],
        );
    }

    public function activate(
        Tenant $tenant,
    ): JsonResponse|RedirectResponse {
        $this->platformAdmin();

        try {
            $tenant = $this->lifecycleService->activate($tenant);
        } catch (DomainException $exception) {
            return $this->responseService->error(
                message: $exception->getMessage(),
                code: 'TENANT_STATUS_TRANSITION_INVALID',
                redirectTo: route('platform.tenants.show', $tenant),
            );
        }

        return $this->responseService->success(
            message: 'Tenant activated successfully.',
            data: $this->tenantDetails($tenant),
            redirectTo: route('platform.tenants.show', $tenant),
        );
    }

    public function suspend(
        Tenant $tenant,
    ): JsonResponse|RedirectResponse {
        $this->platformAdmin();

        try {
            $tenant = $this->lifecycleService->suspend($tenant);
        } catch (DomainException $exception) {
            return $this->responseService->error(
                message: $exception->getMessage(),
                code: 'TENANT_STATUS_TRANSITION_INVALID',
                redirectTo: route('platform.tenants.show', $tenant),
            );
        }

        return $this->responseService->success(
            message: 'Tenant suspended successfully.',
            data: $this->tenantDetails($tenant),
            redirectTo: route('platform.tenants.show', $tenant),
        );
    }

    private function platformAdmin(): PlatformAdmin
    {
        $admin = Auth::guard('platform')->user();

        abort_unless(
            $admin instanceof PlatformAdmin,
            403,
        );

        return $admin;
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantSummary(Tenant $tenant): array
    {
        return [
            'id' => (int) $tenant->getKey(),
            'name' => $tenant->name,
            'code' => $tenant->code,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'email' => $tenant->email,
            'currency_code' => $tenant->currency_code,
            'timezone' => $tenant->timezone,
            'users_count' => (int) ($tenant->getAttribute('users_count') ?? 0),
            'branches_count' => (int) ($tenant->getAttribute('branches_count') ?? 0),
            'warehouses_count' => (int) ($tenant->getAttribute('warehouses_count') ?? 0),
            'created_at' => $tenant->created_at?->toIso8601String(),
            'updated_at' => $tenant->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantDetails(Tenant $tenant): array
    {
        $tenantId = (int) $tenant->getKey();

        $usersQuery = User::query()
            ->where('tenant_id', $tenantId);

        $branchesQuery = Branch::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId);

        $warehousesQuery = Warehouse::query()
            ->withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId);

        return [
            'id' => $tenantId,
            'name' => $tenant->name,
            'code' => $tenant->code,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
            'currency_code' => $tenant->currency_code,
            'timezone' => $tenant->timezone,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'address' => $tenant->address,
            'users_count' => (clone $usersQuery)->count(),
            'active_users_count' => (clone $usersQuery)
                ->where('status', 'active')
                ->count(),
            'branches_count' => (clone $branchesQuery)->count(),
            'active_branches_count' => (clone $branchesQuery)
                ->where('status', 'active')
                ->count(),
            'warehouses_count' => (clone $warehousesQuery)->count(),
            'active_warehouses_count' => (clone $warehousesQuery)
                ->where('status', 'active')
                ->count(),
            'can_activate' => in_array(
                $tenant->status,
                ['trial', 'suspended', 'past_due'],
                true,
            ),
            'can_suspend' => in_array(
                $tenant->status,
                ['trial', 'active', 'past_due'],
                true,
            ),
            'created_at' => $tenant->created_at?->toIso8601String(),
            'updated_at' => $tenant->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function subscriptionDetails(Tenant $tenant): ?array
    {
        $subscription = TenantSubscription::query()
            ->with('plan')
            ->where('tenant_id', $tenant->getKey())
            ->first();

        if (!$subscription instanceof TenantSubscription) {
            return null;
        }

        return [
            'id' => (int) $subscription->getKey(),
            'status' => $subscription->status,
            'billing_cycle' => $subscription->billing_cycle,
            'billing_currency_code' => $subscription->billing_currency_code,
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'current_period_starts_at' => $subscription->current_period_starts_at?->toIso8601String(),
            'current_period_ends_at' => $subscription->current_period_ends_at?->toIso8601String(),
            'past_due_at' => $subscription->past_due_at?->toIso8601String(),
            'past_due_reason' => $subscription->past_due_reason,
            'grace_ends_at' => $subscription->grace_ends_at?->toIso8601String(),
            'suspended_at' => $subscription->suspended_at?->toIso8601String(),
            'suspension_reason' => $subscription->suspension_reason,
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'can_extend_trial' => $subscription->status === 'trial'
                || (
                    in_array(
                        $subscription->status,
                        ['past_due', 'suspended'],
                        true,
                    )
                    && $subscription->past_due_reason === 'trial_expired'
                ),
            'plan' => [
                'id' => (int) $subscription->plan->getKey(),
                'code' => $subscription->plan->code,
                'name' => $subscription->plan->name,
                'billing_currency_code' => $subscription->plan->billing_currency_code,
                'currency_scale' => (int) $subscription->plan->currency_scale,
                'monthly_price_minor' => $subscription->plan->monthly_price_minor,
                'annual_price_minor' => $subscription->plan->annual_price_minor,
            ],
        ];
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    private function planOptions(): array
    {
        return SaasPlan::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (SaasPlan $plan): array => [
                    'id' => (int) $plan->getKey(),
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'billing_currency_code' => $plan->billing_currency_code,
                    'currency_scale' => (int) $plan->currency_scale,
                    'monthly_price_minor' => $plan->monthly_price_minor,
                    'annual_price_minor' => $plan->annual_price_minor,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => 'trial', 'label' => 'Trial'],
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'suspended', 'label' => 'Suspended'],
            ['value' => 'past_due', 'label' => 'Past Due'],
            ['value' => 'cancelled', 'label' => 'Cancelled'],
            ['value' => 'archived', 'label' => 'Archived'],
        ];
    }
}
