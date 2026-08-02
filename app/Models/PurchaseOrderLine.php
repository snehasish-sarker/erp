<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PurchaseOrderLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_order_id',
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
        'received_quantity',
        'unit_price',
        'gross_amount',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
    ];

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrder::class,
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

    public function isStockItem(): bool
    {
        return $this->product_type === 'stock';
    }

    public function isNonStockItem(): bool
    {
        return $this->product_type === 'non_stock';
    }

    public function isService(): bool
    {
        return $this->product_type === 'service';
    }

    public function hasReceivedQuantity(): bool
    {
        return $this->compareDecimals(
            (string) $this->received_quantity,
            '0',
        ) > 0;
    }

    public function hasOutstandingQuantity(): bool
    {
        return $this->compareDecimals(
            (string) $this->received_quantity,
            (string) $this->ordered_quantity,
        ) < 0;
    }

    public function isFullyReceived(): bool
    {
        return $this->compareDecimals(
            (string) $this->received_quantity,
            (string) $this->ordered_quantity,
        ) >= 0;
    }

    public function isOverReceived(): bool
    {
        return $this->compareDecimals(
            (string) $this->received_quantity,
            (string) $this->ordered_quantity,
        ) > 0;
    }

    /**
     * Compare two non-negative decimal values without converting them
     * to floating-point numbers.
     */
    private function compareDecimals(
        string $left,
        string $right,
    ): int {
        [$leftWhole, $leftFraction] =
            $this->decimalParts($left);

        [$rightWhole, $rightFraction] =
            $this->decimalParts($right);

        $wholeLengthComparison =
            strlen($leftWhole)
            <=> strlen($rightWhole);

        if ($wholeLengthComparison !== 0) {
            return $wholeLengthComparison;
        }

        $wholeComparison = strcmp(
            $leftWhole,
            $rightWhole,
        );

        if ($wholeComparison !== 0) {
            return $wholeComparison <=> 0;
        }

        $fractionLength = max(
            strlen($leftFraction),
            strlen($rightFraction),
        );

        $leftFraction = str_pad(
            $leftFraction,
            $fractionLength,
            '0',
        );

        $rightFraction = str_pad(
            $rightFraction,
            $fractionLength,
            '0',
        );

        return strcmp(
            $leftFraction,
            $rightFraction,
        ) <=> 0;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function decimalParts(
        string $value,
    ): array {
        $value = trim($value);

        [$whole, $fraction] = array_pad(
            explode('.', $value, 2),
            2,
            '',
        );

        $whole = ltrim($whole, '0');

        if ($whole === '') {
            $whole = '0';
        }

        $fraction = rtrim(
            $fraction,
            '0',
        );

        return [
            $whole,
            $fraction,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'purchase_order_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',
            'line_number' => 'integer',

            'ordered_quantity' => 'decimal:6',
            'received_quantity' => 'decimal:6',
            'unit_price' => 'decimal:6',
            'gross_amount' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'tax_rate' => 'decimal:6',
            'tax_amount' => 'decimal:6',
            'line_total' => 'decimal:6',
        ];
    }

    /**
     * @return HasMany<GoodsReceiptLine, $this>
     */
    public function goodsReceiptLines(): HasMany
    {
        return $this->hasMany(
            GoodsReceiptLine::class,
        )->orderBy('line_number');
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
}