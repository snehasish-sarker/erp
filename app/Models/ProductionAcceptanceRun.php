<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductionAcceptanceRun extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'status',
        'environment',
        'source',
        'total_checks',
        'passed_checks',
        'warning_checks',
        'failed_checks',
        'blocking_failures',
        'summary',
        'project_fingerprint',
        'fingerprint_payload',
        'started_by_user_id',
        'started_at',
        'completed_at',
    ];

    /** @return HasMany<ProductionAcceptanceCheckItem, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(ProductionAcceptanceCheckItem::class)->orderBy('sequence');
    }

    /** @return BelongsTo<User, $this> */
    public function startedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by_user_id')->withTrashed();
    }

    public function passed(): bool
    {
        return $this->status === 'passed';
    }

    public function blocked(): bool
    {
        return $this->status === 'blocked';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'total_checks' => 'integer',
            'passed_checks' => 'integer',
            'warning_checks' => 'integer',
            'failed_checks' => 'integer',
            'blocking_failures' => 'integer',
            'summary' => 'array',
            'fingerprint_payload' => 'array',
            'started_by_user_id' => 'integer',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
