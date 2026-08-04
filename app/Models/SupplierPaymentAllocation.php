<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupplierPaymentAllocation extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_payment_id',
        'supplier_open_item_id',
        'supplier_invoice_id',
        'supplier_open_item_allocation_id',
        'line_number',
        'invoice_document_number',
        'invoice_due_date',
        'currency_code',
        'invoice_exchange_rate',
        'payment_exchange_rate',
        'amount',
        'payable_base_amount',
        'credit_base_amount',
        'exchange_difference_amount',
        'status',
        'applied_at',
        'reversed_at',
    ];

    /**
     * @return BelongsTo<SupplierPayment, $this>
     */
    public function supplierPayment(): BelongsTo
    {
        return $this->belongsTo(
            SupplierPayment::class,
        );
    }

    /**
     * @return BelongsTo<SupplierOpenItem, $this>
     */
    public function supplierOpenItem(): BelongsTo
    {
        return $this->belongsTo(
            SupplierOpenItem::class,
        );
    }

    /**
     * @return BelongsTo<SupplierInvoice, $this>
     */
    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(
            SupplierInvoice::class,
        );
    }

    /**
     * @return BelongsTo<SupplierOpenItemAllocation, $this>
     */
    public function supplierOpenItemAllocation(): BelongsTo
    {
        return $this->belongsTo(
            SupplierOpenItemAllocation::class,
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
            'supplier_payment_id' => 'integer',
            'supplier_open_item_id' => 'integer',
            'supplier_invoice_id' => 'integer',
            'supplier_open_item_allocation_id' => 'integer',
            'line_number' => 'integer',
            'invoice_due_date' => 'immutable_date',
            'invoice_exchange_rate' => 'decimal:8',
            'payment_exchange_rate' => 'decimal:8',
            'amount' => 'decimal:6',
            'payable_base_amount' => 'decimal:6',
            'credit_base_amount' => 'decimal:6',
            'exchange_difference_amount' => 'decimal:6',
            'applied_at' => 'immutable_datetime',
            'reversed_at' => 'immutable_datetime',
        ];
    }
}