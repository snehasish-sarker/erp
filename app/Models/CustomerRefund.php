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

final class CustomerRefund extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;
    /** @var list<string> */
    protected $fillable = ['branch_id', 'customer_id', 'refund_account_id', 'document_number_allocation_id', 'customer_ledger_entry_id', 'customer_open_item_id', 'refund_number', 'refund_date', 'posting_date', 'currency_code', 'exchange_rate', 'refund_method', 'refund_reference', 'cheque_number', 'cheque_date', 'customer_name', 'customer_code', 'refund_account_code', 'refund_account_name', 'status', 'total_amount', 'base_cash_amount', 'base_credit_amount', 'exchange_difference_amount', 'reason', 'notes', 'revision', 'created_by_user_id', 'submitted_by_user_id', 'submitted_at', 'approved_by_user_id', 'approved_at', 'posted_by_user_id', 'posted_at', 'accounting_posting_reference', 'reversal_posting_date', 'reversed_by_user_id', 'reversed_at', 'reversal_reason', 'accounting_reversal_reference', 'cancelled_by_user_id', 'cancelled_at', 'cancellation_reason',];
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function refundAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'refund_account_id');
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

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerRefundAllocation::class)->orderBy('line_number');
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

    public function canBeDeleted(): bool
    {
        return $this->isDraft();
    }

    public function hasRefundNumber(): bool
    {
        return filled($this->refund_number);
    }
    /** @return array<string, string> */
    protected function casts(): array
    {
        return['tenant_id' => 'integer', 'branch_id' => 'integer', 'customer_id' => 'integer', 'refund_account_id' => 'integer', 'document_number_allocation_id' => 'integer', 'customer_ledger_entry_id' => 'integer', 'customer_open_item_id' => 'integer', 'refund_date' => 'immutable_date', 'posting_date' => 'immutable_date', 'cheque_date' => 'immutable_date', 'exchange_rate' => 'decimal:8', 'total_amount' => 'decimal:6', 'base_cash_amount' => 'decimal:6', 'base_credit_amount' => 'decimal:6', 'exchange_difference_amount' => 'decimal:6', 'revision' => 'integer', 'created_by_user_id' => 'integer', 'submitted_by_user_id' => 'integer', 'approved_by_user_id' => 'integer', 'posted_by_user_id' => 'integer', 'reversed_by_user_id' => 'integer', 'cancelled_by_user_id' => 'integer', 'submitted_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'posted_at' => 'immutable_datetime', 'reversal_posting_date' => 'immutable_date', 'reversed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime',];
    }
}