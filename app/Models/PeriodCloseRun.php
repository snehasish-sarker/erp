<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class PeriodCloseRun extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'accounting_period_id',
        'run_number',
        'status',
        'total_checks',
        'passed_checks',
        'warning_checks',
        'failed_checks',
        'total_reconciliation_difference',
        'closing_journal_ids',
        'close_reason',
        'reopen_reason',
        'prepared_by_user_id',
        'prepared_at',
        'closed_by_user_id',
        'closed_at',
        'reopened_by_user_id',
        'reopened_at',
    ];

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(PeriodCloseCheckItem::class)->orderBy('id');
    }

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id')->withTrashed();
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id')->withTrashed();
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id')->withTrashed();
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'accounting_period_id' => 'integer',
            'run_number' => 'integer',
            'total_checks' => 'integer',
            'passed_checks' => 'integer',
            'warning_checks' => 'integer',
            'failed_checks' => 'integer',
            'total_reconciliation_difference' => 'decimal:6',
            'closing_journal_ids' => 'array',
            'prepared_by_user_id' => 'integer',
            'closed_by_user_id' => 'integer',
            'reopened_by_user_id' => 'integer',
            'prepared_at' => 'immutable_datetime',
            'closed_at' => 'immutable_datetime',
            'reopened_at' => 'immutable_datetime',
        ];
    }
}
