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

final class Supplier extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'supplier_type',
        'contact_person',
        'email',
        'phone',
        'alternate_phone',
        'tax_number',
        'registration_number',
        'address_line_1',
        'address_line_2',
        'city',
        'state',
        'postal_code',
        'country_code',
        'payment_terms_days',
        'notes',
        'status',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompany(): bool
    {
        return $this->supplier_type === 'company';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'payment_terms_days' => 'integer',
        ];
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(
            PurchaseOrder::class,
        );
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

    /**
     * @return HasMany<SupplierInvoice, $this>
     */
    public function supplierInvoices(): HasMany
    {
        return $this->hasMany(
            SupplierInvoice::class,
        )
            ->orderByDesc('invoice_date')
            ->orderByDesc('id');
    }

    /**
     * @return HasMany<SupplierLedgerEntry, $this>
     */
    public function supplierLedgerEntries(): HasMany
    {
        return $this->hasMany(
            SupplierLedgerEntry::class,
        )
            ->orderByDesc('posting_date')
            ->orderByDesc('id');
    }

    /**
     * @return HasMany<SupplierOpenItem, $this>
     */
    public function supplierOpenItems(): HasMany
    {
        return $this->hasMany(
            SupplierOpenItem::class,
        )
            ->orderByDesc('posting_date')
            ->orderByDesc('id');
    }

    /**
     * @return HasMany<SupplierOpenItemAllocation, $this>
     */
    public function supplierOpenItemAllocations(): HasMany
    {
        return $this->hasMany(
            SupplierOpenItemAllocation::class,
        )->orderByDesc('id');
    }
}