<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class InventoryTransferLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'inventory_transfer_id',
        'product_id',
        'unit_id',
        'line_number',
        'product_name',
        'product_sku',
        'unit_name',
        'unit_code',
        'quantity',
        'unit_cost',
        'transfer_value',
    ];

    /** @return BelongsTo<InventoryTransfer, $this> */
    public function inventoryTransfer(): BelongsTo
    {
        return $this->belongsTo(
            InventoryTransfer::class,
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'inventory_transfer_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'line_number' => 'integer',
            'quantity' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'transfer_value' => 'decimal:6',
        ];
    }
}
