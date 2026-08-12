<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinancialStatementService
{
    private const SCALE = 6;

    public function __construct(
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function trialBalance(array $filters, User $actor): array
    {
        $range = $this->range($filters);
        $branchIds = $this->branchIds($actor, $filters['branch_id'] ?? null);

        $rows = $this->accountActivity(
            branchIds: $branchIds,
            dateFrom: $range['date_from'],
            dateTo: $range['date_to'],
            accountTypes: null,
            includeClosingEntries: true,
        );

        $totalOpeningDebit = BigDecimal::zero();
        $totalOpeningCredit = BigDecimal::zero();
        $totalPeriodDebit = BigDecimal::zero();
        $totalPeriodCredit = BigDecimal::zero();
        $totalClosingDebit = BigDecimal::zero();
        $totalClosingCredit = BigDecimal::zero();
        $output = [];

        foreach ($rows as $row) {
            $opening = BigDecimal::of((string) $row->opening_debit)
                ->minus((string) $row->opening_credit);
            $periodDebit = BigDecimal::of((string) $row->period_debit);
            $periodCredit = BigDecimal::of((string) $row->period_credit);
            $closing = $opening->plus($periodDebit)->minus($periodCredit);
            $openingDebit = $opening->isPositive() ? $opening : BigDecimal::zero();
            $openingCredit = $opening->isNegative() ? $opening->abs() : BigDecimal::zero();
            $closingDebit = $closing->isPositive() ? $closing : BigDecimal::zero();
            $closingCredit = $closing->isNegative() ? $closing->abs() : BigDecimal::zero();

            if (
                $opening->isZero()
                && $periodDebit->isZero()
                && $periodCredit->isZero()
                && $closing->isZero()
            ) {
                continue;
            }

            $totalOpeningDebit = $totalOpeningDebit->plus($openingDebit);
            $totalOpeningCredit = $totalOpeningCredit->plus($openingCredit);
            $totalPeriodDebit = $totalPeriodDebit->plus($periodDebit);
            $totalPeriodCredit = $totalPeriodCredit->plus($periodCredit);
            $totalClosingDebit = $totalClosingDebit->plus($closingDebit);
            $totalClosingCredit = $totalClosingCredit->plus($closingCredit);

            $output[] = [
                'account_id' => (int) $row->account_id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'account_type' => (string) $row->account_type,
                'account_subtype' => $row->account_subtype,
                'opening_debit' => $this->decimal($openingDebit),
                'opening_credit' => $this->decimal($openingCredit),
                'period_debit' => $this->decimal($periodDebit),
                'period_credit' => $this->decimal($periodCredit),
                'closing_debit' => $this->decimal($closingDebit),
                'closing_credit' => $this->decimal($closingCredit),
            ];
        }

        return [
            'statement' => 'trial_balance',
            'title' => 'Trial Balance',
            'filters' => $this->publicFilters($range, $filters),
            'currency_code' => $this->tenantContext->tenant()->currency_code,
            'rows' => $output,
            'totals' => [
                'opening_debit' => $this->decimal($totalOpeningDebit),
                'opening_credit' => $this->decimal($totalOpeningCredit),
                'period_debit' => $this->decimal($totalPeriodDebit),
                'period_credit' => $this->decimal($totalPeriodCredit),
                'closing_debit' => $this->decimal($totalClosingDebit),
                'closing_credit' => $this->decimal($totalClosingCredit),
                'opening_difference' => $this->decimal($totalOpeningDebit->minus($totalOpeningCredit)),
                'period_difference' => $this->decimal($totalPeriodDebit->minus($totalPeriodCredit)),
                'closing_difference' => $this->decimal($totalClosingDebit->minus($totalClosingCredit)),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function profitAndLoss(array $filters, User $actor): array
    {
        $range = $this->range($filters);
        $branchIds = $this->branchIds($actor, $filters['branch_id'] ?? null);
        $current = $this->profitAndLossForRange($range['date_from'], $range['date_to'], $branchIds);
        $comparisonRange = $this->comparisonRange($range, (string) ($filters['comparison'] ?? 'none'));
        $comparison = $comparisonRange === null
            ? null
            : $this->profitAndLossForRange(
                $comparisonRange['date_from'],
                $comparisonRange['date_to'],
                $branchIds,
            );
        $comparisonVariance = null;

        if ($comparison !== null) {
            $comparisonVariance = [];

            foreach ($current['totals'] as $key => $amount) {
                $comparisonVariance[$key] = $this->decimal(
                    BigDecimal::of((string) $amount)->minus(
                        (string) ($comparison['totals'][$key] ?? '0'),
                    ),
                );
            }
        }

        return [
            'statement' => 'profit_and_loss',
            'title' => 'Profit and Loss Statement',
            'filters' => $this->publicFilters($range, $filters),
            'comparison_range' => $comparisonRange,
            'currency_code' => $this->tenantContext->tenant()->currency_code,
            'sections' => $current['sections'],
            'totals' => $current['totals'],
            'comparison' => $comparison,
            'comparison_variance' => $comparisonVariance,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function balanceSheet(array $filters, User $actor): array
    {
        $asOf = $this->asOfDate($filters);
        $branchIds = $this->branchIds($actor, $filters['branch_id'] ?? null);
        $rows = $this->accountBalancesAsOf($asOf, $branchIds, ['asset', 'liability', 'equity']);
        $sections = ['assets' => [], 'liabilities' => [], 'equity' => []];
        $totals = ['assets' => BigDecimal::zero(), 'liabilities' => BigDecimal::zero(), 'equity' => BigDecimal::zero()];

        foreach ($rows as $row) {
            $net = BigDecimal::of((string) $row->base_debit)
                ->minus((string) $row->base_credit);
            $amount = $row->account_type === 'asset' ? $net : $net->negated();
            $key = match ((string) $row->account_type) {
                'asset' => 'assets',
                'liability' => 'liabilities',
                default => 'equity',
            };

            if ($amount->isZero()) {
                continue;
            }

            $sections[$key][] = [
                'account_id' => (int) $row->account_id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'account_subtype' => $row->account_subtype,
                'amount' => $this->decimal($amount),
            ];
            $totals[$key] = $totals[$key]->plus($amount);
        }

        $currentEarnings = $this->unclosedCurrentEarnings($asOf, $branchIds);
        if (!$currentEarnings->isZero()) {
            $sections['equity'][] = [
                'account_id' => null,
                'code' => 'CURRENT-EARNINGS',
                'name' => 'Current Fiscal Year Earnings',
                'account_subtype' => 'retained_earnings',
                'amount' => $this->decimal($currentEarnings),
            ];
            $totals['equity'] = $totals['equity']->plus($currentEarnings);
        }

        $liabilitiesAndEquity = $totals['liabilities']->plus($totals['equity']);

        return [
            'statement' => 'balance_sheet',
            'title' => 'Balance Sheet',
            'filters' => ['as_of_date' => $asOf, 'branch_id' => $filters['branch_id'] ?? null],
            'currency_code' => $this->tenantContext->tenant()->currency_code,
            'sections' => $sections,
            'totals' => [
                'assets' => $this->decimal($totals['assets']),
                'liabilities' => $this->decimal($totals['liabilities']),
                'equity' => $this->decimal($totals['equity']),
                'liabilities_and_equity' => $this->decimal($liabilitiesAndEquity),
                'difference' => $this->decimal($totals['assets']->minus($liabilitiesAndEquity)),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function cashFlow(array $filters, User $actor): array
    {
        $range = $this->range($filters);
        $branchIds = $this->branchIds($actor, $filters['branch_id'] ?? null);
        $direct = $this->directCashFlow($range['date_from'], $range['date_to'], $branchIds);
        $indirect = $this->indirectCashFlow($range['date_from'], $range['date_to'], $branchIds, $direct);

        return [
            'statement' => 'cash_flow',
            'title' => 'Cash Flow Statement',
            'filters' => $this->publicFilters($range, $filters),
            'currency_code' => $this->tenantContext->tenant()->currency_code,
            'method' => (string) ($filters['method'] ?? 'direct'),
            'direct' => $direct,
            'indirect' => $indirect,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @param list<int> $branchIds @return array{sections: array<string, list<array<string, mixed>>>, totals: array<string, string>} */
    private function profitAndLossForRange(string $from, string $to, array $branchIds): array
    {
        $rows = $this->accountActivity($branchIds, $from, $to, ['revenue', 'expense'], false);
        $sections = [
            'revenue' => [],
            'cost_of_sales' => [],
            'operating_expenses' => [],
            'finance_and_other' => [],
            'tax' => [],
        ];
        $totals = [
            'revenue' => BigDecimal::zero(),
            'cost_of_sales' => BigDecimal::zero(),
            'operating_expenses' => BigDecimal::zero(),
            'finance_and_other' => BigDecimal::zero(),
            'tax' => BigDecimal::zero(),
        ];

        foreach ($rows as $row) {
            $debit = BigDecimal::of((string) $row->period_debit);
            $credit = BigDecimal::of((string) $row->period_credit);
            $amount = $row->account_type === 'revenue'
                ? $credit->minus($debit)
                : $debit->minus($credit);

            if ($amount->isZero()) {
                continue;
            }

            $subtype = (string) ($row->account_subtype ?? '');
            $section = match (true) {
                $row->account_type === 'revenue' => 'revenue',
                in_array($subtype, ['cost_of_sales', 'purchase_returns', 'purchase_price_variance'], true) => 'cost_of_sales',
                $subtype === 'tax_expense' => 'tax',
                in_array($subtype, ['finance_cost', 'exchange_loss', 'other_expense'], true) => 'finance_and_other',
                default => 'operating_expenses',
            };

            $sections[$section][] = [
                'account_id' => (int) $row->account_id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'account_subtype' => $row->account_subtype,
                'amount' => $this->decimal($amount),
            ];
            $totals[$section] = $totals[$section]->plus($amount);
        }

        $grossProfit = $totals['revenue']->minus($totals['cost_of_sales']);
        $operatingProfit = $grossProfit->minus($totals['operating_expenses']);
        $profitBeforeTax = $operatingProfit->minus($totals['finance_and_other']);
        $netProfit = $profitBeforeTax->minus($totals['tax']);

        return [
            'sections' => $sections,
            'totals' => [
                'revenue' => $this->decimal($totals['revenue']),
                'cost_of_sales' => $this->decimal($totals['cost_of_sales']),
                'gross_profit' => $this->decimal($grossProfit),
                'operating_expenses' => $this->decimal($totals['operating_expenses']),
                'operating_profit' => $this->decimal($operatingProfit),
                'finance_and_other' => $this->decimal($totals['finance_and_other']),
                'profit_before_tax' => $this->decimal($profitBeforeTax),
                'tax' => $this->decimal($totals['tax']),
                'net_profit' => $this->decimal($netProfit),
            ],
        ];
    }

    /** @param list<int> $branchIds @return array<string, mixed> */
    private function directCashFlow(string $from, string $to, array $branchIds): array
    {
        $opening = $this->cashBalanceBefore($from, $branchIds);
        $closing = $this->cashBalanceThrough($to, $branchIds);
        $cashLines = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.tenant_id', $this->tenantContext->id())
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.posting_date', [$from, $to])
            ->whereIn('journal_entry_lines.branch_id', $branchIds)
            ->whereIn('accounts.control_type', ['cash', 'bank'])
            ->select([
                'journal_entries.id as journal_id',
                'journal_entries.journal_type',
                'journal_entries.source_document_number',
                'journal_entries.posting_date',
                'journal_entry_lines.base_debit_amount',
                'journal_entry_lines.base_credit_amount',
            ])
            ->orderBy('journal_entries.posting_date')
            ->orderBy('journal_entries.id')
            ->get();

        $journalIds = $cashLines->pluck('journal_id')->map(static fn (mixed $id): int => (int) $id)->unique()->values()->all();
        $counterparts = $journalIds === []
            ? collect()
            : DB::table('journal_entry_lines')
                ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
                ->whereIn('journal_entry_lines.journal_entry_id', $journalIds)
                ->whereNotIn('accounts.control_type', ['cash', 'bank'])
                ->select(['journal_entry_lines.journal_entry_id', 'accounts.account_type', 'accounts.account_subtype'])
                ->get()
                ->groupBy('journal_entry_id');

        $groups = [
            'operating' => [],
            'investing' => [],
            'financing' => [],
        ];
        $totals = [
            'operating' => BigDecimal::zero(),
            'investing' => BigDecimal::zero(),
            'financing' => BigDecimal::zero(),
        ];

        foreach ($cashLines->groupBy('journal_id') as $journalId => $lines) {
            $movement = BigDecimal::zero();
            foreach ($lines as $line) {
                $movement = $movement
                    ->plus((string) $line->base_debit_amount)
                    ->minus((string) $line->base_credit_amount);
            }
            if ($movement->isZero()) {
                continue;
            }

            $counterpartRows = $counterparts->get((int) $journalId, collect());
            $category = 'operating';
            foreach ($counterpartRows as $counterpart) {
                if (in_array((string) $counterpart->account_subtype, ['fixed_asset', 'accumulated_depreciation', 'other_asset'], true)) {
                    $category = 'investing';
                    break;
                }
                if (
                    $counterpart->account_type === 'equity'
                    || in_array((string) $counterpart->account_subtype, ['cash_in_transit', 'loan', 'share_capital', 'retained_earnings', 'reserves', 'other_equity'], true)
                ) {
                    $category = 'financing';
                }
            }

            $first = $lines->first();
            $label = $this->journalTypeLabel((string) $first->journal_type);
            $existing = $groups[$category][$label] ?? BigDecimal::zero();
            $groups[$category][$label] = $existing->plus($movement);
            $totals[$category] = $totals[$category]->plus($movement);
        }

        $outputGroups = [];
        foreach ($groups as $category => $rows) {
            $outputGroups[$category] = [];
            foreach ($rows as $label => $amount) {
                $outputGroups[$category][] = ['label' => $label, 'amount' => $this->decimal($amount)];
            }
        }

        $netChange = $totals['operating']->plus($totals['investing'])->plus($totals['financing']);

        return [
            'sections' => $outputGroups,
            'totals' => [
                'operating' => $this->decimal($totals['operating']),
                'investing' => $this->decimal($totals['investing']),
                'financing' => $this->decimal($totals['financing']),
                'net_change' => $this->decimal($netChange),
                'opening_cash' => $this->decimal($opening),
                'closing_cash' => $this->decimal($closing),
                'reconciliation_difference' => $this->decimal($opening->plus($netChange)->minus($closing)),
            ],
        ];
    }

    /** @param list<int> $branchIds @param array<string, mixed> $direct @return array<string, mixed> */
    private function indirectCashFlow(string $from, string $to, array $branchIds, array $direct): array
    {
        $profit = $this->profitAndLossForRange($from, $to, $branchIds);
        $netProfit = BigDecimal::of((string) $profit['totals']['net_profit']);
        $depreciation = $this->periodAmountForSubtypes($from, $to, $branchIds, ['depreciation_expense'], 'expense');
        $arChange = $this->balanceForSubtypes($to, $branchIds, ['accounts_receivable'])
            ->minus($this->balanceForSubtypes(CarbonImmutable::parse($from)->subDay()->toDateString(), $branchIds, ['accounts_receivable']));
        $inventoryChange = $this->balanceForSubtypes($to, $branchIds, ['inventory'])
            ->minus($this->balanceForSubtypes(CarbonImmutable::parse($from)->subDay()->toDateString(), $branchIds, ['inventory']));
        $apChange = $this->creditBalanceForSubtypes($to, $branchIds, ['accounts_payable', 'goods_received_not_invoiced'])
            ->minus($this->creditBalanceForSubtypes(CarbonImmutable::parse($from)->subDay()->toDateString(), $branchIds, ['accounts_payable', 'goods_received_not_invoiced']));
        $otherWorkingCapital = $this->creditBalanceForSubtypes($to, $branchIds, ['customer_advances', 'output_tax', 'accrued_liability'])
            ->minus($this->creditBalanceForSubtypes(CarbonImmutable::parse($from)->subDay()->toDateString(), $branchIds, ['customer_advances', 'output_tax', 'accrued_liability']));

        $operating = $netProfit
            ->plus($depreciation)
            ->minus($arChange)
            ->minus($inventoryChange)
            ->plus($apChange)
            ->plus($otherWorkingCapital);
        $investing = BigDecimal::of((string) $direct['totals']['investing']);
        $financing = BigDecimal::of((string) $direct['totals']['financing']);
        $netChange = $operating->plus($investing)->plus($financing);

        return [
            'rows' => [
                ['label' => 'Net profit after tax', 'amount' => $this->decimal($netProfit)],
                ['label' => 'Depreciation and non-cash expense', 'amount' => $this->decimal($depreciation)],
                ['label' => 'Change in accounts receivable', 'amount' => $this->decimal($arChange->negated())],
                ['label' => 'Change in inventory', 'amount' => $this->decimal($inventoryChange->negated())],
                ['label' => 'Change in accounts payable and GRNI', 'amount' => $this->decimal($apChange)],
                ['label' => 'Change in other operating liabilities', 'amount' => $this->decimal($otherWorkingCapital)],
            ],
            'totals' => [
                'operating' => $this->decimal($operating),
                'investing' => $this->decimal($investing),
                'financing' => $this->decimal($financing),
                'net_change' => $this->decimal($netChange),
                'opening_cash' => (string) $direct['totals']['opening_cash'],
                'closing_cash' => (string) $direct['totals']['closing_cash'],
                'reconciliation_difference' => $this->decimal(
                    BigDecimal::of((string) $direct['totals']['opening_cash'])
                        ->plus($netChange)
                        ->minus((string) $direct['totals']['closing_cash']),
                ),
            ],
        ];
    }

    /** @param list<int> $branchIds @param list<string>|null $accountTypes */
    private function accountActivity(array $branchIds, string $dateFrom, string $dateTo, ?array $accountTypes, bool $includeClosingEntries): \Illuminate\Support\Collection
    {
        if ($branchIds === []) {
            return collect();
        }

        return DB::table('accounts')
            ->leftJoin('journal_entry_lines', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->leftJoin('journal_entries', function ($join) use ($branchIds, $dateTo, $includeClosingEntries): void {
                $join->on('journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
                    ->where('journal_entries.status', '=', 'posted')
                    ->where('journal_entries.posting_date', '<=', $dateTo)
                    ->whereIn('journal_entry_lines.branch_id', $branchIds);
                if (!$includeClosingEntries) {
                    $join->whereNotIn('journal_entries.journal_type', ['year_end_closing', 'year_end_closing_reversal']);
                }
            })
            ->where('accounts.tenant_id', $this->tenantContext->id())
            ->where('accounts.is_group', false)
            ->when($accountTypes !== null, static fn (Builder $query): Builder => $query->whereIn('accounts.account_type', $accountTypes))
            ->groupBy(['accounts.id', 'accounts.code', 'accounts.name', 'accounts.account_type', 'accounts.account_subtype'])
            ->orderBy('accounts.code')
            ->select([
                'accounts.id as account_id',
                'accounts.code',
                'accounts.name',
                'accounts.account_type',
                'accounts.account_subtype',
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.posting_date < ? THEN journal_entry_lines.base_debit_amount ELSE 0 END), 0) as opening_debit', [$dateFrom])
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.posting_date < ? THEN journal_entry_lines.base_credit_amount ELSE 0 END), 0) as opening_credit', [$dateFrom])
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.posting_date BETWEEN ? AND ? THEN journal_entry_lines.base_debit_amount ELSE 0 END), 0) as period_debit', [$dateFrom, $dateTo])
            ->selectRaw('COALESCE(SUM(CASE WHEN journal_entries.posting_date BETWEEN ? AND ? THEN journal_entry_lines.base_credit_amount ELSE 0 END), 0) as period_credit', [$dateFrom, $dateTo])
            ->get();
    }

    /** @param list<int> $branchIds @param list<string> $types */
    private function accountBalancesAsOf(string $asOf, array $branchIds, array $types): \Illuminate\Support\Collection
    {
        if ($branchIds === []) {
            return collect();
        }

        return DB::table('accounts')
            ->join('journal_entry_lines', 'journal_entry_lines.account_id', '=', 'accounts.id')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.posting_date', '<=', $asOf)
            ->whereIn('journal_entry_lines.branch_id', $branchIds)
            ->whereIn('accounts.account_type', $types)
            ->where('accounts.tenant_id', $this->tenantContext->id())
            ->where('accounts.is_group', false)
            ->groupBy(['accounts.id', 'accounts.code', 'accounts.name', 'accounts.account_type', 'accounts.account_subtype'])
            ->orderBy('accounts.code')
            ->select(['accounts.id as account_id', 'accounts.code', 'accounts.name', 'accounts.account_type', 'accounts.account_subtype'])
            ->selectRaw('SUM(journal_entry_lines.base_debit_amount) as base_debit')
            ->selectRaw('SUM(journal_entry_lines.base_credit_amount) as base_credit')
            ->get();
    }

    /** @param list<int> $branchIds */
    private function unclosedCurrentEarnings(string $asOf, array $branchIds): BigDecimal
    {
        $fiscalYear = DB::table('fiscal_years')
            ->where('tenant_id', $this->tenantContext->id())
            ->whereDate('start_date', '<=', $asOf)
            ->whereDate('end_date', '>=', $asOf)
            ->first();

        if ($fiscalYear === null) {
            return BigDecimal::zero();
        }

        $closingExists = DB::table('journal_entries')
            ->where('tenant_id', $this->tenantContext->id())
            ->where('status', 'posted')
            ->where('journal_type', 'year_end_closing')
            ->whereBetween('posting_date', [(string) $fiscalYear->start_date, $asOf])
            ->whereIn('branch_id', $branchIds)
            ->exists();

        if ($closingExists) {
            return BigDecimal::zero();
        }

        $profit = $this->profitAndLossForRange(
            (string) $fiscalYear->start_date,
            $asOf,
            $branchIds,
        );

        return BigDecimal::of((string) $profit['totals']['net_profit']);
    }

    /** @param array<string, mixed> $filters @return array{date_from: string, date_to: string} */
    private function range(array $filters): array
    {
        $timezone = $this->tenantContext->tenant()->timezone;
        $today = CarbonImmutable::now($timezone)->toDateString();
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));

        if ($from === '' || $to === '') {
            $period = AccountingPeriod::query()
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->first();
            $from = $from !== '' ? $from : ($period?->start_date?->toDateString() ?? CarbonImmutable::parse($today)->startOfMonth()->toDateString());
            $to = $to !== '' ? $to : ($period?->end_date?->toDateString() ?? CarbonImmutable::parse($today)->endOfMonth()->toDateString());
        }

        if ($from > $to) {
            throw ValidationException::withMessages(['date_to' => ['The ending date must be on or after the starting date.']]);
        }

        return ['date_from' => $from, 'date_to' => $to];
    }

    /** @param array<string, mixed> $filters */
    private function asOfDate(array $filters): string
    {
        $value = trim((string) ($filters['as_of_date'] ?? $filters['date_to'] ?? ''));

        return $value !== ''
            ? CarbonImmutable::parse($value)->toDateString()
            : CarbonImmutable::now($this->tenantContext->tenant()->timezone)->toDateString();
    }

    /** @param array{date_from: string, date_to: string} $range @return array{date_from: string, date_to: string}|null */
    private function comparisonRange(array $range, string $comparison): ?array
    {
        if ($comparison === 'none' || $comparison === '') {
            return null;
        }

        $from = CarbonImmutable::parse($range['date_from']);
        $to = CarbonImmutable::parse($range['date_to']);

        if ($comparison === 'previous_year') {
            return ['date_from' => $from->subYear()->toDateString(), 'date_to' => $to->subYear()->toDateString()];
        }

        $days = $from->diffInDays($to) + 1;
        $previousTo = $from->subDay();

        return ['date_from' => $previousTo->subDays($days - 1)->toDateString(), 'date_to' => $previousTo->toDateString()];
    }

    /** @param list<int> $branchIds */
    private function cashBalanceBefore(string $date, array $branchIds): BigDecimal
    {
        return $this->cashBalance(CarbonImmutable::parse($date)->subDay()->toDateString(), $branchIds);
    }

    /** @param list<int> $branchIds */
    private function cashBalanceThrough(string $date, array $branchIds): BigDecimal
    {
        return $this->cashBalance($date, $branchIds);
    }

    /** @param list<int> $branchIds */
    private function cashBalance(string $date, array $branchIds): BigDecimal
    {
        if ($branchIds === []) {
            return BigDecimal::zero();
        }

        $row = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.tenant_id', $this->tenantContext->id())
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.posting_date', '<=', $date)
            ->whereIn('journal_entry_lines.branch_id', $branchIds)
            ->whereIn('accounts.control_type', ['cash', 'bank'])
            ->selectRaw('COALESCE(SUM(journal_entry_lines.base_debit_amount), 0) as debit')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.base_credit_amount), 0) as credit')
            ->first();

        return BigDecimal::of((string) ($row->debit ?? '0'))->minus((string) ($row->credit ?? '0'));
    }

    /** @param list<int> $branchIds @param list<string> $subtypes */
    private function balanceForSubtypes(string $date, array $branchIds, array $subtypes): BigDecimal
    {
        return $this->subtypeBalance($date, $branchIds, $subtypes, false);
    }

    /** @param list<int> $branchIds @param list<string> $subtypes */
    private function creditBalanceForSubtypes(string $date, array $branchIds, array $subtypes): BigDecimal
    {
        return $this->subtypeBalance($date, $branchIds, $subtypes, true);
    }

    /** @param list<int> $branchIds @param list<string> $subtypes */
    private function subtypeBalance(string $date, array $branchIds, array $subtypes, bool $creditNormal): BigDecimal
    {
        if ($branchIds === []) {
            return BigDecimal::zero();
        }

        $row = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.tenant_id', $this->tenantContext->id())
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.posting_date', '<=', $date)
            ->whereIn('journal_entry_lines.branch_id', $branchIds)
            ->whereIn('accounts.account_subtype', $subtypes)
            ->selectRaw('COALESCE(SUM(journal_entry_lines.base_debit_amount), 0) as debit')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.base_credit_amount), 0) as credit')
            ->first();
        $debit = BigDecimal::of((string) ($row->debit ?? '0'));
        $credit = BigDecimal::of((string) ($row->credit ?? '0'));

        return $creditNormal ? $credit->minus($debit) : $debit->minus($credit);
    }

    /** @param list<int> $branchIds @param list<string> $subtypes */
    private function periodAmountForSubtypes(string $from, string $to, array $branchIds, array $subtypes, string $accountType): BigDecimal
    {
        if ($branchIds === []) {
            return BigDecimal::zero();
        }

        $row = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.tenant_id', $this->tenantContext->id())
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.posting_date', [$from, $to])
            ->whereIn('journal_entry_lines.branch_id', $branchIds)
            ->whereIn('accounts.account_subtype', $subtypes)
            ->selectRaw('COALESCE(SUM(journal_entry_lines.base_debit_amount), 0) as debit')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.base_credit_amount), 0) as credit')
            ->first();
        $debit = BigDecimal::of((string) ($row->debit ?? '0'));
        $credit = BigDecimal::of((string) ($row->credit ?? '0'));

        return $accountType === 'revenue' ? $credit->minus($debit) : $debit->minus($credit);
    }

    /** @return list<int> */
    private function branchIds(User $actor, mixed $branchId): array
    {
        $ids = $this->branchAccessService->accessibleBranches($actor, false)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        if ($branchId === null || $branchId === '') {
            return $ids;
        }

        $selected = (int) $branchId;
        if (!in_array($selected, $ids, true)) {
            throw ValidationException::withMessages(['branch_id' => ['The selected branch is not accessible.']]);
        }

        return [$selected];
    }

    /** @param array{date_from: string, date_to: string} $range @param array<string, mixed> $filters @return array<string, mixed> */
    private function publicFilters(array $range, array $filters): array
    {
        return [
            'date_from' => $range['date_from'],
            'date_to' => $range['date_to'],
            'branch_id' => $filters['branch_id'] ?? null,
            'comparison' => $filters['comparison'] ?? 'none',
            'method' => $filters['method'] ?? 'direct',
        ];
    }

    private function journalTypeLabel(string $type): string
    {
        return ucwords(str_replace('_', ' ', preg_replace('/_reversal$/', ' reversal', $type) ?? $type));
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HalfUp)->__toString();
    }
}
