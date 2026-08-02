<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

final class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit_logs.view');
    }

    public function view(
        User $user,
        AuditLog $auditLog,
    ): bool {
        return (int) $user->tenant_id
            === (int) $auditLog->tenant_id
            && $user->can('audit_logs.view');
    }
}