<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class SalesOrderAllocationLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sales_order_allocation_id',
        'sales_order_line_id',
        'product_id',
        'unit_id',
        'line_number',
        'product_name',
        'product_sku',
        'product_type',
        'unit_name',
        'unit_code',
        'requested_quantity',
        'allocated_quantity',
        'quantity_on_hand_snapshot',
        'quantity_reserved_other_snapshot',
        'quantity_available_snapshot',
    ];

    /**
     * @return BelongsTo<SalesOrderAllocation, $this>
     */
    public function allocation(): BelongsTo
    {
        return $this->belongsTo(
            SalesOrderAllocation::class,
            'sales_order_allocation_id',
        );
    }

    /**
     * @return BelongsTo<SalesOrderLine, $this>
     */
    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(
            SalesOrderLine::class,
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

    /**
     * @return HasOne<InventoryReservation, $this>
     */
    public function reservation(): HasOne
    {
        return $this->hasOne(
            InventoryReservation::class,
        );
    }

    public function isStockItem(): bool
    {
        return $this->product_type === 'stock';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',

            'sales_order_allocation_id' =>
                'integer',

            'sales_order_line_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'line_number' => 'integer',
            'requested_quantity' => 'decimal:6',
            'allocated_quantity' => 'decimal:6',

            'quantity_on_hand_snapshot' =>
                'decimal:6',

            'quantity_reserved_other_snapshot' =>
                'decimal:6',

            'quantity_available_snapshot' =>
                'decimal:6',
        ];
    }
}