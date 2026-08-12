<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\AccountingPeriod;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class FinancialControlDashboardService
{
    public function __construct(
        private readonly FinancialStatementService $statements,
        private readonly FinancialReconciliationService $reconciliations,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(User $actor, ?int $branchId = null): array
    {
        $today = CarbonImmutable::now($this->tenantContext->tenant()->timezone)->toDateString();
        $period = AccountingPeriod::query()
            ->with('fiscalYear')
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();
        $dateFrom = $period?->start_date?->toDateString() ?? CarbonImmutable::parse($today)->startOfMonth()->toDateString();
        $dateTo = $today;
        $pnl = $this->statements->profitAndLoss([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'branch_id' => $branchId,
            'comparison' => 'previous_period',
        ], $actor);
        $balanceSheet = $this->statements->balanceSheet([
            'as_of_date' => $today,
            'branch_id' => $branchId,
        ], $actor);
        $cashFlow = $this->statements->cashFlow([
            'date_from' => $dateFrom,
            'date_to' => $today,
            'branch_id' => $branchId,
            'method' => 'direct',
        ], $actor);
        $reconciliation = $this->reconciliations->build($today, $actor, $branchId);
        $currentAssets = $this->sumStatementRows(
            $balanceSheet['sections']['assets'],
            [
                'cash',
                'bank',
                'cash_in_transit',
                'accounts_receivable',
                'supplier_advances',
                'inventory',
                'input_tax',
                'prepaid_expense',
            ],
        );
        $currentLiabilities = $this->sumStatementRows(
            $balanceSheet['sections']['liabilities'],
            [
                'accounts_payable',
                'goods_received_not_invoiced',
                'customer_advances',
                'output_tax',
                'accrued_liability',
            ],
        );
        $workingCapital = $currentAssets->minus($currentLiabilities);
        $currentRatio = $currentLiabilities->isZero()
            ? null
            : $currentAssets->dividedBy(
                $currentLiabilities,
                6,
                RoundingMode::HalfUp,
            )->__toString();
        $unposted = DB::table('journal_entries')
            ->where('tenant_id', $this->tenantContext->id())
            ->whereIn('status', ['draft', 'approved'])
            ->whereBetween('posting_date', [$dateFrom, $dateTo])
            ->when(
                $branchId !== null,
                static fn ($query) => $query->where(
                    'branch_id',
                    $branchId,
                ),
            )
            ->count();

        return [
            'as_of_date' => $today,
            'period' => $period === null ? null : [
                'id' => (int) $period->getKey(),
                'code' => $period->code,
                'name' => $period->name,
                'status' => $period->status,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
            ],
            'currency_code' => $this->tenantContext->tenant()->currency_code,
            'metrics' => [
                'net_profit' => $pnl['totals']['net_profit'],
                'total_assets' => $balanceSheet['totals']['assets'],
                'cash_and_bank' => $cashFlow['direct']['totals']['closing_cash'],
                'working_capital' => $workingCapital->toScale(
                    6,
                    RoundingMode::HalfUp,
                )->__toString(),
                'current_ratio' => $currentRatio,
                'reconciliation_difference' => $reconciliation['summary']['total_absolute_difference'],
                'unreconciled_bank_accounts' => $reconciliation['summary']['unreconciled_bank_accounts'],
                'unposted_journals' => $unposted,
            ],
            'reconciliation' => $reconciliation,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<string> $subtypes
     */
    private function sumStatementRows(array $rows, array $subtypes): BigDecimal
    {
        return collect($rows)->reduce(
            static function (BigDecimal $carry, array $row) use ($subtypes): BigDecimal {
                if (!in_array((string) ($row['account_subtype'] ?? ''), $subtypes, true)) {
                    return $carry;
                }

                return $carry->plus((string) $row['amount']);
            },
            BigDecimal::zero(),
        );
    }
}
