<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ExportRequest extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'requested_by_user_id',
        'tenant_file_id',
        'request_key',
        'name',
        'export_type',
        'format',
        'filters',
        'status',
        'progress_percent',
        'rows_exported',
        'error_code',
        'error_message',
        'queued_at',
        'started_at',
        'completed_at',
        'failed_at',
        'cancelled_at',
        'expires_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'requested_by_user_id',
        )->withTrashed();
    }

    /**
     * @return BelongsTo<TenantFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(
            TenantFile::class,
            'tenant_file_id',
        )->withTrashed();
    }

    public function isQueued(): bool
    {
        return $this->status === 'queued';
    }

    public function isProcessing(): bool
    {
        return $this->status === 'processing';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isDownloadable(): bool
    {
        return $this->isCompleted()
            && $this->tenant_file_id !== null
            && (
                $this->expires_at === null
                || $this->expires_at->isFuture()
            );
    }

    /**
     * @return list<string>
     */
    public function auditExcludedAttributes(): array
    {
        return [
            'filters',
            'error_message',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_by_user_id' => 'integer',
            'tenant_file_id' => 'integer',
            'filters' => 'array',
            'progress_percent' => 'integer',
            'rows_exported' => 'integer',
            'queued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }
}