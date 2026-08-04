<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class SupplierOpenItemAllocation extends Model
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
        'payable_open_item_id',
        'credit_open_item_id',
        'allocation_type',
        'posting_key',
        'source_type',
        'source_id',
        'allocation_date',
        'posting_date',
        'currency_code',
        'amount',
        'payable_base_amount',
        'credit_base_amount',
        'exchange_difference_amount',
        'status',
        'created_by_user_id',
        'reversed_by_user_id',
        'reversal_accounting_period_id',
        'reversal_posting_date',
        'reversed_at',
        'reversal_reason',
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
     * @return BelongsTo<AccountingPeriod, $this>
     */
    public function reversalAccountingPeriod(): BelongsTo
    {
        return $this->belongsTo(
            AccountingPeriod::class,
            'reversal_accounting_period_id',
        );
    }

    /**
     * @return BelongsTo<SupplierOpenItem, $this>
     */
    public function payableOpenItem(): BelongsTo
    {
        return $this->belongsTo(
            SupplierOpenItem::class,
            'payable_open_item_id',
        );
    }

    /**
     * @return BelongsTo<SupplierOpenItem, $this>
     */
    public function creditOpenItem(): BelongsTo
    {
        return $this->belongsTo(
            SupplierOpenItem::class,
            'credit_open_item_id',
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
     * @return BelongsTo<User, $this>
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'reversed_by_user_id',
        );
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function isApplied(): bool
    {
        return $this->status === 'applied';
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
            'payable_open_item_id' => 'integer',
            'credit_open_item_id' => 'integer',
            'source_id' => 'integer',
            'created_by_user_id' => 'integer',
            'reversed_by_user_id' => 'integer',
            'reversal_accounting_period_id' => 'integer',
            'allocation_date' => 'immutable_date',
            'posting_date' => 'immutable_date',
            'amount' => 'decimal:6',
            'payable_base_amount' => 'decimal:6',
            'credit_base_amount' => 'decimal:6',
            'exchange_difference_amount' => 'decimal:6',
            'reversal_posting_date' => 'immutable_date',
            'reversed_at' => 'immutable_datetime',
        ];
    }
}