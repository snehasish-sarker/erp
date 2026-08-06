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

final class CustomerCreditNote extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sales_invoice_id',
        'sales_order_id',
        'branch_id',
        'warehouse_id',
        'customer_id',
        'document_number_allocation_id',
        'customer_ledger_entry_id',
        'customer_open_item_id',
        'customer_open_item_allocation_id',
        'credit_note_number',
        'draft_key',
        'credit_note_date',
        'posting_date',
        'sales_invoice_number',
        'sales_order_number',
        'customer_name',
        'customer_code',
        'customer_type',
        'customer_contact_person',
        'customer_email',
        'customer_phone',
        'customer_tax_number',
        'billing_address',
        'return_address',
        'currency_code',
        'exchange_rate',
        'gross_amount',
        'discount_amount',
        'subtotal',
        'tax_amount',
        'total_amount',
        'quantity_credit_amount',
        'amount_only_credit_amount',
        'returned_quantity',
        'inventory_return_value',
        'reason',
        'notes',
        'status',
        'revision',
        'created_by_user_id',
        'submitted_by_user_id',
        'submitted_at',
        'approved_by_user_id',
        'approved_at',
        'posted_by_user_id',
        'posted_at',
        'accounting_posting_reference',
        'inventory_posting_reference',
        'reversal_posting_date',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
        'accounting_reversal_reference',
        'inventory_reversal_reference',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancellation_reason',
    ];

    /** @return BelongsTo<SalesInvoice, $this> */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Warehouse, $this> */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<DocumentNumberAllocation, $this> */
    public function documentNumberAllocation(): BelongsTo
    {
        return $this->belongsTo(DocumentNumberAllocation::class);
    }

    /** @return BelongsTo<CustomerLedgerEntry, $this> */
    public function customerLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(CustomerLedgerEntry::class);
    }

    /** @return BelongsTo<CustomerOpenItem, $this> */
    public function customerOpenItem(): BelongsTo
    {
        return $this->belongsTo(CustomerOpenItem::class);
    }

    /** @return BelongsTo<CustomerOpenItemAllocation, $this> */
    public function automaticAllocation(): BelongsTo
    {
        return $this->belongsTo(
            CustomerOpenItemAllocation::class,
            'customer_open_item_allocation_id',
        );
    }

    /** @return HasMany<CustomerCreditNoteLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CustomerCreditNoteLine::class)
            ->orderBy('line_number');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
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
        return $this->status === 'approved';
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function hasCreditNoteNumber(): bool
    {
        return $this->credit_note_number !== null
            && $this->credit_note_number !== '';
    }

    public function canBeEdited(): bool
    {
        return $this->isDraft();
    }

    public function canBeDeleted(): bool
    {
        return $this->isDraft()
            && !$this->hasCreditNoteNumber()
            && $this->submitted_at === null
            && $this->approved_at === null
            && $this->posted_at === null;
    }

    public function canBeSubmitted(): bool
    {
        return $this->isDraft();
    }

    public function canReturnToDraft(): bool
    {
        return $this->isSubmitted() || $this->isApproved();
    }

    public function canBeApproved(): bool
    {
        return $this->isSubmitted();
    }

    public function canBeCancelled(): bool
    {
        return in_array(
            $this->status,
            ['draft', 'submitted', 'approved'],
            true,
        );
    }

    public function canBePosted(): bool
    {
        return $this->isApproved();
    }

    public function canBeReversed(): bool
    {
        return $this->isPosted();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'sales_invoice_id' => 'integer',
            'sales_order_id' => 'integer',
            'branch_id' => 'integer',
            'warehouse_id' => 'integer',
            'customer_id' => 'integer',
            'document_number_allocation_id' => 'integer',
            'customer_ledger_entry_id' => 'integer',
            'customer_open_item_id' => 'integer',
            'customer_open_item_allocation_id' => 'integer',
            'credit_note_date' => 'immutable_date',
            'posting_date' => 'immutable_date',
            'exchange_rate' => 'decimal:8',
            'gross_amount' => 'decimal:6',
            'discount_amount' => 'decimal:6',
            'subtotal' => 'decimal:6',
            'tax_amount' => 'decimal:6',
            'total_amount' => 'decimal:6',
            'quantity_credit_amount' => 'decimal:6',
            'amount_only_credit_amount' => 'decimal:6',
            'returned_quantity' => 'decimal:6',
            'inventory_return_value' => 'decimal:6',
            'revision' => 'integer',
            'created_by_user_id' => 'integer',
            'submitted_by_user_id' => 'integer',
            'submitted_at' => 'immutable_datetime',
            'approved_by_user_id' => 'integer',
            'approved_at' => 'immutable_datetime',
            'posted_by_user_id' => 'integer',
            'posted_at' => 'immutable_datetime',
            'reversal_posting_date' => 'immutable_date',
            'reversed_by_user_id' => 'integer',
            'reversed_at' => 'immutable_datetime',
            'cancelled_by_user_id' => 'integer',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}