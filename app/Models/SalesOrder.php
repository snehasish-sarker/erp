<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SalesOrder extends Model
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
        'customer_id',
        'document_number_allocation_id',
        'document_number',
        'order_date',
        'requested_delivery_date',
        'customer_reference',
        'currency_code',
        'exchange_rate',
        'customer_name',
        'customer_code',
        'customer_type',
        'customer_contact_person',
        'customer_email',
        'customer_phone',
        'customer_tax_number',
        'billing_address',
        'shipping_address',
        'payment_terms_days',
        'credit_limit_snapshot',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'other_charges',
        'total_amount',
        'delivery_instructions',
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
        return $this->belongsTo(
            Tenant::class,
        );
    }

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
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(
            Warehouse::class,
        );
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            Customer::class,
        );
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
     * @return HasMany<SalesOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(
            SalesOrderLine::class,
        )->orderBy('line_number');
    }

    /**
     * @return HasMany<SalesOrderAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(
            SalesOrderAllocation::class,
        )->orderByDesc('revision');
    }

    /**
     * @return HasOne<SalesOrderAllocation, $this>
     */
    public function activeAllocation(): HasOne
    {
        return $this->hasOne(
            SalesOrderAllocation::class,
        )
            ->whereNotNull('active_key')
            ->where('status', 'active');
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
                'partially_allocated',
                'allocated',
                'partially_dispatched',
                'dispatched',
                'partially_invoiced',
                'invoiced',
                'closed',
            ],
            true,
        );
    }

    public function isAllocatable(): bool
    {
        return in_array(
            $this->status,
            [
                'approved',
                'partially_allocated',
                'allocated',
            ],
            true,
        );
    }

    public function isDispatchable(): bool
    {
        return in_array(
            $this->status,
            [
                'approved',
                'partially_allocated',
                'allocated',
                'partially_dispatched',
            ],
            true,
        );
    }

    public function isInvoiceable(): bool
    {
        return in_array(
            $this->status,
            [
                'partially_dispatched',
                'dispatched',
                'partially_invoiced',
            ],
            true,
        );
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

    public function hasActiveAllocation(): bool
    {
        if (
            $this->relationLoaded(
                'activeAllocation',
            )
        ) {
            return $this->activeAllocation
                instanceof SalesOrderAllocation;
        }

        return $this->activeAllocation()
            ->exists();
    }

    public function hasAllocations(): bool
    {
        return $this->lines()
            ->where(
                'allocated_quantity',
                '>',
                0,
            )
            ->exists();
    }

    public function hasDispatches(): bool
    {
        return $this->lines()
            ->where(
                'dispatched_quantity',
                '>',
                0,
            )
            ->exists();
    }

    public function hasInvoices(): bool
    {
        return $this->lines()
            ->where(
                'invoiced_quantity',
                '>',
                0,
            )
            ->exists();
    }

    public function hasReturns(): bool
    {
        return $this->lines()
            ->where(
                'returned_quantity',
                '>',
                0,
            )
            ->exists();
    }

    public function isFullyAllocated(): bool
    {
        $this->loadLinesWhenMissing();

        if ($this->lines->isEmpty()) {
            return false;
        }

        return $this->lines->every(
            static fn (
                SalesOrderLine $line,
            ): bool =>
                $line->isFullyAllocated(),
        );
    }

    public function isPartiallyAllocatedByQuantity(): bool
    {
        $this->loadLinesWhenMissing();

        if ($this->lines->isEmpty()) {
            return false;
        }

        $hasAllocatedQuantity =
            $this->lines->contains(
                static fn (
                    SalesOrderLine $line,
                ): bool =>
                    $line
                        ->hasAllocatedQuantity(),
            );

        return $hasAllocatedQuantity
            && !$this->lines->every(
                static fn (
                    SalesOrderLine $line,
                ): bool =>
                    $line
                        ->isFullyAllocated(),
            );
    }

    public function isFullyDispatched(): bool
    {
        $this->loadLinesWhenMissing();

        if ($this->lines->isEmpty()) {
            return false;
        }

        return $this->lines->every(
            static fn (
                SalesOrderLine $line,
            ): bool =>
                $line->isFullyDispatched(),
        );
    }

    public function isPartiallyDispatchedByQuantity(): bool
    {
        $this->loadLinesWhenMissing();

        if ($this->lines->isEmpty()) {
            return false;
        }

        $hasDispatchedQuantity =
            $this->lines->contains(
                static fn (
                    SalesOrderLine $line,
                ): bool =>
                    $line
                        ->hasDispatchedQuantity(),
            );

        return $hasDispatchedQuantity
            && !$this->lines->every(
                static fn (
                    SalesOrderLine $line,
                ): bool =>
                    $line
                        ->isFullyDispatched(),
            );
    }

    public function isFullyInvoiced(): bool
    {
        $this->loadLinesWhenMissing();

        if ($this->lines->isEmpty()) {
            return false;
        }

        return $this->lines->every(
            static fn (
                SalesOrderLine $line,
            ): bool =>
                $line->isFullyInvoiced(),
        );
    }

    public function isPartiallyInvoicedByQuantity(): bool
    {
        $this->loadLinesWhenMissing();

        if ($this->lines->isEmpty()) {
            return false;
        }

        $hasInvoicedQuantity =
            $this->lines->contains(
                static fn (
                    SalesOrderLine $line,
                ): bool =>
                    $line
                        ->hasInvoicedQuantity(),
            );

        return $hasInvoicedQuantity
            && !$this->lines->every(
                static fn (
                    SalesOrderLine $line,
                ): bool =>
                    $line
                        ->isFullyInvoiced(),
            );
    }

    private function loadLinesWhenMissing(): void
    {
        if (
            !$this->relationLoaded('lines')
        ) {
            $this->load('lines');
        }
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
            'customer_id' => 'integer',

            'document_number_allocation_id' =>
                'integer',

            'order_date' => 'date',

            'requested_delivery_date' =>
                'date',

            'payment_terms_days' =>
                'integer',

            'revision' => 'integer',
            'exchange_rate' => 'decimal:8',

            'credit_limit_snapshot' =>
                'decimal:6',

            'subtotal' => 'decimal:6',

            'discount_amount' =>
                'decimal:6',

            'tax_amount' => 'decimal:6',

            'shipping_amount' =>
                'decimal:6',

            'other_charges' => 'decimal:6',

            'total_amount' =>
                'decimal:6',

            'created_by_user_id' =>
                'integer',

            'submitted_by_user_id' =>
                'integer',

            'approved_by_user_id' =>
                'integer',

            'cancelled_by_user_id' =>
                'integer',

            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}