<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Support\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PlatformTenantLifecycleService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function activate(Tenant $tenant): Tenant
    {
        return $this->transition(
            tenant: $tenant,
            targetStatus: 'active',
            allowedFrom: [
                'trial',
                'suspended',
                'past_due',
            ],
        );
    }

    public function suspend(Tenant $tenant): Tenant
    {
        return $this->transition(
            tenant: $tenant,
            targetStatus: 'suspended',
            allowedFrom: [
                'trial',
                'active',
                'past_due',
            ],
        );
    }

    /**
     * @param list<string> $allowedFrom
     */
    private function transition(
        Tenant $tenant,
        string $targetStatus,
        array $allowedFrom,
    ): Tenant {
        if ($tenant->status === $targetStatus) {
            return $tenant;
        }

        if (!in_array($tenant->status, $allowedFrom, true)) {
            throw new DomainException(
                sprintf(
                    'Tenant status cannot change from "%s" to "%s".',
                    $tenant->status,
                    $targetStatus,
                ),
            );
        }

        $previousTenant = $this->tenantContext->get();

        try {
            $this->tenantContext->set($tenant);

            return DB::transaction(
                function () use ($tenant, $targetStatus): Tenant {
                    $lockedTenant = Tenant::query()
                        ->whereKey($tenant->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $lockedTenant->status = $targetStatus;
                    $lockedTenant->save();

                    $subscription = TenantSubscription::query()
                        ->where('tenant_id', $lockedTenant->getKey())
                        ->lockForUpdate()
                        ->first();

                    if ($subscription instanceof TenantSubscription) {
                        if ($targetStatus === 'active') {
                            $subscription->forceFill([
                                'status' => 'active',
                                'trial_ends_at' => null,
                                'past_due_at' => null,
                                'past_due_reason' => null,
                                'grace_ends_at' => null,
                                'suspended_at' => null,
                                'suspension_reason' => null,
                                'cancelled_at' => null,
                                'ends_at' => null,
                                'current_period_starts_at' => now(),
                                'current_period_ends_at' => null,
                                'lifecycle_processed_at' => now(),
                            ])->save();
                        } else {
                            $subscription->forceFill([
                                'status' => 'suspended',
                                'suspended_at' => now(),
                                'suspension_reason' => 'manual',
                                'lifecycle_processed_at' => now(),
                            ])->save();
                        }
                    }

                    return $lockedTenant->refresh();
                },
            );
        } finally {
            if ($previousTenant instanceof Tenant) {
                if (
                    (int) $previousTenant->getKey()
                    === (int) $tenant->getKey()
                ) {
                    $freshTenant = Tenant::query()
                        ->find($tenant->getKey());

                    if ($freshTenant instanceof Tenant) {
                        $this->tenantContext->set($freshTenant);
                    }
                } else {
                    $this->tenantContext->set($previousTenant);
                }
            } else {
                $this->tenantContext->clear();
            }
        }
    }
}
