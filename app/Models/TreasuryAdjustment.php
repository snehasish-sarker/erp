<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TreasuryAdjustment extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'branch_id',
        'bank_account_id',
        'offset_account_id',
        'bank_statement_line_id',
        'active_statement_key',
        'document_number_allocation_id',
        'adjustment_number',
        'adjustment_type',
        'adjustment_date',
        'posting_date',
        'currency_code',
        'exchange_rate',
        'amount',
        'base_amount',
        'reference',
        'bank_account_code',
        'bank_account_name',
        'offset_account_code',
        'offset_account_name',
        'description',
        'status',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function offsetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'offset_account_id');
    }

    public function bankStatementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class);
    }

    public function documentNumberAllocation(): BelongsTo
    {
        return $this->belongsTo(DocumentNumberAllocation::class);
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

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function canBeDeleted(): bool
    {
        return $this->isDraft() && $this->adjustment_number === null;
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'bank_account_id' => 'integer',
            'offset_account_id' => 'integer',
            'bank_statement_line_id' => 'integer',
            'document_number_allocation_id' => 'integer',
            'adjustment_date' => 'immutable_date',
            'posting_date' => 'immutable_date',
            'exchange_rate' => 'decimal:8',
            'amount' => 'decimal:6',
            'base_amount' => 'decimal:6',
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
