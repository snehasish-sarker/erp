<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class StockLedgerEntry extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'product_id',
        'unit_id',
        'movement_type',
        'posting_key',
        'source_type',
        'source_id',
        'source_line_id',
        'document_number',
        'occurred_at',
        'quantity_in',
        'quantity_out',
        'unit_cost',
        'total_cost',
        'balance_quantity',
        'balance_value',
        'created_by_user_id',
        'reversal_of_id',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(
            Tenant::class,
        );
    }

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
     * @return BelongsTo<StockLedgerEntry, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(
            StockLedgerEntry::class,
            'reversal_of_id',
        );
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
            'source_id' => 'integer',
            'source_line_id' => 'integer',
            'created_by_user_id' => 'integer',
            'reversal_of_id' => 'integer',
            'occurred_at' => 'datetime',
            'quantity_in' => 'decimal:6',
            'quantity_out' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:6',
            'balance_quantity' => 'decimal:6',
            'balance_value' => 'decimal:6',
        ];
    }

    /**
 * @return MorphTo<Model, $this>
 */
public function source(): MorphTo
{
    return $this->morphTo();
}
}