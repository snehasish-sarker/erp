<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use Illuminate\Foundation\Http\FormRequest;

final class MarkAllUserNotificationsReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->status === 'active';
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}