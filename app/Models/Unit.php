<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Unit extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'symbol',
        'category',
        'allow_decimal',
        'decimal_places',
        'status',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            Tenant::class,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function allowsDecimal(): bool
    {
        return $this->allow_decimal;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'allow_decimal' => 'boolean',
            'decimal_places' => 'integer',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(
            PurchaseOrderLine::class,
        );
    }

    /**
     * @return HasMany<SalesOrderLine, $this>
     */
    public function salesOrderLines(): HasMany
    {
        return $this->hasMany(
            SalesOrderLine::class,
        );
    }
}