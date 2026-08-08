<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class FinancialReconciliationService
{
    private const SCALE = 6;

    public function __construct(
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(string $asOfDate, User $actor, ?int $branchId = null): array
    {
        return $this->buildForBranchIds(
            asOfDate: $asOfDate,
            branchIds: $this->branchIds($actor, $branchId),
            branchId: $branchId,
        );
    }

    /** @param list<int> $branchIds @return array<string, mixed> */
    public function buildForBranchIds(string $asOfDate, array $branchIds, ?int $branchId = null): array
    {
        $asOf = CarbonImmutable::parse($asOfDate)->toDateString();
        $ar = $this->accountsReceivable($asOf, $branchIds);
        $ap = $this->accountsPayable($asOf, $branchIds);
        $inventory = $this->inventory($asOf, $branchIds);
        $bank = $this->bankReconciliations($asOf, $branchIds);
        $clearing = $this->systemAccountBalance('treasury_clearing', $asOf, $branchIds, false);
        $bankDifference = collect($bank)->reduce(
            static fn (BigDecimal $carry, array $row): BigDecimal => $carry
                ->plus(BigDecimal::of((string) $row['difference_since_reconciliation'])->abs()),
            BigDecimal::zero(),
        );
        $differences = BigDecimal::of($ar['difference'])
            ->abs()
            ->plus(BigDecimal::of($ap['difference'])->abs())
            ->plus(BigDecimal::of($inventory['difference'])->abs())
            ->plus(BigDecimal::of($clearing)->abs())
            ->plus($bankDifference);

        return [
            'as_of_date' => $asOf,
            'branch_id' => $branchId,
            'currency_code' => $this->tenantContext->tenant()->currency_code,
            'accounts_receivable' => $ar,
            'accounts_payable' => $ap,
            'inventory' => $inventory,
            'bank_accounts' => $bank,
            'treasury_clearing' => [
                'ledger_balance' => $clearing,
                'difference' => $clearing,
                'status' => $this->isZero($clearing) ? 'reconciled' : 'difference',
            ],
            'summary' => [
                'total_absolute_difference' => $this->decimal($differences),
                'unreconciled_bank_accounts' => collect($bank)->where('status', '!=', 'reconciled')->count(),
                'status' => $differences->isZero() && collect($bank)->every(static fn (array $row): bool => $row['status'] === 'reconciled')
                    ? 'reconciled'
                    : 'attention_required',
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @param list<int> $branchIds @return array<string, string> */
    private function accountsReceivable(string $asOf, array $branchIds): array
    {
        $ledger = $this->systemAccountBalance('accounts_receivable_control', $asOf, $branchIds, false);
        $subledger = $this->customerOpenItemBalance($asOf, $branchIds);
        $difference = BigDecimal::of($ledger)->minus($subledger);

        return [
            'general_ledger' => $ledger,
            'subledger' => $subledger,
            'difference' => $this->decimal($difference),
            'status' => $difference->isZero() ? 'reconciled' : 'difference',
        ];
    }

    /** @param list<int> $branchIds @return array<string, string> */
    private function accountsPayable(string $asOf, array $branchIds): array
    {
        $ledger = $this->systemAccountBalance('accounts_payable_control', $asOf, $branchIds, true);
        $subledger = $this->supplierOpenItemBalance($asOf, $branchIds);
        $difference = BigDecimal::of($ledger)->minus($subledger);

        return [
            'general_ledger' => $ledger,
            'subledger' => $subledger,
            'difference' => $this->decimal($difference),
            'status' => $difference->isZero() ? 'reconciled' : 'difference',
        ];
    }

    /** @param list<int> $branchIds @return array<string, string> */
    private function inventory(string $asOf, array $branchIds): array
    {
        $ledger = $this->systemAccountBalance('inventory_asset', $asOf, $branchIds, false);
        $subledger = $this->inventorySubledgerBalance($asOf, $branchIds);
        $difference = BigDecimal::of($ledger)->minus($subledger);

        return [
            'general_ledger' => $ledger,
            'subledger' => $subledger,
            'difference' => $this->decimal($difference),
            'status' => $difference->isZero() ? 'reconciled' : 'difference',
        ];
    }

    /** @param list<int> $branchIds @return list<array<string, mixed>> */
    private function bankReconciliations(string $asOf, array $branchIds): array
    {
        if (!Schema::hasTable('bank_reconciliations')) {
            return [];
        }

        $accounts = DB::table('accounts')
            ->where('tenant_id', $this->tenantContext->id())
            ->where('is_group', false)
            ->where('control_type', 'bank')
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
        $rows = [];

        foreach ($accounts as $account) {
            foreach ($branchIds as $branchId) {
                $bookBalance = $this->accountBalance((int) $account->id, $asOf, [$branchId], false);
                $reconciliation = DB::table('bank_reconciliations')
                    ->where('tenant_id', $this->tenantContext->id())
                    ->where('bank_account_id', $account->id)
                    ->where('branch_id', $branchId)
                    ->where('status', 'completed')
                    ->whereDate('statement_end_date', '<=', $asOf)
                    ->orderByDesc('statement_end_date')
                    ->orderByDesc('id')
                    ->first();
                $covered = $reconciliation !== null
                    && (string) $reconciliation->statement_end_date === $asOf;
                $difference = $reconciliation === null
                    ? BigDecimal::of($bookBalance)
                    : BigDecimal::of($bookBalance)->minus((string) $reconciliation->book_closing_balance);

                if ($this->isZero($bookBalance) && $reconciliation === null) {
                    continue;
                }

                $branch = DB::table('branches')->where('tenant_id', $this->tenantContext->id())->where('id', $branchId)->first(['code', 'name']);
                $rows[] = [
                    'account_id' => (int) $account->id,
                    'account_code' => (string) $account->code,
                    'account_name' => (string) $account->name,
                    'branch_id' => $branchId,
                    'branch_code' => (string) ($branch->code ?? ''),
                    'branch_name' => (string) ($branch->name ?? ''),
                    'book_balance' => $bookBalance,
                    'last_reconciliation_date' => $reconciliation?->statement_end_date,
                    'last_reconciliation_number' => $reconciliation?->reconciliation_number,
                    'difference_since_reconciliation' => $this->decimal($difference),
                    'status' => $covered && $difference->isZero() ? 'reconciled' : 'unreconciled',
                ];
            }
        }

        return $rows;
    }

    /** @param list<int> $branchIds */
    private function systemAccountBalance(string $systemKey, string $asOf, array $branchIds, bool $creditNormal): string
    {
        $accountId = DB::table('accounts')
            ->where('tenant_id', $this->tenantContext->id())
            ->where('system_key', $systemKey)
            ->value('id');
        if ($accountId === null) {
            return '0.000000';
        }

        return $this->accountBalance((int) $accountId, $asOf, $branchIds, $creditNormal);
    }

    /** @param list<int> $branchIds */
    private function accountBalance(int $accountId, string $asOf, array $branchIds, bool $creditNormal): string
    {
        if ($branchIds === []) {
            return '0.000000';
        }

        $row = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.tenant_id', $this->tenantContext->id())
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.posting_date', '<=', $asOf)
            ->where('journal_entry_lines.account_id', $accountId)
            ->whereIn('journal_entry_lines.branch_id', $branchIds)
            ->selectRaw('COALESCE(SUM(journal_entry_lines.base_debit_amount), 0) as debit')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.base_credit_amount), 0) as credit')
            ->first();
        $debit = BigDecimal::of((string) ($row->debit ?? '0'));
        $credit = BigDecimal::of((string) ($row->credit ?? '0'));

        return $this->decimal($creditNormal ? $credit->minus($debit) : $debit->minus($credit));
    }

    /** @param list<int> $branchIds */
    private function inventorySubledgerBalance(string $asOf, array $branchIds): string
    {
        if (!Schema::hasTable('stock_ledger_entries') || $branchIds === []) {
            return '0.000000';
        }

        $latestTimes = DB::table('stock_ledger_entries')
            ->where('tenant_id', $this->tenantContext->id())
            ->whereDate('occurred_at', '<=', $asOf)
            ->whereIn('branch_id', $branchIds)
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

        $value = DB::table('stock_ledger_entries')
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
            ->sum('stock_ledger_entries.balance_value');

        return $this->decimal(BigDecimal::of((string) $value));
    }


    /** @param list<int> $branchIds */
    private function customerOpenItemBalance(string $asOf, array $branchIds): string
    {
        return $this->openItemBalance(
            itemTable: 'customer_open_items',
            allocationTable: 'customer_open_item_allocations',
            positiveTypes: ['invoice', 'refund', 'adjustment_debit'],
            negativeTypes: ['credit', 'adjustment_credit'],
            positiveForeignKey: 'receivable_open_item_id',
            positiveBaseColumn: 'receivable_base_amount',
            ledgerTable: 'customer_ledger_entries',
            ledgerForeignKey: 'customer_ledger_entry_id',
            asOf: $asOf,
            branchIds: $branchIds,
        );
    }

    /** @param list<int> $branchIds */
    private function supplierOpenItemBalance(string $asOf, array $branchIds): string
    {
        return $this->openItemBalance(
            itemTable: 'supplier_open_items',
            allocationTable: 'supplier_open_item_allocations',
            positiveTypes: ['invoice'],
            negativeTypes: ['credit'],
            positiveForeignKey: 'payable_open_item_id',
            positiveBaseColumn: 'payable_base_amount',
            ledgerTable: 'supplier_ledger_entries',
            ledgerForeignKey: 'supplier_ledger_entry_id',
            asOf: $asOf,
            branchIds: $branchIds,
        );
    }

    /**
     * @param list<string> $positiveTypes
     * @param list<string> $negativeTypes
     * @param list<int> $branchIds
     */
    private function openItemBalance(
        string $itemTable,
        string $allocationTable,
        array $positiveTypes,
        array $negativeTypes,
        string $positiveForeignKey,
        string $positiveBaseColumn,
        string $ledgerTable,
        string $ledgerForeignKey,
        string $asOf,
        array $branchIds,
    ): string {
        if (
            !Schema::hasTable($itemTable)
            || !Schema::hasTable($allocationTable)
            || !Schema::hasTable($ledgerTable)
            || $branchIds === []
        ) {
            return '0.000000';
        }

        $positive = $this->openItemSideBalance(
            itemTable: $itemTable,
            allocationTable: $allocationTable,
            itemTypes: $positiveTypes,
            allocationForeignKey: $positiveForeignKey,
            allocationBaseColumn: $positiveBaseColumn,
            ledgerTable: $ledgerTable,
            ledgerForeignKey: $ledgerForeignKey,
            asOf: $asOf,
            branchIds: $branchIds,
        );
        $negative = $this->openItemSideBalance(
            itemTable: $itemTable,
            allocationTable: $allocationTable,
            itemTypes: $negativeTypes,
            allocationForeignKey: 'credit_open_item_id',
            allocationBaseColumn: 'credit_base_amount',
            ledgerTable: $ledgerTable,
            ledgerForeignKey: $ledgerForeignKey,
            asOf: $asOf,
            branchIds: $branchIds,
        );

        return $this->decimal($positive->minus($negative));
    }

    /**
     * @param list<string> $itemTypes
     * @param list<int> $branchIds
     */
    private function openItemSideBalance(
        string $itemTable,
        string $allocationTable,
        array $itemTypes,
        string $allocationForeignKey,
        string $allocationBaseColumn,
        string $ledgerTable,
        string $ledgerForeignKey,
        string $asOf,
        array $branchIds,
    ): BigDecimal {
        if ($itemTypes === []) {
            return BigDecimal::zero();
        }

        $historicalAllocations = DB::table($allocationTable)
            ->where('tenant_id', $this->tenantContext->id())
            ->whereDate('posting_date', '<=', $asOf)
            ->where(static function ($query) use ($asOf): void {
                $query->where('status', 'applied')
                    ->orWhere(static function ($reversed) use ($asOf): void {
                        $reversed->where('status', 'reversed')
                            ->whereDate('reversal_posting_date', '>', $asOf);
                    });
            })
            ->whereNotNull($allocationForeignKey)
            ->groupBy($allocationForeignKey)
            ->selectRaw("{$allocationForeignKey} as open_item_id")
            ->selectRaw("COALESCE(SUM({$allocationBaseColumn}), 0) as allocated_base");

        $historicalReversals = DB::table($ledgerTable)
            ->where('tenant_id', $this->tenantContext->id())
            ->whereNotNull('reversal_of_id')
            ->whereDate('posting_date', '<=', $asOf)
            ->groupBy('reversal_of_id')
            ->selectRaw('reversal_of_id as original_ledger_id');

        $row = DB::table("{$itemTable} as open_items")
            ->leftJoinSub(
                $historicalAllocations,
                'historical_allocations',
                static function ($join): void {
                    $join->on(
                        'historical_allocations.open_item_id',
                        '=',
                        'open_items.id',
                    );
                },
            )
            ->leftJoinSub(
                $historicalReversals,
                'historical_reversals',
                static function ($join) use ($ledgerForeignKey): void {
                    $join->on(
                        'historical_reversals.original_ledger_id',
                        '=',
                        "open_items.{$ledgerForeignKey}",
                    );
                },
            )
            ->where('open_items.tenant_id', $this->tenantContext->id())
            ->whereDate('open_items.posting_date', '<=', $asOf)
            ->whereIn('open_items.branch_id', $branchIds)
            ->whereIn('open_items.item_type', $itemTypes)
            ->whereNull('historical_reversals.original_ledger_id')
            ->selectRaw(
                'COALESCE(SUM(open_items.base_original_amount - '
                . 'COALESCE(historical_allocations.allocated_base, 0)), 0) as balance',
            )
            ->first();

        return BigDecimal::of((string) ($row->balance ?? '0'));
    }

    /** @return list<int> */
    private function branchIds(User $actor, ?int $branchId): array
    {
        $ids = $this->branchAccessService->accessibleBranches($actor, false)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        if ($branchId === null) {
            return $ids;
        }

        if (!in_array($branchId, $ids, true)) {
            throw ValidationException::withMessages([
                'branch_id' => ['The selected branch is not accessible.'],
            ]);
        }

        return [$branchId];
    }

    private function isZero(string $value): bool
    {
        return BigDecimal::of($value)->isZero();
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HALF_UP)->__toString();
    }
}
