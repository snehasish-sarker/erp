<?php

declare(strict_types=1);

namespace App\Services\Saas;

use App\Models\PlatformAdmin;
use App\Models\SaasInvoice;
use App\Models\SaasPayment;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Auditing\AuditLogService;
use DomainException;
use Illuminate\Support\Facades\DB;

final class SaasPaymentService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function recordSucceededPayment(
        SaasInvoice $invoice,
        int $amountMinor,
        string $provider,
        ?string $providerPaymentId = null,
        ?PlatformAdmin $recordedBy = null,
        array $metadata = [],
    ): SaasPayment {
        if ($amountMinor <= 0) {
            throw new DomainException(
                'Payment amount must be greater than zero.',
            );
        }

        return DB::transaction(
            function () use (
                $invoice,
                $amountMinor,
                $provider,
                $providerPaymentId,
                $recordedBy,
                $metadata,
            ): SaasPayment {
                $lockedInvoice = SaasInvoice::query()
                    ->whereKey($invoice->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if (in_array($lockedInvoice->status, ['void', 'uncollectible'], true)) {
                    throw new DomainException(
                        'Payments cannot be recorded against this invoice.',
                    );
                }

                if ($lockedInvoice->isPaid()) {
                    throw new DomainException(
                        'The invoice is already fully paid.',
                    );
                }

                if ($amountMinor > (int) $lockedInvoice->balance_due_minor) {
                    throw new DomainException(
                        'Payment amount cannot exceed the invoice balance.',
                    );
                }

                if (
                    $providerPaymentId !== null
                    && SaasPayment::query()
                        ->where('provider', $provider)
                        ->where('provider_payment_id', $providerPaymentId)
                        ->where('status', 'succeeded')
                        ->exists()
                ) {
                    throw new DomainException(
                        'This provider payment reference has already been recorded.',
                    );
                }

                $tenant = Tenant::query()
                    ->whereKey($lockedInvoice->tenant_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $subscription = TenantSubscription::query()
                    ->whereKey($lockedInvoice->tenant_subscription_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $payment = SaasPayment::query()->create([
                    'tenant_id' => (int) $tenant->getKey(),
                    'tenant_subscription_id' => (int) $subscription->getKey(),
                    'saas_invoice_id' => (int) $lockedInvoice->getKey(),
                    'recorded_by_platform_admin_id' => $recordedBy?->getKey(),
                    'provider' => $provider,
                    'provider_payment_id' => $providerPaymentId,
                    'status' => 'succeeded',
                    'amount_minor' => $amountMinor,
                    'currency_code' => $lockedInvoice->currency_code,
                    'currency_scale' => (int) $lockedInvoice->currency_scale,
                    'paid_at' => now(),
                    'failed_at' => null,
                    'failure_message' => null,
                    'metadata' => $metadata,
                ]);

                $newPaidAmount = min(
                    (int) $lockedInvoice->total_minor,
                    (int) $lockedInvoice->amount_paid_minor + $amountMinor,
                );
                $newBalance = max(
                    0,
                    (int) $lockedInvoice->total_minor - $newPaidAmount,
                );

                $lockedInvoice->forceFill([
                    'amount_paid_minor' => $newPaidAmount,
                    'balance_due_minor' => $newBalance,
                    'status' => $newBalance === 0 ? 'paid' : 'open',
                    'paid_at' => $newBalance === 0 ? now() : null,
                ])->save();

                $this->auditLogService->recordCustomEvent(
                    subject: $tenant,
                    event: 'saas_payment_recorded',
                    newValues: [
                        'invoice_number' => $lockedInvoice->invoice_number,
                        'provider' => $provider,
                        'provider_payment_id' => $providerPaymentId,
                        'amount_minor' => $amountMinor,
                        'currency_code' => $lockedInvoice->currency_code,
                        'invoice_status' => $lockedInvoice->status,
                        'balance_due_minor' => $newBalance,
                    ],
                    metadata: [
                        'saas_invoice_id' => (int) $lockedInvoice->getKey(),
                        'saas_payment_id' => (int) $payment->getKey(),
                        'tenant_subscription_id' => (int) $subscription->getKey(),
                    ],
                );

                return $payment->refresh()->load('invoice');
            },
            attempts: 5,
        );
    }
}
