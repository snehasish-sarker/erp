<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use stdClass;

final class SaasUsageMonitoringService
{
    private const STORAGE_BYTES_PER_MB = 1048576;

    private const NEAR_LIMIT_RATIO = 0.8;

    /**
     * @var array<string, string>
     */
    private const LIMIT_FEATURE_KEYS = [
        'users' => 'users.limit',
        'branches' => 'branches.limit',
        'warehouses' => 'warehouses.limit',
        'products' => 'products.limit',
        'storage' => 'storage_mb.limit',
    ];

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array<string, int|string|null>
     * }
     */
    public function paginate(array $filters): array
    {
        $query = DB::query()
            ->fromSub(
                $this->baseUsageQuery(),
                'usage_rows',
            );

        $this->applyFilters($query, $filters);
        $this->applySort(
            query: $query,
            sort: (string) $filters['sort'],
            direction: (string) $filters['direction'],
        );

        /** @var LengthAwarePaginator<int, stdClass> $paginator */
        $paginator = $query
            ->orderBy('tenant_id')
            ->paginate((int) $filters['per_page'])
            ->withQueryString();

        return [
            'data' => $paginator->getCollection()
                ->map(
                    fn (stdClass $row): array => $this->presentRow($row),
                )
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
                'previous_page_url' => $paginator->previousPageUrl(),
                'next_page_url' => $paginator->nextPageUrl(),
            ],
        ];
    }

    /**
     * @return array{
     *     tenants_total: int,
     *     healthy: int,
     *     near_limit: int,
     *     at_limit: int,
     *     over_limit: int,
     *     no_subscription: int
     * }
     */
    public function metrics(): array
    {
        $overallStatusSql = $this->overallStatusSql();

        $row = DB::query()
            ->fromSub(
                $this->baseUsageQuery(),
                'usage_rows',
            )
            ->selectRaw('COUNT(*) as tenants_total')
            ->selectRaw(
                "SUM(CASE WHEN {$overallStatusSql} = 'healthy' THEN 1 ELSE 0 END) as healthy",
            )
            ->selectRaw(
                "SUM(CASE WHEN {$overallStatusSql} = 'near_limit' THEN 1 ELSE 0 END) as near_limit",
            )
            ->selectRaw(
                "SUM(CASE WHEN {$overallStatusSql} = 'at_limit' THEN 1 ELSE 0 END) as at_limit",
            )
            ->selectRaw(
                "SUM(CASE WHEN {$overallStatusSql} = 'over_limit' THEN 1 ELSE 0 END) as over_limit",
            )
            ->selectRaw(
                "SUM(CASE WHEN {$overallStatusSql} = 'no_subscription' THEN 1 ELSE 0 END) as no_subscription",
            )
            ->first();

        return [
            'tenants_total' => (int) ($row?->tenants_total ?? 0),
            'healthy' => (int) ($row?->healthy ?? 0),
            'near_limit' => (int) ($row?->near_limit ?? 0),
            'at_limit' => (int) ($row?->at_limit ?? 0),
            'over_limit' => (int) ($row?->over_limit ?? 0),
            'no_subscription' => (int) ($row?->no_subscription ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function alerts(int $limit = 6): array
    {
        $overallStatusSql = $this->overallStatusSql();

        return DB::query()
            ->fromSub(
                $this->baseUsageQuery(),
                'usage_rows',
            )
            ->whereRaw(
                "{$overallStatusSql} IN ('over_limit', 'at_limit', 'near_limit')",
            )
            ->orderByRaw(
                "CASE {$overallStatusSql}
                    WHEN 'over_limit' THEN 1
                    WHEN 'at_limit' THEN 2
                    WHEN 'near_limit' THEN 3
                    ELSE 4
                END",
            )
            ->orderBy('tenant_name')
            ->orderBy('tenant_id')
            ->limit($limit)
            ->get()
            ->map(
                fn (stdClass $row): array => $this->presentRow($row),
            )
            ->values()
            ->all();
    }

    private function baseUsageQuery(): Builder
    {
        $activeUsers = DB::table('users')
            ->selectRaw('tenant_id, COUNT(*) as users_usage')
            ->whereNull('deleted_at')
            ->where('status', 'active')
            ->groupBy('tenant_id');

        $branches = DB::table('branches')
            ->selectRaw('tenant_id, COUNT(*) as branches_usage')
            ->whereNull('deleted_at')
            ->groupBy('tenant_id');

        $warehouses = DB::table('warehouses')
            ->selectRaw('tenant_id, COUNT(*) as warehouses_usage')
            ->whereNull('deleted_at')
            ->groupBy('tenant_id');

        $products = DB::table('products')
            ->selectRaw('tenant_id, COUNT(*) as products_usage')
            ->whereNull('deleted_at')
            ->groupBy('tenant_id');

        $storage = DB::table('tenant_files')
            ->selectRaw(
                'tenant_id, COALESCE(SUM(size_bytes), 0) as storage_bytes_usage',
            )
            ->whereNull('deleted_at')
            ->groupBy('tenant_id');

        $planLimits = $this->planLimitsQuery();

        return DB::table('tenants')
            ->leftJoin(
                'tenant_subscriptions',
                'tenant_subscriptions.tenant_id',
                '=',
                'tenants.id',
            )
            ->leftJoin(
                'saas_plans',
                static function (JoinClause $join): void {
                    $join
                        ->on(
                            'saas_plans.id',
                            '=',
                            'tenant_subscriptions.saas_plan_id',
                        )
                        ->whereNull('saas_plans.deleted_at');
                },
            )
            ->leftJoinSub(
                $activeUsers,
                'active_users',
                'active_users.tenant_id',
                '=',
                'tenants.id',
            )
            ->leftJoinSub(
                $branches,
                'branch_usage',
                'branch_usage.tenant_id',
                '=',
                'tenants.id',
            )
            ->leftJoinSub(
                $warehouses,
                'warehouse_usage',
                'warehouse_usage.tenant_id',
                '=',
                'tenants.id',
            )
            ->leftJoinSub(
                $products,
                'product_usage',
                'product_usage.tenant_id',
                '=',
                'tenants.id',
            )
            ->leftJoinSub(
                $storage,
                'storage_usage',
                'storage_usage.tenant_id',
                '=',
                'tenants.id',
            )
            ->leftJoinSub(
                $planLimits,
                'plan_limits',
                'plan_limits.saas_plan_id',
                '=',
                'saas_plans.id',
            )
            ->whereNull('tenants.deleted_at')
            ->select([
                'tenants.id as tenant_id',
                'tenants.name as tenant_name',
                'tenants.code as company_code',
                'tenants.status as tenant_status',
                'tenants.email as tenant_email',
                'tenant_subscriptions.id as subscription_id',
                'tenant_subscriptions.status as subscription_status',
                'saas_plans.id as plan_id',
                'saas_plans.code as plan_code',
                'saas_plans.name as plan_name',
                'saas_plans.status as plan_status',
                'plan_limits.users_limit_enabled',
                'plan_limits.users_limit',
                'plan_limits.branches_limit_enabled',
                'plan_limits.branches_limit',
                'plan_limits.warehouses_limit_enabled',
                'plan_limits.warehouses_limit',
                'plan_limits.products_limit_enabled',
                'plan_limits.products_limit',
                'plan_limits.storage_limit_enabled',
                'plan_limits.storage_limit',
            ])
            ->selectRaw('COALESCE(active_users.users_usage, 0) as users_usage')
            ->selectRaw('COALESCE(branch_usage.branches_usage, 0) as branches_usage')
            ->selectRaw('COALESCE(warehouse_usage.warehouses_usage, 0) as warehouses_usage')
            ->selectRaw('COALESCE(product_usage.products_usage, 0) as products_usage')
            ->selectRaw('COALESCE(storage_usage.storage_bytes_usage, 0) as storage_bytes_usage');
    }

    private function planLimitsQuery(): Builder
    {
        $query = DB::table('saas_plan_features')
            ->join(
                'saas_features',
                static function (JoinClause $join): void {
                    $join
                        ->on(
                            'saas_features.id',
                            '=',
                            'saas_plan_features.saas_feature_id',
                        )
                        ->whereNull('saas_features.deleted_at');
                },
            )
            ->where('saas_features.status', 'active')
            ->where('saas_features.value_type', 'limit')
            ->whereIn(
                'saas_features.key',
                array_values(self::LIMIT_FEATURE_KEYS),
            )
            ->groupBy('saas_plan_features.saas_plan_id')
            ->select('saas_plan_features.saas_plan_id');

        foreach (self::LIMIT_FEATURE_KEYS as $resource => $featureKey) {
            $query
                ->selectRaw(
                    "MAX(CASE WHEN saas_features.key = ? THEN CASE WHEN saas_plan_features.enabled = 1 THEN 1 ELSE 0 END END) as {$resource}_limit_enabled",
                    [$featureKey],
                )
                ->selectRaw(
                    "MAX(CASE WHEN saas_features.key = ? AND saas_plan_features.enabled = 1 THEN saas_plan_features.limit_value END) as {$resource}_limit",
                    [$featureKey],
                );
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(
        Builder $query,
        array $filters,
    ): void {
        $search = $filters['search'] ?? null;

        if (is_string($search) && $search !== '') {
            $query->where(
                static function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('tenant_name', 'like', "%{$search}%")
                        ->orWhere('company_code', 'like', "%{$search}%")
                        ->orWhere('tenant_email', 'like', "%{$search}%")
                        ->orWhere('plan_name', 'like', "%{$search}%")
                        ->orWhere('plan_code', 'like', "%{$search}%");
                },
            );
        }

        if (isset($filters['saas_plan_id'])) {
            $query->where('plan_id', (int) $filters['saas_plan_id']);
        }

        if (isset($filters['tenant_status'])) {
            $query->where(
                'tenant_status',
                (string) $filters['tenant_status'],
            );
        }

        $subscriptionStatus = $filters['subscription_status'] ?? null;

        if ($subscriptionStatus === 'no_subscription') {
            $query->whereNull('subscription_id');
        } elseif (is_string($subscriptionStatus) && $subscriptionStatus !== '') {
            $query->where('subscription_status', $subscriptionStatus);
        }

        $capacity = $filters['capacity'] ?? null;

        if (!is_string($capacity) || $capacity === '') {
            return;
        }

        $resource = (string) $filters['resource'];
        $statusSql = $resource === 'all'
            ? $this->overallStatusSql()
            : $this->resourceStatusSql($resource);

        $query->whereRaw("{$statusSql} = ?", [$capacity]);
    }

    private function applySort(
        Builder $query,
        string $sort,
        string $direction,
    ): void {
        $column = match ($sort) {
            'company_code' => 'company_code',
            'package' => 'plan_name',
            'tenant_status' => 'tenant_status',
            'subscription_status' => 'subscription_status',
            'users_usage' => 'users_usage',
            'branches_usage' => 'branches_usage',
            'warehouses_usage' => 'warehouses_usage',
            'products_usage' => 'products_usage',
            'storage_usage' => 'storage_bytes_usage',
            default => 'tenant_name',
        };

        $query->orderBy($column, $direction);
    }

    private function overallStatusSql(): string
    {
        $over = [];
        $at = [];
        $near = [];

        foreach (array_keys(self::LIMIT_FEATURE_KEYS) as $resource) {
            $statusSql = $this->resourceStatusSql($resource);
            $over[] = "({$statusSql}) = 'over_limit'";
            $at[] = "({$statusSql}) = 'at_limit'";
            $near[] = "({$statusSql}) = 'near_limit'";
        }

        return sprintf(
            "CASE\n".
            "WHEN subscription_id IS NULL OR plan_id IS NULL THEN 'no_subscription'\n".
            "WHEN %s THEN 'over_limit'\n".
            "WHEN %s THEN 'at_limit'\n".
            "WHEN %s THEN 'near_limit'\n".
            "ELSE 'healthy' END",
            implode(' OR ', $over),
            implode(' OR ', $at),
            implode(' OR ', $near),
        );
    }

    private function resourceStatusSql(string $resource): string
    {
        $enabledColumn = "{$resource}_limit_enabled";
        $limitColumn = "{$resource}_limit";
        $usageColumn = $resource === 'storage'
            ? 'storage_bytes_usage'
            : "{$resource}_usage";
        $effectiveLimit = $resource === 'storage'
            ? "({$limitColumn} * ".self::STORAGE_BYTES_PER_MB.')'
            : $limitColumn;

        return "CASE\n".
            "WHEN subscription_id IS NULL OR plan_id IS NULL THEN 'no_subscription'\n".
            "WHEN COALESCE({$enabledColumn}, 0) = 0 AND {$usageColumn} > 0 THEN 'over_limit'\n".
            "WHEN COALESCE({$enabledColumn}, 0) = 0 THEN 'not_included'\n".
            "WHEN {$limitColumn} IS NULL THEN 'unlimited'\n".
            "WHEN {$usageColumn} > {$effectiveLimit} THEN 'over_limit'\n".
            "WHEN {$usageColumn} = {$effectiveLimit} THEN 'at_limit'\n".
            "WHEN {$effectiveLimit} > 0 AND ({$usageColumn} / NULLIF({$effectiveLimit}, 0)) >= ".self::NEAR_LIMIT_RATIO." THEN 'near_limit'\n".
            "ELSE 'healthy' END";
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRow(stdClass $row): array
    {
        $hasSubscriptionPlan = $row->subscription_id !== null
            && $row->plan_id !== null;

        $resources = [
            'users' => $this->countResource(
                key: 'users',
                label: 'Active users',
                usage: (int) $row->users_usage,
                enabled: (int) ($row->users_limit_enabled ?? 0),
                limit: $this->nullableInt($row->users_limit),
                hasSubscriptionPlan: $hasSubscriptionPlan,
            ),
            'branches' => $this->countResource(
                key: 'branches',
                label: 'Branches',
                usage: (int) $row->branches_usage,
                enabled: (int) ($row->branches_limit_enabled ?? 0),
                limit: $this->nullableInt($row->branches_limit),
                hasSubscriptionPlan: $hasSubscriptionPlan,
            ),
            'warehouses' => $this->countResource(
                key: 'warehouses',
                label: 'Warehouses',
                usage: (int) $row->warehouses_usage,
                enabled: (int) ($row->warehouses_limit_enabled ?? 0),
                limit: $this->nullableInt($row->warehouses_limit),
                hasSubscriptionPlan: $hasSubscriptionPlan,
            ),
            'products' => $this->countResource(
                key: 'products',
                label: 'Products',
                usage: (int) $row->products_usage,
                enabled: (int) ($row->products_limit_enabled ?? 0),
                limit: $this->nullableInt($row->products_limit),
                hasSubscriptionPlan: $hasSubscriptionPlan,
            ),
            'storage' => $this->storageResource(
                usageBytes: (int) $row->storage_bytes_usage,
                enabled: (int) ($row->storage_limit_enabled ?? 0),
                limitMb: $this->nullableInt($row->storage_limit),
                hasSubscriptionPlan: $hasSubscriptionPlan,
            ),
        ];

        return [
            'tenant' => [
                'id' => (int) $row->tenant_id,
                'name' => (string) $row->tenant_name,
                'code' => (string) $row->company_code,
                'email' => $row->tenant_email,
                'status' => (string) $row->tenant_status,
            ],
            'subscription_status' => $row->subscription_status,
            'plan' => $row->plan_id === null
                ? null
                : [
                    'id' => (int) $row->plan_id,
                    'code' => (string) $row->plan_code,
                    'name' => (string) $row->plan_name,
                    'status' => (string) $row->plan_status,
                ],
            'overall_status' => $this->overallStatus($resources),
            'resources' => $resources,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function countResource(
        string $key,
        string $label,
        int $usage,
        int $enabled,
        ?int $limit,
        bool $hasSubscriptionPlan,
    ): array {
        $status = $this->capacityStatus(
            usage: $usage,
            enabled: $enabled,
            limit: $limit,
            hasSubscriptionPlan: $hasSubscriptionPlan,
        );

        return [
            'key' => $key,
            'label' => $label,
            'usage' => $usage,
            'limit' => $status === 'unlimited' ? null : $limit,
            'remaining' => $limit === null
                ? null
                : max($limit - $usage, 0),
            'percentage' => $this->percentage($usage, $limit, $enabled),
            'unit' => 'count',
            'status' => $status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function storageResource(
        int $usageBytes,
        int $enabled,
        ?int $limitMb,
        bool $hasSubscriptionPlan,
    ): array {
        $limitBytes = $limitMb === null
            ? null
            : $limitMb * self::STORAGE_BYTES_PER_MB;
        $status = $this->capacityStatus(
            usage: $usageBytes,
            enabled: $enabled,
            limit: $limitBytes,
            hasSubscriptionPlan: $hasSubscriptionPlan,
        );
        $usageMb = $usageBytes / self::STORAGE_BYTES_PER_MB;
        $remainingMb = $limitBytes === null
            ? null
            : max($limitBytes - $usageBytes, 0)
                / self::STORAGE_BYTES_PER_MB;

        return [
            'key' => 'storage',
            'label' => 'Storage',
            'usage' => round($usageMb, 2),
            'limit' => $status === 'unlimited' ? null : $limitMb,
            'remaining' => $remainingMb === null
                ? null
                : round($remainingMb, 2),
            'percentage' => $this->percentage(
                usage: $usageBytes,
                limit: $limitBytes,
                enabled: $enabled,
            ),
            'unit' => 'MB',
            'status' => $status,
        ];
    }

    private function capacityStatus(
        int|float $usage,
        int $enabled,
        ?int $limit,
        bool $hasSubscriptionPlan,
    ): string {
        if (!$hasSubscriptionPlan) {
            return 'no_subscription';
        }

        if ($enabled !== 1) {
            return $usage > 0
                ? 'over_limit'
                : 'not_included';
        }

        if ($limit === null) {
            return 'unlimited';
        }

        if ($usage > $limit) {
            return 'over_limit';
        }

        if ($usage === $limit) {
            return 'at_limit';
        }

        if (
            $limit > 0
            && ($usage / $limit) >= self::NEAR_LIMIT_RATIO
        ) {
            return 'near_limit';
        }

        return 'healthy';
    }

    private function percentage(
        int|float $usage,
        ?int $limit,
        int $enabled,
    ): ?float {
        if ($enabled !== 1 || $limit === null || $limit <= 0) {
            return null;
        }

        return round(($usage / $limit) * 100, 1);
    }

    /**
     * @param array<string, array<string, mixed>> $resources
     */
    private function overallStatus(array $resources): string
    {
        $statuses = array_map(
            static fn (array $resource): string => (string) $resource['status'],
            $resources,
        );

        if (in_array('no_subscription', $statuses, true)) {
            return 'no_subscription';
        }

        if (in_array('over_limit', $statuses, true)) {
            return 'over_limit';
        }

        if (in_array('at_limit', $statuses, true)) {
            return 'at_limit';
        }

        if (in_array('near_limit', $statuses, true)) {
            return 'near_limit';
        }

        return 'healthy';
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
