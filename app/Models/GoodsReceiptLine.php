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

final class GoodsReceiptLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_line_id',
        'product_id',
        'unit_id',
        'line_number',
        'product_name',
        'product_sku',
        'product_type',
        'unit_name',
        'unit_code',
        'ordered_quantity_snapshot',
        'previously_received_quantity_snapshot',
        'receipt_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'unit_cost',
        'total_cost',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'serial_numbers',
        'storage_location',
        'variance_reason',
        'return_reserved_quantity',
        'returned_quantity',
    ];

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(
            GoodsReceipt::class,
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

    public function hasAcceptedQuantity(): bool
    {
        return BigDecimal::of(
            (string) $this->accepted_quantity,
        )->isGreaterThan(
            BigDecimal::zero(),
        );
    }

    public function hasRejectedQuantity(): bool
    {
        return BigDecimal::of(
            (string) $this->rejected_quantity,
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
            'goods_receipt_id' => 'integer',
            'purchase_order_line_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'line_number' => 'integer',

            'ordered_quantity_snapshot' =>
                'decimal:6',

            'previously_received_quantity_snapshot' =>
                'decimal:6',

            'receipt_quantity' => 'decimal:6',
            'accepted_quantity' => 'decimal:6',
            'rejected_quantity' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:6',

            'manufacturing_date' => 'date',
            'expiry_date' => 'date',
            'serial_numbers' => 'array',
            'return_reserved_quantity' =>
    'decimal:6',

'returned_quantity' =>
    'decimal:6',
        ];
    }

    /**
 * @return HasMany<PurchaseReturnLine, $this>
 */
public function purchaseReturnLines(): HasMany
{
    return $this->hasMany(
        PurchaseReturnLine::class,
    )->orderBy('id');
}

public function hasPurchaseReturnActivity(): bool
{
    return BigDecimal::of(
        (string) $this->return_reserved_quantity,
    )->isGreaterThan(
        BigDecimal::zero(),
    )
        || BigDecimal::of(
            (string) $this->returned_quantity,
        )->isGreaterThan(
            BigDecimal::zero(),
        );
}
}