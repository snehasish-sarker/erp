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

final class SupplierOpenItem extends Model
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
        'supplier_ledger_entry_id',
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
     * @return BelongsTo<SupplierLedgerEntry, $this>
     */
    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(
            SupplierLedgerEntry::class,
            'supplier_ledger_entry_id',
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
     * @return HasMany<SupplierOpenItemAllocation, $this>
     */
    public function payableAllocations(): HasMany
    {
        return $this->hasMany(
            SupplierOpenItemAllocation::class,
            'payable_open_item_id',
        )->orderBy('id');
    }

    /**
     * @return HasMany<SupplierOpenItemAllocation, $this>
     */
    public function creditAllocations(): HasMany
    {
        return $this->hasMany(
            SupplierOpenItemAllocation::class,
            'credit_open_item_id',
        )->orderBy('id');
    }

    public function isInvoice(): bool
    {
        return $this->item_type === 'invoice';
    }

    public function isCredit(): bool
    {
        return in_array(
            $this->item_type,
            [
                'credit',
                'payment',
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
            'supplier_id' => 'integer',
            'accounting_period_id' => 'integer',
            'supplier_ledger_entry_id' => 'integer',
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