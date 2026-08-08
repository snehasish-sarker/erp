<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerRefundAllocation extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_refund_id',
        'credit_open_item_id',
        'customer_open_item_allocation_id',
        'line_number',
        'credit_document_number',
        'credit_item_type',
        'credit_source_type',
        'credit_source_id',
        'amount',
        'credit_exchange_rate',
        'credit_base_amount',
        'cash_base_amount',
        'exchange_difference_amount',
        'status',
        'applied_at',
        'reversed_at',
    ];

    /**
     * @return BelongsTo<CustomerRefund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(
            CustomerRefund::class,
            'customer_refund_id',
        );
    }

    /**
     * @return BelongsTo<CustomerOpenItem, $this>
     */
    public function creditOpenItem(): BelongsTo
    {
        return $this->belongsTo(
            CustomerOpenItem::class,
            'credit_open_item_id',
        );
    }

    /**
     * @return BelongsTo<CustomerOpenItemAllocation, $this>
     */
    public function openItemAllocation(): BelongsTo
    {
        return $this->belongsTo(
            CustomerOpenItemAllocation::class,
            'customer_open_item_allocation_id',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'customer_refund_id' => 'integer',
            'credit_open_item_id' => 'integer',
            'customer_open_item_allocation_id' => 'integer',
            'line_number' => 'integer',
            'credit_source_id' => 'integer',
            'amount' => 'decimal:6',
            'credit_exchange_rate' => 'decimal:8',
            'credit_base_amount' => 'decimal:6',
            'cash_base_amount' => 'decimal:6',
            'exchange_difference_amount' => 'decimal:6',
            'applied_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
        ];
    }
}