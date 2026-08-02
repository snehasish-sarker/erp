<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupplierInvoiceMatch extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_invoice_id',
        'supplier_invoice_line_id',
        'purchase_order_id',
        'purchase_order_line_id',
        'goods_receipt_id',
        'goods_receipt_line_id',
        'matched_quantity',
        'receipt_accepted_quantity_snapshot',
        'previously_invoiced_quantity_snapshot',
        'available_quantity_snapshot',
        'purchase_order_unit_price_snapshot',
        'invoice_unit_price_snapshot',
        'price_variance_per_unit',
        'price_variance_amount',
        'matched_amount',
    ];

    /**
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(
            SupplierInvoice::class,
        );
    }

    /**
     * @return BelongsTo<SupplierInvoiceLine, $this>
     */
    public function supplierInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(
            SupplierInvoiceLine::class,
        );
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'supplier_invoice_id' => 'integer',
            'supplier_invoice_line_id' => 'integer',
            'purchase_order_id' => 'integer',
            'purchase_order_line_id' => 'integer',
            'goods_receipt_id' => 'integer',
            'goods_receipt_line_id' => 'integer',
            'matched_quantity' => 'decimal:6',
            'receipt_accepted_quantity_snapshot' => 'decimal:6',
            'previously_invoiced_quantity_snapshot' => 'decimal:6',
            'available_quantity_snapshot' => 'decimal:6',
            'purchase_order_unit_price_snapshot' => 'decimal:6',
            'invoice_unit_price_snapshot' => 'decimal:6',
            'price_variance_per_unit' => 'decimal:6',
            'price_variance_amount' => 'decimal:6',
            'matched_amount' => 'decimal:6',
        ];
    }
}