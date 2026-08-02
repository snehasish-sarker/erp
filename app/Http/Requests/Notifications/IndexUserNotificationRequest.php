<?php

declare(strict_types=1);

namespace App\Http\Requests\Notifications;

use App\Support\Notifications\NotificationCategoryRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexUserNotificationRequest extends FormRequest
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
        $registry = app(
            NotificationCategoryRegistry::class,
        );

        return [
            'search' => [
                'nullable',
                'string',
                'max:160',
            ],
            'category' => [
                'nullable',
                'string',
                Rule::in($registry->keys()),
            ],
            'severity' => [
                'nullable',
                'string',
                Rule::in(
                    $registry->severityKeys(),
                ),
            ],
            'status' => [
                'nullable',
                'string',
                Rule::in([
                    'unread',
                    'read',
                ]),
            ],
            'sort' => [
                'nullable',
                'string',
                Rule::in([
                    'created_at',
                    'title',
                    'category',
                    'severity',
                    'read_at',
                ]),
            ],
            'direction' => [
                'nullable',
                'string',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],
            'per_page' => [
                'nullable',
                'integer',
                Rule::in([
                    10,
                    25,
                    50,
                    100,
                ]),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => trim(
                (string) $this->input('search'),
            ),
            'category' => trim(
                (string) $this->input('category'),
            ),
            'severity' => trim(
                (string) $this->input('severity'),
            ),
            'status' => trim(
                (string) $this->input('status'),
            ),
            'sort' => trim(
                (string) $this->input('sort'),
            ),
            'direction' => trim(
                (string) $this->input('direction'),
            ),
        ]);
    }
}