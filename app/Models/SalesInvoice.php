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

final class SalesInvoice extends Model
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
        'customer_id',
        'sales_order_id',
        'document_number_allocation_id',
        'invoice_number',
        'draft_key',
        'invoice_date',
        'posting_date',
        'due_date',
        'sales_order_number',
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
        'currency_code',
        'exchange_rate',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'other_charges',
        'total_amount',
        'total_cost',
        'notes',
        'status',
        'revision',
        'created_by_user_id',
        'posted_by_user_id',
        'posted_at',
        'accounting_posting_reference',
        'reversal_posting_date',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
        'accounting_reversal_reference',
    ];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
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
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'posted_by_user_id',
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
     * @return HasMany<SalesInvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(
            SalesInvoiceLine::class,
        )->orderBy('line_number');
    }

    /**
     * @return HasOne<CustomerOpenItem, $this>
     */
    public function openItem(): HasOne
    {
        return $this->hasOne(
            CustomerOpenItem::class,
            'source_id',
        )
            ->where(
                'source_type',
                $this->getMorphClass(),
            )
            ->where('item_type', 'invoice');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function canBeDeleted(): bool
    {
        return $this->isDraft();
    }

    public function canBePosted(): bool
    {
        return $this->isDraft();
    }

    public function canBeReversed(): bool
    {
        return $this->isPosted();
    }

    public function hasInvoiceNumber(): bool
    {
        return $this->invoice_number !== null
            && $this->invoice_number !== '';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'customer_id' => 'integer',
            'sales_order_id' => 'integer',
            'document_number_allocation_id' => 'integer',
            'invoice_date' => 'immutable_date',
            'posting_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'payment_terms_days' => 'integer',
            'credit_limit_snapshot' => 'decimal:6',
            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'tax_amount' => 'decimal:6',
            'shipping_amount' => 'decimal:6',
            'other_charges' => 'decimal:6',
            'total_amount' => 'decimal:6',
            'total_cost' => 'decimal:6',
            'revision' => 'integer',
            'created_by_user_id' => 'integer',
            'posted_by_user_id' => 'integer',
            'reversed_by_user_id' => 'integer',
            'posted_at' => 'immutable_datetime',
            'reversal_posting_date' => 'immutable_date',
            'reversed_at' => 'immutable_datetime',
        ];
    }
}