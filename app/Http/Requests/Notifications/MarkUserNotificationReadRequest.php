<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Models\UserNotification;
use Illuminate\Foundation\Http\FormRequest;

final class MarkUserNotificationReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $userNotification = $this->route(
            'userNotification',
        );

        return $userNotification
                instanceof UserNotification
            && $this->user()?->can(
                'markRead',
                $userNotification,
            ) === true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}