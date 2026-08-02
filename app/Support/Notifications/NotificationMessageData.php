<?php

declare(strict_types=1);

namespace App\Support\Notifications;

final readonly class NotificationMessageData
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public string $idempotencyKey,
        public string $category,
        public string $type,
        public string $severity,
        public string $title,
        public string $message,
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
        public ?string $sourceType = null,
        public ?string $sourceId = null,
        public array $data = [],
    ) {
    }
}