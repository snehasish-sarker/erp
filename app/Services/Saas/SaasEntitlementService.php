<?php

declare(strict_types=1);

namespace App\Services\Saas;

use App\Models\SaasPlanFeature;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use DomainException;

final class SaasEntitlementService
{
    /**
     * @var array<int, array{
     *     features: list<string>,
     *     limits: array<string, int|null>
     * }>
     */
    private array $snapshotCache = [];

    public function featureEnabled(
        Tenant $tenant,
        string $featureKey,
    ): bool {
        return in_array(
            $featureKey,
            $this->snapshot($tenant)['features'],
            true,
        );
    }

    /**
     * @param list<string> $featureKeys
     */
    public function featuresEnabled(
        Tenant $tenant,
        array $featureKeys,
    ): bool {
        foreach ($featureKeys as $featureKey) {
            if (!$this->featureEnabled($tenant, $featureKey)) {
                return false;
            }
        }

        return true;
    }

    public function limit(
        Tenant $tenant,
        string $featureKey,
    ): ?int {
        $snapshot = $this->snapshot($tenant);

        if (!array_key_exists($featureKey, $snapshot['limits'])) {
            return 0;
        }

        return $snapshot['limits'][$featureKey];
    }

    /**
     * @return list<string>
     */
    public function enabledFeatureKeys(Tenant $tenant): array
    {
        return $this->snapshot($tenant)['features'];
    }

    /**
     * @return array<string, int|null>
     */
    public function limits(Tenant $tenant): array
    {
        return $this->snapshot($tenant)['limits'];
    }

    public function assertFeatureEnabled(
        Tenant $tenant,
        string $featureKey,
    ): void {
        if ($this->featureEnabled($tenant, $featureKey)) {
            return;
        }

        throw new DomainException(
            "The current SaaS plan does not include the [{$featureKey}] feature.",
        );
    }

    public function assertWithinLimit(
        Tenant $tenant,
        string $featureKey,
        int $currentUsage,
        int $additionalUsage = 1,
    ): void {
        $limit = $this->limit($tenant, $featureKey);

        if ($limit === null) {
            return;
        }

        if (($currentUsage + $additionalUsage) <= $limit) {
            return;
        }

        throw new DomainException(
            "The current SaaS plan limit for [{$featureKey}] is {$limit}.",
        );
    }

    public function subscription(Tenant $tenant): ?TenantSubscription
    {
        return TenantSubscription::query()
            ->with('plan')
            ->where('tenant_id', $tenant->getKey())
            ->first();
    }

    public function forget(Tenant $tenant): void
    {
        unset(
            $this->snapshotCache[(int) $tenant->getKey()],
        );
    }

    /**
     * @return array{
     *     features: list<string>,
     *     limits: array<string, int|null>
     * }
     */
    private function snapshot(Tenant $tenant): array
    {
        $tenantId = (int) $tenant->getKey();

        if (isset($this->snapshotCache[$tenantId])) {
            return $this->snapshotCache[$tenantId];
        }

        $subscription = TenantSubscription::query()
            ->with([
                'plan.entitlements.feature',
            ])
            ->where('tenant_id', $tenantId)
            ->first();

        if (
            !$subscription instanceof TenantSubscription
            || !$subscription->allowsTenantAccessAt()
        ) {
            return $this->snapshotCache[$tenantId] = [
                'features' => [],
                'limits' => [],
            ];
        }

        $plan = $subscription->plan;

        if ($plan === null || $plan->status !== 'active') {
            return $this->snapshotCache[$tenantId] = [
                'features' => [],
                'limits' => [],
            ];
        }

        $features = [];
        $limits = [];

        /** @var SaasPlanFeature $entitlement */
        foreach ($plan->entitlements as $entitlement) {
            $feature = $entitlement->feature;

            if (
                $entitlement->enabled !== true
                || $feature === null
                || $feature->status !== 'active'
            ) {
                continue;
            }

            $features[] = $feature->key;

            if ($feature->value_type === 'limit') {
                $limits[$feature->key] =
                    $entitlement->limit_value === null
                        ? null
                        : (int) $entitlement->limit_value;
            }
        }

        sort($features);
        ksort($limits);

        return $this->snapshotCache[$tenantId] = [
            'features' => array_values(
                array_unique($features),
            ),
            'limits' => $limits,
        ];
    }
}
