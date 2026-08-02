<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupplierDebitNoteLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_debit_note_id',
        'purchase_return_line_id',
        'supplier_invoice_line_id',
        'product_id',
        'unit_id',
        'line_number',
        'product_name',
        'product_sku',
        'unit_name',
        'unit_code',
        'return_quantity',
        'unit_price',
        'gross_amount',
        'discount_amount',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'purchase_return_supplier_unit_cost',
        'purchase_return_supplier_total_cost',
        'purchase_return_inventory_unit_cost',
        'purchase_return_inventory_total_cost',
        'purchase_return_cost_variance',
        'description',
        'notes',
    ];

    /**
     * @return BelongsTo<SupplierDebitNote, $this>
     */
    public function supplierDebitNote(): BelongsTo
    {
        return $this->belongsTo(
            SupplierDebitNote::class,
        );
    }

    /**
     * @return BelongsTo<PurchaseReturnLine, $this>
     */
    public function purchaseReturnLine(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseReturnLine::class,
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
            'supplier_debit_note_id' => 'integer',
            'purchase_return_line_id' => 'integer',
            'supplier_invoice_line_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'line_number' => 'integer',

            'return_quantity' => 'decimal:6',
            'unit_price' => 'decimal:6',
            'gross_amount' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'subtotal' => 'decimal:6',
            'tax_rate' => 'decimal:6',
            'tax_amount' => 'decimal:6',
            'total_amount' => 'decimal:6',

            'purchase_return_supplier_unit_cost' =>
                'decimal:6',

            'purchase_return_supplier_total_cost' =>
                'decimal:6',

            'purchase_return_inventory_unit_cost' =>
                'decimal:6',

            'purchase_return_inventory_total_cost' =>
                'decimal:6',

            'purchase_return_cost_variance' =>
                'decimal:6',
        ];
    }
}