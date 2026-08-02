<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class User extends Authenticatable
{
    use Auditable;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    protected string $guard_name = 'web';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'name',
        'email',
        'password',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<TenantFile, $this>
     */
    public function uploadedFiles(): HasMany
    {
        return $this->hasMany(
            TenantFile::class,
            'uploaded_by_user_id',
        );
    }

    /**
     * @return HasMany<ExportRequest, $this>
     */
    public function requestedExports(): HasMany
    {
        return $this->hasMany(
            ExportRequest::class,
            'requested_by_user_id',
        );
    }

    /**
     * @return HasMany<UserNotification, $this>
     */
    public function userNotifications(): HasMany
    {
        return $this->hasMany(
            UserNotification::class,
            'recipient_user_id',
        );
    }

    /**
     * @return HasMany<UserNotification, $this>
     */
    public function actedNotifications(): HasMany
    {
        return $this->hasMany(
            UserNotification::class,
            'actor_user_id',
        );
    }
}