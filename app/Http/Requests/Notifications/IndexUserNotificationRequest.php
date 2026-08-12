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
                'required',
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
                'required',
                'string',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],
            'per_page' => [
                'required',
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
            'search' => $this->nullableString(
                'search',
            ),

            'category' => $this->nullableString(
                'category',
            ),

            'severity' => $this->nullableString(
                'severity',
            ),

            'status' => $this->nullableString(
                'status',
            ),

            'sort' => $this->filled('sort')
                ? trim(
                    (string) $this->input('sort'),
                )
                : 'created_at',

            'direction' => $this->filled('direction')
                ? mb_strtolower(
                    trim(
                        (string) $this->input(
                            'direction',
                        ),
                    ),
                )
                : 'desc',

            'per_page' => $this->filled('per_page')
                ? $this->input('per_page')
                : 25,
        ]);
    }

    private function nullableString(
        string $field,
    ): ?string {
        if (!$this->filled($field)) {
            return null;
        }

        $value = trim(
            (string) $this->input($field),
        );

        return $value === ''
            ? null
            : $value;
    }
}