<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SupplierDebitNote extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_return_id',
        'supplier_invoice_id',
        'purchase_order_id',
        'goods_receipt_id',
        'branch_id',
        'supplier_id',
        'document_number_allocation_id',
        'debit_note_number',
        'debit_note_date',
        'posting_date',
        'currency_code',
        'exchange_rate',
        'supplier_name',
        'supplier_code',
        'purchase_return_number',
        'supplier_invoice_number',
        'purchase_order_number',
        'goods_receipt_number',
        'source_purchase_return_revision',
        'status',
        'gross_amount',
        'discount_amount',
        'subtotal',
        'tax_amount',
        'total_amount',
        'allocated_amount',
        'unallocated_amount',
        'purchase_return_supplier_value',
        'purchase_return_inventory_value',
        'purchase_return_cost_variance',
        'supplier_reference',
        'reason',
        'notes',
        'revision',
        'created_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'posted_by_user_id',
        'posted_at',
        'accounting_posting_reference',
        'reversed_by_user_id',
        'reversal_posting_date',
        'reversed_at',
        'reversal_reason',
        'accounting_reversal_reference',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
    ];

    /**
     * @return BelongsTo<PurchaseReturn, $this>
     */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(
            PurchaseReturn::class,
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(
            Branch::class,
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
     * @return HasMany<SupplierDebitNoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(
            SupplierDebitNoteLine::class,
        )->orderBy('line_number');
    }

    /**
     * @return HasMany<SupplierDebitNoteAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(
            SupplierDebitNoteAllocation::class,
        )->orderBy('id');
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

    public function hasDebitNoteNumber(): bool
    {
        return $this->debit_note_number !== null
            && $this->debit_note_number !== '';
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function canBeDeleted(): bool
    {
        return $this->isDraft()
            && !$this->hasDebitNoteNumber()
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
            && $this->accounting_posting_reference
                === null
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
            'purchase_return_id' => 'integer',
            'supplier_invoice_id' => 'integer',
            'purchase_order_id' => 'integer',
            'goods_receipt_id' => 'integer',
            'branch_id' => 'integer',
            'supplier_id' => 'integer',

            'document_number_allocation_id' =>
                'integer',

            'debit_note_date' => 'date',
            'posting_date' => 'date',

            'exchange_rate' => 'decimal:8',

            'source_purchase_return_revision' =>
                'integer',

            'gross_amount' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'subtotal' => 'decimal:6',
            'tax_amount' => 'decimal:6',
            'total_amount' => 'decimal:6',
            'allocated_amount' => 'decimal:6',
            'unallocated_amount' => 'decimal:6',

            'purchase_return_supplier_value' =>
                'decimal:6',

            'purchase_return_inventory_value' =>
                'decimal:6',

            'purchase_return_cost_variance' =>
                'decimal:6',

            'revision' => 'integer',

            'created_by_user_id' => 'integer',
            'submitted_by_user_id' => 'integer',
            'approved_by_user_id' => 'integer',
            'posted_by_user_id' => 'integer',
            'reversed_by_user_id' => 'integer',
            'cancelled_by_user_id' => 'integer',

            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',

            'reversal_posting_date' =>
                'date',

            'reversed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}