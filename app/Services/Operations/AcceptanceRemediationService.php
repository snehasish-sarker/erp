<?php

declare(strict_types=1);

namespace App\Services\Operations;

final class AcceptanceRemediationService
{
    /**
     * @return list<string>
     */
    public function for(string $key): array
    {
        return match (true) {
            str_contains(
                $key,
                'required_project_files',
            ) => [
                'Restore the missing module files from the authoritative project source; do not make route loading conditional.',
                'Run php artisan optimize:clear and then rerun the production acceptance audit.',
            ],

            str_contains(
                $key,
                'required_named_routes',
            ),
            str_contains(
                $key,
                'duplicate_route_names',
            ) => [
                'Run php artisan route:list and inspect the owning route file and service provider.',
                'Correct route registration or duplicate names, clear route caches, and rerun acceptance.',
            ],

            str_contains(
                $key,
                'route_permissions',
            ) => [
                'Confirm the route permission name is intentional and present in PermissionSeeder.',
                'Run php artisan db:seed --class=PermissionSeeder and php artisan permission:cache-reset.',
            ],

            str_contains(
                $key,
                'document_sequences',
            ) => [
                'Create an active document-number sequence for every missing transactional document type in the tenant.',
                'Do not bypass numbering by hard-coding document numbers.',
            ],

            str_contains(
                $key,
                'branch_integrity',
            ) => [
                'Inspect the reported records and their branch ownership before making any data correction.',
                'Correct the originating workflow or migration first; never bulk-rewrite tenant IDs without an audited repair plan.',
            ],

            str_contains(
                $key,
                'journal_integrity',
            ) => [
                'Identify the source document and journal that created each mismatch.',
                'Do not edit posted journal lines directly; use an explicit reversal or compensating accounting workflow.',
            ],

            str_contains(
                $key,
                'open_items',
            ),
            str_contains(
                $key,
                'allocation_ownership',
            ) => [
                'Inspect the source invoice/payment/receipt and its allocation history under a database transaction.',
                'Repair through the settlement workflow or an explicit audited repair command; do not manually overwrite posted balances.',
            ],

            str_contains(
                $key,
                'inventory.balance_integrity',
            ) => [
                'Trace the affected product and warehouse through the stock ledger before correcting the balance.',
                'Fix the originating stock workflow and rebuild balances only through an audited inventory reconciliation process.',
            ],

            str_contains(
                $key,
                'inventory.purchase_return_reservations',
            ) => [
                'Inspect the affected Goods Receipt line and its related Purchase Return documents.',
                'Confirm accepted quantity, already returned quantity, and active return-reserved quantity before making any correction.',
                'Repair the originating Purchase Return workflow or reservation state; do not manually overwrite posted stock quantities.',
                'Rerun production acceptance after returned quantity plus return-reserved quantity no longer exceeds the accepted quantity.',
            ],

            str_contains(
                $key,
                'pending_migrations',
            ),
            str_contains(
                $key,
                'migrations.pending',
            ),
            str_contains(
                $key,
                'migration_state',
            ) => [
                'Review pending migrations and backups, then run php artisan migrate --force in the controlled deployment window.',
                'Rerun production acceptance after the migration state is stable.',
            ],

            str_contains(
                $key,
                'composer_lock',
            ) => [
                'Restore the committed composer.lock generated from the approved dependency set.',
                'Use composer install for deployment; do not generate a new dependency set with composer update during cutover.',
            ],

            str_contains(
                $key,
                'frontend_lock',
            ) => [
                'Restore the committed frontend dependency lock file used by the project package manager.',
                'Install dependencies from the lock file before rebuilding production assets.',
            ],

            str_contains(
                $key,
                'vite',
            ),
            str_contains(
                $key,
                'build',
            ) => [
                'Run npm run build and confirm public/build/manifest.json exists and is readable.',
            ],

            str_contains(
                $key,
                'queue',
            ) => [
                'Verify the production queue worker/process manager and run php artisan queue:restart after deployment.',
            ],

            str_contains(
                $key,
                'scheduler',
            ) => [
                'Verify the server invokes php artisan schedule:run every minute and confirm the scheduler heartbeat becomes current.',
            ],

            default => [],
        };
    }
}