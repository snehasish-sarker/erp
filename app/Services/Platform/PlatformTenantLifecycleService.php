<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Auditing\AuditLogService;
use App\Support\Tenancy\TenantContext;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PlatformTenantLifecycleService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogService $auditLogService,
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

                    $oldTenantStatus = (string) $lockedTenant->status;

                    $lockedTenant->status = $targetStatus;
                    $lockedTenant->save();

                    $subscription = TenantSubscription::query()
                        ->where('tenant_id', $lockedTenant->getKey())
                        ->lockForUpdate()
                        ->first();

                    $oldSubscriptionValues = [];

                    if ($subscription instanceof TenantSubscription) {
                        $oldSubscriptionValues = $subscription->only([
                            'status',
                            'trial_ends_at',
                            'current_period_starts_at',
                            'current_period_ends_at',
                            'past_due_at',
                            'grace_ends_at',
                            'suspended_at',
                            'cancelled_at',
                            'ends_at',
                        ]);

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

                    $this->auditLogService->recordCustomEvent(
                        subject: $lockedTenant,
                        event: $targetStatus === 'active'
                            ? 'saas_subscription_manually_activated'
                            : 'saas_subscription_manually_suspended',
                        oldValues: [
                            'tenant_status' => $oldTenantStatus,
                            ...$oldSubscriptionValues,
                        ],
                        newValues: [
                            'tenant_status' => $targetStatus,
                            'status' => $subscription?->status,
                            'trial_ends_at' => $subscription?->trial_ends_at,
                            'current_period_starts_at' => $subscription?->current_period_starts_at,
                            'current_period_ends_at' => $subscription?->current_period_ends_at,
                            'past_due_at' => $subscription?->past_due_at,
                            'grace_ends_at' => $subscription?->grace_ends_at,
                            'suspended_at' => $subscription?->suspended_at,
                            'cancelled_at' => $subscription?->cancelled_at,
                            'ends_at' => $subscription?->ends_at,
                        ],
                        metadata: [
                            'tenant_subscription_id' => $subscription?->getKey(),
                            'manual_lifecycle_action' => true,
                        ],
                    );

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
