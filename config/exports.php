<?php

declare(strict_types=1);

return [
    'queue' => env(
        'EXPORT_QUEUE',
        'exports',
    ),

    'retention_days' => (int) env(
        'EXPORT_RETENTION_DAYS',
        7,
    ),

    'chunk_size' => (int) env(
        'EXPORT_CHUNK_SIZE',
        500,
    ),

    'print_max_rows' => (int) env(
        'REPORT_PRINT_MAX_ROWS',
        5000,
    ),
];