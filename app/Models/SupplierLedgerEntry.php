<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

final class SupplierLedgerEntry extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'supplier_id',
        'accounting_period_id',
        'reference',
        'posting_key',
        'journal_reference',
        'entry_type',
        'source_type',
        'source_id',
        'source_document_number',
        'document_date',
        'posting_date',
        'due_date',
        'currency_code',
        'exchange_rate',
        'debit_amount',
        'credit_amount',
        'base_debit_amount',
        'base_credit_amount',
        'description',
        'created_by_user_id',
        'reversal_of_id',
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
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(
            Supplier::class,
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id',
        );
    }

    /**
     * @return BelongsTo<SupplierLedgerEntry, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(
            SupplierLedgerEntry::class,
            'reversal_of_id',
        );
    }

    /**
     * @return HasOne<SupplierLedgerEntry, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(
            SupplierLedgerEntry::class,
            'reversal_of_id',
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
     * @return HasOne<SupplierOpenItem, $this>
     */
    public function openItem(): HasOne
    {
        return $this->hasOne(
            SupplierOpenItem::class,
        );
    }

    public function isDebit(): bool
    {
        return (string) $this->debit_amount
            !== '0.000000';
    }

    public function isCredit(): bool
    {
        return (string) $this->credit_amount
            !== '0.000000';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'supplier_id' => 'integer',
            'accounting_period_id' => 'integer',
            'source_id' => 'integer',
            'created_by_user_id' => 'integer',
            'reversal_of_id' => 'integer',
            'document_date' => 'immutable_date',
            'posting_date' => 'immutable_date',
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
        static::updating(
            static function (): never {
                throw new LogicException(
                    'Supplier ledger entries are immutable and cannot be updated.',
                );
            },
        );

        static::deleting(
            static function (): never {
                throw new LogicException(
                    'Supplier ledger entries are immutable and cannot be deleted.',
                );
            },
        );
    }
}