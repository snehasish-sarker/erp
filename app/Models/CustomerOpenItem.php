<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class CustomerOpenItem extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'customer_id',
        'accounting_period_id',
        'customer_ledger_entry_id',
        'item_type',
        'source_type',
        'source_id',
        'document_number',
        'document_date',
        'posting_date',
        'due_date',
        'currency_code',
        'exchange_rate',
        'original_amount',
        'allocated_amount',
        'outstanding_amount',
        'base_original_amount',
        'base_allocated_amount',
        'base_outstanding_amount',
        'status',
        'created_by_user_id',
        'closed_at',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<AccountingPeriod, $this>
     */
    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    /**
     * @return BelongsTo<CustomerLedgerEntry, $this>
     */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(
            CustomerLedgerEntry::class,
            'customer_ledger_entry_id',
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
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<CustomerOpenItemAllocation, $this>
     */
    public function receivableAllocations(): HasMany
    {
        return $this->hasMany(
            CustomerOpenItemAllocation::class,
            'receivable_open_item_id',
        )->orderBy('id');
    }

    /**
     * @return HasMany<CustomerOpenItemAllocation, $this>
     */
    public function creditAllocations(): HasMany
    {
        return $this->hasMany(
            CustomerOpenItemAllocation::class,
            'credit_open_item_id',
        )->orderBy('id');
    }

    public function isInvoice(): bool
    {
        return $this->item_type === 'invoice';
    }

    public function isReceivable(): bool
    {
        return in_array(
            $this->item_type,
            [
                'invoice',
                'refund',
                'adjustment_debit',
            ],
            true,
        );
    }

    public function isCredit(): bool
    {
        return in_array(
            $this->item_type,
            [
                'credit',
                'receipt',
                'adjustment_credit',
            ],
            true,
        );
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isPartiallySettled(): bool
    {
        return $this->status === 'partially_settled';
    }

    public function isSettled(): bool
    {
        return $this->status === 'settled';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'customer_id' => 'integer',
            'accounting_period_id' => 'integer',
            'customer_ledger_entry_id' => 'integer',
            'source_id' => 'integer',
            'created_by_user_id' => 'integer',
            'document_date' => 'immutable_date',
            'posting_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'exchange_rate' => 'decimal:8',
            'original_amount' => 'decimal:6',
            'allocated_amount' => 'decimal:6',
            'outstanding_amount' => 'decimal:6',
            'base_original_amount' => 'decimal:6',
            'base_allocated_amount' => 'decimal:6',
            'base_outstanding_amount' => 'decimal:6',
            'closed_at' => 'immutable_datetime',
        ];
    }
}