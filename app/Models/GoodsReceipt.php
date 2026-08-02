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

final class GoodsReceipt extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_order_id',
        'branch_id',
        'warehouse_id',
        'supplier_id',
        'document_number_allocation_id',
        'receipt_number',
        'receipt_date',
        'supplier_delivery_note',
        'supplier_name',
        'supplier_code',
        'purchase_order_number',
        'status',
        'inspection_status',
        'total_received_quantity',
        'total_accepted_quantity',
        'total_rejected_quantity',
        'total_inventory_value',
        'notes',
        'created_by_user_id',
        'posted_by_user_id',
        'posted_at',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
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
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseOrder::class,
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class,
        );
    }

    /**
     * @return BelongsTo<DocumentNumberAllocation, $this>
     */
    public function documentNumberAllocation(): BelongsTo
    {
        return $this->belongsTo(
            DocumentNumberAllocation::class,
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
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'posted_by_user_id',
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reversed_by_user_id',
        );
    }

    /**
     * @return HasMany<GoodsReceiptLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(
            GoodsReceiptLine::class,
        )->orderBy('line_number');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function canBePosted(): bool
    {
        return $this->isDraft();
    }

    public function canBeReversed(): bool
    {
        return $this->isPosted();
    }

    public function hasReceiptNumber(): bool
    {
        return $this->receipt_number !== null
            && $this->receipt_number !== '';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'purchase_order_id' => 'integer',
            'branch_id' => 'integer',
            'warehouse_id' => 'integer',
            'supplier_id' => 'integer',

            'document_number_allocation_id' =>
                'integer',

            'receipt_date' => 'date',

            'total_received_quantity' =>
                'decimal:6',

            'total_accepted_quantity' =>
                'decimal:6',

            'total_rejected_quantity' =>
                'decimal:6',

            'total_inventory_value' =>
                'decimal:6',

            'created_by_user_id' => 'integer',
            'posted_by_user_id' => 'integer',
            'reversed_by_user_id' => 'integer',

            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphMany<StockLedgerEntry, $this>
     */
    public function stockLedgerEntries(): MorphMany
    {
        return $this->morphMany(
            StockLedgerEntry::class,
            'source',
        )->orderBy('occurred_at')
            ->orderBy('id');
    }

    public function canBeDeleted(): bool
    {
        return $this->isDraft()
            && !$this->hasReceiptNumber()
            && $this->document_number_allocation_id === null
            && $this->posted_by_user_id === null
            && $this->posted_at === null
            && $this->reversed_by_user_id === null
            && $this->reversed_at === null
            && $this->reversal_reason === null;
    }

    /**
     * @return HasMany<PurchaseReturn, $this>
     */
    public function purchaseReturns(): HasMany
    {
        return $this->hasMany(
            PurchaseReturn::class,
        )
            ->orderByDesc('return_date')
            ->orderByDesc('id');
    }

    /**
 * @return HasMany<SupplierDebitNote, $this>
 */
public function supplierDebitNotes(): HasMany
{
    return $this->hasMany(
        SupplierDebitNote::class,
    )
        ->orderByDesc(
            'debit_note_date',
        )
        ->orderByDesc('id');
}
}