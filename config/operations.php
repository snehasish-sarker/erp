<?php

return [
    'backups' => [
        'enabled' => (bool) env('ERP_BACKUPS_ENABLED', false),
        'disk' => env('ERP_BACKUP_DISK', 'operations_private'),
        'directory' => env('ERP_BACKUP_DIRECTORY', 'backups'),
        'mysqldump_binary' => env('ERP_MYSQLDUMP_BINARY', 'mysqldump'),
        'timeout_seconds' => (int) env('ERP_BACKUP_TIMEOUT_SECONDS', 1800),
        'schedule_time' => env('ERP_BACKUP_SCHEDULE_TIME', '01:00'),
        'retention_days' => (int) env('ERP_BACKUP_RETENTION_DAYS', 14),
        'minimum_keep' => (int) env('ERP_BACKUP_MINIMUM_KEEP', 7),
    ],

    'health' => [
        'scheduler_stale_minutes' => (int) env('ERP_SCHEDULER_STALE_MINUTES', 3),
        'queue_stale_minutes' => (int) env('ERP_QUEUE_STALE_MINUTES', 10),
        'queued_job_warning' => (int) env('ERP_QUEUED_JOB_WARNING', 500),
        'failed_job_warning' => (int) env('ERP_FAILED_JOB_WARNING', 1),
        'storage_warning_percent' => (int) env('ERP_STORAGE_WARNING_PERCENT', 85),
        'long_query_seconds' => (int) env('ERP_LONG_QUERY_SECONDS', 30),
    ],

    'cleanup' => [
        'temporary_file_hours' => (int) env('ERP_TEMPORARY_FILE_HOURS', 24),
        'schedule_time' => env('ERP_CLEANUP_SCHEDULE_TIME', '03:00'),
    ],
];