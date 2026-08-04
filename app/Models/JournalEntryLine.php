<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class JournalEntryLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'journal_entry_id',
        'line_number',
        'account_id',
        'branch_id',
        'supplier_id',
        'customer_id',
        'reference',
        'description',
        'due_date',
        'currency_code',
        'exchange_rate',
        'debit_amount',
        'credit_amount',
        'base_debit_amount',
        'base_credit_amount',
    ];

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(
            JournalEntry::class,
        );
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
        );
    }

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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class,
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

    public function isDebit(): bool
    {
        return (string) $this->base_debit_amount
            !== '0.000000';
    }

    public function isCredit(): bool
    {
        return (string) $this->base_credit_amount
            !== '0.000000';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'journal_entry_id' => 'integer',
            'line_number' => 'integer',
            'account_id' => 'integer',
            'branch_id' => 'integer',
            'supplier_id' => 'integer',
            'customer_id' => 'integer',
            'due_date' => 'immutable_date',
            'exchange_rate' => 'decimal:8',
            'debit_amount' => 'decimal:6',
            'credit_amount' => 'decimal:6',
            'base_debit_amount' => 'decimal:6',
            'base_credit_amount' => 'decimal:6',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            static function (JournalEntryLine $line): void {
                $line->ensureJournalIsEditable();
            },
        );

        static::updating(
            static function (JournalEntryLine $line): void {
                $line->ensureJournalIsEditable();
            },
        );

        static::deleting(
            static function (JournalEntryLine $line): void {
                $line->ensureJournalIsEditable();
            },
        );
    }

    private function ensureJournalIsEditable(): void
    {
        $journalEntry = JournalEntry::query()
            ->whereKey($this->journal_entry_id)
            ->first();

        if (
            $journalEntry instanceof JournalEntry
            && $journalEntry->canBeEdited()
        ) {
            return;
        }

        throw new LogicException(
            'Journal lines can only be changed while the journal entry is in draft status.',
        );
    }
}