<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class TenantFile extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uploaded_by_user_id',
        'disk',
        'category',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum_sha256',
        'visibility',
        'status',
        'attachable_type',
        'attachable_id',
        'metadata',
    ];

    /**
     * Prevent internal storage information from being exposed by accidental
     * model serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'disk',
        'path',
        'stored_name',
        'checksum_sha256',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by_user_id',
        )->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isAttached(): bool
    {
        return $this->attachable_type !== null
            && $this->attachable_id !== null;
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->deleted_at === null;
    }

    /**
     * @return list<string>
     */
    public function auditExcludedAttributes(): array
    {
        return [
            'path',
            'stored_name',
            'checksum_sha256',
            'metadata',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uploaded_by_user_id' => 'integer',
            'size_bytes' => 'integer',
            'attachable_id' => 'integer',
            'metadata' => 'array',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(
            Unit::class,
        );
    }
}