<?php

declare(strict_types=1);

namespace App\Services\Saas;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Auditing\AuditLogService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaasSubscriptionLifecycleService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function synchronizeTenant(
        Tenant $tenant,
        ?CarbonInterface $at = null,
    ): ?TenantSubscription {
        $at ??= now();

        $subscriptionId = TenantSubscription::query()
            ->where('tenant_id', $tenant->getKey())
            ->value('id');

        if ($subscriptionId === null) {
            return null;
        }

        return $this->synchronizeSubscriptionById(
            subscriptionId: (int) $subscriptionId,
            at: $at,
        );
    }

    /**
     * @return array{
     *     examined: int,
     *     updated: int,
     *     trial_initialized: int,
     *     past_due: int,
     *     suspended: int
     * }
     */
    public function processDueSubscriptions(
        int $limit = 500,
        ?CarbonInterface $at = null,
    ): array {
        $at ??= now();
        $limit = max(1, min($limit, 5000));

        $ids = TenantSubscription::query()
            ->where(
                function ($query) use ($at): void {
                    $query
                        ->where(
                            function ($trialQuery) use ($at): void {
                                $trialQuery
                                    ->where('status', 'trial')
                                    ->where(
                                        function ($expiryQuery) use ($at): void {
                                            $expiryQuery
                                                ->whereNull('trial_ends_at')
                                                ->orWhere(
                                                    'trial_ends_at',
                                                    '<=',
                                                    $at,
                                                );
                                        },
                                    );
                            },
                        )
                        ->orWhere(
                            function ($activeQuery) use ($at): void {
                                $activeQuery
                                    ->where('status', 'active')
                                    ->whereNotNull('current_period_ends_at')
                                    ->where(
                                        'current_period_ends_at',
                                        '<=',
                                        $at,
                                    );
                            },
                        )
                        ->orWhere(
                            function ($pastDueQuery) use ($at): void {
                                $pastDueQuery
                                    ->where('status', 'past_due')
                                    ->where(
                                        function ($graceQuery) use ($at): void {
                                            $graceQuery
                                                ->whereNull('grace_ends_at')
                                                ->orWhere(
                                                    'grace_ends_at',
                                                    '<=',
                                                    $at,
                                                );
                                        },
                                    );
                            },
                        );
                },
            )
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $summary = [
            'examined' => 0,
            'updated' => 0,
            'trial_initialized' => 0,
            'past_due' => 0,
            'suspended' => 0,
        ];

        foreach ($ids as $subscriptionId) {
            $before = TenantSubscription::query()
                ->find($subscriptionId);

            if (!$before instanceof TenantSubscription) {
                continue;
            }

            $beforeStatus = $before->status;
            $beforeTrialEndsAt = $before->trial_ends_at?->toDateTimeString();

            $after = $this->synchronizeSubscriptionById(
                subscriptionId: $subscriptionId,
                at: $at,
            );

            if (!$after instanceof TenantSubscription) {
                continue;
            }

            $summary['examined']++;

            if (
                $beforeStatus !== $after->status
                || $beforeTrialEndsAt
                    !== $after->trial_ends_at?->toDateTimeString()
            ) {
                $summary['updated']++;
            }

            if (
                $beforeStatus === 'trial'
                && $beforeTrialEndsAt === null
                && $after->trial_ends_at !== null
            ) {
                $summary['trial_initialized']++;
            }

            if (
                $beforeStatus !== 'past_due'
                && $after->status === 'past_due'
            ) {
                $summary['past_due']++;
            }

            if (
                $beforeStatus !== 'suspended'
                && $after->status === 'suspended'
            ) {
                $summary['suspended']++;
            }
        }

        return $summary;
    }

    public function extendTrial(
        Tenant $tenant,
        int $days,
    ): TenantSubscription {
        $days = max(1, min($days, 90));

        $previousTenant = $this->tenantContext->get();

        try {
            return DB::transaction(
                function () use ($tenant, $days): TenantSubscription {
                    $lockedTenant = Tenant::query()
                        ->whereKey($tenant->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $subscription = TenantSubscription::query()
                        ->where('tenant_id', $lockedTenant->getKey())
                        ->lockForUpdate()
                        ->first();

                    if (!$subscription instanceof TenantSubscription) {
                        throw new DomainException(
                            'The tenant does not have a SaaS subscription.',
                        );
                    }

                    $canExtend = $subscription->status === 'trial'
                        || (
                            in_array(
                                $subscription->status,
                                ['past_due', 'suspended'],
                                true,
                            )
                            && $subscription->past_due_reason === 'trial_expired'
                        );

                    if (!$canExtend) {
                        throw new DomainException(
                            'Only a trial subscription, or a trial that expired into past-due/suspended status, can be extended.',
                        );
                    }

                    $this->tenantContext->set($lockedTenant);

                    $oldStatus = $subscription->status;
                    $oldTrialEndsAt = $subscription->trial_ends_at;

                    $base = $subscription->trial_ends_at !== null
                        && $subscription->trial_ends_at->isFuture()
                            ? $subscription->trial_ends_at->copy()
                            : now();

                    $subscription->forceFill([
                        'status' => 'trial',
                        'trial_ends_at' => $base->addDays($days),
                        'past_due_at' => null,
                        'past_due_reason' => null,
                        'grace_ends_at' => null,
                        'suspended_at' => null,
                        'suspension_reason' => null,
                        'cancelled_at' => null,
                        'ends_at' => null,
                        'lifecycle_processed_at' => now(),
                    ])->save();

                    Tenant::withoutEvents(
                        function () use ($lockedTenant): void {
                            $lockedTenant->status = 'trial';
                            $lockedTenant->save();
                        },
                    );

                    $this->auditLogService->recordCustomEvent(
                        subject: $lockedTenant,
                        event: 'saas_trial_extended',
                        oldValues: [
                            'tenant_status' => $tenant->status,
                            'subscription_status' => $oldStatus,
                            'trial_ends_at' => $oldTrialEndsAt,
                        ],
                        newValues: [
                            'tenant_status' => 'trial',
                            'subscription_status' => 'trial',
                            'trial_ends_at' => $subscription->trial_ends_at,
                        ],
                        metadata: [
                            'tenant_subscription_id' => (int) $subscription->getKey(),
                            'days_added' => $days,
                        ],
                    );

                    return $subscription->refresh()->load('plan');
                },
                attempts: 5,
            );
        } finally {
            $this->restoreTenantContext(
                previousTenant: $previousTenant,
                processedTenantId: (int) $tenant->getKey(),
            );
        }
    }

    private function synchronizeSubscriptionById(
        int $subscriptionId,
        CarbonInterface $at,
    ): ?TenantSubscription {
        $previousTenant = $this->tenantContext->get();
        $processedTenantId = null;

        try {
            return DB::transaction(
                function () use (
                    $subscriptionId,
                    $at,
                    &$processedTenantId,
                ): ?TenantSubscription {
                    $subscription = TenantSubscription::query()
                        ->whereKey($subscriptionId)
                        ->lockForUpdate()
                        ->first();

                    if (!$subscription instanceof TenantSubscription) {
                        return null;
                    }

                    $tenant = Tenant::query()
                        ->whereKey($subscription->tenant_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $processedTenantId = (int) $tenant->getKey();
                    $this->tenantContext->set($tenant);

                    if (
                        $subscription->status === 'trial'
                        && $subscription->trial_ends_at === null
                    ) {
                        $trialDays = max(
                            1,
                            (int) config(
                                'saas.subscription.trial_days',
                                14,
                            ),
                        );

                        $trialStartsAt = $subscription->starts_at
                            ?? $subscription->created_at
                            ?? $at;

                        $subscription->trial_ends_at = $trialStartsAt
                            ->copy()
                            ->addDays($trialDays);
                    }

                    if (
                        $subscription->status === 'trial'
                        && $subscription->trial_ends_at !== null
                        && $subscription->trial_ends_at->lte($at)
                    ) {
                        $this->expireIntoGraceOrSuspend(
                            tenant: $tenant,
                            subscription: $subscription,
                            reason: 'trial_expired',
                            expiredAt: $subscription->trial_ends_at,
                            at: $at,
                        );
                    } elseif (
                        $subscription->status === 'active'
                        && $subscription->current_period_ends_at !== null
                        && $subscription->current_period_ends_at->lte($at)
                    ) {
                        $this->expireIntoGraceOrSuspend(
                            tenant: $tenant,
                            subscription: $subscription,
                            reason: 'period_expired',
                            expiredAt: $subscription->current_period_ends_at,
                            at: $at,
                        );
                    } elseif ($subscription->status === 'past_due') {
                        $this->synchronizePastDue(
                            tenant: $tenant,
                            subscription: $subscription,
                            at: $at,
                        );
                    }

                    if ($subscription->isDirty()) {
                        $subscription->lifecycle_processed_at = $at;
                        $subscription->save();
                    }

                    $this->synchronizeTenantStatus(
                        tenant: $tenant,
                        subscription: $subscription,
                    );

                    return $subscription->refresh()->load('plan');
                },
                attempts: 5,
            );
        } finally {
            $this->restoreTenantContext(
                previousTenant: $previousTenant,
                processedTenantId: $processedTenantId,
            );
        }
    }

    private function expireIntoGraceOrSuspend(
        Tenant $tenant,
        TenantSubscription $subscription,
        string $reason,
        CarbonInterface $expiredAt,
        CarbonInterface $at,
    ): void {
        $graceDays = max(
            0,
            (int) config(
                'saas.subscription.grace_days',
                7,
            ),
        );

        $graceEndsAt = $expiredAt
            ->copy()
            ->addDays($graceDays);

        $oldStatus = $subscription->status;
        $oldTenantStatus = $tenant->status;

        $subscription->past_due_at = $expiredAt;
        $subscription->past_due_reason = $reason;
        $subscription->grace_ends_at = $graceEndsAt;

        if ($graceEndsAt->lte($at)) {
            $subscription->status = 'suspended';
            $subscription->suspended_at = $at;
            $subscription->suspension_reason = 'grace_expired';

            $this->setTenantStatus(
                tenant: $tenant,
                status: 'suspended',
            );

            $this->auditLogService->recordCustomEvent(
                subject: $tenant,
                event: 'saas_subscription_suspended',
                oldValues: [
                    'subscription_status' => $oldStatus,
                    'tenant_status' => $oldTenantStatus,
                ],
                newValues: [
                    'subscription_status' => 'suspended',
                    'tenant_status' => 'suspended',
                ],
                metadata: [
                    'tenant_subscription_id' => (int) $subscription->getKey(),
                    'past_due_reason' => $reason,
                    'expired_at' => $expiredAt,
                    'grace_ends_at' => $graceEndsAt,
                    'actor_type' => 'system',
                    'source' => 'saas_subscription_lifecycle',
                ],
            );

            return;
        }

        $subscription->status = 'past_due';
        $subscription->suspended_at = null;
        $subscription->suspension_reason = null;

        $this->setTenantStatus(
            tenant: $tenant,
            status: 'past_due',
        );

        $this->auditLogService->recordCustomEvent(
            subject: $tenant,
            event: 'saas_subscription_past_due',
            oldValues: [
                'subscription_status' => $oldStatus,
                'tenant_status' => $oldTenantStatus,
            ],
            newValues: [
                'subscription_status' => 'past_due',
                'tenant_status' => 'past_due',
            ],
            metadata: [
                'tenant_subscription_id' => (int) $subscription->getKey(),
                'past_due_reason' => $reason,
                'expired_at' => $expiredAt,
                'grace_ends_at' => $graceEndsAt,
                'actor_type' => 'system',
                'source' => 'saas_subscription_lifecycle',
            ],
        );
    }

    private function synchronizePastDue(
        Tenant $tenant,
        TenantSubscription $subscription,
        CarbonInterface $at,
    ): void {
        if ($subscription->grace_ends_at === null) {
            $graceDays = max(
                0,
                (int) config(
                    'saas.subscription.grace_days',
                    7,
                ),
            );

            $base = $subscription->past_due_at ?? $at;
            $subscription->grace_ends_at = $base
                ->copy()
                ->addDays($graceDays);
        }

        if ($subscription->grace_ends_at->gt($at)) {
            $this->setTenantStatus(
                tenant: $tenant,
                status: 'past_due',
            );

            return;
        }

        $subscription->status = 'suspended';
        $subscription->suspended_at = $at;
        $subscription->suspension_reason = 'grace_expired';

        $this->setTenantStatus(
            tenant: $tenant,
            status: 'suspended',
        );

        $this->auditLogService->recordCustomEvent(
            subject: $tenant,
            event: 'saas_subscription_suspended',
            oldValues: [
                'subscription_status' => 'past_due',
                'tenant_status' => 'past_due',
            ],
            newValues: [
                'subscription_status' => 'suspended',
                'tenant_status' => 'suspended',
            ],
            metadata: [
                'tenant_subscription_id' => (int) $subscription->getKey(),
                'past_due_reason' => $subscription->past_due_reason,
                'grace_ends_at' => $subscription->grace_ends_at,
                'actor_type' => 'system',
                'source' => 'saas_subscription_lifecycle',
            ],
        );
    }

    private function synchronizeTenantStatus(
        Tenant $tenant,
        TenantSubscription $subscription,
    ): void {
        $targetStatus = match ($subscription->status) {
            'trial' => 'trial',
            'active' => 'active',
            'past_due' => 'past_due',
            'suspended' => 'suspended',
            'cancelled' => 'cancelled',
            default => $tenant->status,
        };

        $this->setTenantStatus(
            tenant: $tenant,
            status: $targetStatus,
        );
    }

    private function setTenantStatus(
        Tenant $tenant,
        string $status,
    ): void {
        if ($tenant->status === $status) {
            return;
        }

        Tenant::withoutEvents(
            function () use ($tenant, $status): void {
                $tenant->status = $status;
                $tenant->save();
            },
        );
    }

    private function restoreTenantContext(
        ?Tenant $previousTenant,
        ?int $processedTenantId,
    ): void {
        if (!$previousTenant instanceof Tenant) {
            $this->tenantContext->clear();

            return;
        }

        if (
            $processedTenantId !== null
            && (int) $previousTenant->getKey() === $processedTenantId
        ) {
            $freshTenant = Tenant::query()->find($processedTenantId);

            if ($freshTenant instanceof Tenant) {
                $this->tenantContext->set($freshTenant);

                return;
            }
        }

        $this->tenantContext->set($previousTenant);
    }
}
