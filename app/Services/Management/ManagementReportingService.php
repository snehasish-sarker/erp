<?php

declare(strict_types=1);

namespace App\Services\Management;

use App\Models\ManagementBudget;
use App\Models\User;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManagementReportingService
{
    private const SCALE = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly FinancialStatementService $financialStatementService,
    ) {
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function dashboard(array $filters, User $actor): array
    {
        $context = $this->context($filters, $actor);
        $statementFilters = [
            'date_from' => $context['date_from'],
            'date_to' => $context['date_to'],
            'branch_id' => $context['branch_id'],
            'comparison' => 'previous_period',
        ];
        $profit = $this->financialStatementService->profitAndLoss($statementFilters, $actor);
        $balance = $this->financialStatementService->balanceSheet([
            'as_of_date' => $context['date_to'],
            'branch_id' => $context['branch_id'],
        ], $actor);
        $cash = $this->financialStatementService->cashFlow([
            'date_from' => $context['date_from'],
            'date_to' => $context['date_to'],
            'branch_id' => $context['branch_id'],
            'method' => 'direct',
        ], $actor);

        $revenue = BigDecimal::of((string) $profit['totals']['revenue']);
        $grossProfit = BigDecimal::of((string) $profit['totals']['gross_profit']);
        $netProfit = BigDecimal::of((string) $profit['totals']['net_profit']);
        $comparisonRevenue = BigDecimal::of((string) ($profit['comparison']['totals']['revenue'] ?? '0'));
        $comparisonNetProfit = BigDecimal::of((string) ($profit['comparison']['totals']['net_profit'] ?? '0'));

        return [
            'filters' => $context,
            'currency_code' => strtoupper((string) $this->tenantContext->tenant()->currency_code),
            'kpis' => [
                'revenue' => $this->decimal($revenue),
                'revenue_change_percent' => $this->percentageChange($revenue, $comparisonRevenue),
                'gross_profit' => $this->decimal($grossProfit),
                'gross_margin_percent' => $this->percentage($grossProfit, $revenue),
                'net_profit' => $this->decimal($netProfit),
                'net_profit_change_percent' => $this->percentageChange($netProfit, $comparisonNetProfit),
                'net_margin_percent' => $this->percentage($netProfit, $revenue),
                'total_assets' => (string) $balance['totals']['assets'],
                'total_liabilities' => (string) $balance['totals']['liabilities'],
                'closing_cash' => (string) $cash['totals']['closing_cash'],
            ],
            'branch_profitability' => array_slice($this->branchProfitability($context, $actor), 0, 8),
            'gross_margin_trend' => $this->grossMargin($context, $actor),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function branchProfitability(array $filters, User $actor): array
    {
        $context = $this->context($filters, $actor);
        $branchIds = $this->branchIds($actor, $context['branch_id']);

        if ($branchIds === []) {
            return [];
        }

        $rows = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->join('branches', 'branches.id', '=', 'journal_entry_lines.branch_id')
            ->where('journal_entries.tenant_id', $this->tenantContext->id())
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.posting_date', [$context['date_from'], $context['date_to']])
            ->whereIn('journal_entry_lines.branch_id', $branchIds)
            ->whereIn('accounts.account_type', ['revenue', 'expense'])
            ->groupBy('branches.id', 'branches.code', 'branches.name')
            ->select(['branches.id', 'branches.code', 'branches.name'])
            ->selectRaw("COALESCE(SUM(CASE WHEN accounts.account_type = 'revenue' THEN journal_entry_lines.base_credit_amount - journal_entry_lines.base_debit_amount ELSE 0 END), 0) AS revenue")
            ->selectRaw("COALESCE(SUM(CASE WHEN accounts.account_type = 'expense' THEN journal_entry_lines.base_debit_amount - journal_entry_lines.base_credit_amount ELSE 0 END), 0) AS expenses")
            ->orderByDesc('revenue')
            ->get();

        return $rows->map(function (object $row): array {
            $revenue = BigDecimal::of((string) $row->revenue);
            $expenses = BigDecimal::of((string) $row->expenses);
            $profit = $revenue->minus($expenses);

            return [
                'branch_id' => (int) $row->id,
                'branch_code' => (string) $row->code,
                'branch_name' => (string) $row->name,
                'revenue' => $this->decimal($revenue),
                'expenses' => $this->decimal($expenses),
                'profit' => $this->decimal($profit),
                'margin_percent' => $this->percentage($profit, $revenue),
            ];
        })->values()->all();
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function budgetVsActual(array $filters, User $actor): array
    {
        $budgetId = (int) ($filters['budget_id'] ?? 0);
        $budget = ManagementBudget::query()->whereKey($budgetId)->first();

        if (!$budget instanceof ManagementBudget) {
            throw ValidationException::withMessages(['budget_id' => ['Select a valid management budget.']]);
        }

        $accessible = $this->branchIds($actor, (int) $budget->branch_id);
        if ($accessible === []) {
            throw ValidationException::withMessages(['budget_id' => ['The selected budget is outside your branch access.']]);
        }

        $budget->load(['branch:id,code,name', 'fiscalYear:id,name,code,start_date,end_date', 'lines.account:id,code,name,account_type']);
        $from = $budget->fiscalYear?->start_date?->format('Y-m-d');
        $to = $budget->fiscalYear?->end_date?->format('Y-m-d');

        if ($from === null || $to === null) {
            throw ValidationException::withMessages(['budget_id' => ['The selected budget does not retain a valid fiscal year.']]);
        }

        $actualRows = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.tenant_id', $this->tenantContext->id())
            ->where('journal_entries.status', 'posted')
            ->where('journal_entry_lines.branch_id', $budget->branch_id)
            ->whereBetween('journal_entries.posting_date', [$from, $to])
            ->whereIn('accounts.account_type', ['revenue', 'expense'])
            ->groupBy('journal_entry_lines.account_id', 'accounts.account_type')
            ->groupByRaw('MONTH(journal_entries.posting_date)')
            ->select(['journal_entry_lines.account_id', 'accounts.account_type'])
            ->selectRaw('MONTH(journal_entries.posting_date) AS month_number')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.base_debit_amount), 0) AS debit')
            ->selectRaw('COALESCE(SUM(journal_entry_lines.base_credit_amount), 0) AS credit')
            ->get();

        $actual = [];
        foreach ($actualRows as $row) {
            $value = $row->account_type === 'revenue'
                ? BigDecimal::of((string) $row->credit)->minus((string) $row->debit)
                : BigDecimal::of((string) $row->debit)->minus((string) $row->credit);
            $actual[(int) $row->account_id.':'.(int) $row->month_number] = $value;
        }

        $rows = [];
        $budgetTotal = BigDecimal::zero();
        $actualTotal = BigDecimal::zero();

        foreach ($budget->lines as $line) {
            $planned = BigDecimal::of((string) $line->amount);
            $actualAmount = $actual[(int) $line->account_id.':'.(int) $line->month_number] ?? BigDecimal::zero();
            $variance = $line->account?->account_type === 'revenue'
                ? $actualAmount->minus($planned)
                : $planned->minus($actualAmount);
            $budgetTotal = $budgetTotal->plus($planned);
            $actualTotal = $actualTotal->plus($actualAmount);

            $rows[] = [
                'account_id' => (int) $line->account_id,
                'account_code' => $line->account?->code ?? '',
                'account_name' => $line->account?->name ?? '',
                'account_type' => $line->account?->account_type ?? '',
                'month_number' => (int) $line->month_number,
                'budget_amount' => $this->decimal($planned),
                'actual_amount' => $this->decimal($actualAmount),
                'variance_amount' => $this->decimal($variance),
                'variance_percent' => $this->percentage($variance, $planned),
            ];
        }

        return [
            'budget' => [
                'id' => (int) $budget->getKey(),
                'name' => $budget->name,
                'status' => $budget->status,
                'branch' => $budget->branch === null ? null : [
                    'id' => (int) $budget->branch->getKey(),
                    'code' => $budget->branch->code,
                    'name' => $budget->branch->name,
                ],
                'fiscal_year' => $budget->fiscalYear === null ? null : [
                    'id' => (int) $budget->fiscalYear->getKey(),
                    'name' => $budget->fiscalYear->name,
                    'start_date' => $from,
                    'end_date' => $to,
                ],
            ],
            'currency_code' => $budget->currency_code,
            'rows' => $rows,
            'totals' => [
                'budget' => $this->decimal($budgetTotal),
                'actual' => $this->decimal($actualTotal),
                'difference' => $this->decimal($actualTotal->minus($budgetTotal)),
            ],
        ];
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function productProfitability(array $filters, User $actor): array
    {
        $context = $this->context($filters, $actor);
        $branchIds = $this->branchIds($actor, $context['branch_id']);
        $limit = $context['limit'];
        if ($branchIds === []) {
            return [];
        }

        $sales = DB::table('sales_invoice_lines')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_lines.sales_invoice_id')
            ->where('sales_invoices.tenant_id', $this->tenantContext->id())
            ->where('sales_invoices.status', 'posted')
            ->whereBetween('sales_invoices.posting_date', [$context['date_from'], $context['date_to']])
            ->whereIn('sales_invoices.branch_id', $branchIds)
            ->groupBy('sales_invoice_lines.product_id', 'sales_invoice_lines.product_sku', 'sales_invoice_lines.product_name')
            ->select(['sales_invoice_lines.product_id', 'sales_invoice_lines.product_sku', 'sales_invoice_lines.product_name'])
            ->selectRaw('SUM(sales_invoice_lines.line_total * sales_invoices.exchange_rate) AS revenue')
            ->selectRaw('SUM(sales_invoice_lines.total_cost) AS cost')
            ->get()->keyBy('product_id');

        $credits = DB::table('customer_credit_note_lines')
            ->join('customer_credit_notes', 'customer_credit_notes.id', '=', 'customer_credit_note_lines.customer_credit_note_id')
            ->where('customer_credit_notes.tenant_id', $this->tenantContext->id())
            ->where('customer_credit_notes.status', 'posted')
            ->whereBetween('customer_credit_notes.posting_date', [$context['date_from'], $context['date_to']])
            ->whereIn('customer_credit_notes.branch_id', $branchIds)
            ->groupBy('customer_credit_note_lines.product_id')
            ->select('customer_credit_note_lines.product_id')
            ->selectRaw('SUM(customer_credit_note_lines.line_total * customer_credit_notes.exchange_rate) AS revenue_credit')
            ->selectRaw('SUM(customer_credit_note_lines.total_cost) AS cost_credit')
            ->get()->keyBy('product_id');

        $rows = [];
        foreach ($sales as $productId => $row) {
            $credit = $credits->get($productId);
            $revenue = BigDecimal::of((string) $row->revenue)->minus((string) ($credit->revenue_credit ?? '0'));
            $cost = BigDecimal::of((string) $row->cost)->minus((string) ($credit->cost_credit ?? '0'));
            $margin = $revenue->minus($cost);
            $rows[] = [
                'product_id' => (int) $row->product_id,
                'product_sku' => (string) $row->product_sku,
                'product_name' => (string) $row->product_name,
                'revenue' => $this->decimal($revenue),
                'cost' => $this->decimal($cost),
                'gross_profit' => $this->decimal($margin),
                'margin_percent' => $this->percentage($margin, $revenue),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => (float) $b['gross_profit'] <=> (float) $a['gross_profit']);
        return array_slice($rows, 0, $limit);
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function customerProfitability(array $filters, User $actor): array
    {
        $context = $this->context($filters, $actor);
        $branchIds = $this->branchIds($actor, $context['branch_id']);
        if ($branchIds === []) {
            return [];
        }

        $sales = DB::table('sales_invoice_lines')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_lines.sales_invoice_id')
            ->join('customers', 'customers.id', '=', 'sales_invoices.customer_id')
            ->where('sales_invoices.tenant_id', $this->tenantContext->id())
            ->where('sales_invoices.status', 'posted')
            ->whereBetween('sales_invoices.posting_date', [$context['date_from'], $context['date_to']])
            ->whereIn('sales_invoices.branch_id', $branchIds)
            ->groupBy('customers.id', 'customers.code', 'customers.name')
            ->select(['customers.id', 'customers.code', 'customers.name'])
            ->selectRaw('SUM(sales_invoice_lines.line_total * sales_invoices.exchange_rate) AS revenue')
            ->selectRaw('SUM(sales_invoice_lines.total_cost) AS cost')
            ->get()->keyBy('id');

        $credits = DB::table('customer_credit_notes')
            ->join('customer_credit_note_lines', 'customer_credit_note_lines.customer_credit_note_id', '=', 'customer_credit_notes.id')
            ->where('customer_credit_notes.tenant_id', $this->tenantContext->id())
            ->where('customer_credit_notes.status', 'posted')
            ->whereBetween('customer_credit_notes.posting_date', [$context['date_from'], $context['date_to']])
            ->whereIn('customer_credit_notes.branch_id', $branchIds)
            ->groupBy('customer_credit_notes.customer_id')
            ->select('customer_credit_notes.customer_id')
            ->selectRaw('SUM(customer_credit_note_lines.line_total * customer_credit_notes.exchange_rate) AS revenue_credit')
            ->selectRaw('SUM(customer_credit_note_lines.total_cost) AS cost_credit')
            ->get()->keyBy('customer_id');

        $rows = [];
        foreach ($sales as $customerId => $row) {
            $credit = $credits->get($customerId);
            $revenue = BigDecimal::of((string) $row->revenue)->minus((string) ($credit->revenue_credit ?? '0'));
            $cost = BigDecimal::of((string) $row->cost)->minus((string) ($credit->cost_credit ?? '0'));
            $profit = $revenue->minus($cost);
            $rows[] = [
                'customer_id' => (int) $row->id,
                'customer_code' => (string) $row->code,
                'customer_name' => (string) $row->name,
                'revenue' => $this->decimal($revenue),
                'cost' => $this->decimal($cost),
                'gross_profit' => $this->decimal($profit),
                'margin_percent' => $this->percentage($profit, $revenue),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => (float) $b['gross_profit'] <=> (float) $a['gross_profit']);
        return array_slice($rows, 0, $context['limit']);
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function supplierSpend(array $filters, User $actor): array
    {
        $context = $this->context($filters, $actor);
        $branchIds = $this->branchIds($actor, $context['branch_id']);
        if ($branchIds === []) {
            return [];
        }

        $invoices = DB::table('supplier_invoices')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_invoices.supplier_id')
            ->where('supplier_invoices.tenant_id', $this->tenantContext->id())
            ->where('supplier_invoices.status', 'posted')
            ->whereBetween('supplier_invoices.posting_date', [$context['date_from'], $context['date_to']])
            ->whereIn('supplier_invoices.branch_id', $branchIds)
            ->groupBy('suppliers.id', 'suppliers.code', 'suppliers.name')
            ->select(['suppliers.id', 'suppliers.code', 'suppliers.name'])
            ->selectRaw('SUM(supplier_invoices.total_amount * supplier_invoices.exchange_rate) AS gross_spend')
            ->get()->keyBy('id');

        $debits = DB::table('supplier_debit_notes')
            ->where('tenant_id', $this->tenantContext->id())
            ->where('status', 'posted')
            ->whereBetween('posting_date', [$context['date_from'], $context['date_to']])
            ->whereIn('branch_id', $branchIds)
            ->groupBy('supplier_id')
            ->select('supplier_id')
            ->selectRaw('SUM(total_amount * exchange_rate) AS debit_amount')
            ->get()->keyBy('supplier_id');

        $rows = [];
        foreach ($invoices as $supplierId => $row) {
            $gross = BigDecimal::of((string) $row->gross_spend);
            $debit = BigDecimal::of((string) ($debits->get($supplierId)->debit_amount ?? '0'));
            $rows[] = [
                'supplier_id' => (int) $row->id,
                'supplier_code' => (string) $row->code,
                'supplier_name' => (string) $row->name,
                'gross_spend' => $this->decimal($gross),
                'debit_notes' => $this->decimal($debit),
                'net_spend' => $this->decimal($gross->minus($debit)),
            ];
        }
        usort($rows, static fn (array $a, array $b): int => (float) $b['net_spend'] <=> (float) $a['net_spend']);
        return array_slice($rows, 0, $context['limit']);
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function grossMargin(array $filters, User $actor): array
    {
        $context = $this->context($filters, $actor);
        $branchIds = $this->branchIds($actor, $context['branch_id']);
        if ($branchIds === []) {
            return [];
        }

        $sales = DB::table('sales_invoice_lines')
            ->join('sales_invoices', 'sales_invoices.id', '=', 'sales_invoice_lines.sales_invoice_id')
            ->where('sales_invoices.tenant_id', $this->tenantContext->id())
            ->where('sales_invoices.status', 'posted')
            ->whereBetween('sales_invoices.posting_date', [$context['date_from'], $context['date_to']])
            ->whereIn('sales_invoices.branch_id', $branchIds)
            ->groupByRaw("DATE_FORMAT(sales_invoices.posting_date, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(sales_invoices.posting_date, '%Y-%m') AS period")
            ->selectRaw('SUM(sales_invoice_lines.line_total * sales_invoices.exchange_rate) AS revenue')
            ->selectRaw('SUM(sales_invoice_lines.total_cost) AS cost')
            ->orderBy('period')
            ->get()->keyBy('period');

        $credits = DB::table('customer_credit_note_lines')
            ->join('customer_credit_notes', 'customer_credit_notes.id', '=', 'customer_credit_note_lines.customer_credit_note_id')
            ->where('customer_credit_notes.tenant_id', $this->tenantContext->id())
            ->where('customer_credit_notes.status', 'posted')
            ->whereBetween('customer_credit_notes.posting_date', [$context['date_from'], $context['date_to']])
            ->whereIn('customer_credit_notes.branch_id', $branchIds)
            ->groupByRaw("DATE_FORMAT(customer_credit_notes.posting_date, '%Y-%m')")
            ->selectRaw("DATE_FORMAT(customer_credit_notes.posting_date, '%Y-%m') AS period")
            ->selectRaw('SUM(customer_credit_note_lines.line_total * customer_credit_notes.exchange_rate) AS revenue_credit')
            ->selectRaw('SUM(customer_credit_note_lines.total_cost) AS cost_credit')
            ->get()->keyBy('period');

        $periods = $sales->keys()->merge($credits->keys())->unique()->sort()->values();
        return $periods->map(function (mixed $period) use ($sales, $credits): array {
            $sale = $sales->get($period);
            $credit = $credits->get($period);
            $revenue = BigDecimal::of((string) ($sale->revenue ?? '0'))->minus((string) ($credit->revenue_credit ?? '0'));
            $cost = BigDecimal::of((string) ($sale->cost ?? '0'))->minus((string) ($credit->cost_credit ?? '0'));
            $profit = $revenue->minus($cost);
            return [
                'period' => (string) $period,
                'revenue' => $this->decimal($revenue),
                'cost' => $this->decimal($cost),
                'gross_profit' => $this->decimal($profit),
                'margin_percent' => $this->percentage($profit, $revenue),
            ];
        })->all();
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function context(array $filters, User $actor): array
    {
        $timezone = $this->tenantContext->tenant()->timezone;
        $today = CarbonImmutable::now($timezone);
        $from = trim((string) ($filters['date_from'] ?? ''));
        $to = trim((string) ($filters['date_to'] ?? ''));
        if ($from === '') {
            $from = $today->startOfMonth()->toDateString();
        }
        if ($to === '') {
            $to = $today->toDateString();
        }
        if ($from > $to) {
            throw ValidationException::withMessages(['date_to' => ['The ending date must be on or after the starting date.']]);
        }

        $branchId = isset($filters['branch_id']) && $filters['branch_id'] !== '' ? (int) $filters['branch_id'] : null;
        $this->branchIds($actor, $branchId);

        return [
            'date_from' => CarbonImmutable::parse($from)->toDateString(),
            'date_to' => CarbonImmutable::parse($to)->toDateString(),
            'branch_id' => $branchId,
            'budget_id' => isset($filters['budget_id']) ? (int) $filters['budget_id'] : null,
            'limit' => max(10, min(500, (int) ($filters['limit'] ?? 50))),
        ];
    }

    /** @return list<int> */
    private function branchIds(User $actor, ?int $branchId): array
    {
        $ids = $this->branchAccessService->accessibleBranches($actor, false)
            ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
        if ($branchId === null) {
            return $ids;
        }
        if (!in_array($branchId, $ids, true)) {
            throw ValidationException::withMessages(['branch_id' => ['The selected branch is outside your access.']]);
        }
        return [$branchId];
    }

    private function decimal(BigDecimal $value): string
    {
        return $value->toScale(self::SCALE, RoundingMode::HALF_UP)->__toString();
    }

    private function percentage(BigDecimal $part, BigDecimal $whole): string
    {
        if ($whole->isZero()) {
            return '0.00';
        }
        return $part->multipliedBy('100')->dividedBy($whole, 2, RoundingMode::HALF_UP)->__toString();
    }

    private function percentageChange(BigDecimal $current, BigDecimal $previous): string
    {
        if ($previous->isZero()) {
            return $current->isZero() ? '0.00' : '100.00';
        }
        return $current->minus($previous)->multipliedBy('100')->dividedBy($previous->abs(), 2, RoundingMode::HALF_UP)->__toString();
    }
}