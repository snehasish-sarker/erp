<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use stdClass;

final class SaasOperationalDashboardService
{
    public const EXPIRING_SOON_DAYS = 14;

    /**
     * @return array{
     *     tenants_total: int,
     *     tenant_users_total: int,
     *     active_tenant_users: int,
     *     subscriptions_trial: int,
     *     subscriptions_active: int,
     *     subscriptions_past_due: int,
     *     subscriptions_suspended: int,
     *     subscriptions_cancelled: int,
     *     subscriptions_no_subscription: int,
     *     subscriptions_expiring_soon: int,
     *     subscriptions_expired: int,
     *     subscriptions_indefinite_active: int
     * }
     */
    public function metrics(CarbonImmutable $now): array
    {
        $soon = $now->addDays(self::EXPIRING_SOON_DAYS);
        $subscriptionBase = DB::query()->fromSub(
            $this->subscriptionBaseQuery(),
            'subscription_rows',
        );

        $subscriptionCounts = (clone $subscriptionBase)
            ->selectRaw(
                "SUM(CASE WHEN subscription_status = 'trial' THEN 1 ELSE 0 END) as trial_count",
            )
            ->selectRaw(
                "SUM(CASE WHEN subscription_status = 'active' THEN 1 ELSE 0 END) as active_count",
            )
            ->selectRaw(
                "SUM(CASE WHEN subscription_status = 'past_due' THEN 1 ELSE 0 END) as past_due_count",
            )
            ->selectRaw(
                "SUM(CASE WHEN subscription_status = 'suspended' THEN 1 ELSE 0 END) as suspended_count",
            )
            ->selectRaw(
                "SUM(CASE WHEN subscription_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count",
            )
            ->selectRaw(
                'SUM(CASE WHEN subscription_id IS NULL THEN 1 ELSE 0 END) as no_subscription_count',
            )
            ->selectRaw(
                "SUM(CASE WHEN subscription_status = 'active' AND current_period_ends_at IS NULL THEN 1 ELSE 0 END) as indefinite_active_count",
            )
            ->first();

        return [
            'tenants_total' => DB::table('tenants')
                ->whereNull('deleted_at')
                ->count(),
            'tenant_users_total' => DB::table('users')
                ->whereNull('deleted_at')
                ->count(),
            'active_tenant_users' => DB::table('users')
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->count(),
            'subscriptions_trial' => (int) ($subscriptionCounts?->trial_count ?? 0),
            'subscriptions_active' => (int) ($subscriptionCounts?->active_count ?? 0),
            'subscriptions_past_due' => (int) ($subscriptionCounts?->past_due_count ?? 0),
            'subscriptions_suspended' => (int) ($subscriptionCounts?->suspended_count ?? 0),
            'subscriptions_cancelled' => (int) ($subscriptionCounts?->cancelled_count ?? 0),
            'subscriptions_no_subscription' => (int) ($subscriptionCounts?->no_subscription_count ?? 0),
            'subscriptions_expiring_soon' => $this->expiryCount(
                now: $now,
                soon: $soon,
                expired: false,
            ),
            'subscriptions_expired' => $this->expiryCount(
                now: $now,
                soon: $soon,
                expired: true,
            ),
            'subscriptions_indefinite_active' => (int) ($subscriptionCounts?->indefinite_active_count ?? 0),
        ];
    }

    /**
     * @return list<array{
     *     plan_id: int|null,
     *     code: string|null,
     *     name: string,
     *     status: string|null,
     *     subscriptions_count: int,
     *     percentage: float
     * }>
     */
    public function packageDistribution(): array
    {
        $totalSubscriptions = DB::table('tenant_subscriptions')
            ->join(
                'tenants',
                static function (JoinClause $join): void {
                    $join
                        ->on('tenants.id', '=', 'tenant_subscriptions.tenant_id')
                        ->whereNull('tenants.deleted_at');
                },
            )
            ->count();

        if ($totalSubscriptions === 0) {
            return [];
        }

        return DB::table('tenant_subscriptions')
            ->join(
                'tenants',
                static function (JoinClause $join): void {
                    $join
                        ->on('tenants.id', '=', 'tenant_subscriptions.tenant_id')
                        ->whereNull('tenants.deleted_at');
                },
            )
            ->leftJoin(
                'saas_plans',
                'saas_plans.id',
                '=',
                'tenant_subscriptions.saas_plan_id',
            )
            ->groupBy([
                'saas_plans.id',
                'saas_plans.code',
                'saas_plans.name',
                'saas_plans.status',
                'saas_plans.sort_order',
            ])
            ->select([
                'saas_plans.id as plan_id',
                'saas_plans.code',
                'saas_plans.name',
                'saas_plans.status',
            ])
            ->selectRaw('COUNT(*) as subscriptions_count')
            ->orderByRaw('saas_plans.sort_order IS NULL')
            ->orderBy('saas_plans.sort_order')
            ->orderByDesc('subscriptions_count')
            ->get()
            ->map(
                static function (stdClass $row) use ($totalSubscriptions): array {
                    $count = (int) $row->subscriptions_count;

                    return [
                        'plan_id' => $row->plan_id === null
                            ? null
                            : (int) $row->plan_id,
                        'code' => $row->code === null
                            ? null
                            : (string) $row->code,
                        'name' => $row->name === null
                            ? 'Unknown package'
                            : (string) $row->name,
                        'status' => $row->status === null
                            ? null
                            : (string) $row->status,
                        'subscriptions_count' => $count,
                        'percentage' => round(
                            ($count / $totalSubscriptions) * 100,
                            1,
                        ),
                    ];
                },
            )
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     tenant: array{id: int, name: string, code: string, status: string},
     *     plan: array{id: int|null, name: string, code: string|null},
     *     subscription_status: string,
     *     alert_status: string,
     *     effective_expiry_at: string|null,
     *     days_remaining: int|null
     * }>
     */
    public function subscriptionAlerts(
        CarbonImmutable $now,
        int $limit = 8,
    ): array {
        $soon = $now->addDays(self::EXPIRING_SOON_DAYS);

        return DB::query()
            ->fromSub($this->subscriptionBaseQuery(), 'subscription_rows')
            ->whereNotNull('subscription_id')
            ->where(
                static function (Builder $query) use ($now, $soon): void {
                    $query
                        ->whereIn('subscription_status', [
                            'past_due',
                            'suspended',
                        ])
                        ->orWhere(
                            static function (Builder $expiryQuery) use ($now, $soon): void {
                                $expiryQuery
                                    ->whereNotNull('effective_expiry_at')
                                    ->where('effective_expiry_at', '<=', $soon);
                            },
                        );
                },
            )
            ->orderByRaw(
                "CASE
                    WHEN effective_expiry_at IS NOT NULL AND effective_expiry_at <= ? THEN 1
                    WHEN subscription_status = 'past_due' THEN 2
                    WHEN subscription_status = 'suspended' THEN 3
                    ELSE 4
                END",
                [$now],
            )
            ->orderBy('effective_expiry_at')
            ->orderBy('tenant_name')
            ->limit($limit)
            ->get()
            ->map(
                function (stdClass $row) use ($now): array {
                    $effectiveExpiry = $row->effective_expiry_at === null
                        ? null
                        : CarbonImmutable::parse((string) $row->effective_expiry_at);
                    $daysRemaining = $effectiveExpiry === null
                        ? null
                        : $now->startOfDay()->diffInDays(
                            $effectiveExpiry->startOfDay(),
                            false,
                        );

                    return [
                        'tenant' => [
                            'id' => (int) $row->tenant_id,
                            'name' => (string) $row->tenant_name,
                            'code' => (string) $row->company_code,
                            'status' => (string) $row->tenant_status,
                        ],
                        'plan' => [
                            'id' => $row->plan_id === null
                                ? null
                                : (int) $row->plan_id,
                            'name' => $row->plan_name === null
                                ? 'No package'
                                : (string) $row->plan_name,
                            'code' => $row->plan_code === null
                                ? null
                                : (string) $row->plan_code,
                        ],
                        'subscription_status' => (string) $row->subscription_status,
                        'alert_status' => $this->subscriptionAlertStatus(
                            row: $row,
                            now: $now,
                        ),
                        'effective_expiry_at' => $effectiveExpiry?->toIso8601String(),
                        'days_remaining' => $daysRemaining,
                    ];
                },
            )
            ->values()
            ->all();
    }

    private function subscriptionBaseQuery(): Builder
    {
        return DB::table('tenants')
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
            ->whereNull('tenants.deleted_at')
            ->select([
                'tenants.id as tenant_id',
                'tenants.name as tenant_name',
                'tenants.code as company_code',
                'tenants.status as tenant_status',
                'tenant_subscriptions.id as subscription_id',
                'tenant_subscriptions.status as subscription_status',
                'tenant_subscriptions.current_period_ends_at',
                'tenant_subscriptions.trial_ends_at',
                'tenant_subscriptions.grace_ends_at',
                'tenant_subscriptions.suspension_reason',
                'saas_plans.id as plan_id',
                'saas_plans.code as plan_code',
                'saas_plans.name as plan_name',
            ])
            ->selectRaw($this->effectiveExpirySql().' as effective_expiry_at');
    }

    private function expiryCount(
        CarbonImmutable $now,
        CarbonImmutable $soon,
        bool $expired,
    ): int {
        $query = DB::query()
            ->fromSub($this->subscriptionBaseQuery(), 'subscription_rows')
            ->whereNotNull('effective_expiry_at');

        if ($expired) {
            return $query
                ->where('effective_expiry_at', '<=', $now)
                ->count();
        }

        return $query
            ->where('effective_expiry_at', '>', $now)
            ->where('effective_expiry_at', '<=', $soon)
            ->count();
    }

    private function effectiveExpirySql(): string
    {
        return <<<'SQL'
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
    }

    private function subscriptionAlertStatus(
        stdClass $row,
        CarbonImmutable $now,
    ): string {
        if (
            $row->effective_expiry_at !== null
            && CarbonImmutable::parse((string) $row->effective_expiry_at)->lte($now)
        ) {
            return 'expired';
        }

        if ($row->subscription_status === 'past_due') {
            return 'past_due';
        }

        if ($row->subscription_status === 'suspended') {
            return 'suspended';
        }

        return 'expiring_soon';
    }
}
