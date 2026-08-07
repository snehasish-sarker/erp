<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerCreditApplicationLine extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    /** @var list<string> */
    protected $fillable = ['customer_credit_application_id', 'receivable_open_item_id', 'credit_open_item_id', 'customer_open_item_allocation_id', 'line_number', 'receivable_document_number', 'credit_document_number', 'credit_item_type', 'amount', 'receivable_exchange_rate', 'credit_exchange_rate', 'receivable_base_amount', 'credit_base_amount', 'exchange_difference_amount', 'status', 'applied_at', 'reversed_at',];
    public function application(): BelongsTo
    {
        return $this->belongsTo(CustomerCreditApplication::class, 'customer_credit_application_id');
    }

    public function receivableOpenItem(): BelongsTo
    {
        return $this->belongsTo(CustomerOpenItem::class, 'receivable_open_item_id');
    }

    public function creditOpenItem(): BelongsTo
    {
        return $this->belongsTo(CustomerOpenItem::class, 'credit_open_item_id');
    }

    public function openItemAllocation(): BelongsTo
    {
        return $this->belongsTo(CustomerOpenItemAllocation::class, 'customer_open_item_allocation_id');
    }
    /** @return array<string, string> */
    protected function casts(): array
    {
        return['tenant_id' => 'integer', 'customer_credit_application_id' => 'integer', 'receivable_open_item_id' => 'integer', 'credit_open_item_id' => 'integer', 'customer_open_item_allocation_id' => 'integer', 'line_number' => 'integer', 'amount' => 'decimal:6', 'receivable_exchange_rate' => 'decimal:8', 'credit_exchange_rate' => 'decimal:8', 'receivable_base_amount' => 'decimal:6', 'credit_base_amount' => 'decimal:6', 'exchange_difference_amount' => 'decimal:6', 'applied_at' => 'immutable_datetime', 'reversed_at' => 'immutable_datetime',];
    }
}