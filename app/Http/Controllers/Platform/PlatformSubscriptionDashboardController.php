<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\IndexPlatformSubscriptionRequest;
use App\Models\PlatformAdmin;
use App\Models\SaasPlan;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformSubscriptionDashboardController extends Controller
{
    private const EXPIRING_SOON_DAYS = 14;

    private const EXPIRY_SQL = <<<'SQL'
CASE
    WHEN tenant_subscriptions.status = 'trial' THEN tenant_subscriptions.trial_ends_at
    WHEN tenant_subscriptions.status = 'active' THEN tenant_subscriptions.current_period_ends_at
    WHEN tenant_subscriptions.status = 'past_due' THEN tenant_subscriptions.grace_ends_at
    WHEN tenant_subscriptions.status = 'suspended'
        AND tenant_subscriptions.suspension_reason = 'grace_expired'
        THEN tenant_subscriptions.grace_ends_at
    ELSE NULL
END
SQL;

    public function __invoke(
        IndexPlatformSubscriptionRequest $request,
    ): Response {
        $this->platformAdmin();

        $validated = $request->validated();

        $search = $validated['search'] ?? null;
        $planId = isset($validated['saas_plan_id'])
            ? (int) $validated['saas_plan_id']
            : null;
        $status = $validated['status'] ?? null;
        $expiry = $validated['expiry'] ?? null;
        $sort = (string) $validated['sort'];
        $direction = (string) $validated['direction'];
        $perPage = (int) $validated['per_page'];
        $now = CarbonImmutable::now();

        $query = Tenant::query()
            ->leftJoin(
                'tenant_subscriptions',
                'tenant_subscriptions.tenant_id',
                '=',
                'tenants.id',
            )
            ->leftJoin(
                'saas_plans',
                'saas_plans.id',
                '=',
                'tenant_subscriptions.saas_plan_id',
            )
            ->select([
                'tenants.id',
                'tenants.name',
                'tenants.code',
                'tenants.status as tenant_status',
                'tenants.email',
                'tenant_subscriptions.id as subscription_id',
                'tenant_subscriptions.status as subscription_status',
                'tenant_subscriptions.billing_cycle',
                'tenant_subscriptions.starts_at',
                'tenant_subscriptions.trial_ends_at',
                'tenant_subscriptions.current_period_starts_at',
                'tenant_subscriptions.current_period_ends_at',
                'tenant_subscriptions.past_due_at',
                'tenant_subscriptions.past_due_reason',
                'tenant_subscriptions.grace_ends_at',
                'tenant_subscriptions.suspended_at',
                'tenant_subscriptions.suspension_reason',
                'tenant_subscriptions.cancelled_at',
                'tenant_subscriptions.ends_at',
                'saas_plans.id as plan_id',
                'saas_plans.code as plan_code',
                'saas_plans.name as plan_name',
                'saas_plans.status as plan_status',
            ])
            ->selectRaw(self::EXPIRY_SQL.' as effective_expiry_at')
            ->when(
                $search !== null,
                static function (Builder $query) use ($search): void {
                    $query->where(
                        static function (Builder $searchQuery) use ($search): void {
                            $searchQuery
                                ->where('tenants.name', 'like', "%{$search}%")
                                ->orWhere('tenants.code', 'like', "%{$search}%")
                                ->orWhere('tenants.email', 'like', "%{$search}%")
                                ->orWhere('saas_plans.name', 'like', "%{$search}%")
                                ->orWhere('saas_plans.code', 'like', "%{$search}%");
                        },
                    );
                },
            )
            ->when(
                $planId !== null,
                static fn (Builder $query): Builder => $query->where(
                    'tenant_subscriptions.saas_plan_id',
                    $planId,
                ),
            );

        $this->applyStatusFilter($query, $status);
        $this->applyExpiryFilter($query, $expiry, $now);
        $this->applySort($query, $sort, $direction);

        $subscriptions = $query
            ->orderBy('tenants.id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Platform/Subscriptions/Index',
            [
                'subscriptionPage' => [
                    'data' => $subscriptions->getCollection()
                        ->map(
                            fn (Tenant $tenant): array => $this->subscriptionRow(
                                tenant: $tenant,
                                now: $now,
                            ),
                        )
                        ->values()
                        ->all(),
                    'meta' => [
                        'current_page' => $subscriptions->currentPage(),
                        'last_page' => $subscriptions->lastPage(),
                        'per_page' => $subscriptions->perPage(),
                        'from' => $subscriptions->firstItem(),
                        'to' => $subscriptions->lastItem(),
                        'total' => $subscriptions->total(),
                        'previous_page_url' => $subscriptions->previousPageUrl(),
                        'next_page_url' => $subscriptions->nextPageUrl(),
                    ],
                ],
                'filters' => [
                    'search' => $search ?? '',
                    'saas_plan_id' => $planId,
                    'status' => $status ?? '',
                    'expiry' => $expiry ?? '',
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],
                'planOptions' => $this->planOptions(),
                'metrics' => $this->metrics($now),
                'expiringSoonDays' => self::EXPIRING_SOON_DAYS,
            ],
        );
    }

    private function platformAdmin(): PlatformAdmin
    {
        $admin = Auth::guard('platform')->user();

        abort_unless($admin instanceof PlatformAdmin, 403);

        return $admin;
    }

    private function applyStatusFilter(
        Builder $query,
        ?string $status,
    ): void {
        if ($status === null) {
            return;
        }

        if ($status === 'no_subscription') {
            $query->whereNull('tenant_subscriptions.id');

            return;
        }

        $query->where('tenant_subscriptions.status', $status);
    }

    private function applyExpiryFilter(
        Builder $query,
        ?string $expiry,
        CarbonImmutable $now,
    ): void {
        if ($expiry === null) {
            return;
        }

        if ($expiry === 'indefinite') {
            $query
                ->where('tenant_subscriptions.status', 'active')
                ->whereNull('tenant_subscriptions.current_period_ends_at');

            return;
        }

        if ($expiry === 'expired') {
            $query->where(
                static function (Builder $expiryQuery) use ($now): void {
                    $expiryQuery
                        ->where(
                            static fn (Builder $trialQuery): Builder => $trialQuery
                                ->where('tenant_subscriptions.status', 'trial')
                                ->whereNotNull('tenant_subscriptions.trial_ends_at')
                                ->where('tenant_subscriptions.trial_ends_at', '<=', $now),
                        )
                        ->orWhere(
                            static fn (Builder $activeQuery): Builder => $activeQuery
                                ->where('tenant_subscriptions.status', 'active')
                                ->whereNotNull('tenant_subscriptions.current_period_ends_at')
                                ->where('tenant_subscriptions.current_period_ends_at', '<=', $now),
                        )
                        ->orWhere(
                            static fn (Builder $pastDueQuery): Builder => $pastDueQuery
                                ->where('tenant_subscriptions.status', 'past_due')
                                ->whereNotNull('tenant_subscriptions.grace_ends_at')
                                ->where('tenant_subscriptions.grace_ends_at', '<=', $now),
                        )
                        ->orWhere(
                            static fn (Builder $suspendedQuery): Builder => $suspendedQuery
                                ->where('tenant_subscriptions.status', 'suspended')
                                ->where('tenant_subscriptions.suspension_reason', 'grace_expired')
                                ->whereNotNull('tenant_subscriptions.grace_ends_at')
                                ->where('tenant_subscriptions.grace_ends_at', '<=', $now),
                        );
                },
            );

            return;
        }

        $soon = $now->addDays(self::EXPIRING_SOON_DAYS);

        $query->where(
            static function (Builder $expiryQuery) use ($now, $soon): void {
                $expiryQuery
                    ->where(
                        static fn (Builder $trialQuery): Builder => $trialQuery
                            ->where('tenant_subscriptions.status', 'trial')
                            ->where('tenant_subscriptions.trial_ends_at', '>', $now)
                            ->where('tenant_subscriptions.trial_ends_at', '<=', $soon),
                    )
                    ->orWhere(
                        static fn (Builder $activeQuery): Builder => $activeQuery
                            ->where('tenant_subscriptions.status', 'active')
                            ->where('tenant_subscriptions.current_period_ends_at', '>', $now)
                            ->where('tenant_subscriptions.current_period_ends_at', '<=', $soon),
                    )
                    ->orWhere(
                        static fn (Builder $pastDueQuery): Builder => $pastDueQuery
                            ->where('tenant_subscriptions.status', 'past_due')
                            ->where('tenant_subscriptions.grace_ends_at', '>', $now)
                            ->where('tenant_subscriptions.grace_ends_at', '<=', $soon),
                    );
            },
        );
    }

    private function applySort(
        Builder $query,
        string $sort,
        string $direction,
    ): void {
        if ($sort === 'expiry') {
            $query->orderByRaw(self::EXPIRY_SQL.' '.$direction);

            return;
        }

        $column = match ($sort) {
            'tenant_name' => 'tenants.name',
            'company_code' => 'tenants.code',
            'package' => 'saas_plans.name',
            'subscription_status' => 'tenant_subscriptions.status',
            'billing_cycle' => 'tenant_subscriptions.billing_cycle',
            'trial_ends_at' => 'tenant_subscriptions.trial_ends_at',
            'current_period_starts_at' => 'tenant_subscriptions.current_period_starts_at',
            'current_period_ends_at' => 'tenant_subscriptions.current_period_ends_at',
            'grace_ends_at' => 'tenant_subscriptions.grace_ends_at',
            default => 'tenants.name',
        };

        $query->orderBy($column, $direction);
    }

    /** @return list<array{id: int, code: string, name: string, status: string}> */
    private function planOptions(): array
    {
        return SaasPlan::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (SaasPlan $plan): array => [
                    'id' => (int) $plan->getKey(),
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'status' => $plan->status,
                ],
            )
            ->values()
            ->all();
    }

    /** @return array<string, int> */
    private function metrics(CarbonImmutable $now): array
    {
        $baseQuery = Tenant::query()
            ->leftJoin(
                'tenant_subscriptions',
                'tenant_subscriptions.tenant_id',
                '=',
                'tenants.id',
            );

        $soon = $now->addDays(self::EXPIRING_SOON_DAYS);

        return [
            'tenants_total' => (clone $baseQuery)->count('tenants.id'),
            'trial' => (clone $baseQuery)
                ->where('tenant_subscriptions.status', 'trial')
                ->count('tenants.id'),
            'active' => (clone $baseQuery)
                ->where('tenant_subscriptions.status', 'active')
                ->count('tenants.id'),
            'past_due' => (clone $baseQuery)
                ->where('tenant_subscriptions.status', 'past_due')
                ->count('tenants.id'),
            'suspended' => (clone $baseQuery)
                ->where('tenant_subscriptions.status', 'suspended')
                ->count('tenants.id'),
            'expiring_soon' => $this->expiryCount(
                query: clone $baseQuery,
                now: $now,
                soon: $soon,
                expired: false,
            ),
            'expired' => $this->expiryCount(
                query: clone $baseQuery,
                now: $now,
                soon: $soon,
                expired: true,
            ),
            'no_subscription' => (clone $baseQuery)
                ->whereNull('tenant_subscriptions.id')
                ->count('tenants.id'),
        ];
    }

    private function expiryCount(
        Builder $query,
        CarbonImmutable $now,
        CarbonImmutable $soon,
        bool $expired,
    ): int {
        $query->where(
            static function (Builder $expiryQuery) use (
                $now,
                $soon,
                $expired,
            ): void {
                $expiryQuery
                    ->where(
                        static function (Builder $trialQuery) use (
                            $now,
                            $soon,
                            $expired,
                        ): void {
                            $trialQuery
                                ->where('tenant_subscriptions.status', 'trial')
                                ->whereNotNull('tenant_subscriptions.trial_ends_at');

                            if ($expired) {
                                $trialQuery->where(
                                    'tenant_subscriptions.trial_ends_at',
                                    '<=',
                                    $now,
                                );

                                return;
                            }

                            $trialQuery->where('tenant_subscriptions.trial_ends_at', '>', $now)
                            ->where('tenant_subscriptions.trial_ends_at', '<=', $soon);
                        },
                    )
                    ->orWhere(
                        static function (Builder $activeQuery) use (
                            $now,
                            $soon,
                            $expired,
                        ): void {
                            $activeQuery
                                ->where('tenant_subscriptions.status', 'active')
                                ->whereNotNull('tenant_subscriptions.current_period_ends_at');

                            if ($expired) {
                                $activeQuery->where(
                                    'tenant_subscriptions.current_period_ends_at',
                                    '<=',
                                    $now,
                                );

                                return;
                            }

                            $activeQuery->where('tenant_subscriptions.current_period_ends_at', '>', $now)
                            ->where('tenant_subscriptions.current_period_ends_at', '<=', $soon);
                        },
                    )
                    ->orWhere(
                        static function (Builder $pastDueQuery) use (
                            $now,
                            $soon,
                            $expired,
                        ): void {
                            $pastDueQuery
                                ->where('tenant_subscriptions.status', 'past_due')
                                ->whereNotNull('tenant_subscriptions.grace_ends_at');

                            if ($expired) {
                                $pastDueQuery->where(
                                    'tenant_subscriptions.grace_ends_at',
                                    '<=',
                                    $now,
                                );

                                return;
                            }

                            $pastDueQuery->where('tenant_subscriptions.grace_ends_at', '>', $now)
                            ->where('tenant_subscriptions.grace_ends_at', '<=', $soon);
                        },
                    );

                if ($expired) {
                    $expiryQuery->orWhere(
                        static fn (Builder $suspendedQuery): Builder => $suspendedQuery
                            ->where('tenant_subscriptions.status', 'suspended')
                            ->where('tenant_subscriptions.suspension_reason', 'grace_expired')
                            ->whereNotNull('tenant_subscriptions.grace_ends_at')
                            ->where('tenant_subscriptions.grace_ends_at', '<=', $now),
                    );
                }
            },
        );

        return $query->count('tenants.id');
    }

    /** @return array<string, mixed> */
    private function subscriptionRow(
        Tenant $tenant,
        CarbonImmutable $now,
    ): array {
        $subscriptionStatus = $this->nullableStringAttribute(
            $tenant,
            'subscription_status',
        );
        $tenantStatus = (string) $tenant->getAttribute('tenant_status');
        $effectiveExpiryAt = $this->carbonAttribute(
            $tenant,
            'effective_expiry_at',
        );
        $currentPeriodEndsAt = $this->carbonAttribute(
            $tenant,
            'current_period_ends_at',
        );

        $isIndefinite = $subscriptionStatus === 'active'
            && $currentPeriodEndsAt === null;
        $isExpired = $effectiveExpiryAt !== null
            && $effectiveExpiryAt->lessThanOrEqualTo($now);

        return [
            'tenant' => [
                'id' => (int) $tenant->getKey(),
                'name' => $tenant->name,
                'code' => $tenant->code,
                'email' => $tenant->email,
                'status' => $tenantStatus,
            ],
            'subscription_id' => $tenant->getAttribute('subscription_id') !== null
                ? (int) $tenant->getAttribute('subscription_id')
                : null,
            'subscription_status' => $subscriptionStatus,
            'billing_cycle' => $this->nullableStringAttribute(
                $tenant,
                'billing_cycle',
            ),
            'starts_at' => $this->isoAttribute($tenant, 'starts_at'),
            'trial_ends_at' => $this->isoAttribute($tenant, 'trial_ends_at'),
            'current_period_starts_at' => $this->isoAttribute(
                $tenant,
                'current_period_starts_at',
            ),
            'current_period_ends_at' => $currentPeriodEndsAt?->toIso8601String(),
            'past_due_at' => $this->isoAttribute($tenant, 'past_due_at'),
            'past_due_reason' => $this->nullableStringAttribute(
                $tenant,
                'past_due_reason',
            ),
            'can_extend_trial' => $subscriptionStatus === 'trial'
                || (
                    in_array(
                        $subscriptionStatus,
                        ['past_due', 'suspended'],
                        true,
                    )
                    && $this->nullableStringAttribute(
                        $tenant,
                        'past_due_reason',
                    ) === 'trial_expired'
                ),
            'grace_ends_at' => $this->isoAttribute($tenant, 'grace_ends_at'),
            'suspended_at' => $this->isoAttribute($tenant, 'suspended_at'),
            'cancelled_at' => $this->isoAttribute($tenant, 'cancelled_at'),
            'ends_at' => $this->isoAttribute($tenant, 'ends_at'),
            'effective_expiry_at' => $effectiveExpiryAt?->toIso8601String(),
            'days_remaining' => $this->daysRemaining(
                expiryAt: $effectiveExpiryAt,
                now: $now,
            ),
            'is_expired' => $isExpired,
            'is_indefinite' => $isIndefinite,
            'access_active' => $this->accessActive(
                tenantStatus: $tenantStatus,
                subscriptionStatus: $subscriptionStatus,
                trialEndsAt: $this->carbonAttribute($tenant, 'trial_ends_at'),
                currentPeriodEndsAt: $currentPeriodEndsAt,
                graceEndsAt: $this->carbonAttribute($tenant, 'grace_ends_at'),
                now: $now,
            ),
            'plan' => $tenant->getAttribute('plan_id') !== null
                ? [
                    'id' => (int) $tenant->getAttribute('plan_id'),
                    'code' => (string) $tenant->getAttribute('plan_code'),
                    'name' => (string) $tenant->getAttribute('plan_name'),
                    'status' => (string) $tenant->getAttribute('plan_status'),
                ]
                : null,
        ];
    }

    private function accessActive(
        string $tenantStatus,
        ?string $subscriptionStatus,
        ?CarbonImmutable $trialEndsAt,
        ?CarbonImmutable $currentPeriodEndsAt,
        ?CarbonImmutable $graceEndsAt,
        CarbonImmutable $now,
    ): bool {
        if (!in_array($tenantStatus, ['trial', 'active', 'past_due'], true)) {
            return false;
        }

        return match ($subscriptionStatus) {
            'trial' => $trialEndsAt === null || $trialEndsAt->gt($now),
            'active' => $currentPeriodEndsAt === null
                || $currentPeriodEndsAt->gt($now),
            'past_due' => $graceEndsAt !== null && $graceEndsAt->gt($now),
            default => false,
        };
    }

    private function daysRemaining(
        ?CarbonImmutable $expiryAt,
        CarbonImmutable $now,
    ): ?int {
        if ($expiryAt === null) {
            return null;
        }

        if ($expiryAt->lessThanOrEqualTo($now)) {
            return 0;
        }

        $secondsRemaining = $now->diffInSeconds($expiryAt, true);

        return (int) ceil($secondsRemaining / 86400);
    }

    private function isoAttribute(
        Tenant $tenant,
        string $attribute,
    ): ?string {
        return $this->carbonAttribute(
            tenant: $tenant,
            attribute: $attribute,
        )?->toIso8601String();
    }

    private function carbonAttribute(
        Tenant $tenant,
        string $attribute,
    ): ?CarbonImmutable {
        $value = $tenant->getAttribute($attribute);

        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        return CarbonImmutable::parse((string) $value);
    }

    private function nullableStringAttribute(
        Tenant $tenant,
        string $attribute,
    ): ?string {
        $value = $tenant->getAttribute($attribute);

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
