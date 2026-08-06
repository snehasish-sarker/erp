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
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class InventoryReservation extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    private const SCALE = 6;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'product_id',
        'unit_id',
        'sales_order_allocation_line_id',
        'reservation_key',
        'active_key',
        'source_type',
        'source_id',
        'source_line_id',
        'reserved_quantity',
        'consumed_quantity',
        'released_quantity',
        'status',
        'reserved_at',
        'expires_at',
        'created_by_user_id',
        'released_by_user_id',
        'released_at',
        'release_reason',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(
            Branch::class,
        );
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
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

    /**
     * @return BelongsTo<SalesOrderAllocationLine, $this>
     */
    public function salesOrderAllocationLine(): BelongsTo
    {
        return $this->belongsTo(
            SalesOrderAllocationLine::class,
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id',
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'released_by_user_id',
        );
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function isOpen(): bool
    {
        return in_array(
            $this->status,
            [
                'active',
                'partially_consumed',
            ],
            true,
        ) && $this->active_key !== null;
    }

    public function hasConsumedQuantity(): bool
    {
        return BigDecimal::of(
            (string) $this->consumed_quantity,
        )->isGreaterThan(
            BigDecimal::zero(),
        );
    }

    public function outstandingQuantity(): string
    {
        return BigDecimal::of(
            (string) $this->reserved_quantity,
        )
            ->minus(
                BigDecimal::of(
                    (string) $this->consumed_quantity,
                ),
            )
            ->minus(
                BigDecimal::of(
                    (string) $this->released_quantity,
                ),
            )
            ->toScale(
                self::SCALE,
                RoundingMode::UNNECESSARY,
            )
            ->__toString();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'warehouse_id' => 'integer',
            'product_id' => 'integer',
            'unit_id' => 'integer',

            'sales_order_allocation_line_id' =>
                'integer',

            'source_id' => 'integer',
            'source_line_id' => 'integer',
            'reserved_quantity' => 'decimal:6',
            'consumed_quantity' => 'decimal:6',
            'released_quantity' => 'decimal:6',
            'reserved_at' => 'datetime',
            'expires_at' => 'datetime',
            'created_by_user_id' => 'integer',
            'released_by_user_id' => 'integer',
            'released_at' => 'datetime',
        ];
    }
}