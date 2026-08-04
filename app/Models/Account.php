<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Account extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_account_id',
        'code',
        'name',
        'account_type',
        'account_subtype',
        'normal_balance',
        'control_type',
        'system_key',
        'level',
        'is_group',
        'allow_manual_posting',
        'status',
        'description',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            Account::class,
            'parent_account_id',
        );
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(
            Account::class,
            'parent_account_id',
        )
            ->orderBy('code')
            ->orderBy('id');
    }

    /**
     * @return HasMany<JournalEntryLine, $this>
     */
    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(
            JournalEntryLine::class,
        )->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by_user_id',
        )->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by_user_id',
        )->withTrashed();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isGroupAccount(): bool
    {
        return (bool) $this->is_group;
    }

    public function isPostingAccount(): bool
    {
        return !$this->isGroupAccount();
    }

    public function isControlAccount(): bool
    {
        return $this->control_type !== null
            && $this->control_type !== '';
    }

    public function isSystemAccount(): bool
    {
        return $this->system_key !== null
            && $this->system_key !== '';
    }

    public function allowsManualPosting(): bool
    {
        return $this->isActive()
            && $this->isPostingAccount()
            && (bool) $this->allow_manual_posting;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'parent_account_id' => 'integer',
            'level' => 'integer',
            'is_group' => 'boolean',
            'allow_manual_posting' => 'boolean',
            'created_by_user_id' => 'integer',
            'updated_by_user_id' => 'integer',
        ];
    }
}