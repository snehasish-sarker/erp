<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryStockCountLine extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'inventory_stock_count_id',
        'product_id',
        'unit_id',
        'line_number',
        'product_name',
        'product_sku',
        'unit_name',
        'unit_code',
        'system_quantity',
        'reserved_quantity',
        'counted_quantity',
        'variance_quantity',
        'snapshot_ledger_entry_id',
        'unit_cost',
        'variance_value',
        'stock_ledger_entry_id',
    ];

    /** @return BelongsTo<InventoryStockCount, $this> */
    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(
            InventoryStockCount::class,
            'inventory_stock_count_id',
        );
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<StockLedgerEntry, $this> */
    public function stockLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(
            StockLedgerEntry::class,
            'stock_ledger_entry_id',
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'inventory_stock_count_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'line_number' => 'integer',
            'snapshot_ledger_entry_id' => 'integer',
            'stock_ledger_entry_id' => 'integer',
            'system_quantity' => 'decimal:6',
            'reserved_quantity' => 'decimal:6',
            'counted_quantity' => 'decimal:6',
            'variance_quantity' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'variance_value' => 'decimal:6',
        ];
    }
}
