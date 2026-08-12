<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class TenantSubscription extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'saas_plan_id',
        'assigned_by_platform_admin_id',
        'status',
        'billing_cycle',
        'billing_currency_code',
        'starts_at',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'past_due_at',
        'past_due_reason',
        'grace_ends_at',
        'suspended_at',
        'suspension_reason',
        'cancelled_at',
        'lifecycle_processed_at',
        'ends_at',
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<SaasPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class, 'saas_plan_id');
    }

    /** @return BelongsTo<PlatformAdmin, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(
            PlatformAdmin::class,
            'assigned_by_platform_admin_id',
        );
    }

    /** @return HasMany<SaasInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(
            SaasInvoice::class,
            'tenant_subscription_id',
        );
    }

    /** @return HasMany<SaasPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(
            SaasPayment::class,
            'tenant_subscription_id',
        );
    }

    public function allowsTenantAccessAt(
        ?CarbonInterface $at = null,
    ): bool {
        $at ??= now();

        return match ($this->status) {
            'trial' => $this->trial_ends_at === null
                || $this->trial_ends_at->gt($at),

            'active' => $this->current_period_ends_at === null
                || $this->current_period_ends_at->gt($at),

            'past_due' => $this->grace_ends_at !== null
                && $this->grace_ends_at->gt($at),

            default => false,
        };
    }

    public function isInGracePeriodAt(
        ?CarbonInterface $at = null,
    ): bool {
        $at ??= now();

        return $this->status === 'past_due'
            && $this->grace_ends_at !== null
            && $this->grace_ends_at->gt($at);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'current_period_starts_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'past_due_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'lifecycle_processed_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
