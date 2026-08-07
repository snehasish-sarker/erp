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

final class BankStatementLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'bank_statement_import_id',
        'bank_account_id',
        'line_number',
        'transaction_date',
        'value_date',
        'bank_reference',
        'description',
        'debit_amount',
        'credit_amount',
        'signed_amount',
        'running_balance',
        'matched_amount',
        'fingerprint',
        'status',
        'ignore_reason',
        'ignored_by_user_id',
        'ignored_at',
    ];

    public function statementImport(): BelongsTo
    {
        return $this->belongsTo(BankStatementImport::class, 'bank_statement_import_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
    }

    public function ignoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ignored_by_user_id')->withTrashed();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(BankReconciliationMatch::class)->orderBy('id');
    }

    public function treasuryAdjustment(): HasOne
    {
        return $this->hasOne(TreasuryAdjustment::class);
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'bank_statement_import_id' => 'integer',
            'bank_account_id' => 'integer',
            'line_number' => 'integer',
            'transaction_date' => 'immutable_date',
            'value_date' => 'immutable_date',
            'debit_amount' => 'decimal:6',
            'credit_amount' => 'decimal:6',
            'signed_amount' => 'decimal:6',
            'running_balance' => 'decimal:6',
            'matched_amount' => 'decimal:6',
            'ignored_by_user_id' => 'integer',
            'ignored_at' => 'immutable_datetime',
        ];
    }
}
