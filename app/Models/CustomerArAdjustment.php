<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CustomerArAdjustment extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;
    /** @var list<string> */
    protected $fillable = ['branch_id', 'customer_id', 'offset_account_id', 'document_number_allocation_id', 'customer_ledger_entry_id', 'customer_open_item_id', 'adjustment_number', 'adjustment_date', 'posting_date', 'currency_code', 'exchange_rate', 'direction', 'customer_name', 'customer_code', 'offset_account_code', 'offset_account_name', 'status', 'amount', 'base_amount', 'reason', 'notes', 'revision', 'created_by_user_id', 'submitted_by_user_id', 'submitted_at', 'approved_by_user_id', 'approved_at', 'posted_by_user_id', 'posted_at', 'accounting_posting_reference', 'reversal_posting_date', 'reversed_by_user_id', 'reversed_at', 'reversal_reason', 'accounting_reversal_reference', 'cancelled_by_user_id', 'cancelled_at', 'cancellation_reason',];
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function offsetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'offset_account_id');
    }

    public function documentNumberAllocation(): BelongsTo
    {
        return $this->belongsTo(DocumentNumberAllocation::class);
    }

    public function customerLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(CustomerLedgerEntry::class);
    }

    public function customerOpenItem(): BelongsTo
    {
        return $this->belongsTo(CustomerOpenItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id')->withTrashed();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id')->withTrashed();
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id')->withTrashed();
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id')->withTrashed();
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id')->withTrashed();
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source')->orderBy('posting_date')->orderBy('id');
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

    public function isDebit(): bool
    {
        return $this->direction === 'debit';
    }

    public function isCredit(): bool
    {
        return $this->direction === 'credit';
    }

    public function canBeDeleted(): bool
    {
        return $this->isDraft();
    }

    public function hasAdjustmentNumber(): bool
    {
        return filled($this->adjustment_number);
    }
    /** @return array<string, string> */
    protected function casts(): array
    {
        return['tenant_id' => 'integer', 'branch_id' => 'integer', 'customer_id' => 'integer', 'offset_account_id' => 'integer', 'document_number_allocation_id' => 'integer', 'customer_ledger_entry_id' => 'integer', 'customer_open_item_id' => 'integer', 'adjustment_date' => 'immutable_date', 'posting_date' => 'immutable_date', 'exchange_rate' => 'decimal:8', 'amount' => 'decimal:6', 'base_amount' => 'decimal:6', 'revision' => 'integer', 'created_by_user_id' => 'integer', 'submitted_by_user_id' => 'integer', 'approved_by_user_id' => 'integer', 'posted_by_user_id' => 'integer', 'reversed_by_user_id' => 'integer', 'cancelled_by_user_id' => 'integer', 'submitted_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'posted_at' => 'immutable_datetime', 'reversal_posting_date' => 'immutable_date', 'reversed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',];
    }
}