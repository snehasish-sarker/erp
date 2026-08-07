<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BankReconciliation extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'branch_id',
        'bank_account_id',
        'bank_statement_import_id',
        'document_number_allocation_id',
        'reconciliation_number',
        'active_key',
        'statement_start_date',
        'statement_end_date',
        'currency_code',
        'statement_opening_balance',
        'statement_closing_balance',
        'book_closing_balance',
        'outstanding_deposits',
        'outstanding_payments',
        'adjusted_bank_balance',
        'difference_amount',
        'status',
        'notes',
        'created_by_user_id',
        'completed_by_user_id',
        'completed_at',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function documentNumberAllocation(): BelongsTo
    {
        return $this->belongsTo(DocumentNumberAllocation::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BankReconciliationMatch::class)->orderBy('id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id')->withTrashed();
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id')->withTrashed();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'bank_account_id' => 'integer',
            'bank_statement_import_id' => 'integer',
            'document_number_allocation_id' => 'integer',
            'statement_start_date' => 'immutable_date',
            'statement_end_date' => 'immutable_date',
            'statement_opening_balance' => 'decimal:6',
            'statement_closing_balance' => 'decimal:6',
            'book_closing_balance' => 'decimal:6',
            'outstanding_deposits' => 'decimal:6',
            'outstanding_payments' => 'decimal:6',
            'adjusted_bank_balance' => 'decimal:6',
            'difference_amount' => 'decimal:6',
            'created_by_user_id' => 'integer',
            'completed_by_user_id' => 'integer',
            'reversed_by_user_id' => 'integer',
            'completed_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
        ];
    }
}
