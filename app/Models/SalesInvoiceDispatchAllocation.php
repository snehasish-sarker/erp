<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SalesInvoiceDispatchAllocation extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sales_invoice_line_id',
        'customer_dispatch_line_id',
        'allocated_quantity',
        'unit_cost',
        'total_cost',
    ];

    /**
     * @return BelongsTo<SalesInvoiceLine, $this>
     */
    public function salesInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceLine::class);
    }

    /**
     * @return BelongsTo<CustomerDispatchLine, $this>
     */
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
            'sales_invoice_line_id' => 'integer',
            'customer_dispatch_line_id' => 'integer',
            'allocated_quantity' => 'decimal:6',
            'unit_cost' => 'decimal:6',
            'total_cost' => 'decimal:6',
        ];
    }
}