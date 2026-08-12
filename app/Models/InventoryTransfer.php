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

final class InventoryTransfer extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_branch_id',
        'destination_branch_id',
        'source_warehouse_id',
        'destination_warehouse_id',
        'document_number_allocation_id',
        'transfer_number',
        'transfer_date',
        'status',
        'notes',
        'created_by_user_id',
        'posted_by_user_id',
        'posted_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(
            Branch::class,
            'source_branch_id',
        );
    }

    /** @return BelongsTo<Branch, $this> */
    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(
            Branch::class,
            'destination_branch_id',
        );
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
            'source_warehouse_id',
        );
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
            'destination_warehouse_id',
        );
    }

    /** @return BelongsTo<DocumentNumberAllocation, $this> */
    public function documentNumberAllocation(): BelongsTo
    {
        return $this->belongsTo(
            DocumentNumberAllocation::class,
        );
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id',
        );
    }

    /** @return BelongsTo<User, $this> */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'posted_by_user_id',
        );
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by_user_id',
        );
    }

    /** @return HasMany<InventoryTransferLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(
            InventoryTransferLine::class,
        )->orderBy('line_number');
    }

    /** @return MorphMany<StockLedgerEntry, $this> */
    public function stockLedgerEntries(): MorphMany
    {
        return $this->morphMany(
            StockLedgerEntry::class,
            'source',
        )
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'source_branch_id' => 'integer',
            'destination_branch_id' => 'integer',
            'source_warehouse_id' => 'integer',
            'destination_warehouse_id' => 'integer',
            'document_number_allocation_id' => 'integer',
            'created_by_user_id' => 'integer',
            'posted_by_user_id' => 'integer',
            'cancelled_by_user_id' => 'integer',
            'transfer_date' => 'immutable_date',
            'posted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
