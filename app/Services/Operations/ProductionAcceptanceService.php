<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\ProductionAcceptanceCheckItem;
use App\Models\ProductionAcceptanceRun;
use App\Models\User;
use App\Support\DocumentNumbers\DocumentTypeRegistry;
use App\Support\Operations\ProductionAcceptanceRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class ProductionAcceptanceService
{
    public function __construct(
        private readonly DeploymentPreflightService $preflightService,
        private readonly ProductionAcceptanceRegistry $registry,
        private readonly DocumentTypeRegistry $documentTypeRegistry,
        private readonly ReleaseFingerprintService $fingerprintService,
        private readonly AcceptanceRemediationService $remediationService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(?User $actor, string $source = 'web'): array
    {
        if (
            !Schema::hasTable('production_acceptance_runs')
            || !Schema::hasTable('production_acceptance_check_items')
        ) {
            return $this->infrastructureFailureReport(
                $actor,
                $source,
            );
        }

        $run = ProductionAcceptanceRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'status' => 'running',
            'environment' => app()->environment(),
            'source' => $source,
            'started_by_user_id' => $actor?->getKey(),
            'started_at' => now(),
        ]);

        $checks = [];
        $fingerprint = null;

        try {
            $this->appendPreflightChecks($checks);
            $this->appendProjectFileChecks($checks);
            $this->appendRouteChecks($checks);
            $this->appendPermissionChecks($checks);
            $this->appendDocumentNumberChecks($checks);
            $this->appendTenantBranchChecks($checks);
            $this->appendJournalIntegrityChecks($checks);
            $this->appendOpenItemChecks($checks);
            $this->appendInventoryChecks($checks);
            $this->appendPurchaseReturnReservationChecks(
                $checks,
            );
            $this->appendAcceptanceInfrastructureChecks(
                $checks,
            );

            $fingerprint = $this->fingerprintService->capture();
            $payload = $fingerprint['payload'];

            $checks[] = $this->check(
                category: 'acceptance',
                key: 'acceptance.composer_lock',
                label: 'Composer dependency lock',
                status: $payload['composer_lock_sha256'] !== null
                    ? 'passed'
                    : 'failed',
                blocking: true,
                message: $payload['composer_lock_sha256'] !== null
                    ? 'composer.lock is present and included in the accepted release fingerprint.'
                    : 'composer.lock is missing; a reproducible backend release cannot be frozen.',
            );

            $checks[] = $this->check(
                category: 'acceptance',
                key: 'acceptance.frontend_lock',
                label: 'Frontend dependency lock',
                status: $payload['frontend_lock_sha256'] !== null
                    ? 'passed'
                    : 'failed',
                blocking: true,
                message: $payload['frontend_lock_sha256'] !== null
                    ? ((string) $payload['frontend_lock_file'])
                        .' is present and included in the accepted release fingerprint.'
                    : 'No supported frontend dependency lock file was found.',
            );

            $checks[] = $this->check(
                category: 'acceptance',
                key: 'acceptance.vite_manifest',
                label: 'Production frontend build manifest',
                status: $payload['vite_manifest_sha256'] !== null
                    ? 'passed'
                    : 'failed',
                blocking: true,
                message: $payload['vite_manifest_sha256'] !== null
                    ? 'The production Vite manifest is present and fingerprinted.'
                    : 'public/build/manifest.json is missing. Run the production frontend build before acceptance.',
            );

            $checks[] = $this->check(
                category: 'acceptance',
                key: 'acceptance.migration_state',
                label: 'Applied migration fingerprint',
                status: $payload['migration_state_sha256'] !== null
                    ? 'passed'
                    : 'failed',
                blocking: true,
                message: $payload['migration_state_sha256'] !== null
                    ? 'The applied migration state is included in the accepted release fingerprint.'
                    : 'The migrations table is unavailable, so the deployed schema state cannot be fingerprinted.',
            );

            $checks[] = $this->check(
                category: 'acceptance',
                key: 'acceptance.release_fingerprint',
                label: 'Release fingerprint capture',
                status: 'passed',
                blocking: true,
                message: 'The accepted source, routes, migrations, permissions, dependency locks, and production build fingerprint were captured.',
            );
        } catch (Throwable $exception) {
            report($exception);

            $checks[] = $this->check(
                category: 'acceptance',
                key: 'acceptance.internal_error',
                label: 'Acceptance audit execution',
                status: 'failed',
                blocking: true,
                message: 'The acceptance audit encountered an internal error. Review the application log before production cutover.',
                context: [
                    'exception' => $exception::class,
                ],
            );
        }

        $summary = $this->summary($checks);

        $this->persistChecks(
            $run,
            $checks,
        );

        $run->update([
            'status' => $summary['blocking_failures'] > 0
                ? 'blocked'
                : 'passed',
            'total_checks' => $summary['checks'],
            'passed_checks' => $summary['passed'],
            'warning_checks' => $summary['warnings'],
            'failed_checks' => $summary['failed'],
            'blocking_failures' => $summary['blocking_failures'],
            'summary' => $summary,
            'project_fingerprint' => $fingerprint['fingerprint'] ?? null,
            'fingerprint_payload' => $fingerprint['payload'] ?? null,
            'completed_at' => now(),
        ]);

        return $this->present(
            $run->fresh([
                'checks',
                'startedBy:id,name',
            ]),
        );
    }

    /** @return array<string, mixed> */
    public function present(ProductionAcceptanceRun $run): array
    {
        $run->loadMissing([
            'checks',
            'startedBy:id,name',
        ]);

        return [
            'id' => (int) $run->getKey(),
            'uuid' => $run->uuid,
            'status' => $run->status,
            'environment' => $run->environment,
            'source' => $run->source,
            'project_fingerprint' => $run->project_fingerprint,
            'summary' => [
                'checks' => (int) $run->total_checks,
                'passed' => (int) $run->passed_checks,
                'warnings' => (int) $run->warning_checks,
                'failed' => (int) $run->failed_checks,
                'blocking_failures' => (int) $run->blocking_failures,
                'ready' => $run->status === 'passed',
            ],
            'started_by' => $run->startedBy === null
                ? null
                : [
                    'id' => (int) $run->startedBy->getKey(),
                    'name' => $run->startedBy->name,
                ],
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'checks' => $run->checks
                ->map(
                    static fn (
                        ProductionAcceptanceCheckItem $item,
                    ): array => [
                        'id' => (int) $item->getKey(),
                        'sequence' => (int) $item->sequence,
                        'category' => $item->category,
                        'key' => $item->check_key,
                        'label' => $item->label,
                        'status' => $item->status,
                        'blocking' => (bool) $item->blocking,
                        'message' => $item->message,
                        'context' => $item->context,
                        'remediation' => $item->status === 'passed'
                            ? []
                            : $this->remediationService->for(
                                $item->check_key,
                            ),
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $checks
     */
    private function appendPreflightChecks(array &$checks): void
    {
        $report = $this->preflightService->run();

        foreach (
            ($report['production_readiness']['checks'] ?? [])
            as $item
        ) {
            $passed = ($item['status'] ?? 'failed') === 'passed';
            $blocking = (bool) ($item['blocking'] ?? false);

            $checks[] = $this->check(
                'readiness',
                'readiness.'.(string) (
                    $item['key'] ?? Str::uuid()
                ),
                (string) (
                    $item['label']
                    ?? 'Production readiness'
                ),
                $passed
                    ? 'passed'
                    : ($blocking ? 'failed' : 'warning'),
                $blocking,
                (string) ($item['message'] ?? ''),
            );
        }

        foreach (
            ($report['operations_health']['checks'] ?? [])
            as $item
        ) {
            $passed = ($item['status'] ?? 'failed') === 'passed';
            $critical = (bool) ($item['critical'] ?? false);

            $checks[] = $this->check(
                'operations',
                'operations.'.(string) (
                    $item['key'] ?? Str::uuid()
                ),
                (string) (
                    $item['label']
                    ?? 'Operations health'
                ),
                $passed
                    ? 'passed'
                    : ($critical ? 'failed' : 'warning'),
                $critical,
                (string) ($item['message'] ?? ''),
            );
        }

        foreach (
            ($report['security']['checks'] ?? [])
            as $item
        ) {
            $passed = (bool) ($item['passed'] ?? false);
            $critical = (bool) ($item['critical'] ?? false);

            $checks[] = $this->check(
                'security',
                'security.'.(string) (
                    $item['key'] ?? Str::uuid()
                ),
                (string) (
                    $item['label']
                    ?? 'Security hardening'
                ),
                $passed
                    ? 'passed'
                    : ($critical ? 'failed' : 'warning'),
                $critical,
                (string) ($item['message'] ?? ''),
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $checks
     */
    private function appendProjectFileChecks(array &$checks): void
    {
        $missing = array_values(
            array_filter(
                $this->registry->requiredProjectFiles(),
                static fn (string $path): bool =>
                    !is_file(base_path($path)),
            ),
        );

        $checks[] = $this->check(
            'integration',
            'integration.required_project_files',
            'Required ERP module files',
            $missing === []
                ? 'passed'
                : 'failed',
            true,
            $missing === []
                ? 'All critical ERP route, model, and Vue entry files are present.'
                : 'Missing critical project files: '
                    .implode(', ', $missing),
            [
                'missing' => $missing,
            ],
        );
    }

    /**
     * @param list<array<string, mixed>> $checks
     */
    private function appendRouteChecks(array &$checks): void
    {
        $missingNames = array_values(
            array_filter(
                $this->registry->requiredRouteNames(),
                static fn (string $name): bool =>
                    !Route::has($name),
            ),
        );

        $checks[] = $this->check(
            'integration',
            'integration.required_named_routes',
            'Critical named-route coverage',
            $missingNames === []
                ? 'passed'
                : 'failed',
            true,
            $missingNames === []
                ? 'All critical ERP named routes are registered.'
                : 'Missing critical named routes: '
                    .implode(', ', $missingNames),
            [
                'missing' => $missingNames,
            ],
        );

        $nameCounts = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || $name === '') {
                continue;
            }

            $nameCounts[$name] =
                ($nameCounts[$name] ?? 0) + 1;
        }

        $duplicates = array_keys(
            array_filter(
                $nameCounts,
                static fn (int $count): bool =>
                    $count > 1,
            ),
        );

        $checks[] = $this->check(
            'integration',
            'integration.duplicate_route_names',
            'Named-route uniqueness',
            $duplicates === []
                ? 'passed'
                : 'failed',
            true,
            $duplicates === []
                ? 'No duplicate named routes were detected.'
                : 'Duplicate route names: '
                    .implode(', ', $duplicates),
            [
                'duplicates' => $duplicates,
            ],
        );
    }

    /**
     * @param list<array<string, mixed>> $checks
     */
    private function appendPermissionChecks(array &$checks): void
    {
        if (!Schema::hasTable('permissions')) {
            $checks[] = $this->check(
                'security',
                'security.route_permissions',
                'Route permission coverage',
                'failed',
                true,
                'The permissions table does not exist.',
            );

            return;
        }

        $required = [];

        foreach (Route::getRoutes() as $route) {
            if (!$route instanceof IlluminateRoute) {
                continue;
            }

            foreach ($route->gatherMiddleware() as $middleware) {
                if (
                    !is_string($middleware)
                    || !str_starts_with(
                        $middleware,
                        'permission:',
                    )
                ) {
                    continue;
                }

                $definition = substr(
                    $middleware,
                    strlen('permission:'),
                );

                $permissionPart = explode(
                    ',',
                    $definition,
                    2,
                )[0];

                foreach (
                    explode('|', $permissionPart)
                    as $permission
                ) {
                    $permission = trim($permission);

                    if ($permission !== '') {
                        $required[$permission] = true;
                    }
                }
            }
        }

        $existing = DB::table('permissions')
            ->where(
                'guard_name',
                'web',
            )
            ->pluck('name')
            ->map(
                static fn (mixed $name): string =>
                    (string) $name,
            )
            ->all();

        $missing = array_values(
            array_diff(
                array_keys($required),
                $existing,
            ),
        );

        sort($missing);

        $checks[] = $this->check(
            'security',
            'security.route_permissions',
            'Route permission coverage',
            $missing === []
                ? 'passed'
                : 'failed',
            true,
            $missing === []
                ? 'Every permission-protected route references a seeded web-guard permission.'
                : 'Route permissions missing from the database: '
                    .implode(', ', $missing),
            [
                'missing' => $missing,
            ],
        );
    }

    /**
     * @param list<array<string, mixed>> $checks
     */
    private function appendDocumentNumberChecks(
        array &$checks,
    ): void {
        if (!Schema::hasTable('document_sequences')) {
            $checks[] = $this->check(
                'integration',
                'integration.document_sequences',
                'Document-number sequence coverage',
                'failed',
                true,
                'The document_sequences table is unavailable.',
            );

            return;
        }

        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            $checks[] = $this->check(
                'integration',
                'integration.document_sequences',
                'Document-number sequence coverage',
                'failed',
                true,
                'Tenant context is unavailable.',
            );

            return;
        }

        $missing = [];

        foreach (
            $this->documentTypeRegistry->keys()
            as $documentType
        ) {
            $exists = DB::table('document_sequences')
                ->where(
                    'tenant_id',
                    $tenantId,
                )
                ->where(
                    'document_type',
                    $documentType,
                )
                ->where(
                    'status',
                    'active',
                )
                ->exists();

            if (!$exists) {
                $missing[] = $documentType;
            }
        }

        $checks[] = $this->check(
            'integration',
            'integration.document_sequences',
            'Document-number sequence coverage',
            $missing === []
                ? 'passed'
                : 'failed',
            true,
            $missing === []
                ? 'Every registered transactional document type has an active numbering sequence.'
                : 'Missing active document-number sequences: '
                    .implode(', ', $missing),
            [
                'missing' => $missing,
            ],
        );
    }

    /**
     * @param list<array<string, mixed>> $checks
     */
    private function appendTenantBranchChecks(
        array &$checks,
    ): void {
        if (!Schema::hasTable('branches')) {
            $checks[] = $this->check(
                'tenancy',
                'tenancy.branch_integrity',
                'Tenant/branch ownership integrity',
                'failed',
                true,
                'The branches table does not exist.',
            );

            return;
        }

        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            $checks[] = $this->check(
                'tenancy',
                'tenancy.branch_integrity',
                'Tenant/branch ownership integrity',
                'failed',
                true,
                'Tenant context is unavailable for the branch ownership check.',
            );

            return;
        }

        $violations = [];

        foreach (
            $this->registry->branchOwnedTables()
            as $table
        ) {
            if (
                !Schema::hasTable($table)
                || !Schema::hasColumn(
                    $table,
                    'tenant_id',
                )
                || !Schema::hasColumn(
                    $table,
                    'branch_id',
                )
            ) {
                continue;
            }

            $count = DB::table($table)
                ->join(
                    'branches',
                    $table.'.branch_id',
                    '=',
                    'branches.id',
                )
                ->where(
                    $table.'.tenant_id',
                    $tenantId,
                )
                ->whereNotNull(
                    $table.'.branch_id',
                )
                ->whereColumn(
                    $table.'.tenant_id',
                    '<>',
                    'branches.tenant_id',
                )
                ->count();

            if ($count > 0) {
                $violations[$table] = $count;
            }
        }

        if (
            Schema::hasTable('treasury_transfers')
            && Schema::hasColumn(
                'treasury_transfers',
                'tenant_id',
            )
            && Schema::hasColumn(
                'treasury_transfers',
                'source_branch_id',
            )
            && Schema::hasColumn(
                'treasury_transfers',
                'destination_branch_id',
            )
        ) {
            $treasuryMismatch = DB::table(
                'treasury_transfers',
            )
                ->join(
                    'branches as source_branch',
                    'treasury_transfers.source_branch_id',
                    '=',
                    'source_branch.id',
                )
                ->join(
                    'branches as destination_branch',
                    'treasury_transfers.destination_branch_id',
                    '=',
                    'destination_branch.id',
                )
                ->where(
                    'treasury_transfers.tenant_id',
                    $tenantId,
                )
                ->where(
                    function ($query): void {
                        $query
                            ->whereColumn(
                                'treasury_transfers.tenant_id',
                                '<>',
                                'source_branch.tenant_id',
                            )
                            ->orWhereColumn(
                                'treasury_transfers.tenant_id',
                                '<>',
                                'destination_branch.tenant_id',
                            );
                    },
                )
                ->count();

            if ($treasuryMismatch > 0) {
                $violations['treasury_transfers'] =
                    $treasuryMismatch;
            }
        }

        $passed = $violations === [];

        $checks[] = $this->check(
            'tenancy',
            'tenancy.branch_integrity',
            'Tenant/branch ownership integrity',
            $passed
                ? 'passed'
                : 'failed',
            true,
            $passed
                ? 'No tenant/branch ownership mismatches were found for the current tenant.'
                : 'Tenant/branch mismatches: '
                    .$this->formatCounts($violations),
            [
                'tenant_id' => $tenantId,
                'violations' => $violations,
            ],
        );
    }

    /**
 * @param list<array<string, mixed>> $checks
 */
private function appendJournalIntegrityChecks(
    array &$checks,
): void {
    if (
        !Schema::hasTable('journal_entries')
        || !Schema::hasTable('journal_entry_lines')
    ) {
        $checks[] = $this->check(
            'accounting',
            'accounting.journal_integrity',
            'General Ledger relational integrity',
            'failed',
            true,
            'Journal entry tables are unavailable.',
        );

        return;
    }

    $tenantId = $this->tenantContext->id();

    if ($tenantId === null) {
        $checks[] = $this->check(
            'accounting',
            'accounting.journal_integrity',
            'General Ledger relational integrity',
            'failed',
            true,
            'Tenant context is unavailable for the General Ledger integrity check.',
        );

        return;
    }

    $requiredJournalColumns = [
        'tenant_id',
        'branch_id',
        'status',
        'base_total_debit',
        'base_total_credit',
    ];

    $requiredLineColumns = [
        'journal_entry_id',
        'tenant_id',
        'branch_id',
        'base_debit_amount',
        'base_credit_amount',
    ];

    $missingJournalColumns = array_values(
        array_filter(
            $requiredJournalColumns,
            static fn (string $column): bool =>
                !Schema::hasColumn(
                    'journal_entries',
                    $column,
                ),
        ),
    );

    $missingLineColumns = array_values(
        array_filter(
            $requiredLineColumns,
            static fn (string $column): bool =>
                !Schema::hasColumn(
                    'journal_entry_lines',
                    $column,
                ),
        ),
    );

    if (
        $missingJournalColumns !== []
        || $missingLineColumns !== []
    ) {
        $parts = [];

        if ($missingJournalColumns !== []) {
            $parts[] = 'journal_entries: '
                .implode(
                    ', ',
                    $missingJournalColumns,
                );
        }

        if ($missingLineColumns !== []) {
            $parts[] = 'journal_entry_lines: '
                .implode(
                    ', ',
                    $missingLineColumns,
                );
        }

        $checks[] = $this->check(
            'accounting',
            'accounting.journal_integrity',
            'General Ledger relational integrity',
            'failed',
            true,
            'Required General Ledger columns are unavailable: '
                .implode('; ', $parts)
                .'.',
            [
                'tenant_id' => $tenantId,
                'missing_journal_columns' =>
                    $missingJournalColumns,
                'missing_line_columns' =>
                    $missingLineColumns,
            ],
        );

        return;
    }

    $headerImbalance = DB::table(
        'journal_entries',
    )
        ->where(
            'tenant_id',
            $tenantId,
        )
        ->where(
            'status',
            'posted',
        )
        ->whereRaw(
            'ABS(base_total_debit - base_total_credit) > 0.000001',
        )
        ->count();

    $ownershipMismatch = DB::table(
        'journal_entry_lines',
    )
        ->join(
            'journal_entries',
            'journal_entry_lines.journal_entry_id',
            '=',
            'journal_entries.id',
        )
        ->where(
            'journal_entries.tenant_id',
            $tenantId,
        )
        ->where(
            function ($query): void {
                $query
                    ->whereColumn(
                        'journal_entry_lines.tenant_id',
                        '<>',
                        'journal_entries.tenant_id',
                    )
                    ->orWhereColumn(
                        'journal_entry_lines.branch_id',
                        '<>',
                        'journal_entries.branch_id',
                    );
            },
        )
        ->count();

    $lineSummaryQuery = DB::table(
        'journal_entries',
    )
        ->leftJoin(
            'journal_entry_lines',
            'journal_entries.id',
            '=',
            'journal_entry_lines.journal_entry_id',
        )
        ->where(
            'journal_entries.tenant_id',
            $tenantId,
        )
        ->where(
            'journal_entries.status',
            'posted',
        )
        ->groupBy(
            'journal_entries.id',
        )
        ->selectRaw(
            'journal_entries.id, '
            .'COUNT(journal_entry_lines.id) AS line_count, '
            .'ABS('
            .'COALESCE(SUM(journal_entry_lines.base_debit_amount), 0) '
            .'- '
            .'COALESCE(SUM(journal_entry_lines.base_credit_amount), 0)'
            .') AS line_difference, '
            .'ABS('
            .'COALESCE(SUM(journal_entry_lines.base_debit_amount), 0) '
            .'- '
            .'MAX(journal_entries.base_total_debit)'
            .') AS debit_header_difference, '
            .'ABS('
            .'COALESCE(SUM(journal_entry_lines.base_credit_amount), 0) '
            .'- '
            .'MAX(journal_entries.base_total_credit)'
            .') AS credit_header_difference',
        );

    $invalidLineStructure = DB::query()
        ->fromSub(
            clone $lineSummaryQuery,
            'journal_line_structure_check',
        )
        ->where(
            function ($query): void {
                $query
                    ->where(
                        'line_count',
                        '<',
                        2,
                    )
                    ->orWhere(
                        'line_difference',
                        '>',
                        0.000001,
                    );
            },
        )
        ->count();

    $headerLineMismatch = DB::query()
        ->fromSub(
            clone $lineSummaryQuery,
            'journal_header_line_check',
        )
        ->where(
            function ($query): void {
                $query
                    ->where(
                        'debit_header_difference',
                        '>',
                        0.000001,
                    )
                    ->orWhere(
                        'credit_header_difference',
                        '>',
                        0.000001,
                    );
            },
        )
        ->count();

    $passed =
        $headerImbalance === 0
        && $invalidLineStructure === 0
        && $headerLineMismatch === 0
        && $ownershipMismatch === 0;

    $checks[] = $this->check(
        'accounting',
        'accounting.journal_integrity',
        'General Ledger relational integrity',
        $passed
            ? 'passed'
            : 'failed',
        true,
        $passed
            ? 'Posted journal headers and lines reconcile exactly and retain consistent tenant/branch ownership for the current tenant.'
            : sprintf(
                'Header imbalance: %d; invalid line structure/balance: %d; header-to-line mismatch: %d; ownership mismatch: %d.',
                $headerImbalance,
                $invalidLineStructure,
                $headerLineMismatch,
                $ownershipMismatch,
            ),
        [
            'tenant_id' =>
                $tenantId,

            'header_imbalance' =>
                $headerImbalance,

            'invalid_line_structure' =>
                $invalidLineStructure,

            'header_line_mismatch' =>
                $headerLineMismatch,

            'ownership_mismatch' =>
                $ownershipMismatch,
        ],
    );
}

    /**
     * @param list<array<string, mixed>> $checks
     */
    private function appendOpenItemChecks(
        array &$checks,
    ): void {
        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            $checks[] = $this->check(
                'accounting',
                'accounting.open_items.tenant_context',
                'Accounting open-item tenant context',
                'failed',
                true,
                'Tenant context is unavailable for AR/AP open-item integrity checks.',
            );

            return;
        }

        $this->appendSingleOpenItemCheck(
            checks: $checks,
            table: 'customer_open_items',
            label: 'Accounts Receivable open-item arithmetic',
            tenantId: $tenantId,
        );

        $this->appendSingleOpenItemCheck(
            checks: $checks,
            table: 'supplier_open_items',
            label: 'Accounts Payable open-item arithmetic',
            tenantId: $tenantId,
        );

        if (
            Schema::hasTable(
                'customer_open_item_allocations',
            )
            && Schema::hasTable(
                'customer_open_items',
            )
        ) {
            $mismatch = DB::table(
                'customer_open_item_allocations as a',
            )
                ->join(
                    'customer_open_items as r',
                    'a.receivable_open_item_id',
                    '=',
                    'r.id',
                )
                ->join(
                    'customer_open_items as c',
                    'a.credit_open_item_id',
                    '=',
                    'c.id',
                )
                ->where(
                    'a.tenant_id',
                    $tenantId,
                )
                ->where(
                    function ($query): void {
                        $query
                            ->whereColumn(
                                'a.tenant_id',
                                '<>',
                                'r.tenant_id',
                            )
                            ->orWhereColumn(
                                'a.tenant_id',
                                '<>',
                                'c.tenant_id',
                            )
                            ->orWhereColumn(
                                'a.branch_id',
                                '<>',
                                'r.branch_id',
                            )
                            ->orWhereColumn(
                                'a.branch_id',
                                '<>',
                                'c.branch_id',
                            )
                            ->orWhereColumn(
                                'a.customer_id',
                                '<>',
                                'r.customer_id',
                            )
                            ->orWhereColumn(
                                'a.customer_id',
                                '<>',
                                'c.customer_id',
                            );
                    },
                )
                ->count();

            $checks[] = $this->check(
                'accounting',
                'accounting.customer_allocation_ownership',
                'Customer allocation ownership',
                $mismatch === 0
                    ? 'passed'
                    : 'failed',
                true,
                $mismatch === 0
                    ? 'Customer settlement allocations remain within one tenant, branch, and customer for the current tenant.'
                    : "{$mismatch} customer allocation ownership mismatch(es) were found.",
                [
                    'tenant_id' => $tenantId,
                    'mismatches' => $mismatch,
                ],
            );
        }

        if (
            Schema::hasTable(
                'supplier_open_item_allocations',
            )
            && Schema::hasTable(
                'supplier_open_items',
            )
        ) {
            $mismatch = DB::table(
                'supplier_open_item_allocations as a',
            )
                ->join(
                    'supplier_open_items as p',
                    'a.payable_open_item_id',
                    '=',
                    'p.id',
                )
                ->join(
                    'supplier_open_items as c',
                    'a.credit_open_item_id',
                    '=',
                    'c.id',
                )
                ->where(
                    'a.tenant_id',
                    $tenantId,
                )
                ->where(
                    function ($query): void {
                        $query
                            ->whereColumn(
                                'a.tenant_id',
                                '<>',
                                'p.tenant_id',
                            )
                            ->orWhereColumn(
                                'a.tenant_id',
                                '<>',
                                'c.tenant_id',
                            )
                            ->orWhereColumn(
                                'a.branch_id',
                                '<>',
                                'p.branch_id',
                            )
                            ->orWhereColumn(
                                'a.branch_id',
                                '<>',
                                'c.branch_id',
                            )
                            ->orWhereColumn(
                                'a.supplier_id',
                                '<>',
                                'p.supplier_id',
                            )
                            ->orWhereColumn(
                                'a.supplier_id',
                                '<>',
                                'c.supplier_id',
                            );
                    },
                )
                ->count();

            $checks[] = $this->check(
                'accounting',
                'accounting.supplier_allocation_ownership',
                'Supplier allocation ownership',
                $mismatch === 0
                    ? 'passed'
                    : 'failed',
                true,
                $mismatch === 0
                    ? 'Supplier settlement allocations remain within one tenant, branch, and supplier for the current tenant.'
                    : "{$mismatch} supplier allocation ownership mismatch(es) were found.",
                [
                    'tenant_id' => $tenantId,
                    'mismatches' => $mismatch,
                ],
            );
        }
    }

    /**
     * @param list<array<string, mixed>> $checks
     */
    private function appendSingleOpenItemCheck(
        array &$checks,
        string $table,
        string $label,
        int $tenantId,
    ): void {
        if (!Schema::hasTable($table)) {
            $checks[] = $this->check(
                'accounting',
                'accounting.'.$table.'.arithmetic',
                $label,
                'failed',
                true,
                'The '.$table.' table is unavailable.',
            );

            return;
        }

        $required = [
            'tenant_id',
            'original_amount',
            'allocated_amount',
            'outstanding_amount',
            'base_original_amount',
            'base_allocated_amount',
            'base_outstanding_amount',
        ];

        $missingColumns = array_values(
            array_filter(
                $required,
                static fn (string $column): bool =>
                    !Schema::hasColumn(
                        $table,
                        $column,
                    ),
            ),
        );

        if ($missingColumns !== []) {
            $checks[] = $this->check(
                'accounting',
                'accounting.'.$table.'.arithmetic',
                $label,
                'failed',
                true,
                'Missing amount columns: '
                    .implode(', ', $missingColumns),
                [
                    'tenant_id' => $tenantId,
                    'missing_columns' =>
                        $missingColumns,
                ],
            );

            return;
        }

        $invalid = DB::table($table)
            ->where(
                'tenant_id',
                $tenantId,
            )
            ->where(
                function ($query): void {
                    $query
                        ->whereRaw(
                            'ABS((allocated_amount + outstanding_amount) - original_amount) > 0.000001',
                        )
                        ->orWhereRaw(
                            'ABS((base_allocated_amount + base_outstanding_amount) - base_original_amount) > 0.000001',
                        )
                        ->orWhere(
                            'original_amount',
                            '<',
                            0,
                        )
                        ->orWhere(
                            'allocated_amount',
                            '<',
                            0,
                        )
                        ->orWhere(
                            'outstanding_amount',
                            '<',
                            0,
                        )
                        ->orWhere(
                            'base_original_amount',
                            '<',
                            0,
                        )
                        ->orWhere(
                            'base_allocated_amount',
                            '<',
                            0,
                        )
                        ->orWhere(
                            'base_outstanding_amount',
                            '<',
                            0,
                        );
                },
            )
            ->count();

        $checks[] = $this->check(
            'accounting',
            'accounting.'.$table.'.arithmetic',
            $label,
            $invalid === 0
                ? 'passed'
                : 'failed',
            true,
            $invalid === 0
                ? 'Original, allocated, and outstanding balances reconcile exactly for the current tenant.'
                : "{$invalid} open item(s) contain invalid settlement arithmetic.",
            [
                'tenant_id' => $tenantId,
                'invalid_items' => $invalid,
            ],
        );
    }

    /**
     * @param list<array<string, mixed>> $checks
     */
    private function appendInventoryChecks(
        array &$checks,
    ): void {
        if (!Schema::hasTable('inventory_balances')) {
            $checks[] = $this->check(
                category: 'inventory',
                key: 'inventory.balance_integrity',
                label: 'Inventory balance integrity',
                status: 'failed',
                blocking: true,
                message: 'The inventory_balances table is unavailable.',
            );

            return;
        }

        $requiredColumns = [
            'tenant_id',
            'quantity_on_hand',
            'inventory_value',
        ];

        $missingColumns = array_values(
            array_filter(
                $requiredColumns,
                static fn (string $column): bool =>
                    !Schema::hasColumn(
                        'inventory_balances',
                        $column,
                    ),
            ),
        );

        if ($missingColumns !== []) {
            $checks[] = $this->check(
                category: 'inventory',
                key: 'inventory.balance_integrity',
                label: 'Inventory balance integrity',
                status: 'failed',
                blocking: true,
                message: 'Required inventory balance columns are missing: '
                    .implode(', ', $missingColumns)
                    .'.',
                context: [
                    'missing_columns' =>
                        $missingColumns,
                ],
            );

            return;
        }

        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            $checks[] = $this->check(
                category: 'inventory',
                key: 'inventory.balance_integrity',
                label: 'Inventory balance integrity',
                status: 'failed',
                blocking: true,
                message: 'Tenant context is unavailable for the inventory integrity check.',
            );

            return;
        }

        $invalidBalances = DB::table(
            'inventory_balances',
        )
            ->where(
                'tenant_id',
                $tenantId,
            )
            ->where(
                function ($query): void {
                    $query
                        ->where(
                            'quantity_on_hand',
                            '<',
                            0,
                        )
                        ->orWhere(
                            'inventory_value',
                            '<',
                            0,
                        )
                        ->orWhere(
                            function ($query): void {
                                $query
                                    ->where(
                                        'quantity_on_hand',
                                        '=',
                                        0,
                                    )
                                    ->whereRaw(
                                        'ABS(inventory_value) > 0.000001',
                                    );
                            },
                        );
                },
            )
            ->count();

        $passed = $invalidBalances === 0;

        $checks[] = $this->check(
            category: 'inventory',
            key: 'inventory.balance_integrity',
            label: 'Inventory balance integrity',
            status: $passed
                ? 'passed'
                : 'failed',
            blocking: true,
            message: $passed
                ? 'On-hand quantity and inventory value balances are non-negative and internally consistent for the current tenant.'
                : "{$invalidBalances} inventory balance row(s) violate quantity/value controls for the current tenant.",
            context: [
                'tenant_id' => $tenantId,
                'invalid_balances' =>
                    $invalidBalances,
            ],
        );
    }

    /**
     * @param list<array<string, mixed>> $checks
     */
    private function appendPurchaseReturnReservationChecks(
        array &$checks,
    ): void {
        if (!Schema::hasTable('goods_receipt_lines')) {
            $checks[] = $this->check(
                category: 'inventory',
                key: 'inventory.purchase_return_reservations',
                label: 'Purchase Return reservation integrity',
                status: 'failed',
                blocking: true,
                message: 'The goods_receipt_lines table is unavailable.',
            );

            return;
        }

        $requiredColumns = [
            'tenant_id',
            'accepted_quantity',
            'return_reserved_quantity',
            'returned_quantity',
        ];

        $missingColumns = array_values(
            array_filter(
                $requiredColumns,
                static fn (string $column): bool =>
                    !Schema::hasColumn(
                        'goods_receipt_lines',
                        $column,
                    ),
            ),
        );

        if ($missingColumns !== []) {
            $checks[] = $this->check(
                category: 'inventory',
                key: 'inventory.purchase_return_reservations',
                label: 'Purchase Return reservation integrity',
                status: 'failed',
                blocking: true,
                message: 'Required Goods Receipt reservation columns are missing: '
                    .implode(', ', $missingColumns)
                    .'.',
                context: [
                    'missing_columns' =>
                        $missingColumns,
                ],
            );

            return;
        }

        $tenantId = $this->tenantContext->id();

        if ($tenantId === null) {
            $checks[] = $this->check(
                category: 'inventory',
                key: 'inventory.purchase_return_reservations',
                label: 'Purchase Return reservation integrity',
                status: 'failed',
                blocking: true,
                message: 'Tenant context is unavailable for the Purchase Return reservation integrity check.',
            );

            return;
        }

        $invalidReservations = DB::table(
            'goods_receipt_lines',
        )
            ->where(
                'tenant_id',
                $tenantId,
            )
            ->where(
                function ($query): void {
                    $query
                        ->where(
                            'accepted_quantity',
                            '<',
                            0,
                        )
                        ->orWhere(
                            'returned_quantity',
                            '<',
                            0,
                        )
                        ->orWhere(
                            'return_reserved_quantity',
                            '<',
                            0,
                        )
                        ->orWhereRaw(
                            'returned_quantity '
                            .'+ return_reserved_quantity '
                            .'> accepted_quantity + 0.000001',
                        );
                },
            )
            ->count();

        $passed = $invalidReservations === 0;

        $checks[] = $this->check(
            category: 'inventory',
            key: 'inventory.purchase_return_reservations',
            label: 'Purchase Return reservation integrity',
            status: $passed
                ? 'passed'
                : 'failed',
            blocking: true,
            message: $passed
                ? 'Goods Receipt return reservations and returned quantities remain within accepted quantities for the current tenant.'
                : "{$invalidReservations} Goods Receipt line(s) contain invalid Purchase Return reservation arithmetic.",
            context: [
                'tenant_id' => $tenantId,
                'invalid_reservations' =>
                    $invalidReservations,
            ],
        );
    }

    /**
     * @param list<array<string, mixed>> $checks
     */
    private function appendAcceptanceInfrastructureChecks(
        array &$checks,
    ): void {
        $required = [
            'production_acceptance_runs',
            'production_acceptance_check_items',
        ];

        $missing = array_values(
            array_filter(
                $required,
                static fn (string $table): bool =>
                    !Schema::hasTable($table),
            ),
        );

        $checks[] = $this->check(
            'acceptance',
            'acceptance.persistence',
            'Production acceptance persistence',
            $missing === []
                ? 'passed'
                : 'failed',
            true,
            $missing === []
                ? 'Acceptance run and check-item tables are available.'
                : 'Missing acceptance tables: '
                    .implode(', ', $missing),
            [
                'missing' => $missing,
            ],
        );
    }

    /**
     * @param ProductionAcceptanceRun $run
     * @param list<array<string, mixed>> $checks
     */
    private function persistChecks(
        ProductionAcceptanceRun $run,
        array $checks,
    ): void {
        foreach ($checks as $index => $check) {
            $run->checks()->create([
                'sequence' => $index + 1,
                'category' => $check['category'],
                'check_key' => $check['key'],
                'label' => $check['label'],
                'status' => $check['status'],
                'blocking' => $check['blocking'],
                'message' => $check['message'],
                'context' => $check['context'],
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>> $checks
     *
     * @return array{
     *     checks:int,
     *     passed:int,
     *     warnings:int,
     *     failed:int,
     *     blocking_failures:int,
     *     ready:bool
     * }
     */
    private function summary(array $checks): array
    {
        $passed = count(
            array_filter(
                $checks,
                static fn (array $check): bool =>
                    $check['status'] === 'passed',
            ),
        );

        $warnings = count(
            array_filter(
                $checks,
                static fn (array $check): bool =>
                    $check['status'] === 'warning',
            ),
        );

        $failed = count(
            array_filter(
                $checks,
                static fn (array $check): bool =>
                    $check['status'] === 'failed',
            ),
        );

        $blockingFailures = count(
            array_filter(
                $checks,
                static fn (array $check): bool =>
                    $check['status'] === 'failed'
                    && $check['blocking'] === true,
            ),
        );

        return [
            'checks' => count($checks),
            'passed' => $passed,
            'warnings' => $warnings,
            'failed' => $failed,
            'blocking_failures' => $blockingFailures,
            'ready' => $blockingFailures === 0,
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function check(
        string $category,
        string $key,
        string $label,
        string $status,
        bool $blocking,
        string $message,
        array $context = [],
    ): array {
        return [
            'category' => $category,
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'blocking' => $blocking,
            'message' => $message,
            'context' => $context === []
                ? null
                : $context,
        ];
    }

    /** @return array<string, mixed> */
    private function infrastructureFailureReport(
        ?User $actor,
        string $source,
    ): array {
        $message = 'Production acceptance persistence tables are unavailable. Run pending migrations before acceptance.';

        return [
            'id' => 0,
            'uuid' => (string) Str::uuid(),
            'status' => 'blocked',
            'environment' => app()->environment(),
            'source' => $source,
            'project_fingerprint' => null,
            'summary' => [
                'checks' => 1,
                'passed' => 0,
                'warnings' => 0,
                'failed' => 1,
                'blocking_failures' => 1,
                'ready' => false,
            ],
            'started_by' => $actor === null
                ? null
                : [
                    'id' => (int) $actor->getKey(),
                    'name' => $actor->name,
                ],
            'started_at' => now()->toIso8601String(),
            'completed_at' => now()->toIso8601String(),
            'checks' => [
                [
                    'id' => 0,
                    'sequence' => 1,
                    'category' => 'acceptance',
                    'key' => 'acceptance.persistence',
                    'label' => 'Production acceptance persistence',
                    'status' => 'failed',
                    'blocking' => true,
                    'message' => $message,
                    'context' => null,
                    'remediation' => [
                        'Create and verify a database backup when applicable, then run php artisan migrate --force.',
                        'Rerun production acceptance after the migration completes.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, int> $counts
     */
    private function formatCounts(array $counts): string
    {
        $parts = [];

        foreach ($counts as $key => $count) {
            $parts[] = $key.'='.$count;
        }

        return implode(', ', $parts);
    }
}