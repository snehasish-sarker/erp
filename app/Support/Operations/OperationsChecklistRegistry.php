<?php

declare(strict_types=1);

namespace App\Support\Operations;

final class OperationsChecklistRegistry
{
    /** @return list<array{key:string,label:string,owner:string}> */
    public function cutover(): array
    {
        return [
            ['key' => 'maintenance_window', 'label' => 'Confirm the production maintenance window and stakeholder notice.', 'owner' => 'Operations'],
            ['key' => 'backup_verified', 'label' => 'Create and verify a fresh database backup before deployment.', 'owner' => 'Operations'],
            ['key' => 'acceptance_passed', 'label' => 'Run the persisted ERP production acceptance audit and resolve every blocking failure.', 'owner' => 'Engineering/QA'],
            ['key' => 'release_candidate_frozen', 'label' => 'Freeze and verify the exact accepted source/build as the production release candidate.', 'owner' => 'Engineering/QA'],
            ['key' => 'migrations_reviewed', 'label' => 'Review pending migrations and their rollback implications.', 'owner' => 'Engineering'],
            ['key' => 'workers_stopped', 'label' => 'Gracefully stop queue workers before schema-changing deployment steps.', 'owner' => 'Operations'],
            ['key' => 'assets_built', 'label' => 'Build production frontend assets and verify the Vite manifest.', 'owner' => 'Engineering'],
            ['key' => 'cache_rebuilt', 'label' => 'Rebuild application, route, configuration, and view caches.', 'owner' => 'Engineering'],
            ['key' => 'workers_restarted', 'label' => 'Restart queue workers after deployment.', 'owner' => 'Operations'],
            ['key' => 'scheduler_verified', 'label' => 'Verify the Laravel scheduler heartbeat after cutover.', 'owner' => 'Operations'],
        ];
    }

    /** @return list<array{key:string,label:string,owner:string}> */
    public function postDeployment(): array
    {
        return [
            ['key' => 'health_green', 'label' => 'Run ERP health diagnostics and resolve every critical failure.', 'owner' => 'Operations'],
            ['key' => 'acceptance_recheck', 'label' => 'Re-run production acceptance after cutover and confirm zero blocking failures.', 'owner' => 'Engineering/QA'],
            ['key' => 'release_candidate_verified', 'label' => 'Verify the deployed source/build fingerprint against the frozen release candidate.', 'owner' => 'Engineering/QA'],
            ['key' => 'login_smoke', 'label' => 'Confirm authenticated login, tenant context, and branch access.', 'owner' => 'QA'],
            ['key' => 'accounting_smoke', 'label' => 'Post and reverse a controlled accounting smoke transaction.', 'owner' => 'Finance/QA'],
            ['key' => 'inventory_smoke', 'label' => 'Verify inventory summary and stock-ledger reads.', 'owner' => 'Warehouse/QA'],
            ['key' => 'exports_smoke', 'label' => 'Generate one CSV and one XLSX export through the queue.', 'owner' => 'QA'],
            ['key' => 'backup_followup', 'label' => 'Confirm the next scheduled backup and retention cycle.', 'owner' => 'Operations'],
            ['key' => 'logs_reviewed', 'label' => 'Review application, queue, and web-server logs for deployment errors.', 'owner' => 'Operations'],
        ];
    }
}
