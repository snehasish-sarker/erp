<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\PlatformAdmin;
use App\Models\SaasPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Auditing\AuditLogService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaasSubscriptionService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function assignPlan(
        Tenant $tenant,
        SaasPlan $plan,
        ?PlatformAdmin $assignedBy = null,
        string $billingCycle = 'monthly',
    ): TenantSubscription {
        if ($plan->status !== 'active') {
            throw new DomainException(
                'Only active SaaS plans can be assigned to a tenant.',
            );
        }

        if (!in_array($billingCycle, ['monthly', 'annual'], true)) {
            throw new DomainException(
                'The SaaS billing cycle must be monthly or annual.',
            );
        }

        $previousTenant = $this->tenantContext->get();

        try {
            return DB::transaction(
                function () use (
                    $tenant,
                    $plan,
                    $assignedBy,
                    $billingCycle,
                ): TenantSubscription {
                    $lockedTenant = Tenant::query()
                        ->lockForUpdate()
                        ->findOrFail($tenant->getKey());

                    $this->tenantContext->set($lockedTenant);

                    $subscription = TenantSubscription::query()
                        ->lockForUpdate()
                        ->firstOrNew([
                            'tenant_id' => (int) $lockedTenant->getKey(),
                        ]);

                    $previousPlanId = $subscription->exists
                        ? (int) $subscription->saas_plan_id
                        : null;
                    $previousBillingCycle = $subscription->exists
                        ? $subscription->billing_cycle
                        : null;
                    $isNewSubscription = !$subscription->exists;

                    $status = match ($lockedTenant->status) {
                        'trial' => 'trial',
                        'active' => 'active',
                        'past_due' => 'past_due',
                        'suspended' => 'suspended',
                        default => 'cancelled',
                    };

                    $subscription->fill([
                        'saas_plan_id' => (int) $plan->getKey(),
                        'assigned_by_platform_admin_id' => $assignedBy?->getKey(),
                        'status' => $status,
                        'billing_cycle' => $billingCycle,
                        'billing_currency_code' => $plan->billing_currency_code,
                        'starts_at' => $subscription->starts_at ?? now(),
                        'ends_at' => $status === 'cancelled'
                            ? ($subscription->ends_at ?? now())
                            : null,
                    ]);

                    if (
                        $status === 'trial'
                        && $subscription->trial_ends_at === null
                    ) {
                        $trialDays = max(
                            1,
                            (int) config(
                                'saas.subscription.trial_days',
                                14,
                            ),
                        );

                        $subscription->trial_ends_at = now()
                            ->addDays($trialDays);
                    }

                    if ($isNewSubscription) {
                        $subscription->forceFill([
                            'current_period_starts_at' => null,
                            'current_period_ends_at' => null,
                            'past_due_at' => null,
                            'past_due_reason' => null,
                            'grace_ends_at' => null,
                            'suspended_at' => $status === 'suspended'
                                ? now()
                                : null,
                            'suspension_reason' => $status === 'suspended'
                                ? 'manual'
                                : null,
                            'cancelled_at' => $status === 'cancelled'
                                ? now()
                                : null,
                        ]);
                    }

                    $subscription->save();

                    $this->auditLogService->recordCustomEvent(
                        subject: $lockedTenant,
                        event: 'saas_plan_assigned',
                        oldValues: [
                            'saas_plan_id' => $previousPlanId,
                            'billing_cycle' => $previousBillingCycle,
                        ],
                        newValues: [
                            'saas_plan_id' => (int) $plan->getKey(),
                            'saas_plan_code' => $plan->code,
                            'subscription_status' => $subscription->status,
                            'billing_cycle' => $billingCycle,
                            'billing_currency_code' => $plan->billing_currency_code,
                            'trial_ends_at' => $subscription->trial_ends_at,
                        ],
                        metadata: [
                            'tenant_subscription_id' => (int) $subscription->getKey(),
                        ],
                    );

                    return $subscription->refresh()->load('plan');
                },
                attempts: 5,
            );
        } finally {
            $this->restoreTenantContext($previousTenant);
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateManualAllocation(
        Tenant $tenant,
        SaasPlan $plan,
        array $attributes,
        PlatformAdmin $assignedBy,
    ): TenantSubscription {
        if ($plan->status !== 'active') {
            throw new DomainException(
                'Only active SaaS plans can be allocated to a tenant.',
            );
        }

        $billingCycle = (string) $attributes['billing_cycle'];
        $status = (string) $attributes['status'];

        if (!in_array($billingCycle, ['monthly', 'annual'], true)) {
            throw new DomainException(
                'The SaaS billing cycle must be monthly or annual.',
            );
        }

        if (!in_array(
            $status,
            ['trial', 'active', 'past_due', 'suspended', 'cancelled'],
            true,
        )) {
            throw new DomainException(
                'The selected subscription status is not supported.',
            );
        }

        $previousTenant = $this->tenantContext->get();

        try {
            return DB::transaction(
                function () use (
                    $tenant,
                    $plan,
                    $attributes,
                    $assignedBy,
                    $billingCycle,
                    $status,
                ): TenantSubscription {
                    $lockedTenant = Tenant::query()
                        ->lockForUpdate()
                        ->findOrFail($tenant->getKey());

                    $this->tenantContext->set($lockedTenant);

                    $subscription = TenantSubscription::query()
                        ->lockForUpdate()
                        ->firstOrNew([
                            'tenant_id' => (int) $lockedTenant->getKey(),
                        ]);

                    $oldValues = $subscription->exists
                        ? $subscription->only([
                            'saas_plan_id',
                            'status',
                            'billing_cycle',
                            'billing_currency_code',
                            'starts_at',
                            'trial_ends_at',
                            'current_period_starts_at',
                            'current_period_ends_at',
                            'past_due_at',
                            'grace_ends_at',
                            'suspended_at',
                            'cancelled_at',
                            'ends_at',
                        ])
                        : [];

                    $startsAt = $this->dateValue(
                        $attributes['starts_at'] ?? null,
                    ) ?? CarbonImmutable::now();

                    $subscription->fill([
                        'saas_plan_id' => (int) $plan->getKey(),
                        'assigned_by_platform_admin_id' => (int) $assignedBy->getKey(),
                        'status' => $status,
                        'billing_cycle' => $billingCycle,
                        'billing_currency_code' => $plan->billing_currency_code,
                        'starts_at' => $startsAt,
                    ]);

                    $this->applyManualStatusFields(
                        subscription: $subscription,
                        status: $status,
                        attributes: $attributes,
                    );

                    $subscription->lifecycle_processed_at = now();
                    $subscription->save();

                    $lockedTenant->forceFill([
                        'status' => match ($status) {
                            'trial' => 'trial',
                            'active' => 'active',
                            'past_due' => 'past_due',
                            'suspended' => 'suspended',
                            'cancelled' => 'cancelled',
                        },
                    ])->save();

                    $this->auditLogService->recordCustomEvent(
                        subject: $lockedTenant,
                        event: 'saas_subscription_manually_updated',
                        oldValues: $oldValues,
                        newValues: [
                            'saas_plan_id' => (int) $plan->getKey(),
                            'saas_plan_code' => $plan->code,
                            'status' => $subscription->status,
                            'billing_cycle' => $subscription->billing_cycle,
                            'billing_currency_code' => $subscription->billing_currency_code,
                            'starts_at' => $subscription->starts_at,
                            'trial_ends_at' => $subscription->trial_ends_at,
                            'current_period_starts_at' => $subscription->current_period_starts_at,
                            'current_period_ends_at' => $subscription->current_period_ends_at,
                            'past_due_at' => $subscription->past_due_at,
                            'grace_ends_at' => $subscription->grace_ends_at,
                            'suspended_at' => $subscription->suspended_at,
                            'cancelled_at' => $subscription->cancelled_at,
                            'ends_at' => $subscription->ends_at,
                        ],
                        metadata: [
                            'tenant_subscription_id' => (int) $subscription->getKey(),
                            'manual_allocation' => true,
                        ],
                    );

                    return $subscription->refresh()->load('plan');
                },
                attempts: 5,
            );
        } finally {
            $this->restoreTenantContext($previousTenant);
        }
    }

    public function assignDefaultPlan(
        Tenant $tenant,
        ?PlatformAdmin $assignedBy = null,
    ): TenantSubscription {
        $defaultPlan = SaasPlan::query()
            ->where('status', 'active')
            ->where('is_default', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (!$defaultPlan instanceof SaasPlan) {
            throw new DomainException(
                'A default active SaaS plan must exist before provisioning tenants.',
            );
        }

        return $this->assignPlan(
            tenant: $tenant,
            plan: $defaultPlan,
            assignedBy: $assignedBy,
            billingCycle: 'monthly',
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function applyManualStatusFields(
        TenantSubscription $subscription,
        string $status,
        array $attributes,
    ): void {
        $currentPeriodStartsAt = $this->dateValue(
            $attributes['current_period_starts_at'] ?? null,
        );
        $currentPeriodEndsAt = $this->dateValue(
            $attributes['current_period_ends_at'] ?? null,
        );

        $subscription->forceFill([
            'trial_ends_at' => $this->dateValue(
                $attributes['trial_ends_at'] ?? null,
            ),
            'current_period_starts_at' => $currentPeriodStartsAt,
            'current_period_ends_at' => $currentPeriodEndsAt,
            'past_due_at' => null,
            'past_due_reason' => null,
            'grace_ends_at' => null,
            'suspended_at' => null,
            'suspension_reason' => null,
            'cancelled_at' => null,
            'ends_at' => null,
        ]);

        if ($status === 'trial') {
            $subscription->forceFill([
                'current_period_starts_at' => null,
                'current_period_ends_at' => null,
            ]);

            return;
        }

        if ($status === 'active') {
            return;
        }

        if ($status === 'past_due') {
            $subscription->forceFill([
                'past_due_at' => $this->dateValue(
                    $attributes['past_due_at'] ?? null,
                ) ?? CarbonImmutable::now(),
                'past_due_reason' => 'manual',
                'grace_ends_at' => $this->dateValue(
                    $attributes['grace_ends_at'] ?? null,
                ),
            ]);

            return;
        }

        if ($status === 'suspended') {
            $subscription->forceFill([
                'suspended_at' => CarbonImmutable::now(),
                'suspension_reason' => 'manual',
            ]);

            return;
        }

        $subscription->forceFill([
            'cancelled_at' => CarbonImmutable::now(),
            'ends_at' => $this->dateValue(
                $attributes['ends_at'] ?? null,
            ) ?? CarbonImmutable::now(),
        ]);
    }

    private function dateValue(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return CarbonImmutable::parse((string) $value);
    }

    private function restoreTenantContext(mixed $previousTenant): void
    {
        if ($previousTenant instanceof Tenant) {
            $this->tenantContext->set($previousTenant);

            return;
        }

        $this->tenantContext->clear();
    }
}
