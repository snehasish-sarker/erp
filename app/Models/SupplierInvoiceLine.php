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

final class SupplierInvoiceLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_invoice_id',
        'purchase_order_line_id',
        'product_id',
        'unit_id',
        'line_number',
        'product_name',
        'product_sku',
        'product_type',
        'unit_name',
        'unit_code',
        'description',
        'ordered_quantity_snapshot',
        'received_quantity_snapshot',
        'previously_invoiced_quantity_snapshot',
        'available_to_invoice_quantity_snapshot',
        'invoiced_quantity',
        'matched_quantity',
        'purchase_order_unit_price',
        'invoice_unit_price',
        'gross_amount',
        'expected_discount_amount',
        'discount_amount',
        'purchase_order_tax_rate',
        'invoice_tax_rate',
        'expected_tax_amount',
        'tax_amount',
        'expected_line_total',
        'line_total',
        'quantity_variance',
        'price_variance_amount',
        'discount_variance_amount',
        'tax_variance_amount',
        'total_variance_amount',
        'match_status',
        'variance_reason',
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
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return HasMany<SupplierInvoiceMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(
            SupplierInvoiceMatch::class,
        )->orderBy('id');
    }

    public function isFullyMatched(): bool
    {
        return BigDecimal::of(
            (string) $this->matched_quantity,
        )->isEqualTo(
            BigDecimal::of(
                (string) $this->invoiced_quantity,
            ),
        );
    }

    public function hasVariance(): bool
    {
        return $this->match_status === 'variance';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'supplier_invoice_id' => 'integer',
            'purchase_order_line_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'line_number' => 'integer',
            'ordered_quantity_snapshot' => 'decimal:6',
            'received_quantity_snapshot' => 'decimal:6',
            'previously_invoiced_quantity_snapshot' => 'decimal:6',
            'available_to_invoice_quantity_snapshot' => 'decimal:6',
            'invoiced_quantity' => 'decimal:6',
            'matched_quantity' => 'decimal:6',
            'purchase_order_unit_price' => 'decimal:6',
            'invoice_unit_price' => 'decimal:6',
            'gross_amount' => 'decimal:6',
            'expected_discount_amount' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'purchase_order_tax_rate' => 'decimal:6',
            'invoice_tax_rate' => 'decimal:6',
            'expected_tax_amount' => 'decimal:6',
            'tax_amount' => 'decimal:6',
            'expected_line_total' => 'decimal:6',
            'line_total' => 'decimal:6',
            'quantity_variance' => 'decimal:6',
            'price_variance_amount' => 'decimal:6',
            'discount_variance_amount' => 'decimal:6',
            'tax_variance_amount' => 'decimal:6',
            'total_variance_amount' => 'decimal:6',
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