<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PeriodCloseChecklistService
{
    private const SCALE = 6;

    public function __construct(
        private readonly FinancialReconciliationService $reconciliationService,
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function run(AccountingPeriod $period, User $actor): array
    {
        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $tenantBranchIds = Branch::query()
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        $accessibleBranchIds = $this->branchAccessService
            ->accessibleBranches($actor, false)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        sort($tenantBranchIds);
        sort($accessibleBranchIds);

        $checks = [];
        $checks[] = $this->check(
            key: 'branch_scope_complete',
            category: 'general_ledger',
            label: 'Complete tenant branch authority',
            issueCount: $tenantBranchIds === $accessibleBranchIds ? 0 : 1,
            difference: '0',
            blocking: true,
            message: $tenantBranchIds === $accessibleBranchIds
                ? 'The closing user can review every tenant branch.'
                : 'Period close requires access to every tenant branch.',
        );

        $unpostedJournals = DB::table('journal_entries')
            ->where('tenant_id', $period->tenant_id)
            ->whereBetween('posting_date', [$start, $end])
            ->whereIn('status', ['draft', 'approved'])
            ->count();
        $checks[] = $this->check(
            'unposted_journals',
            'general_ledger',
            'Unposted journal entries',
            $unpostedJournals,
            '0',
            true,
            $unpostedJournals === 0
                ? 'All journals dated in the period are posted or cancelled.'
                : "{$unpostedJournals} journal entries remain unposted.",
        );

        $unbalancedJournals = DB::table('journal_entries')
            ->where('tenant_id', $period->tenant_id)
            ->whereBetween('posting_date', [$start, $end])
            ->where('status', 'posted')
            ->whereColumn('base_total_debit', '!=', 'base_total_credit')
            ->count();
        $checks[] = $this->check(
            'unbalanced_journals',
            'general_ledger',
            'Balanced posted journals',
            $unbalancedJournals,
            '0',
            true,
            $unbalancedJournals === 0
                ? 'All posted journals are balanced in base currency.'
                : "{$unbalancedJournals} posted journals are not balanced.",
        );

        $documentIssues = $this->unpostedOperationalDocuments((int) $period->tenant_id, $start, $end);
        $checks[] = $this->check(
            'unposted_operational_documents',
            'documents',
            'Unposted accounting documents',
            array_sum(array_column($documentIssues, 'count')),
            '0',
            true,
            $documentIssues === []
                ? 'All accounting documents dated in the period are finalized.'
                : 'Accounting documents remain in a non-final workflow status.',
            $documentIssues,
        );

        $reconciliation = $this->reconciliationService->buildForBranchIds($end, $tenantBranchIds);
        foreach ([
            'accounts_receivable' => ['Accounts Receivable to General Ledger', 'subledgers'],
            'accounts_payable' => ['Accounts Payable to General Ledger', 'subledgers'],
            'inventory' => ['Inventory to General Ledger', 'inventory'],
            'treasury_clearing' => ['Treasury clearing account', 'treasury'],
        ] as $key => [$label, $category]) {
            $row = $reconciliation[$key];
            $difference = (string) $row['difference'];
            $checks[] = $this->check(
                "reconciliation_{$key}",
                $category,
                $label,
                BigDecimal::of($difference)->isZero() ? 0 : 1,
                $difference,
                true,
                BigDecimal::of($difference)->isZero()
                    ? "{$label} is reconciled."
                    : "{$label} has a base-currency difference of {$difference}.",
                $row,
            );
        }

        $unreconciledBanks = (int) $reconciliation['summary'][
            'unreconciled_bank_accounts'
        ];
        $bankDifference = collect($reconciliation['bank_accounts'])->reduce(
            static fn (BigDecimal $carry, array $row): BigDecimal => $carry
                ->plus(
                    BigDecimal::of(
                        (string) $row['difference_since_reconciliation'],
                    )->abs(),
                ),
            BigDecimal::zero(),
        );
        $checks[] = $this->check(
            'bank_reconciliations_complete',
            'treasury',
            'Bank reconciliations through period end',
            $unreconciledBanks,
            $this->decimal($bankDifference),
            true,
            $unreconciledBanks === 0
                ? 'All active bank balances are reconciled through period end.'
                : "{$unreconciledBanks} bank account and branch combinations are not reconciled through period end.",
            $reconciliation['bank_accounts'],
        );

        $negativeInventory = $this->historicalNegativeInventoryCount(
            tenantId: (int) $period->tenant_id,
            asOf: $end,
        );
        $checks[] = $this->check(
            'negative_inventory',
            'inventory',
            'Negative inventory balances',
            $negativeInventory,
            '0',
            true,
            $negativeInventory === 0
                ? 'No negative quantity or inventory value was detected.'
                : "{$negativeInventory} inventory balance records are negative.",
        );

        $isFinalPeriod = !$period->fiscalYear->periods()
            ->where('period_number', '>', $period->period_number)
            ->exists();
        $retainedEarningsExists = DB::table('accounts')
            ->where('tenant_id', $period->tenant_id)
            ->where('system_key', 'retained_earnings')
            ->where('status', 'active')
            ->where('is_group', false)
            ->exists();
        $checks[] = $this->check(
            'year_end_closing_account',
            'closing',
            'Year-end retained earnings account',
            $isFinalPeriod && !$retainedEarningsExists ? 1 : 0,
            '0',
            true,
            !$isFinalPeriod
                ? 'A year-end closing journal is not required for this period.'
                : ($retainedEarningsExists
                    ? 'The protected retained earnings account is configured.'
                    : 'The retained earnings system account is missing.'),
        );

        return $checks;
    }

    private function historicalNegativeInventoryCount(
        int $tenantId,
        string $asOf,
    ): int {
        if (!Schema::hasTable('stock_ledger_entries')) {
            return 0;
        }

        $latestTimes = DB::table('stock_ledger_entries')
            ->where('tenant_id', $tenantId)
            ->whereDate('occurred_at', '<=', $asOf)
            ->groupBy([
                'tenant_id',
                'branch_id',
                'warehouse_id',
                'product_id',
                'unit_id',
            ])
            ->select([
                'tenant_id',
                'branch_id',
                'warehouse_id',
                'product_id',
                'unit_id',
            ])
            ->selectRaw('MAX(occurred_at) as latest_occurred_at');

        $latestIds = DB::table('stock_ledger_entries as dated_stock')
            ->joinSub(
                $latestTimes,
                'latest_times',
                static function ($join): void {
                    $join->on(
                        'latest_times.tenant_id',
                        '=',
                        'dated_stock.tenant_id',
                    )
                        ->on(
                            'latest_times.branch_id',
                            '=',
                            'dated_stock.branch_id',
                        )
                        ->on(
                            'latest_times.warehouse_id',
                            '=',
                            'dated_stock.warehouse_id',
                        )
                        ->on(
                            'latest_times.product_id',
                            '=',
                            'dated_stock.product_id',
                        )
                        ->on(
                            'latest_times.unit_id',
                            '=',
                            'dated_stock.unit_id',
                        )
                        ->on(
                            'latest_times.latest_occurred_at',
                            '=',
                            'dated_stock.occurred_at',
                        );
                },
            )
            ->groupBy([
                'dated_stock.tenant_id',
                'dated_stock.branch_id',
                'dated_stock.warehouse_id',
                'dated_stock.product_id',
                'dated_stock.unit_id',
            ])
            ->selectRaw('MAX(dated_stock.id) as id');

        return DB::table('stock_ledger_entries')
            ->joinSub(
                $latestIds,
                'latest_stock',
                static function ($join): void {
                    $join->on(
                        'stock_ledger_entries.id',
                        '=',
                        'latest_stock.id',
                    );
                },
            )
            ->where(static function ($query): void {
                $query->where('stock_ledger_entries.balance_quantity', '<', 0)
                    ->orWhere('stock_ledger_entries.balance_value', '<', 0);
            })
            ->count();
    }

    /** @return list<array{table: string, label: string, count: int}> */
    private function unpostedOperationalDocuments(int $tenantId, string $start, string $end): array
    {
        $definitions = [
            ['table' => 'goods_receipts', 'label' => 'Goods Receipts'],
            ['table' => 'purchase_returns', 'label' => 'Purchase Returns'],
            ['table' => 'supplier_invoices', 'label' => 'Supplier Invoices'],
            ['table' => 'supplier_debit_notes', 'label' => 'Supplier Debit Notes'],
            ['table' => 'supplier_payments', 'label' => 'Supplier Payments'],
            ['table' => 'customer_dispatches', 'label' => 'Customer Dispatches'],
            ['table' => 'sales_invoices', 'label' => 'Sales Invoices'],
            ['table' => 'customer_receipts', 'label' => 'Customer Receipts'],
            ['table' => 'customer_credit_notes', 'label' => 'Customer Credit Notes'],
            ['table' => 'customer_credit_applications', 'label' => 'Customer Credit Applications'],
            ['table' => 'customer_refunds', 'label' => 'Customer Refunds'],
            ['table' => 'customer_ar_adjustments', 'label' => 'AR Adjustments'],
            ['table' => 'treasury_transfers', 'label' => 'Treasury Transfers'],
            ['table' => 'treasury_adjustments', 'label' => 'Treasury Adjustments'],
        ];
        $issues = [];

        foreach ($definitions as $definition) {
            $table = $definition['table'];
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'posting_date') || !Schema::hasColumn($table, 'status')) {
                continue;
            }

            $count = DB::table($table)
                ->where('tenant_id', $tenantId)
                ->whereBetween('posting_date', [$start, $end])
                ->whereNotIn('status', ['posted', 'reversed', 'cancelled'])
                ->count();
            if ($count > 0) {
                $issues[] = ['table' => $table, 'label' => $definition['label'], 'count' => $count];
            }
        }

        return $issues;
    }

    /** @param array<string, mixed>|list<mixed>|null $details @return array<string, mixed> */
    private function check(
        string $key,
        string $category,
        string $label,
        int $issueCount,
        string $difference,
        bool $blocking,
        string $message,
        ?array $details = null,
    ): array {
        $hasIssue = $issueCount > 0 || !BigDecimal::of($difference)->isZero();

        return [
            'check_key' => $key,
            'category' => $category,
            'label' => $label,
            'status' => $hasIssue ? ($blocking ? 'failed' : 'warning') : 'passed',
            'is_blocking' => $blocking,
            'issue_count' => $issueCount,
            'difference_amount' => $this->decimal(BigDecimal::of($difference)),
            'message' => $message,
            'details' => $details,
            'checked_at' => now(),
        ];
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HALF_UP)->__toString();
    }
}
