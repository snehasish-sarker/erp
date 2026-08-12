<?php

declare(strict_types=1);

return [
    'subscription' => [
        /*
         * Default trial period applied to newly provisioned tenants whose
         * initial tenant status is "trial".
         */
        'trial_days' => (int) env('SAAS_TRIAL_DAYS', 14),

        /*
         * Access remains available while a past-due subscription is inside
         * this grace window. When it expires, the tenant is suspended.
         */
        'grace_days' => (int) env('SAAS_GRACE_DAYS', 7),

        /*
         * Maximum number of due subscriptions processed per scheduled run.
         */
        'lifecycle_batch_limit' => (int) env(
            'SAAS_LIFECYCLE_BATCH_LIMIT',
            500,
        ),
    ],

    'billing' => [
        /*
         * Number of days from invoice issue until payment is due.
         */
        'invoice_due_days' => (int) env(
            'SAAS_INVOICE_DUE_DAYS',
            7,
        ),
    ],
];