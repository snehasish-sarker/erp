<?php

declare(strict_types=1);

return [
    'queue' => env(
        'NOTIFICATION_QUEUE',
        'notifications',
    ),

    'retention_days' => (int) env(
        'NOTIFICATION_RETENTION_DAYS',
        90,
    ),

    'dispatch_batch_size' => (int) env(
        'NOTIFICATION_DISPATCH_BATCH_SIZE',
        500,
    ),

    'header_limit' => (int) env(
        'NOTIFICATION_HEADER_LIMIT',
        10,
    ),
];