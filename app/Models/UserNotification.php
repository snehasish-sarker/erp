<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserNotification extends Model
{
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'recipient_user_id',
        'actor_user_id',
        'notification_key',
        'idempotency_key',
        'category',
        'type',
        'severity',
        'title',
        'message',
        'action_url',
        'action_label',
        'source_type',
        'source_id',
        'actor_name',
        'actor_email',
        'data',
        'read_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'recipient_user_id',
        )->withTrashed();
    }

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

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipient_user_id' => 'integer',
            'actor_user_id' => 'integer',
            'data' => 'array',
            'read_at' => 'immutable_datetime',
        ];
    }
}