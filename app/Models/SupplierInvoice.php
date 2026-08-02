<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SupplierInvoice extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'supplier_id',
        'purchase_order_id',
        'document_number_allocation_id',
        'document_number',
        'supplier_invoice_number',
        'supplier_invoice_number_normalized',
        'supplier_invoice_number_hash',
        'invoice_date',
        'posting_date',
        'due_date',
        'currency_code',
        'exchange_rate',
        'supplier_name',
        'supplier_code',
        'supplier_tax_number',
        'supplier_address',
        'payment_terms_days',
        'purchase_order_number',
        'total_invoiced_quantity',
        'total_matched_quantity',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'other_charges',
        'rounding_adjustment',
        'total_amount',
        'quantity_variance',
        'price_variance_amount',
        'discount_variance_amount',
        'tax_variance_amount',
        'total_variance_amount',
        'status',
        'match_status',
        'notes',
        'matching_notes',
        'revision',
        'matching_reserved_at',
        'created_by_user_id',
        'validated_by_user_id',
        'validated_at',
        'approved_by_user_id',
        'approved_at',
        'disputed_by_user_id',
        'disputed_at',
        'dispute_reason',
        'posted_by_user_id',
        'posted_at',
        'accounting_posting_reference',
        'reversal_posting_date',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
        'accounting_reversal_reference',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
        'debit_note_reserved_amount',
'debited_amount',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
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
     * @return HasMany<SupplierInvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(
            SupplierInvoiceLine::class,
        )->orderBy('line_number');
    }

    /**
     * @return HasMany<SupplierInvoiceMatch, $this>
     */
    public function matches(): HasMany
    {
        return $this->hasMany(
            SupplierInvoiceMatch::class,
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
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'validated_by_user_id',
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
    public function disputedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'disputed_by_user_id',
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

    public function isValidated(): bool
    {
        return $this->status === 'validated';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isDisputed(): bool
    {
        return $this->status === 'disputed';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function hasDocumentNumber(): bool
    {
        return $this->document_number !== null
            && $this->document_number !== '';
    }

    public function hasMatchingReservation(): bool
    {
        return $this->matching_reserved_at !== null;
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function canBeDeleted(): bool
    {
        return $this->isDraft()
            && !$this->hasDocumentNumber()
            && $this->document_number_allocation_id === null
            && !$this->hasMatchingReservation()
            && $this->validated_at === null
            && $this->approved_at === null
            && $this->posted_at === null
            && $this->reversed_at === null
            && $this->cancelled_at === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'supplier_id' => 'integer',
            'purchase_order_id' => 'integer',
            'document_number_allocation_id' => 'integer',
            'invoice_date' => 'date',
            'posting_date' => 'date',
            'due_date' => 'date',
            'reversal_posting_date' => 'date',
            'payment_terms_days' => 'integer',
            'revision' => 'integer',
            'exchange_rate' => 'decimal:8',
            'total_invoiced_quantity' => 'decimal:6',
            'total_matched_quantity' => 'decimal:6',
            'subtotal' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'tax_amount' => 'decimal:6',
            'other_charges' => 'decimal:6',
            'rounding_adjustment' => 'decimal:6',
            'total_amount' => 'decimal:6',
            'quantity_variance' => 'decimal:6',
            'price_variance_amount' => 'decimal:6',
            'discount_variance_amount' => 'decimal:6',
            'tax_variance_amount' => 'decimal:6',
            'total_variance_amount' => 'decimal:6',
            'created_by_user_id' => 'integer',
            'validated_by_user_id' => 'integer',
            'approved_by_user_id' => 'integer',
            'disputed_by_user_id' => 'integer',
            'posted_by_user_id' => 'integer',
            'reversed_by_user_id' => 'integer',
            'cancelled_by_user_id' => 'integer',
            'matching_reserved_at' => 'datetime',
            'validated_at' => 'datetime',
            'approved_at' => 'datetime',
            'disputed_at' => 'datetime',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'debit_note_reserved_amount' =>
    'decimal:6',

'debited_amount' =>
    'decimal:6',
        ];
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

/**
 * @return HasMany<SupplierDebitNoteAllocation, $this>
 */
public function supplierDebitNoteAllocations(): HasMany
{
    return $this->hasMany(
        SupplierDebitNoteAllocation::class,
    )->orderBy('id');
}

public function availableDebitNoteAmount(): BigDecimal
{
    return BigDecimal::of(
        (string) $this->total_amount,
    )
        ->minus(
            BigDecimal::of(
                (string) $this
                    ->debit_note_reserved_amount,
            ),
        )
        ->minus(
            BigDecimal::of(
                (string) $this
                    ->debited_amount,
            ),
        );
}
}