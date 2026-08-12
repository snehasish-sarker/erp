<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryAdjustmentLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'inventory_adjustment_id',
        'product_id',
        'unit_id',
        'line_number',
        'product_name',
        'product_sku',
        'unit_name',
        'unit_code',
        'adjustment_type',
        'quantity',
        'unit_cost',
        'adjustment_value',
        'quantity_before',
        'quantity_after',
        'stock_ledger_entry_id',
    ];

    /** @return BelongsTo<InventoryAdjustment, $this> */
    public function inventoryAdjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class);
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
        return $this->belongsTo(StockLedgerEntry::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'inventory_adjustment_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'line_number' => 'integer',
            'quantity' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'adjustment_value' => 'decimal:6',
            'quantity_before' => 'decimal:6',
            'quantity_after' => 'decimal:6',
            'stock_ledger_entry_id' => 'integer',
        ];
    }
}
