<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerDispatchLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_dispatch_id',
        'sales_order_line_id',
        'sales_order_allocation_line_id',
        'inventory_reservation_id',
        'product_id',
        'unit_id',
        'line_number',
        'product_name',
        'product_sku',
        'product_type',
        'unit_name',
        'unit_code',
        'description',
        'dispatched_quantity',
        'unit_cost',
        'total_cost',
        'stock_ledger_entry_id',
        'reversal_stock_ledger_entry_id',
    ];

    /**
     * @return BelongsTo<CustomerDispatch, $this>
     */
    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(
            CustomerDispatch::class,
            'customer_dispatch_id',
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
     * @return BelongsTo<SalesOrderAllocationLine, $this>
     */
    public function allocationLine(): BelongsTo
    {
        return $this->belongsTo(
            SalesOrderAllocationLine::class,
            'sales_order_allocation_line_id',
        );
    }

    /**
     * @return BelongsTo<InventoryReservation, $this>
     */
    public function inventoryReservation(): BelongsTo
    {
        return $this->belongsTo(
            InventoryReservation::class,
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
     * @return BelongsTo<StockLedgerEntry, $this>
     */
    public function stockLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(
            StockLedgerEntry::class,
        );
    }

    /**
     * @return BelongsTo<StockLedgerEntry, $this>
     */
    public function reversalStockLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(
            StockLedgerEntry::class,
            'reversal_stock_ledger_entry_id',
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
            'customer_dispatch_id' => 'integer',
            'sales_order_line_id' => 'integer',
            'sales_order_allocation_line_id' => 'integer',
            'inventory_reservation_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'line_number' => 'integer',
            'dispatched_quantity' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:6',
            'stock_ledger_entry_id' => 'integer',
            'reversal_stock_ledger_entry_id' => 'integer',
        ];
    }
}