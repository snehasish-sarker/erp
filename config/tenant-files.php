<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant File Disk
    |--------------------------------------------------------------------------
    |
    | Change this to "s3" when private tenant files should be stored in an
    | S3-compatible bucket. Paths remain isolated under tenants/{tenant_id}.
    |
    */

    'disk' => env(
        'TENANT_FILESYSTEM_DISK',
        'tenant_private',
    ),

    /*
    |--------------------------------------------------------------------------
    | Root Path Prefix
    |--------------------------------------------------------------------------
    */

    'path_prefix' => 'tenants',
];