<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BankReconciliationMatch extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'bank_reconciliation_id',
        'bank_statement_line_id',
        'journal_entry_line_id',
        'match_type',
        'matched_amount',
        'active_key',
        'status',
        'matched_by_user_id',
        'matched_at',
        'reversed_by_user_id',
        'reversed_at',
    ];

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_statement_line_id');
    }

    public function journalEntryLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class);
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by_user_id')->withTrashed();
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id')->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'bank_reconciliation_id' => 'integer',
            'bank_statement_line_id' => 'integer',
            'journal_entry_line_id' => 'integer',
            'matched_amount' => 'decimal:6',
            'matched_by_user_id' => 'integer',
            'reversed_by_user_id' => 'integer',
            'matched_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
        ];
    }
}
