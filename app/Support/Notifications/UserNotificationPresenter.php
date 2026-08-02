<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Models\UserNotification;

final class UserNotificationPresenter
{
    public function __construct(
        private readonly NotificationCategoryRegistry $categoryRegistry,
    ) {
    }

    /**
     * @return array{
     *     id: int,
     *     notification_key: string,
     *     category: string,
     *     category_label: string,
     *     type: string,
     *     severity: string,
     *     title: string,
     *     message: string,
     *     action_url: string|null,
     *     action_label: string|null,
     *     source_type: string|null,
     *     source_id: string|null,
     *     actor: array{
     *         id: int|null,
     *         name: string|null,
     *         email: string|null
     *     },
     *     is_read: bool,
     *     read_at: string|null,
     *     created_at: string|null
     * }
     */
    public function present(
        UserNotification $userNotification,
    ): array {
        return [
            'id' => (int) $userNotification->getKey(),

            'notification_key' =>
                $userNotification->notification_key,

            'category' => $userNotification->category,

            'category_label' =>
                $this->categoryRegistry->label(
                    $userNotification->category,
                ),

            'type' => $userNotification->type,
            'severity' => $userNotification->severity,
            'title' => $userNotification->title,
            'message' => $userNotification->message,

            'action_url' =>
                $userNotification->action_url,

            'action_label' =>
                $userNotification->action_label,

            'source_type' =>
                $userNotification->source_type,

            'source_id' =>
                $userNotification->source_id,

            'actor' => [
                'id' => $userNotification->actor_user_id,
                'name' => $userNotification->actor_name,
                'email' => $userNotification->actor_email,
            ],

            'is_read' => $userNotification->isRead(),

            'read_at' =>
                $userNotification
                    ->read_at
                    ?->toIso8601String(),

            'created_at' =>
                $userNotification
                    ->created_at
                    ?->toIso8601String(),
        ];
    }
}