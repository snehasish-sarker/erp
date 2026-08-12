<?php

declare(strict_types=1);

namespace App\Services\Saas;

use App\Models\PlatformAdmin;
use App\Models\SaasInvoice;
use App\Models\SaasPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Auditing\AuditLogService;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaasBillingService
{
    public function __construct(
        private readonly SaasInvoiceNumberService $invoiceNumberService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function generateInvoice(
        Tenant $tenant,
        ?PlatformAdmin $createdBy = null,
        ?CarbonInterface $at = null,
    ): SaasInvoice {
        $at ??= now();

        return DB::transaction(
            function () use ($tenant, $createdBy, $at): SaasInvoice {
                $lockedTenant = Tenant::query()
                    ->whereKey($tenant->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $subscription = TenantSubscription::query()
                    ->with('plan')
                    ->where('tenant_id', $lockedTenant->getKey())
                    ->lockForUpdate()
                    ->first();

                if (!$subscription instanceof TenantSubscription) {
                    throw new DomainException(
                        'The tenant does not have a SaaS subscription.',
                    );
                }

                $plan = $subscription->plan;

                if (!$plan instanceof SaasPlan || $plan->status !== 'active') {
                    throw new DomainException(
                        'The tenant subscription does not have an active SaaS plan.',
                    );
                }

                $billingCycle = $subscription->billing_cycle;

                if (!in_array($billingCycle, ['monthly', 'annual'], true)) {
                    throw new DomainException(
                        'The subscription billing cycle is invalid.',
                    );
                }

                $priceMinor = $plan->priceForBillingCycle($billingCycle);

                if ($priceMinor === null) {
                    throw new DomainException(
                        sprintf(
                            'The %s price is not configured for the %s plan.',
                            $billingCycle,
                            $plan->name,
                        ),
                    );
                }

                [$periodStartsAt, $periodEndsAt] = $this->nextPeriod(
                    subscription: $subscription,
                    at: $at,
                );

                $existingInvoice = SaasInvoice::query()
                    ->where(
                        'tenant_subscription_id',
                        $subscription->getKey(),
                    )
                    ->where('period_starts_at', $periodStartsAt)
                    ->where('period_ends_at', $periodEndsAt)
                    ->whereIn('status', ['open', 'paid'])
                    ->first();

                if ($existingInvoice instanceof SaasInvoice) {
                    throw new DomainException(
                        sprintf(
                            'Invoice %s already covers this subscription period.',
                            $existingInvoice->invoice_number,
                        ),
                    );
                }

                $currencyCode = $plan->billing_currency_code;
                $currencyScale = (int) $plan->currency_scale;
                $dueDays = max(
                    0,
                    (int) config('saas.billing.invoice_due_days', 7),
                );

                $invoice = SaasInvoice::query()->create([
                    'tenant_id' => (int) $lockedTenant->getKey(),
                    'tenant_subscription_id' => (int) $subscription->getKey(),
                    'saas_plan_id' => (int) $plan->getKey(),
                    'created_by_platform_admin_id' => $createdBy?->getKey(),
                    'invoice_number' => $this->invoiceNumberService->next(),
                    'status' => $priceMinor === 0 ? 'paid' : 'open',
                    'billing_cycle' => $billingCycle,
                    'currency_code' => $currencyCode,
                    'currency_scale' => $currencyScale,
                    'period_starts_at' => $periodStartsAt,
                    'period_ends_at' => $periodEndsAt,
                    'issued_at' => $at,
                    'due_at' => $dueDays === 0
                        ? $at
                        : $at->copy()->addDays($dueDays),
                    'paid_at' => $priceMinor === 0 ? $at : null,
                    'subtotal_minor' => $priceMinor,
                    'discount_minor' => 0,
                    'tax_minor' => 0,
                    'total_minor' => $priceMinor,
                    'amount_paid_minor' => $priceMinor === 0 ? 0 : 0,
                    'balance_due_minor' => $priceMinor,
                    'notes' => null,
                    'metadata' => [
                        'plan_code' => $plan->code,
                        'plan_name' => $plan->name,
                    ],
                ]);

                $invoice->lines()->create([
                    'description' => sprintf(
                        '%s plan — %s billing',
                        $plan->name,
                        ucfirst($billingCycle),
                    ),
                    'quantity' => 1,
                    'unit_amount_minor' => $priceMinor,
                    'line_total_minor' => $priceMinor,
                    'metadata' => [
                        'saas_plan_id' => (int) $plan->getKey(),
                        'billing_cycle' => $billingCycle,
                    ],
                ]);

                if ($priceMinor === 0) {
                    $invoice->forceFill([
                        'amount_paid_minor' => 0,
                        'balance_due_minor' => 0,
                    ])->save();

                    $this->activateSubscriptionPeriod(
                        tenant: $lockedTenant,
                        subscription: $subscription,
                        invoice: $invoice,
                    );
                }

                $this->auditLogService->recordCustomEvent(
                    subject: $lockedTenant,
                    event: 'saas_invoice_generated',
                    newValues: [
                        'invoice_number' => $invoice->invoice_number,
                        'status' => $invoice->status,
                        'billing_cycle' => $billingCycle,
                        'currency_code' => $currencyCode,
                        'total_minor' => $priceMinor,
                        'period_starts_at' => $periodStartsAt,
                        'period_ends_at' => $periodEndsAt,
                    ],
                    metadata: [
                        'saas_invoice_id' => (int) $invoice->getKey(),
                        'tenant_subscription_id' => (int) $subscription->getKey(),
                    ],
                );

                return $invoice->refresh()->load([
                    'tenant',
                    'plan',
                    'subscription',
                    'lines',
                    'payments',
                ]);
            },
            attempts: 5,
        );
    }

    public function activateSubscriptionPeriod(
        Tenant $tenant,
        TenantSubscription $subscription,
        SaasInvoice $invoice,
    ): void {
        $subscription->forceFill([
            'status' => 'active',
            'billing_currency_code' => $invoice->currency_code,
            'starts_at' => $subscription->starts_at ?? $invoice->period_starts_at,
            'current_period_starts_at' => $invoice->period_starts_at,
            'current_period_ends_at' => $invoice->period_ends_at,
            'past_due_at' => null,
            'past_due_reason' => null,
            'grace_ends_at' => null,
            'suspended_at' => null,
            'suspension_reason' => null,
            'cancelled_at' => null,
            'ends_at' => null,
        ])->save();

        $tenant->forceFill([
            'status' => 'active',
        ])->save();
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function nextPeriod(
        TenantSubscription $subscription,
        CarbonInterface $at,
    ): array {
        $periodStartsAt = $at->copy();

        if (
            $subscription->status === 'trial'
            && $subscription->trial_ends_at !== null
            && $subscription->trial_ends_at->gt($periodStartsAt)
        ) {
            $periodStartsAt = $subscription->trial_ends_at->copy();
        }

        if (
            $subscription->current_period_ends_at !== null
            && $subscription->current_period_ends_at->gt($periodStartsAt)
        ) {
            $periodStartsAt = $subscription->current_period_ends_at->copy();
        }

        $periodEndsAt = match ($subscription->billing_cycle) {
            'monthly' => $periodStartsAt->copy()->addMonthNoOverflow(),
            'annual' => $periodStartsAt->copy()->addYear(),
            default => throw new DomainException(
                'The subscription billing cycle is invalid.',
            ),
        };

        return [$periodStartsAt, $periodEndsAt];
    }
}
