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
        $billingCycle = (string) $attributes['billing_cycle'];
        $status = (string) $attributes['status'];

        $this->assertManualAllocationInputs(
            plan: $plan,
            billingCycle: $billingCycle,
            status: $status,
        );

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

                    return $this->persistManualAllocation(
                        tenant: $lockedTenant,
                        subscription: $subscription,
                        plan: $plan,
                        attributes: $attributes,
                        assignedBy: $assignedBy,
                        billingCycle: $billingCycle,
                        status: $status,
                        auditEvent: 'saas_subscription_manually_updated',
                        auditMetadata: [
                            'manual_allocation' => true,
                        ],
                    );
                },
                attempts: 5,
            );
        } finally {
            $this->restoreTenantContext($previousTenant);
        }
    }

    public function applyQuickAction(
        Tenant $tenant,
        string $action,
        PlatformAdmin $assignedBy,
    ): TenantSubscription {
        if (!in_array(
            $action,
            [
                'extend_month',
                'extend_year',
                'renew_monthly',
                'renew_annual',
                'activate_indefinite',
            ],
            true,
        )) {
            throw new DomainException(
                'The selected subscription quick action is not supported.',
            );
        }

        $previousTenant = $this->tenantContext->get();

        try {
            return DB::transaction(
                function () use (
                    $tenant,
                    $action,
                    $assignedBy,
                ): TenantSubscription {
                    $lockedTenant = Tenant::query()
                        ->lockForUpdate()
                        ->findOrFail($tenant->getKey());

                    $this->tenantContext->set($lockedTenant);

                    $subscription = TenantSubscription::query()
                        ->where(
                            'tenant_id',
                            (int) $lockedTenant->getKey(),
                        )
                        ->lockForUpdate()
                        ->first();

                    if (!$subscription instanceof TenantSubscription) {
                        throw new DomainException(
                            'The tenant does not have a SaaS subscription.',
                        );
                    }

                    $plan = SaasPlan::query()
                        ->whereKey((int) $subscription->saas_plan_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$plan instanceof SaasPlan || $plan->status !== 'active') {
                        throw new DomainException(
                            'The current package is not active. Select an active package through manual package management first.',
                        );
                    }

                    $attributes = $this->quickActionAttributes(
                        subscription: $subscription,
                        action: $action,
                    );

                    return $this->persistManualAllocation(
                        tenant: $lockedTenant,
                        subscription: $subscription,
                        plan: $plan,
                        attributes: $attributes,
                        assignedBy: $assignedBy,
                        billingCycle: (string) $attributes['billing_cycle'],
                        status: 'active',
                        auditEvent: 'saas_subscription_quick_action_applied',
                        auditMetadata: [
                            'quick_action' => $action,
                            'quick_action_label' => $this->quickActionLabel($action),
                            'manual_allocation' => true,
                        ],
                    );
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

    private function assertManualAllocationInputs(
        SaasPlan $plan,
        string $billingCycle,
        string $status,
    ): void {
        if ($plan->status !== 'active') {
            throw new DomainException(
                'Only active SaaS plans can be allocated to a tenant.',
            );
        }

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
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $auditMetadata
     */
    private function persistManualAllocation(
        Tenant $tenant,
        TenantSubscription $subscription,
        SaasPlan $plan,
        array $attributes,
        PlatformAdmin $assignedBy,
        string $billingCycle,
        string $status,
        string $auditEvent,
        array $auditMetadata,
    ): TenantSubscription {
        $this->assertManualAllocationInputs(
            plan: $plan,
            billingCycle: $billingCycle,
            status: $status,
        );

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

        $tenant->forceFill([
            'status' => match ($status) {
                'trial' => 'trial',
                'active' => 'active',
                'past_due' => 'past_due',
                'suspended' => 'suspended',
                'cancelled' => 'cancelled',
            },
        ])->save();

        $this->auditLogService->recordCustomEvent(
            subject: $tenant,
            event: $auditEvent,
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
                ...$auditMetadata,
            ],
        );

        return $subscription->refresh()->load('plan');
    }

    /** @return array<string, mixed> */
    private function quickActionAttributes(
        TenantSubscription $subscription,
        string $action,
    ): array {
        $now = CarbonImmutable::now();
        $startsAt = $subscription->starts_at !== null
            ? CarbonImmutable::instance($subscription->starts_at)
            : $now;

        if (in_array($action, ['extend_month', 'extend_year'], true)) {
            if ($subscription->status !== 'active') {
                throw new DomainException(
                    'Only an active subscription can be extended. Use Renew Monthly or Renew Annual to reactivate another lifecycle status.',
                );
            }

            if ($subscription->current_period_ends_at === null) {
                throw new DomainException(
                    'An indefinite active subscription does not have an end date to extend.',
                );
            }

            $existingEnd = CarbonImmutable::instance(
                $subscription->current_period_ends_at,
            );
            $baseEnd = $existingEnd->isFuture()
                ? $existingEnd
                : $now;
            $periodStart = $subscription->current_period_starts_at !== null
                ? CarbonImmutable::instance(
                    $subscription->current_period_starts_at,
                )
                : $now;

            return [
                'billing_cycle' => $subscription->billing_cycle,
                'status' => 'active',
                'starts_at' => $startsAt,
                'trial_ends_at' => null,
                'current_period_starts_at' => $periodStart,
                'current_period_ends_at' => $action === 'extend_month'
                    ? $baseEnd->addMonthNoOverflow()
                    : $baseEnd->addYear(),
                'past_due_at' => null,
                'grace_ends_at' => null,
                'ends_at' => null,
            ];
        }

        if (in_array($action, ['renew_monthly', 'renew_annual'], true)) {
            $billingCycle = $action === 'renew_monthly'
                ? 'monthly'
                : 'annual';

            return [
                'billing_cycle' => $billingCycle,
                'status' => 'active',
                'starts_at' => $startsAt,
                'trial_ends_at' => null,
                'current_period_starts_at' => $now,
                'current_period_ends_at' => $billingCycle === 'monthly'
                    ? $now->addMonthNoOverflow()
                    : $now->addYear(),
                'past_due_at' => null,
                'grace_ends_at' => null,
                'ends_at' => null,
            ];
        }

        return [
            'billing_cycle' => $subscription->billing_cycle,
            'status' => 'active',
            'starts_at' => $startsAt,
            'trial_ends_at' => null,
            'current_period_starts_at' => $now,
            'current_period_ends_at' => null,
            'past_due_at' => null,
            'grace_ends_at' => null,
            'ends_at' => null,
        ];
    }

    private function quickActionLabel(string $action): string
    {
        return match ($action) {
            'extend_month' => 'Extend active period by one month',
            'extend_year' => 'Extend active period by one year',
            'renew_monthly' => 'Renew for one month',
            'renew_annual' => 'Renew for one year',
            'activate_indefinite' => 'Activate indefinitely',
            default => 'Subscription quick action',
        };
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
