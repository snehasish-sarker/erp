<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class AccountingPeriod extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'fiscal_year_id',
        'period_number',
        'name',
        'code',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by_user_id',
    ];

    /**
     * @return BelongsTo<FiscalYear, $this>
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'closed_by_user_id',
        )->withTrashed();
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fiscal_year_id' => 'integer',
            'period_number' => 'integer',
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'closed_at' => 'immutable_datetime',
            'closed_by_user_id' => 'integer',
        ];
    }

    /**
     * @return HasMany<SupplierLedgerEntry, $this>
     */
    public function supplierLedgerEntries(): HasMany
    {
        return $this->hasMany(
            SupplierLedgerEntry::class,
        )
            ->orderBy('posting_date')
            ->orderBy('id');
    }

    /**
     * @return HasMany<SupplierOpenItem, $this>
     */
    public function supplierOpenItems(): HasMany
    {
        return $this->hasMany(
            SupplierOpenItem::class,
        )
            ->orderBy('posting_date')
            ->orderBy('id');
    }

    /**
     * @return HasMany<SupplierOpenItemAllocation, $this>
     */
    public function supplierOpenItemAllocations(): HasMany
    {
        return $this->hasMany(
            SupplierOpenItemAllocation::class,
        )->orderBy('id');
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(
            JournalEntry::class,
        )
            ->orderBy('posting_date')
            ->orderBy('id');
    }
}