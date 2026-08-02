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

final class Product extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_category_id',
        'brand_id',
        'base_unit_id',
        'name',
        'sku',
        'slug',
        'barcode',
        'product_type',
        'description',
        'cost_price',
        'selling_price',
        'is_purchasable',
        'is_sellable',
        'status',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(
            ProductCategory::class,
            'product_category_id',
        );
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class,
            'base_unit_id',
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isStockItem(): bool
    {
        return $this->product_type === 'stock';
    }

    public function isService(): bool
    {
        return $this->product_type === 'service';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'product_category_id' => 'integer',
            'brand_id' => 'integer',
            'base_unit_id' => 'integer',
            'cost_price' => 'decimal:6',
            'selling_price' => 'decimal:6',
            'is_purchasable' => 'boolean',
            'is_sellable' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ProductBranchSetting, $this>
     */
    public function branchSettings(): HasMany
    {
        return $this->hasMany(
            ProductBranchSetting::class,
        );
    }

    /**
     * @return HasMany<ProductWarehouseSetting, $this>
     */
    public function warehouseSettings(): HasMany
    {
        return $this->hasMany(
            ProductWarehouseSetting::class,
        );
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
}