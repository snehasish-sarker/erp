<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class AuditLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'actor_name',
        'actor_email',
        'event',
        'subject_type',
        'subject_id',
        'subject_label',
        'old_values',
        'new_values',
        'metadata',
        'request_id',
        'route_name',
        'http_method',
        'ip_address',
        'url',
        'user_agent',
        'created_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'actor_user_id',
        )->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(
            static function (): never {
                throw new LogicException(
                    'Audit logs are immutable and cannot be updated.',
                );
            },
        );

        static::deleting(
            static function (): never {
                throw new LogicException(
                    'Audit logs are immutable and cannot be deleted.',
                );
            },
        );
    }
}