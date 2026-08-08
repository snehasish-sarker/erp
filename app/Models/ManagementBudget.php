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

final class ManagementBudget extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'branch_id',
        'fiscal_year_id',
        'name',
        'currency_code',
        'status',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
        'approved_by_user_id',
        'approved_at',
    ];

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<FiscalYear, $this> */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /** @return HasMany<ManagementBudgetLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ManagementBudgetLine::class)->orderBy('month_number')->orderBy('account_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id')->withTrashed();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'fiscal_year_id' => 'integer',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
            'approved_by_user_id' => 'integer',
            'approved_at' => 'immutable_datetime',
        ];
    }
}
