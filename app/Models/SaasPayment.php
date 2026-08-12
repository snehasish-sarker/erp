<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SaasPayment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'tenant_subscription_id',
        'saas_invoice_id',
        'recorded_by_platform_admin_id',
        'provider',
        'provider_payment_id',
        'status',
        'amount_minor',
        'currency_code',
        'currency_scale',
        'paid_at',
        'failed_at',
        'failure_message',
        'metadata',
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<TenantSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(
            TenantSubscription::class,
            'tenant_subscription_id',
        );
    }

    /** @return BelongsTo<SaasInvoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SaasInvoice::class, 'saas_invoice_id');
    }

    /** @return BelongsTo<PlatformAdmin, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(
            PlatformAdmin::class,
            'recorded_by_platform_admin_id',
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'currency_scale' => 'integer',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
