<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class CustomerCreditNoteLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_credit_note_id',
        'sales_invoice_line_id',
        'sales_order_line_id',
        'product_id',
        'unit_id',
        'line_number',
        'line_type',
        'product_name',
        'product_sku',
        'product_type',
        'unit_name',
        'unit_code',
        'description',
        'credit_quantity',
        'return_to_stock',
        'unit_price',
        'gross_amount',
        'discount_amount',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'line_total',
        'unit_cost',
        'total_cost',
        'stock_ledger_entry_id',
        'reversal_stock_ledger_entry_id',
    ];

    /** @return BelongsTo<CustomerCreditNote, $this> */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CustomerCreditNote::class, 'customer_credit_note_id');
    }

    /** @return BelongsTo<SalesInvoiceLine, $this> */
    public function salesInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceLine::class);
    }

    /** @return BelongsTo<SalesOrderLine, $this> */
    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class);
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

    /** @return BelongsTo<StockLedgerEntry, $this> */
    public function reversalStockLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(
            StockLedgerEntry::class,
            'reversal_stock_ledger_entry_id',
        );
    }

    /** @return HasMany<CustomerCreditNoteDispatchAllocation, $this> */
    public function dispatchAllocations(): HasMany
    {
        return $this->hasMany(CustomerCreditNoteDispatchAllocation::class)
            ->orderBy('id');
    }

    public function isQuantityCredit(): bool
    {
        return $this->line_type === 'quantity';
    }

    public function isAmountCredit(): bool
    {
        return $this->line_type === 'amount';
    }

    public function isStockItem(): bool
    {
        return $this->product_type === 'stock';
    }

    public function restoresInventory(): bool
    {
        return $this->isQuantityCredit()
            && $this->isStockItem()
            && $this->return_to_stock;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'customer_credit_note_id' => 'integer',
            'sales_invoice_line_id' => 'integer',
            'sales_order_line_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'line_number' => 'integer',
            'credit_quantity' => 'decimal:6',
            'return_to_stock' => 'boolean',
            'unit_price' => 'decimal:6',
            'gross_amount' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'subtotal' => 'decimal:6',
            'tax_rate' => 'decimal:6',
            'tax_amount' => 'decimal:6',
            'line_total' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:6',
            'stock_ledger_entry_id' => 'integer',
            'reversal_stock_ledger_entry_id' => 'integer',
        ];
    }
}