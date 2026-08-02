<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TenantFile;
use App\Models\User;

final class TenantFilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('files.view');
    }

    public function create(User $user): bool
    {
        return $user->can('files.upload');
    }

    public function view(
        User $user,
        TenantFile $tenantFile,
    ): bool {
        return $user->tenant_id
                === $tenantFile->tenant_id
            && $user->can('files.download');
    }

    public function delete(
        User $user,
        TenantFile $tenantFile,
    ): bool {
        return $user->tenant_id
                === $tenantFile->tenant_id
            && $user->can('files.delete');
    }
}