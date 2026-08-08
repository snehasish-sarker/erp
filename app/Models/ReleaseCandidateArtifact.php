<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReleaseCandidateArtifact extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    protected $fillable = [
        'release_candidate_id',
        'artifact_key',
        'label',
        'sha256',
        'metadata',
    ];

    /** @return BelongsTo<ReleaseCandidate, $this> */
    public function releaseCandidate(): BelongsTo
    {
        return $this->belongsTo(ReleaseCandidate::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'release_candidate_id' => 'integer',
            'metadata' => 'array',
        ];
    }
}
