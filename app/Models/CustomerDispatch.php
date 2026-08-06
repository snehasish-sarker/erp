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

final class CustomerDispatch extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'sales_order_id',
        'sales_order_allocation_id',
        'branch_id',
        'warehouse_id',
        'customer_id',
        'document_number_allocation_id',
        'dispatch_number',
        'draft_key',
        'dispatch_date',
        'sales_order_number',
        'customer_name',
        'customer_code',
        'customer_contact_person',
        'customer_phone',
        'shipping_address',
        'delivery_instructions',
        'carrier_name',
        'vehicle_number',
        'tracking_number',
        'notes',
        'status',
        'created_by_user_id',
        'posted_by_user_id',
        'posted_at',
        'reversed_by_user_id',
        'reversed_at',
        'reversal_reason',
    ];

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(
            SalesOrder::class,
        );
    }

    /**
     * @return BelongsTo<SalesOrderAllocation, $this>
     */
    public function salesOrderAllocation(): BelongsTo
    {
        return $this->belongsTo(
            SalesOrderAllocation::class,
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
     * @return HasMany<CustomerDispatchLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(
            CustomerDispatchLine::class,
        )->orderBy('line_number');
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

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'sales_order_id' => 'integer',
            'sales_order_allocation_id' => 'integer',
            'branch_id' => 'integer',
            'warehouse_id' => 'integer',
            'customer_id' => 'integer',
            'document_number_allocation_id' => 'integer',
            'dispatch_date' => 'date',
            'created_by_user_id' => 'integer',
            'posted_by_user_id' => 'integer',
            'posted_at' => 'datetime',
            'reversed_by_user_id' => 'integer',
            'reversed_at' => 'datetime',
        ];
    }
}