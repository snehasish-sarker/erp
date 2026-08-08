<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReleaseCandidate;
use App\Models\User;

final class ReleaseCandidatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('release_candidates.view');
    }

    public function view(User $user, ReleaseCandidate $releaseCandidate): bool
    {
        return (int) $user->tenant_id === (int) $releaseCandidate->tenant_id
            && $user->can('release_candidates.view');
    }

    public function create(User $user): bool
    {
        return $user->can('release_candidates.create');
    }

    public function verify(User $user, ReleaseCandidate $releaseCandidate): bool
    {
        return (int) $user->tenant_id === (int) $releaseCandidate->tenant_id
            && $user->can('release_candidates.verify');
    }
}
