<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SaasPlan extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'description',
        'billing_currency_code',
        'currency_scale',
        'monthly_price_minor',
        'annual_price_minor',
        'status',
        'is_default',
        'sort_order',
    ];

    /** @return HasMany<SaasPlanFeature, $this> */
    public function entitlements(): HasMany
    {
        return $this->hasMany(SaasPlanFeature::class);
    }

    /** @return HasMany<TenantSubscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    /** @return HasMany<SaasInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(SaasInvoice::class);
    }

    public function priceForBillingCycle(string $billingCycle): ?int
    {
        return match ($billingCycle) {
            'monthly' => $this->monthly_price_minor,
            'annual' => $this->annual_price_minor,
            default => null,
        };
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'currency_scale' => 'integer',
            'monthly_price_minor' => 'integer',
            'annual_price_minor' => 'integer',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
