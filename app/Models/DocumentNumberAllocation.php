<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

final class DocumentNumberAllocation extends Model
{
    use Auditable;
    use BelongsToTenant;
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'document_sequence_id',
        'branch_id',
        'document_type',
        'reset_key',
        'sequence_number',
        'number',
        'idempotency_key',
        'allocatable_type',
        'allocatable_id',
        'allocated_at',
    ];

    /**
     * @return BelongsTo<DocumentSequence, $this>
     */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(
            DocumentSequence::class,
            'document_sequence_id',
        );
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return list<string>
     */
    public function auditExcludedAttributes(): array
    {
        return [
            'idempotency_key',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'sequence_number' => 'integer',
            'allocatable_id' => 'integer',
            'allocated_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(
            static function (): never {
                throw new LogicException(
                    'Document number allocations are immutable and cannot be updated.',
                );
            },
        );

        static::deleting(
            static function (): never {
                throw new LogicException(
                    'Document number allocations are immutable and cannot be deleted.',
                );
            },
        );
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function allocatable(): MorphTo
    {
        return $this->morphTo();
    }
}