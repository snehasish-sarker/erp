<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerReceiptAllocation extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_receipt_id',
        'customer_open_item_id',
        'sales_invoice_id',
        'customer_open_item_allocation_id',
        'line_number',
        'invoice_document_number',
        'invoice_due_date',
        'currency_code',
        'invoice_exchange_rate',
        'receipt_exchange_rate',
        'amount',
        'receivable_base_amount',
        'receipt_base_amount',
        'exchange_difference_amount',
        'status',
        'applied_at',
        'reversed_at',
    ];

    /**
     * @return BelongsTo<CustomerReceipt, $this>
     */
    public function customerReceipt(): BelongsTo
    {
        return $this->belongsTo(
            CustomerReceipt::class,
        );
    }

    /**
     * @return BelongsTo<CustomerOpenItem, $this>
     */
    public function customerOpenItem(): BelongsTo
    {
        return $this->belongsTo(
            CustomerOpenItem::class,
        );
    }

    /**
     * @return BelongsTo<SalesInvoice, $this>
     */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(
            SalesInvoice::class,
        );
    }

    /**
     * @return BelongsTo<CustomerOpenItemAllocation, $this>
     */
    public function customerOpenItemAllocation(): BelongsTo
    {
        return $this->belongsTo(
            CustomerOpenItemAllocation::class,
        );
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isApplied(): bool
    {
        return $this->status === 'applied';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'customer_receipt_id' => 'integer',
            'customer_open_item_id' => 'integer',
            'sales_invoice_id' => 'integer',
            'customer_open_item_allocation_id' => 'integer',
            'line_number' => 'integer',
            'invoice_due_date' => 'immutable_date',
            'invoice_exchange_rate' => 'decimal:8',
            'receipt_exchange_rate' => 'decimal:8',
            'amount' => 'decimal:6',
            'receivable_base_amount' => 'decimal:6',
            'receipt_base_amount' => 'decimal:6',
            'exchange_difference_amount' => 'decimal:6',
            'applied_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
        ];
    }
}