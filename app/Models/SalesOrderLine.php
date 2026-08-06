<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SalesOrderLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sales_order_id',
        'product_id',
        'unit_id',
        'line_number',
        'product_name',
        'product_sku',
        'product_type',
        'unit_name',
        'unit_code',
        'description',
        'ordered_quantity',
        'allocated_quantity',
        'dispatched_quantity',
        'invoiced_quantity',
        'returned_quantity',
        'unit_price',
        'gross_amount',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
    ];

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
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

    /** @return HasMany<SalesOrderAllocationLine, $this> */
    public function allocationLines(): HasMany
    {
        return $this->hasMany(SalesOrderAllocationLine::class);
    }

    /** @return HasMany<CustomerDispatchLine, $this> */
    public function dispatchLines(): HasMany
    {
        return $this->hasMany(CustomerDispatchLine::class);
    }

    /** @return HasMany<SalesInvoiceLine, $this> */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(SalesInvoiceLine::class);
    }

    /** @return HasMany<CustomerCreditNoteLine, $this> */
    public function creditNoteLines(): HasMany
    {
        return $this->hasMany(CustomerCreditNoteLine::class);
    }

    public function isStockItem(): bool
    {
        return $this->product_type === 'stock';
    }

    public function remainingToAllocate(): string
    {
        return $this->remaining(
            total: (string) $this->ordered_quantity,
            completed: (string) $this->allocated_quantity,
        );
    }

    public function remainingToDispatch(): string
    {
        return $this->remaining(
            total: (string) $this->allocated_quantity,
            completed: (string) $this->dispatched_quantity,
        );
    }

    public function remainingToInvoice(): string
    {
        return $this->remaining(
            total: (string) $this->dispatched_quantity,
            completed: (string) $this->invoiced_quantity,
        );
    }

    public function remainingReturnable(): string
    {
        return $this->remaining(
            total: (string) $this->invoiced_quantity,
            completed: (string) $this->returned_quantity,
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'sales_order_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'line_number' => 'integer',
            'ordered_quantity' => 'decimal:6',
            'allocated_quantity' => 'decimal:6',
            'dispatched_quantity' => 'decimal:6',
            'invoiced_quantity' => 'decimal:6',
            'returned_quantity' => 'decimal:6',
            'unit_price' => 'decimal:6',
            'gross_amount' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'tax_rate' => 'decimal:6',
            'tax_amount' => 'decimal:6',
            'line_total' => 'decimal:6',
        ];
    }

    private function remaining(
        string $total,
        string $completed,
    ): string {
        $remaining = BigDecimal::of($total)
            ->minus(BigDecimal::of($completed));

        if ($remaining->isLessThan(BigDecimal::zero())) {
            return '0.000000';
        }

        return $remaining
            ->toScale(6, RoundingMode::HALF_UP)
            ->__toString();
    }
}