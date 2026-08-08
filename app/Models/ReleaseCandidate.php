<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReleaseCandidate extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'production_acceptance_run_id',
        'version',
        'status',
        'environment',
        'source',
        'project_fingerprint',
        'git_commit',
        'notes',
        'frozen_by_user_id',
        'frozen_at',
        'superseded_at',
        'verified_at',
        'verification_status',
        'verification_summary',
    ];

    /** @return BelongsTo<ProductionAcceptanceRun, $this> */
    public function acceptanceRun(): BelongsTo
    {
        return $this->belongsTo(ProductionAcceptanceRun::class, 'production_acceptance_run_id');
    }

    /** @return BelongsTo<User, $this> */
    public function frozenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frozen_by_user_id')->withTrashed();
    }

    /** @return HasMany<ReleaseCandidateArtifact, $this> */
    public function artifacts(): HasMany
    {
        return $this->hasMany(ReleaseCandidateArtifact::class)->orderBy('artifact_key');
    }

    public function frozen(): bool
    {
        return $this->status === 'frozen';
    }

    public function drifted(): bool
    {
        return $this->verification_status === 'drifted';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'production_acceptance_run_id' => 'integer',
            'frozen_by_user_id' => 'integer',
            'frozen_at' => 'immutable_datetime',
            'superseded_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'verification_summary' => 'array',
        ];
    }
}
