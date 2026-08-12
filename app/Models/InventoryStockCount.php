<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class InventoryStockCount extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'document_number_allocation_id',
        'count_number',
        'count_date',
        'status',
        'notes',
        'total_lines',
        'variance_line_count',
        'total_positive_variance',
        'total_negative_variance',
        'total_value_gain',
        'total_value_loss',
        'created_by_user_id',
        'posted_by_user_id',
        'posted_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<DocumentNumberAllocation, $this> */
    public function documentNumberAllocation(): BelongsTo
    {
        return $this->belongsTo(DocumentNumberAllocation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /** @return HasMany<InventoryStockCountLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryStockCountLine::class)
            ->orderBy('line_number');
    }

    /** @return MorphMany<StockLedgerEntry, $this> */
    public function stockLedgerEntries(): MorphMany
    {
        return $this->morphMany(StockLedgerEntry::class, 'source')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'warehouse_id' => 'integer',
            'document_number_allocation_id' => 'integer',
            'created_by_user_id' => 'integer',
            'posted_by_user_id' => 'integer',
            'cancelled_by_user_id' => 'integer',
            'count_date' => 'immutable_date',
            'total_lines' => 'integer',
            'variance_line_count' => 'integer',
            'total_positive_variance' => 'decimal:6',
            'total_negative_variance' => 'decimal:6',
            'total_value_gain' => 'decimal:6',
            'total_value_loss' => 'decimal:6',
            'posted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
