<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\Auditable;

final class Tenant extends Model
{
    use Auditable;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'slug',
        'status',
        'currency_code',
        'timezone',
        'email',
        'phone',
        'address',
    ];

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Warehouse, $this>
     */
    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    /**
     * @return HasMany<DocumentSequence, $this>
     */
    public function documentSequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    /**
     * @return HasMany<FiscalYear, $this>
     */
    public function fiscalYears(): HasMany
    {
        return $this->hasMany(FiscalYear::class);
    }

    /**
     * @return HasMany<AccountingPeriod, $this>
     */
    public function accountingPeriods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class);
    }

    /**
     * @return HasMany<TenantFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(TenantFile::class);
    }

    /**
     * @return HasMany<ExportRequest, $this>
     */
    public function exportRequests(): HasMany
    {
        return $this->hasMany(
            ExportRequest::class,
        );
    }

    /**
     * @return HasMany<UserNotification, $this>
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(
            UserNotification::class,
        );
    }

    /**
     * @return HasMany<ProductCategory, $this>
     */
    public function productCategories(): HasMany
    {
        return $this->hasMany(
            ProductCategory::class,
        );
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
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(
            Account::class,
        )
            ->orderBy('code')
            ->orderBy('id');
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(
            JournalEntry::class,
        )
            ->orderByDesc('posting_date')
            ->orderByDesc('id');
    }
}