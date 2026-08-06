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

final class CustomerReceipt extends Model
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
        'customer_id',
        'receipt_account_id',
        'document_number_allocation_id',
        'receipt_number',
        'receipt_date',
        'posting_date',
        'currency_code',
        'exchange_rate',
        'receipt_method',
        'receipt_reference',
        'cheque_number',
        'cheque_date',
        'customer_name',
        'customer_code',
        'receipt_account_code',
        'receipt_account_name',
        'status',
        'total_amount',
        'allocated_amount',
        'unallocated_amount',
        'base_total_amount',
        'base_allocated_amount',
        'base_unallocated_amount',
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(
            Branch::class,
        );
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class,
        );
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function receiptAccount(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'receipt_account_id',
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
     * @return HasMany<CustomerReceiptAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(
            CustomerReceiptAllocation::class,
        )->orderBy('line_number');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id',
        )->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'submitted_by_user_id',
        )->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by_user_id',
        )->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'posted_by_user_id',
        )->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reversed_by_user_id',
        )->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by_user_id',
        )->withTrashed();
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

    /**
     * @return MorphMany<CustomerLedgerEntry, $this>
     */
    public function customerLedgerEntries(): MorphMany
    {
        return $this->morphMany(
            CustomerLedgerEntry::class,
            'source',
        )
            ->orderBy('posting_date')
            ->orderBy('id');
    }

    /**
     * @return MorphMany<CustomerOpenItem, $this>
     */
    public function customerOpenItems(): MorphMany
    {
        return $this->morphMany(
            CustomerOpenItem::class,
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

    public function hasReceiptNumber(): bool
    {
        return $this->receipt_number !== null
            && $this->receipt_number !== '';
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function canBeDeleted(): bool
    {
        return $this->isDraft()
            && !$this->hasReceiptNumber()
            && $this->document_number_allocation_id === null
            && $this->submitted_by_user_id === null
            && $this->submitted_at === null
            && $this->approved_by_user_id === null
            && $this->approved_at === null
            && $this->posted_by_user_id === null
            && $this->posted_at === null
            && $this->accounting_posting_reference === null
            && $this->reversed_by_user_id === null
            && $this->reversed_at === null
            && $this->cancelled_by_user_id === null
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
            'customer_id' => 'integer',
            'receipt_account_id' => 'integer',
            'document_number_allocation_id' => 'integer',
            'receipt_date' => 'immutable_date',
            'posting_date' => 'immutable_date',
            'cheque_date' => 'immutable_date',
            'exchange_rate' => 'decimal:8',
            'total_amount' => 'decimal:6',
            'allocated_amount' => 'decimal:6',
            'unallocated_amount' => 'decimal:6',
            'base_total_amount' => 'decimal:6',
            'base_allocated_amount' => 'decimal:6',
            'base_unallocated_amount' => 'decimal:6',
            'revision' => 'integer',
            'created_by_user_id' => 'integer',
            'submitted_by_user_id' => 'integer',
            'approved_by_user_id' => 'integer',
            'posted_by_user_id' => 'integer',
            'reversed_by_user_id' => 'integer',
            'cancelled_by_user_id' => 'integer',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'posted_at' => 'immutable_datetime',
            'reversal_posting_date' => 'immutable_date',
            'reversed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}