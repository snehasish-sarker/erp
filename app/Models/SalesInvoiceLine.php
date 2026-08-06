<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SalesInvoiceLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sales_invoice_id',
        'sales_order_line_id',
        'product_id',
        'unit_id',
        'line_number',
        'product_name',
        'product_sku',
        'product_type',
        'unit_name',
        'unit_code',
        'description',
        'invoiced_quantity',
        'credited_quantity',
        'unit_price',
        'gross_amount',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'credited_amount',
        'unit_cost',
        'total_cost',
    ];

    /** @return BelongsTo<SalesInvoice, $this> */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
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

    /** @return HasMany<SalesInvoiceDispatchAllocation, $this> */
    public function dispatchAllocations(): HasMany
    {
        return $this->hasMany(SalesInvoiceDispatchAllocation::class)
            ->orderBy('id');
    }

    /** @return HasMany<CustomerCreditNoteLine, $this> */
    public function creditNoteLines(): HasMany
    {
        return $this->hasMany(CustomerCreditNoteLine::class)
            ->orderBy('id');
    }

    public function isStockItem(): bool
    {
        return $this->product_type === 'stock';
    }

    public function isService(): bool
    {
        return $this->product_type === 'service';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'sales_invoice_id' => 'integer',
            'sales_order_line_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'line_number' => 'integer',
            'invoiced_quantity' => 'decimal:6',
            'credited_quantity' => 'decimal:6',
            'unit_price' => 'decimal:6',
            'gross_amount' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'tax_rate' => 'decimal:6',
            'tax_amount' => 'decimal:6',
            'line_total' => 'decimal:6',
            'credited_amount' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:6',
        ];
    }
}