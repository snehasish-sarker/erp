<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SystemBackup extends Model
{
    use Auditable;

    /** @var list<string> */
    protected $fillable = [
        'requested_tenant_id',
        'requested_by_user_id',
        'scope',
        'initiated_by',
        'database_connection',
        'database_name',
        'disk',
        'path',
        'filename',
        'size_bytes',
        'checksum_sha256',
        'status',
        'verification_status',
        'verification_message',
        'error_message',
        'metadata',
        'started_at',
        'completed_at',
        'failed_at',
        'verified_at',
        'pruned_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'path',
        'database_name',
        'error_message',
    ];

    /** @return BelongsTo<Tenant, $this> */
    public function requestedTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'requested_tenant_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id')->withTrashed();
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPruned(): bool
    {
        return $this->status === 'pruned';
    }

    /** @return list<string> */
    public function auditExcludedAttributes(): array
    {
        return [
            'path',
            'database_name',
            'error_message',
            'metadata',
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'requested_tenant_id' => 'integer',
            'requested_by_user_id' => 'integer',
            'size_bytes' => 'integer',
            'metadata' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'pruned_at' => 'immutable_datetime',
        ];
    }
}
