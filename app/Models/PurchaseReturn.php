<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PurchaseReturn extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_order_id',
        'goods_receipt_id',
        'supplier_invoice_id',
        'branch_id',
        'warehouse_id',
        'supplier_id',
        'document_number_allocation_id',
        'return_number',
        'return_date',
        'posting_date',
        'supplier_reference',
        'supplier_name',
        'supplier_code',
        'purchase_order_number',
        'goods_receipt_number',
        'supplier_invoice_number',
        'status',
        'total_return_quantity',
        'total_supplier_value',
        'total_inventory_value',
        'total_cost_variance',
        'return_reason',
        'notes',
        'revision',
        'created_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'posted_by_user_id',
        'posted_at',
        'accounting_reference',
        'reversed_by_user_id',
        'reversal_posting_date',
        'reversed_at',
        'accounting_reversal_reference',
        'reversal_reason',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
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
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(
            GoodsReceipt::class,
        );
    }

    /**
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(
            SupplierInvoice::class,
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
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'submitted_by_user_id',
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by_user_id',
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
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by_user_id',
        );
    }

    /**
     * @return HasMany<PurchaseReturnLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(
            PurchaseReturnLine::class,
        )->orderBy('line_number');
    }

    /**
     * @return MorphMany<StockLedgerEntry, $this>
     */
    public function stockLedgerEntries(): MorphMany
    {
        return $this->morphMany(
            StockLedgerEntry::class,
            'source',
        )
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    /**
     * @return MorphMany<JournalEntry, $this>
     */
    public function journalEntries(): MorphMany
    {
        return $this->morphMany(
            JournalEntry::class,
            'source',
        )
            ->orderBy('posting_date')
            ->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function hasReturnNumber(): bool
    {
        return $this->return_number !== null
            && $this->return_number !== '';
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function canBeDeleted(): bool
    {
        return $this->isDraft()
            && !$this->hasReturnNumber()
            && $this->document_number_allocation_id
                === null
            && $this->submitted_by_user_id
                === null
            && $this->submitted_at === null
            && $this->approved_by_user_id
                === null
            && $this->approved_at === null
            && $this->posted_by_user_id
                === null
            && $this->posted_at === null
            && $this->reversed_by_user_id
                === null
            && $this->reversed_at === null
            && $this->cancelled_by_user_id
                === null
            && $this->cancelled_at === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'purchase_order_id' => 'integer',
            'goods_receipt_id' => 'integer',
            'supplier_invoice_id' => 'integer',
            'branch_id' => 'integer',
            'warehouse_id' => 'integer',
            'supplier_id' => 'integer',

            'document_number_allocation_id' =>
                'integer',

            'return_date' => 'date',
            'posting_date' => 'date',

            'reversal_posting_date' =>
                'date',

            'total_return_quantity' =>
                'decimal:6',

            'total_supplier_value' =>
                'decimal:6',

            'total_inventory_value' =>
                'decimal:6',

            'total_cost_variance' =>
                'decimal:6',

            'revision' => 'integer',

            'created_by_user_id' =>
                'integer',

            'submitted_by_user_id' =>
                'integer',

            'approved_by_user_id' =>
                'integer',

            'posted_by_user_id' =>
                'integer',

            'reversed_by_user_id' =>
                'integer',

            'cancelled_by_user_id' =>
                'integer',

            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<SupplierDebitNote, $this>
     */
    public function supplierDebitNote(): HasOne
    {
        return $this->hasOne(
            SupplierDebitNote::class,
        );
    }
}