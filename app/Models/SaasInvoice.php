<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SaasInvoice extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'tenant_subscription_id',
        'saas_plan_id',
        'created_by_platform_admin_id',
        'invoice_number',
        'status',
        'billing_cycle',
        'currency_code',
        'currency_scale',
        'period_starts_at',
        'period_ends_at',
        'issued_at',
        'due_at',
        'paid_at',
        'subtotal_minor',
        'discount_minor',
        'tax_minor',
        'total_minor',
        'amount_paid_minor',
        'balance_due_minor',
        'notes',
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

    /** @return BelongsTo<SaasPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    /** @return BelongsTo<PlatformAdmin, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            PlatformAdmin::class,
            'created_by_platform_admin_id',
        );
    }

    /** @return HasMany<SaasInvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SaasInvoiceLine::class);
    }

    /** @return HasMany<SaasPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SaasPayment::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid'
            && (int) $this->balance_due_minor === 0;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'currency_scale' => 'integer',
            'period_starts_at' => 'datetime',
            'period_ends_at' => 'datetime',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'subtotal_minor' => 'integer',
            'discount_minor' => 'integer',
            'tax_minor' => 'integer',
            'total_minor' => 'integer',
            'amount_paid_minor' => 'integer',
            'balance_due_minor' => 'integer',
            'metadata' => 'array',
        ];
    }
}
