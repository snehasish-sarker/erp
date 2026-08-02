<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\UserNotification;

final class UserNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->status === 'active';
    }

    public function view(
        User $user,
        UserNotification $userNotification,
    ): bool {
        return $user->status === 'active'
            && (int) $user->tenant_id
                === (int) $userNotification->tenant_id
            && (int) $user->getKey()
                === (int) $userNotification
                    ->recipient_user_id;
    }

    public function markRead(
        User $user,
        UserNotification $userNotification,
    ): bool {
        return $this->view(
            $user,
            $userNotification,
        );
    }

    public function markAllRead(
        User $user,
    ): bool {
        return $user->status === 'active';
    }
}