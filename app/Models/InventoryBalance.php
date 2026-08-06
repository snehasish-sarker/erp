<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryBalance extends Model
{
    use BelongsToTenant;
    use HasFactory;

    private const SCALE = 6;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'product_id',
        'unit_id',
        'quantity_on_hand',
        'quantity_reserved',
        'inventory_value',
        'average_unit_cost',
        'version',
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

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(
            Branch::class,
        );
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
        );
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class,
        );
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(
            Unit::class,
        );
    }

    public function availableQuantity(): string
    {
        return BigDecimal::of(
            (string) $this->quantity_on_hand,
        )
            ->minus(
                BigDecimal::of(
                    (string) $this->quantity_reserved,
                ),
            )
            ->toScale(
                self::SCALE,
                RoundingMode::HALF_UP,
            )
            ->__toString();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'warehouse_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'quantity_on_hand' => 'decimal:6',
            'quantity_reserved' => 'decimal:6',
            'inventory_value' => 'decimal:6',
            'average_unit_cost' => 'decimal:6',
            'version' => 'integer',
        ];
    }
}