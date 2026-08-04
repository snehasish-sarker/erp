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
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

final class JournalEntry extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'accounting_period_id',
        'document_number_allocation_id',
        'journal_number',
        'posting_key',
        'journal_type',
        'status',
        'source_type',
        'source_id',
        'source_document_number',
        'document_date',
        'posting_date',
        'currency_code',
        'exchange_rate',
        'total_debit',
        'total_credit',
        'base_total_debit',
        'base_total_credit',
        'description',
        'prepared_by_user_id',
        'approved_by_user_id',
        'approved_at',
        'posted_by_user_id',
        'posted_at',
        'reversal_of_id',
        'reversal_reason',
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
     * @return BelongsTo<AccountingPeriod, $this>
     */
    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(
            AccountingPeriod::class,
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
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<JournalEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(
            JournalEntryLine::class,
        )
            ->orderBy('line_number')
            ->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'prepared_by_user_id',
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
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by_user_id',
        )->withTrashed();
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(
            JournalEntry::class,
            'reversal_of_id',
        );
    }

    /**
     * @return HasOne<JournalEntry, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(
            JournalEntry::class,
            'reversal_of_id',
        );
    }

    public function hasJournalNumber(): bool
    {
        return $this->journal_number !== null
            && $this->journal_number !== '';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
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

    public function isBalanced(): bool
    {
        return (string) $this->base_total_debit
                === (string) $this->base_total_credit
            && (string) $this->base_total_debit
                !== '0.000000';
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function canBeDeleted(): bool
    {
        return $this->isDraft()
            && !$this->hasJournalNumber();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'accounting_period_id' => 'integer',
            'document_number_allocation_id' => 'integer',
            'source_id' => 'integer',
            'prepared_by_user_id' => 'integer',
            'approved_by_user_id' => 'integer',
            'posted_by_user_id' => 'integer',
            'reversal_of_id' => 'integer',
            'cancelled_by_user_id' => 'integer',
            'document_date' => 'immutable_date',
            'posting_date' => 'immutable_date',
            'exchange_rate' => 'decimal:8',
            'total_debit' => 'decimal:6',
            'total_credit' => 'decimal:6',
            'base_total_debit' => 'decimal:6',
            'base_total_credit' => 'decimal:6',
            'approved_at' => 'immutable_datetime',
            'posted_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(
            static function (JournalEntry $journalEntry): void {
                $originalStatus = (string) $journalEntry
                    ->getOriginal('status');

                if (
                    in_array(
                        $originalStatus,
                        [
                            'approved',
                            'posted',
                            'reversed',
                            'cancelled',
                        ],
                        true,
                    )
                ) {
                    throw new LogicException(
                        'Approved, posted, reversed, or cancelled journal entries are immutable outside controlled workflow transitions.',
                    );
                }
            },
        );

        static::deleting(
            static function (JournalEntry $journalEntry): void {
                if ($journalEntry->canBeDeleted()) {
                    return;
                }

                throw new LogicException(
                    'Only an unnumbered draft journal entry can be deleted.',
                );
            },
        );
    }
}