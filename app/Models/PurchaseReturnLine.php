<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PurchaseReturnLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_return_id',
        'goods_receipt_line_id',
        'purchase_order_line_id',
        'product_id',
        'unit_id',
        'line_number',
        'product_name',
        'product_sku',
        'product_type',
        'unit_name',
        'unit_code',
        'accepted_quantity_snapshot',
        'previously_returned_quantity_snapshot',
        'previously_reserved_quantity_snapshot',
        'returnable_quantity_snapshot',
        'return_quantity',
        'supplier_unit_cost',
        'supplier_total_cost',
        'inventory_unit_cost',
        'inventory_total_cost',
        'cost_variance_amount',
        'batch_number',
        'serial_numbers',
        'return_reason',
        'notes',
    ];

    /**
     * @return BelongsTo<PurchaseReturn, $this>
     */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseReturn::class,
        );
    }

    /**
     * @return BelongsTo<GoodsReceiptLine, $this>
     */
    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(
            GoodsReceiptLine::class,
        );
    }

    /**
     * @return BelongsTo<PurchaseOrderLine, $this>
     */
    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrderLine::class,
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

    public function isStockItem(): bool
    {
        return $this->product_type === 'stock';
    }

    public function isService(): bool
    {
        return $this->product_type === 'service';
    }

    public function hasReturnQuantity(): bool
    {
        return BigDecimal::of(
            (string) $this->return_quantity,
        )->isGreaterThan(
            BigDecimal::zero(),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'purchase_return_id' => 'integer',
            'goods_receipt_line_id' => 'integer',
            'purchase_order_line_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'line_number' => 'integer',

            'accepted_quantity_snapshot' =>
                'decimal:6',

            'previously_returned_quantity_snapshot' =>
                'decimal:6',

            'previously_reserved_quantity_snapshot' =>
                'decimal:6',

            'returnable_quantity_snapshot' =>
                'decimal:6',

            'return_quantity' => 'decimal:6',
            'supplier_unit_cost' => 'decimal:6',
            'supplier_total_cost' => 'decimal:6',
            'inventory_unit_cost' => 'decimal:6',
            'inventory_total_cost' => 'decimal:6',
            'cost_variance_amount' => 'decimal:6',
            'serial_numbers' => 'array',
        ];
    }

    /**
 * @return HasMany<SupplierDebitNoteLine, $this>
 */
public function supplierDebitNoteLines(): HasMany
{
    return $this->hasMany(
        SupplierDebitNoteLine::class,
    )->orderBy('id');
}
}