<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PurchaseOrder extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'warehouse_id',
        'supplier_id',
        'document_number_allocation_id',
        'document_number',
        'order_date',
        'expected_delivery_date',
        'supplier_reference',
        'currency_code',
        'exchange_rate',
        'supplier_name',
        'supplier_code',
        'supplier_contact_person',
        'supplier_email',
        'supplier_phone',
        'supplier_tax_number',
        'supplier_address',
        'delivery_address',
        'payment_terms_days',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'other_charges',
        'total_amount',
        'terms_and_conditions',
        'notes',
        'status',
        'revision',
        'created_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<DocumentNumberAllocation, $this>
     */
    public function documentNumberAllocation(): BelongsTo
    {
        return $this->belongsTo(
            DocumentNumberAllocation::class,
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
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'submitted_by_user_id',
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by_user_id',
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'cancelled_by_user_id',
        );
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(
            PurchaseOrderLine::class,
        )->orderBy('line_number');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isApproved(): bool
    {
        return in_array(
            $this->status,
            [
                'approved',
                'partially_received',
                'received',
                'closed',
            ],
            true,
        );
    }

    public function isReceivable(): bool
    {
        return in_array(
            $this->status,
            [
                'approved',
                'partially_received',
            ],
            true,
        );
    }

    public function isPartiallyReceived(): bool
    {
        return $this->status
            === 'partially_received';
    }

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isFinal(): bool
    {
        return in_array(
            $this->status,
            [
                'closed',
                'cancelled',
            ],
            true,
        );
    }

    public function hasDocumentNumber(): bool
    {
        return $this->document_number !== null
            && $this->document_number !== '';
    }

    public function hasReceipts(): bool
    {
        return $this->lines()
            ->where(
                'received_quantity',
                '>',
                0,
            )
            ->exists();
    }

    public function isFullyReceived(): bool
    {
        if (!$this->relationLoaded('lines')) {
            $this->load('lines');
        }

        if ($this->lines->isEmpty()) {
            return false;
        }

        return $this->lines->every(
            static fn (
                PurchaseOrderLine $line,
            ): bool => $line->isFullyReceived(),
        );
    }

    public function isPartiallyReceivedByQuantity(): bool
    {
        if (!$this->relationLoaded('lines')) {
            $this->load('lines');
        }

        if ($this->lines->isEmpty()) {
            return false;
        }

        $hasReceivedQuantity = $this->lines->contains(
            static fn (
                PurchaseOrderLine $line,
            ): bool => $line->hasReceivedQuantity(),
        );

        return $hasReceivedQuantity
            && !$this->lines->every(
                static fn (
                    PurchaseOrderLine $line,
                ): bool => $line->isFullyReceived(),
            );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'warehouse_id' => 'integer',
            'supplier_id' => 'integer',

            'document_number_allocation_id' =>
                'integer',

            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'payment_terms_days' => 'integer',
            'revision' => 'integer',

            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'tax_amount' => 'decimal:6',
            'shipping_amount' => 'decimal:6',
            'other_charges' => 'decimal:6',
            'total_amount' => 'decimal:6',

            'created_by_user_id' => 'integer',
            'submitted_by_user_id' => 'integer',
            'approved_by_user_id' => 'integer',
            'cancelled_by_user_id' => 'integer',

            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<GoodsReceipt, $this>
     */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(
            GoodsReceipt::class,
        )->orderByDesc('receipt_date')
            ->orderByDesc('id');
    }

    /**
 * @return HasMany<PurchaseReturn, $this>
 */
public function purchaseReturns(): HasMany
{
    return $this->hasMany(
        PurchaseReturn::class,
    )
        ->orderByDesc('return_date')
        ->orderByDesc('id');
}

/**
 * @return HasMany<SupplierDebitNote, $this>
 */
public function supplierDebitNotes(): HasMany
{
    return $this->hasMany(
        SupplierDebitNote::class,
    )
        ->orderByDesc(
            'debit_note_date',
        )
        ->orderByDesc('id');
}
}