<?php

declare(strict_types=1);

namespace App\Services\Management;

use App\Support\DocumentNumbers\DocumentTypeRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class ProductionReadinessService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly DocumentTypeRegistry $documentTypeRegistry,
    ) {
    }

    /** @return array<string, mixed> */
    public function audit(): array
    {
        return $this->run();
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $checks = [];
        $this->check($checks, 'database.connection', 'Database connection', fn (): bool => DB::connection()->getPdo() !== null, true, 'Database connection is available.');

        foreach ([
            'tenants', 'branches', 'users', 'accounts', 'journal_entries', 'journal_entry_lines',
            'inventory_balances', 'supplier_invoices', 'sales_invoices', 'customer_open_items',
            'supplier_open_items', 'document_sequences', 'export_requests', 'bank_reconciliations',
            'management_budgets', 'management_budget_lines', 'management_report_schedules',
            'system_backups', 'operations_runtime_states', 'production_acceptance_runs',
            'production_acceptance_check_items', 'release_candidates', 'release_candidate_artifacts',
        ] as $table) {
            $this->check($checks, 'table.'.$table, 'Table: '.$table, fn (): bool => Schema::hasTable($table), true, 'Required application table exists.');
        }

        $migrationFiles = glob(database_path('migrations/*.php')) ?: [];
        $appliedMigrations = Schema::hasTable('migrations')
            ? DB::table('migrations')->pluck('migration')->map(static fn (mixed $name): string => (string) $name)->all()
            : [];
        $pendingMigrations = [];
        foreach ($migrationFiles as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (!in_array($name, $appliedMigrations, true)) {
                $pendingMigrations[] = $name;
            }
        }
        $checks[] = $this->result(
            'migrations.pending',
            'Pending database migrations',
            $pendingMigrations === [],
            true,
            $pendingMigrations === []
                ? 'Every migration file is recorded as applied.'
                : 'Pending migrations: '.implode(', ', array_slice($pendingMigrations, 0, 12)),
        );

        $tenantTables = [
            'branches', 'users', 'accounts', 'journal_entries', 'journal_entry_lines',
            'inventory_balances', 'supplier_invoices', 'sales_invoices', 'customer_open_items',
            'supplier_open_items', 'bank_reconciliations', 'management_budgets', 'management_report_schedules',
            'production_acceptance_runs', 'production_acceptance_check_items', 'release_candidates', 'release_candidate_artifacts',
        ];
        $missingTenantColumns = array_values(array_filter(
            $tenantTables,
            static fn (string $table): bool => Schema::hasTable($table) && !Schema::hasColumn($table, 'tenant_id'),
        ));
        $checks[] = $this->result(
            'security.tenant_columns',
            'Tenant ownership columns',
            $missingTenantColumns === [],
            true,
            $missingTenantColumns === []
                ? 'Critical operational and accounting tables retain tenant ownership columns.'
                : 'Missing tenant_id on: '.implode(', ', $missingTenantColumns),
        );

        foreach ([
            'financial-control.index',
            'reports.financial-statements.trial-balance',
            'treasury.index',
            'reports.accounts-receivable.aging',
            'reports.accounts-payable.aging',
            'management.index',
            'management.budgets.index',
            'operations.index',
            'operations.preflight',
            'production-acceptance.index',
            'release-candidates.index',
        ] as $name) {
            $this->check($checks, 'route.'.$name, 'Route: '.$name, fn (): bool => Route::has($name), true, 'Required named route is registered.');
        }

        if ($this->tenantContext->hasTenant()) {
            $tenantId = (int) $this->tenantContext->id();
            $requiredAccounts = [
                'accounts_receivable_control', 'accounts_payable_control', 'inventory_asset',
                'cash_control', 'bank_control', 'treasury_clearing', 'retained_earnings',
            ];
            foreach ($requiredAccounts as $key) {
                $this->check(
                    $checks,
                    'account.'.$key,
                    'System account: '.$key,
                    fn (): bool => DB::table('accounts')->where('tenant_id', $tenantId)->where('system_key', $key)->where('status', 'active')->exists(),
                    true,
                    'Required protected system account is active.',
                );
            }

            if (Schema::hasTable('document_sequences')) {
                $missingSequences = [];
                foreach ($this->documentTypeRegistry->keys() as $type) {
                    $exists = DB::table('document_sequences')
                        ->where('tenant_id', $tenantId)
                        ->where('document_type', $type)
                        ->where('status', 'active')
                        ->exists();
                    if (!$exists) {
                        $missingSequences[] = $type;
                    }
                }
                $checks[] = $this->result(
                    'document_sequences.coverage',
                    'Document numbering coverage',
                    $missingSequences === [],
                    true,
                    $missingSequences === []
                        ? 'Every registered document type has at least one active numbering sequence.'
                        : 'Missing active sequences: '.implode(', ', $missingSequences),
                );
            } else {
                $checks[] = $this->result(
                    'document_sequences.coverage',
                    'Document numbering coverage',
                    false,
                    true,
                    'The document_sequences table is unavailable. Run pending migrations before production readiness can pass.',
                );
            }

            if (
    Schema::hasTable('journal_entries')
    && Schema::hasTable('journal_entry_lines')
) {
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

        $checks[] = $this->result(
            'accounting.journal_integrity',
            'Posted journal integrity',
            false,
            true,
            'Required General Ledger columns are unavailable: '
                .implode('; ', $parts)
                .'.',
        );
    } else {
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
                'readiness_journal_line_check',
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
                'readiness_journal_header_check',
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

        $checks[] = $this->result(
            'accounting.journal_integrity',
            'Posted journal integrity',
            $passed,
            true,
            $passed
                ? 'Posted journal headers and lines reconcile exactly and retain consistent tenant/branch ownership.'
                : sprintf(
                    'Header imbalance: %d; invalid line structure/balance: %d; header-to-line mismatch: %d; ownership mismatch: %d.',
                    $headerImbalance,
                    $invalidLineStructure,
                    $headerLineMismatch,
                    $ownershipMismatch,
                ),
        );
    }
} else {
    $checks[] = $this->result(
        'accounting.journal_integrity',
        'Posted journal integrity',
        false,
        true,
        'The journal_entries or journal_entry_lines table is unavailable.',
    );
}

            foreach ([
                ['supplier_invoices', 'accounting_posting_reference'],
                ['supplier_payments', 'accounting_posting_reference'],
                ['customer_receipts', 'accounting_posting_reference'],
                ['customer_credit_notes', 'accounting_posting_reference'],
                ['customer_refunds', 'accounting_posting_reference'],
                ['customer_ar_adjustments', 'accounting_posting_reference'],
                ['treasury_transfers', 'accounting_posting_reference'],
                ['treasury_adjustments', 'accounting_posting_reference'],
                ['goods_receipts', 'accounting_reference'],
            ] as [$table, $referenceColumn]) {
                if (!Schema::hasTable($table) || !Schema::hasColumn($table, $referenceColumn)) {
                    continue;
                }
                $missing = DB::table($table)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'posted')
                    ->whereNull($referenceColumn)
                    ->count();
                $checks[] = $this->result(
                    'accounting.posting_reference.'.$table,
                    'Accounting linkage: '.$table,
                    $missing === 0,
                    true,
                    $missing === 0
                        ? 'Every posted record has an accounting posting reference.'
                        : "{$missing} posted record(s) are missing an accounting posting reference.",
                );
            }

            if (Schema::hasTable('inventory_balances') && Schema::hasColumn('inventory_balances', 'quantity_on_hand')) {
                $negativeInventory = DB::table('inventory_balances')
                    ->where('tenant_id', $tenantId)
                    ->where('quantity_on_hand', '<', 0)
                    ->count();
                $checks[] = $this->result('inventory.negative_balances', 'Negative inventory', $negativeInventory === 0, true, $negativeInventory === 0 ? 'No negative inventory balances were found.' : "{$negativeInventory} negative inventory balances require investigation.");
            } else {
                $checks[] = $this->result('inventory.negative_balances', 'Negative inventory', false, true, 'Inventory balance storage is unavailable or incomplete.');
            }

            if (Schema::hasTable('export_requests')) {
                $staleExports = DB::table('export_requests')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', ['queued', 'processing'])
                    ->where('created_at', '<', now()->subHour())
                    ->count();
                $checks[] = $this->result('queue.stale_exports', 'Queued export health', $staleExports === 0, false, $staleExports === 0 ? 'No stale queued exports were detected.' : "{$staleExports} exports have been queued or processing for more than one hour.");
            } else {
                $checks[] = $this->result('queue.stale_exports', 'Queued export health', false, false, 'The export_requests table is unavailable.');
            }
        }

        $production = app()->environment('production');
        $debug = (bool) config('app.debug', false);
        $checks[] = $this->result('config.debug', 'Application debug mode', !$production || !$debug, true, $production && $debug ? 'APP_DEBUG must be disabled in production.' : 'Debug configuration is acceptable for the current environment.');
        $checks[] = $this->result('config.app_key', 'Application encryption key', is_string(config('app.key')) && trim((string) config('app.key')) !== '', true, 'APP_KEY must be configured.');

        $checks[] = $this->result(
            'config.queue',
            'Queue connection',
            (string) config('queue.default') !== 'sync'
                || !$production,
            true,
            $production
                && (string) config('queue.default') === 'sync'
                    ? 'Production requires a durable asynchronous queue connection because queued exports and background ERP workloads must not execute synchronously.'
                    : 'Queue configuration is acceptable for the current environment.',
        );
        
        $checks[] = $this->result(
    'config.session_secure',
    'Secure session cookie',
    !$production || (bool) config('session.secure'),
    true,
    $production && !(bool) config('session.secure')
        ? 'Production requires secure session cookies so authentication cookies are transmitted only over HTTPS.'
        : 'Session cookie security is acceptable for the current environment.',
);

        $buildManifest = public_path('build/manifest.json');
        $checks[] = $this->result(
            'frontend.build_manifest',
            'Frontend production build',
            !app()->environment('production') || is_file($buildManifest),
            app()->environment('production'),
            is_file($buildManifest)
                ? 'Vite build manifest is present.'
                : 'Run the production frontend build before deployment.',
        );
        $checks[] = $this->result(
            'filesystem.storage_writable',
            'Writable storage directory',
            is_dir(storage_path()) && is_writable(storage_path()),
            true,
            is_writable(storage_path()) ? 'Laravel storage is writable.' : 'Laravel storage must be writable by the application process.',
        );
       $zipAvailable = class_exists(\ZipArchive::class);

$checks[] = $this->result(
    'php.zip',
    'PHP Zip extension',
    !$production || $zipAvailable,
    $production,
    !$production || $zipAvailable
        ? 'PHP Zip configuration is acceptable for the current environment.'
        : 'PHP Zip is required in production because native XLSX export generation depends on ZipArchive.',
);

        $blockingFailures = collect($checks)->where('blocking', true)->where('status', 'failed')->count();
        $warnings = collect($checks)->where('blocking', false)->where('status', 'failed')->count();

        return [
            'environment' => app()->environment(),
            'generated_at' => now()->toIso8601String(),
            'summary' => [
                'checks' => count($checks),
                'passed' => collect($checks)->where('status', 'passed')->count(),
                'blocking_failures' => $blockingFailures,
                'warnings' => $warnings,
                'ready' => $blockingFailures === 0,
            ],
            'checks' => $checks,
            'deployment_checklist' => [
                'Run all migrations on staging and production.',
                'Seed permissions and the default chart of accounts idempotently.',
                'Verify every operational document-number sequence.',
                'Run npm production build and strict TypeScript checks.',
                'Run queue workers and scheduler under a process supervisor.',
                'Disable application debug mode and enforce HTTPS.',
                'Configure backups and perform a restore rehearsal.',
                'Verify tenant isolation and branch permissions with representative roles.',
                'Complete AR, AP, inventory, bank, and Trial Balance reconciliation.',
                'Perform end-to-end purchase-to-pay and order-to-cash smoke tests.',
            ],
        ];
    }

    /** @param list<array<string, mixed>> $checks */
    private function check(array &$checks, string $key, string $label, callable $callback, bool $blocking, string $successMessage): void
    {
        try {
            $passed = (bool) $callback();
            $checks[] = $this->result($key, $label, $passed, $blocking, $passed ? $successMessage : 'The readiness check failed.');
        } catch (Throwable $exception) {
            $checks[] = $this->result($key, $label, false, $blocking, 'Check error: '.$exception->getMessage());
        }
    }

    /** @return array<string, mixed> */
    private function result(string $key, string $label, bool $passed, bool $blocking, string $message): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $passed ? 'passed' : 'failed',
            'blocking' => $blocking,
            'message' => $message,
        ];
    }
}
