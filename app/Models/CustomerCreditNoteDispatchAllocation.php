<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class CustomerCreditNoteDispatchAllocation extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'customer_credit_note_line_id',
        'sales_invoice_dispatch_allocation_id',
        'customer_dispatch_line_id',
        'allocated_quantity',
        'unit_cost',
        'total_cost',
    ];

    /** @return BelongsTo<CustomerCreditNoteLine, $this> */
    public function creditNoteLine(): BelongsTo
    {
        return $this->belongsTo(CustomerCreditNoteLine::class, 'customer_credit_note_line_id');
    }

    /** @return BelongsTo<SalesInvoiceDispatchAllocation, $this> */
    public function salesInvoiceDispatchAllocation(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceDispatchAllocation::class);
    }

    /** @return BelongsTo<CustomerDispatchLine, $this> */
    public function customerDispatchLine(): BelongsTo
    {
        return $this->belongsTo(CustomerDispatchLine::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'customer_credit_note_line_id' => 'integer',
            'sales_invoice_dispatch_allocation_id' => 'integer',
            'customer_dispatch_line_id' => 'integer',
            'allocated_quantity' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:6',
        ];
    }
}