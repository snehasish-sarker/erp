<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ExportRequest;
use App\Models\User;

final class ExportRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('exports.view');
    }

    public function view(
        User $user,
        ExportRequest $exportRequest,
    ): bool {
        return $user->tenant_id
                === $exportRequest->tenant_id
            && $user->can('exports.view');
    }

    public function create(User $user): bool
    {
        return $user->can('exports.create');
    }

    public function download(
        User $user,
        ExportRequest $exportRequest,
    ): bool {
        return $user->tenant_id
                === $exportRequest->tenant_id
            && $user->can('exports.download')
            && $exportRequest->isDownloadable();
    }

    public function cancel(
        User $user,
        ExportRequest $exportRequest,
    ): bool {
        return $user->tenant_id
                === $exportRequest->tenant_id
            && $user->can('exports.cancel')
            && $exportRequest->isQueued();
    }
}