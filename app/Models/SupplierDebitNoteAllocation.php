<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SupplierDebitNoteAllocation extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_debit_note_id',
        'supplier_invoice_id',
        'amount',
        'status',
        'reserved_at',
        'applied_at',
        'reversed_at',
        'cancelled_at',
    ];

    /**
     * @return BelongsTo<SupplierDebitNote, $this>
     */
    public function supplierDebitNote(): BelongsTo
    {
        return $this->belongsTo(
            SupplierDebitNote::class,
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

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isReserved(): bool
    {
        return $this->status === 'reserved';
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

            'supplier_debit_note_id' =>
                'integer',

            'supplier_invoice_id' =>
                'integer',

            'amount' => 'decimal:6',

            'reserved_at' => 'datetime',
            'applied_at' => 'datetime',
            'reversed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}