<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerOpenItem;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\AccountsReceivableAgingBucketRegistry;
use App\Support\Accounting\AccountsReceivableRegistry;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use LogicException;

final class AccountsReceivableAgingService
{
    private const MONEY_SCALE = 6;

    /** @var list<string> */
    private const AGING_SORTS = [
        'customer_name',
        'total_receivable',
        'unapplied_credit',
        'net_outstanding',
        'ledger_balance',
        'difference',
        'current',
        'days_1_30',
        'days_31_60',
        'days_61_90',
        'days_91_120',
        'days_over_120',
    ];

    /** @var list<string> */
    private const OPEN_INVOICE_SORTS = [
        'customer_name',
        'document_number',
        'document_date',
        'due_date',
        'original_amount',
        'outstanding_amount',
        'days_overdue',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly AccountsReceivableAgingBucketRegistry $bucketRegistry,
        private readonly AccountsReceivableRegistry $accountsReceivableRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function normalizeExportFilters(
        array $filters,
        User $actor,
    ): array {
        return $this->agingContext(
            filters: $filters,
            actor: $actor,
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function normalizeOpenInvoiceExportFilters(
        array $filters,
        User $actor,
        bool $overdueOnly,
    ): array {
        return [
            ...$this->openInvoiceContext(
                filters: $filters,
                actor: $actor,
            ),
            'overdue_only' => $overdueOnly,
        ];
    }

    /** @param array<string, mixed> $filters */
    public function exportSummaryTotalRows(
        array $filters,
        User $actor,
    ): int {
        $context = $this->agingContext($filters, $actor);

        return DB::query()
            ->fromSub(
                $this->summaryQuery($context, $actor),
                'ar_aging_summary',
            )
            ->count();
    }

    /**
     * @param array<string, mixed> $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function exportSummaryRows(
        array $filters,
        User $actor,
    ): LazyCollection {
        $context = $this->agingContext($filters, $actor);

        return $this->summaryQuery($context, $actor)
            ->orderBy(
                $this->summarySortColumn((string) $context['sort']),
                (string) $context['direction'],
            )
            ->orderBy('customers.name')
            ->orderBy('customer_universe.customer_id')
            ->cursor()
            ->map(
                fn (object $row): array =>
                    $this->normalizeSummaryRow($row),
            );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildSummary(
        array $filters,
        User $actor,
    ): array {
        $context = $this->agingContext($filters, $actor);
        $query = $this->summaryQuery($context, $actor);

        /** @var LengthAwarePaginator<object> $paginator */
        $paginator = (clone $query)
            ->orderBy(
                $this->summarySortColumn((string) $context['sort']),
                (string) $context['direction'],
            )
            ->orderBy('customers.name')
            ->orderBy('customer_universe.customer_id')
            ->paginate((int) $context['per_page'])
            ->withQueryString();

        return [
            'filters' => $context,
            'base_currency_code' => $this->baseCurrencyCode(),
            'buckets' => $this->bucketRegistry->options(),
            'dashboard' => $this->dashboard(
                context: $context,
                actor: $actor,
            ),
            'totals' => $this->summaryTotals(clone $query),
            'customers' => [
                'data' => collect($paginator->items())
                    ->map(
                        fn (object $row): array =>
                            $this->normalizeSummaryRow($row),
                    )
                    ->values()
                    ->all(),
                'meta' => $this->paginationMeta($paginator),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildPrintableSummary(
        array $filters,
        User $actor,
    ): array {
        $context = $this->agingContext($filters, $actor);
        $query = $this->summaryQuery($context, $actor);

        return [
            'filters' => $context,
            'base_currency_code' => $this->baseCurrencyCode(),
            'buckets' => $this->bucketRegistry->options(),
            'dashboard' => $this->dashboard($context, $actor),
            'totals' => $this->summaryTotals(clone $query),
            'customers' => (clone $query)
                ->orderBy(
                    $this->summarySortColumn((string) $context['sort']),
                    (string) $context['direction'],
                )
                ->orderBy('customers.name')
                ->orderBy('customer_universe.customer_id')
                ->get()
                ->map(
                    fn (object $row): array =>
                        $this->normalizeSummaryRow($row),
                )
                ->values()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function exportCustomerDetailTotalRows(
        Customer $customer,
        array $filters,
        User $actor,
    ): int {
        return $this->customerDetailQuery(
            customer: $customer,
            filters: $filters,
            actor: $actor,
        )->count();
    }

    /**
     * @param array<string, mixed> $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function exportCustomerDetailRows(
        Customer $customer,
        array $filters,
        User $actor,
    ): LazyCollection {
        return $this->customerDetailQuery(
            customer: $customer,
            filters: $filters,
            actor: $actor,
        )
            ->orderByDesc('aging_items.is_receivable')
            ->orderByRaw(
                'COALESCE(aging_items.due_date, aging_items.document_date) ASC',
            )
            ->orderBy('aging_items.document_date')
            ->orderBy('aging_items.open_item_id')
            ->cursor()
            ->map(
                fn (object $item): array =>
                    $this->normalizeOpenItemRow($item),
            );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildCustomerDetail(
        Customer $customer,
        array $filters,
        User $actor,
    ): array {
        $context = $this->customerContext(
            customer: $customer,
            filters: $filters,
            actor: $actor,
        );

        $query = DB::query()->fromSub(
            $this->historicalItemQuery(
                actor: $actor,
                asOfDate: (string) $context['as_of_date'],
                branchId: $context['branch_id'],
                customerId: (int) $customer->getKey(),
                currencyCode: $context['currency_code'],
                search: (string) $context['search'],
                includeDocumentSearch: true,
            ),
            'aging_items',
        );

        /** @var LengthAwarePaginator<object> $paginator */
        $paginator = (clone $query)
            ->orderByDesc('aging_items.is_receivable')
            ->orderByRaw(
                'COALESCE(aging_items.due_date, aging_items.document_date) ASC',
            )
            ->orderBy('aging_items.document_date')
            ->orderBy('aging_items.open_item_id')
            ->paginate((int) $context['per_page'])
            ->withQueryString();

        return [
            'customer' => $this->customerReference($customer),
            'filters' => $context,
            'base_currency_code' => $this->baseCurrencyCode(),
            'buckets' => $this->bucketRegistry->options(),
            'summary' => $this->detailTotals(clone $query),
            'items' => [
                'data' => collect($paginator->items())
                    ->map(
                        fn (object $item): array =>
                            $this->normalizeOpenItemRow($item),
                    )
                    ->values()
                    ->all(),
                'meta' => $this->paginationMeta($paginator),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildPrintableCustomerDetail(
        Customer $customer,
        array $filters,
        User $actor,
    ): array {
        $context = $this->customerContext($customer, $filters, $actor);
        $query = DB::query()->fromSub(
            $this->historicalItemQuery(
                actor: $actor,
                asOfDate: (string) $context['as_of_date'],
                branchId: $context['branch_id'],
                customerId: (int) $customer->getKey(),
                currencyCode: $context['currency_code'],
                search: (string) $context['search'],
                includeDocumentSearch: true,
            ),
            'aging_items',
        );

        return [
            'customer' => $this->customerReference($customer),
            'filters' => $context,
            'base_currency_code' => $this->baseCurrencyCode(),
            'buckets' => $this->bucketRegistry->options(),
            'summary' => $this->detailTotals(clone $query),
            'items' => (clone $query)
                ->orderByDesc('aging_items.is_receivable')
                ->orderByRaw(
                    'COALESCE(aging_items.due_date, aging_items.document_date) ASC',
                )
                ->orderBy('aging_items.document_date')
                ->orderBy('aging_items.open_item_id')
                ->get()
                ->map(
                    fn (object $item): array =>
                        $this->normalizeOpenItemRow($item),
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildOpenInvoices(
        array $filters,
        User $actor,
        bool $overdueOnly,
    ): array {
        $context = $this->openInvoiceContext($filters, $actor);
        $query = $this->openInvoiceQuery(
            context: $context,
            actor: $actor,
            overdueOnly: $overdueOnly,
        );

        /** @var LengthAwarePaginator<object> $paginator */
        $paginator = (clone $query)
            ->orderBy(
                $this->openInvoiceSortColumn((string) $context['sort']),
                (string) $context['direction'],
            )
            ->orderBy('aging_items.customer_name')
            ->orderBy('aging_items.open_item_id')
            ->paginate((int) $context['per_page'])
            ->withQueryString();

        return [
            'mode' => $overdueOnly ? 'overdue' : 'open',
            'filters' => $context,
            'base_currency_code' => $this->baseCurrencyCode(),
            'summary' => $this->openInvoiceTotals(clone $query),
            'invoices' => [
                'data' => collect($paginator->items())
                    ->map(
                        fn (object $item): array =>
                            $this->normalizeOpenItemRow($item),
                    )
                    ->values()
                    ->all(),
                'meta' => $this->paginationMeta($paginator),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildPrintableOpenInvoices(
        array $filters,
        User $actor,
        bool $overdueOnly,
    ): array {
        $context = $this->openInvoiceContext($filters, $actor);
        $query = $this->openInvoiceQuery($context, $actor, $overdueOnly);

        return [
            'mode' => $overdueOnly ? 'overdue' : 'open',
            'filters' => $context,
            'base_currency_code' => $this->baseCurrencyCode(),
            'summary' => $this->openInvoiceTotals(clone $query),
            'invoices' => (clone $query)
                ->orderBy(
                    $this->openInvoiceSortColumn((string) $context['sort']),
                    (string) $context['direction'],
                )
                ->orderBy('aging_items.customer_name')
                ->orderBy('aging_items.open_item_id')
                ->get()
                ->map(
                    fn (object $item): array =>
                        $this->normalizeOpenItemRow($item),
                )
                ->values()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function exportOpenInvoiceTotalRows(
        array $filters,
        User $actor,
        bool $overdueOnly,
    ): int {
        $context = $this->openInvoiceContext($filters, $actor);

        return $this->openInvoiceQuery(
            $context,
            $actor,
            $overdueOnly,
        )->count();
    }

    /**
     * @param array<string, mixed> $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function exportOpenInvoiceRows(
        array $filters,
        User $actor,
        bool $overdueOnly,
    ): LazyCollection {
        $context = $this->openInvoiceContext($filters, $actor);

        return $this->openInvoiceQuery(
            $context,
            $actor,
            $overdueOnly,
        )
            ->orderBy(
                $this->openInvoiceSortColumn((string) $context['sort']),
                (string) $context['direction'],
            )
            ->orderBy('aging_items.customer_name')
            ->orderBy('aging_items.open_item_id')
            ->cursor()
            ->map(
                fn (object $item): array =>
                    $this->normalizeOpenItemRow($item),
            );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function summaryQuery(
        array $context,
        User $actor,
    ): QueryBuilder {
        $items = $this->historicalItemQuery(
            actor: $actor,
            asOfDate: (string) $context['as_of_date'],
            branchId: $context['branch_id'],
            customerId: $context['customer_id'],
            currencyCode: $context['currency_code'],
            search: (string) $context['search'],
            includeDocumentSearch: false,
        );

        $itemSummary = DB::query()
            ->fromSub($items, 'aging_items')
            ->select('aging_items.customer_id')
            ->selectRaw(
                'SUM(CASE WHEN aging_items.is_receivable = 1 THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS total_receivable',
            )
            ->selectRaw(
                'SUM(CASE WHEN aging_items.is_receivable = 0 THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS unapplied_credit',
            )
            ->selectRaw(
                'SUM(CASE WHEN aging_items.is_receivable = 1 THEN aging_items.historical_base_outstanding_amount ELSE -aging_items.historical_base_outstanding_amount END) AS net_outstanding',
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_receivable = 1 AND aging_items.bucket_key = 'current' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS current_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_receivable = 1 AND aging_items.bucket_key = 'days_1_30' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_1_30_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_receivable = 1 AND aging_items.bucket_key = 'days_31_60' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_31_60_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_receivable = 1 AND aging_items.bucket_key = 'days_61_90' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_61_90_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_receivable = 1 AND aging_items.bucket_key = 'days_91_120' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_91_120_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_receivable = 1 AND aging_items.bucket_key = 'days_over_120' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_over_120_amount",
            )
            ->selectRaw(
                'SUM(CASE WHEN aging_items.is_receivable = 1 AND aging_items.item_type = ? THEN 1 ELSE 0 END) AS open_invoice_count',
                ['invoice'],
            )
            ->groupBy('aging_items.customer_id');

        $ledgerBalances = $this->ledgerBalanceQuery(
            actor: $actor,
            asOfDate: (string) $context['as_of_date'],
            branchId: $context['branch_id'],
            customerId: $context['customer_id'],
            currencyCode: $context['currency_code'],
        );

        $itemCustomers = DB::query()
            ->fromSub(clone $itemSummary, 'item_customers')
            ->select('item_customers.customer_id');

        $ledgerCustomers = DB::query()
            ->fromSub(clone $ledgerBalances, 'ledger_customers')
            ->select('ledger_customers.customer_id');

        $customerUniverse = $itemCustomers->union($ledgerCustomers);

        return DB::query()
            ->fromSub($customerUniverse, 'customer_universe')
            ->join(
                'customers',
                'customers.id',
                '=',
                'customer_universe.customer_id',
            )
            ->leftJoinSub(
                $itemSummary,
                'item_summary',
                static function ($join): void {
                    $join->on(
                        'item_summary.customer_id',
                        '=',
                        'customer_universe.customer_id',
                    );
                },
            )
            ->leftJoinSub(
                $ledgerBalances,
                'ledger_balances',
                static function ($join): void {
                    $join->on(
                        'ledger_balances.customer_id',
                        '=',
                        'customer_universe.customer_id',
                    );
                },
            )
            ->select([
                'customer_universe.customer_id',
                'customers.code AS customer_code',
                'customers.name AS customer_name',
                'customers.status AS customer_status',
                'customers.customer_type',
            ])
            ->selectRaw('COALESCE(item_summary.total_receivable, 0) AS total_receivable')
            ->selectRaw('COALESCE(item_summary.unapplied_credit, 0) AS unapplied_credit')
            ->selectRaw('COALESCE(item_summary.net_outstanding, 0) AS net_outstanding')
            ->selectRaw('COALESCE(ledger_balances.ledger_balance, 0) AS ledger_balance')
            ->selectRaw(
                'COALESCE(ledger_balances.ledger_balance, 0) - COALESCE(item_summary.net_outstanding, 0) AS difference',
            )
            ->selectRaw('COALESCE(item_summary.current_amount, 0) AS current_amount')
            ->selectRaw('COALESCE(item_summary.days_1_30_amount, 0) AS days_1_30_amount')
            ->selectRaw('COALESCE(item_summary.days_31_60_amount, 0) AS days_31_60_amount')
            ->selectRaw('COALESCE(item_summary.days_61_90_amount, 0) AS days_61_90_amount')
            ->selectRaw('COALESCE(item_summary.days_91_120_amount, 0) AS days_91_120_amount')
            ->selectRaw('COALESCE(item_summary.days_over_120_amount, 0) AS days_over_120_amount')
            ->selectRaw('COALESCE(item_summary.open_invoice_count, 0) AS open_invoice_count')
            ->whereRaw(
                '(ABS(COALESCE(item_summary.net_outstanding, 0)) > 0.000001 OR ABS(COALESCE(ledger_balances.ledger_balance, 0)) > 0.000001)',
            )
            ->when(
                (string) $context['search'] !== '',
                static function (QueryBuilder $query) use ($context): void {
                    $search = (string) $context['search'];

                    $query->where(
                        static function (QueryBuilder $query) use ($search): void {
                            $query
                                ->where('customers.name', 'like', "%{$search}%")
                                ->orWhere('customers.code', 'like', "%{$search}%");
                        },
                    );
                },
            );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function openInvoiceQuery(
        array $context,
        User $actor,
        bool $overdueOnly,
    ): QueryBuilder {
        $query = DB::query()->fromSub(
            $this->historicalItemQuery(
                actor: $actor,
                asOfDate: (string) $context['as_of_date'],
                branchId: $context['branch_id'],
                customerId: $context['customer_id'],
                currencyCode: $context['currency_code'],
                search: (string) $context['search'],
                includeDocumentSearch: true,
            ),
            'aging_items',
        )
            ->where('aging_items.is_receivable', 1)
            ->where('aging_items.item_type', 'invoice');

        if ($overdueOnly) {
            $query
                ->whereNotNull('aging_items.due_date')
                ->where('aging_items.days_overdue', '>', 0);
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function customerDetailQuery(
        Customer $customer,
        array $filters,
        User $actor,
    ): QueryBuilder {
        $context = $this->customerContext($customer, $filters, $actor);

        return DB::query()->fromSub(
            $this->historicalItemQuery(
                actor: $actor,
                asOfDate: (string) $context['as_of_date'],
                branchId: $context['branch_id'],
                customerId: (int) $customer->getKey(),
                currencyCode: $context['currency_code'],
                search: (string) $context['search'],
                includeDocumentSearch: true,
            ),
            'aging_items',
        );
    }

    private function historicalItemQuery(
        User $actor,
        string $asOfDate,
        ?int $branchId,
        ?int $customerId,
        ?string $currencyCode,
        string $search,
        bool $includeDocumentSearch,
    ): EloquentBuilder {
        $tenantId = (int) $this->tenantContext
            ->tenant()
            ->getKey();

        $allocations = $this->allocationTotalsQuery(
            tenantId: $tenantId,
            asOfDate: $asOfDate,
        );

        $reversals = DB::table('customer_ledger_entries')
            ->select([
                'reversal_of_id',
            ])
            ->where('tenant_id', $tenantId)
            ->whereNotNull('reversal_of_id')
            ->whereDate('posting_date', '<=', $asOfDate)
            ->groupBy('reversal_of_id');

        $query = CustomerOpenItem::query()
            ->join(
                'customer_ledger_entries',
                'customer_ledger_entries.id',
                '=',
                'customer_open_items.customer_ledger_entry_id',
            )
            ->join(
                'customers',
                'customers.id',
                '=',
                'customer_open_items.customer_id',
            )
            ->join(
                'branches',
                'branches.id',
                '=',
                'customer_open_items.branch_id',
            )
            ->leftJoinSub(
                $allocations,
                'allocation_totals',
                static function ($join): void {
                    $join->on(
                        'allocation_totals.open_item_id',
                        '=',
                        'customer_open_items.id',
                    );
                },
            )
            ->leftJoinSub(
                $reversals,
                'historical_reversals',
                static function ($join): void {
                    $join->on(
                        'historical_reversals.reversal_of_id',
                        '=',
                        'customer_ledger_entries.id',
                    );
                },
            )
            ->whereNull('historical_reversals.reversal_of_id')
            ->whereDate(
                'customer_open_items.posting_date',
                '<=',
                $asOfDate,
            )
            ->whereRaw(
                '(customer_open_items.original_amount - COALESCE(allocation_totals.allocated_amount, 0)) > 0.000000',
            )
            ->select([
                'customer_open_items.id AS open_item_id',
                'customer_open_items.customer_ledger_entry_id',
                'customer_open_items.branch_id',
                'branches.code AS branch_code',
                'branches.name AS branch_name',
                'customer_open_items.customer_id',
                'customers.code AS customer_code',
                'customers.name AS customer_name',
                'customers.status AS customer_status',
                'customers.customer_type',
                'customer_open_items.item_type',
                'customer_open_items.source_type',
                'customer_open_items.source_id',
                'customer_open_items.document_number',
                'customer_open_items.document_date',
                'customer_open_items.posting_date',
                'customer_open_items.due_date',
                'customer_open_items.currency_code',
                'customer_open_items.exchange_rate',
                'customer_open_items.original_amount',
                'customer_open_items.base_original_amount',
                'customer_ledger_entries.entry_type',
                'customer_ledger_entries.reference',
                'customer_ledger_entries.journal_reference',
                'customer_ledger_entries.description',
            ])
            ->selectRaw(
                'COALESCE(allocation_totals.allocated_amount, 0) AS historical_allocated_amount',
            )
            ->selectRaw(
                'COALESCE(allocation_totals.base_allocated_amount, 0) AS historical_base_allocated_amount',
            )
            ->selectRaw(
                '(customer_open_items.original_amount - COALESCE(allocation_totals.allocated_amount, 0)) AS historical_outstanding_amount',
            )
            ->selectRaw(
                '(customer_open_items.base_original_amount - COALESCE(allocation_totals.base_allocated_amount, 0)) AS historical_base_outstanding_amount',
            )
            ->selectRaw(
                'CASE WHEN customer_ledger_entries.base_debit_amount > customer_ledger_entries.base_credit_amount THEN 1 ELSE 0 END AS is_receivable',
            )
            ->selectRaw(
                "CASE WHEN customer_ledger_entries.base_debit_amount > customer_ledger_entries.base_credit_amount THEN DATEDIFF(?, COALESCE(customer_open_items.due_date, customer_open_items.document_date)) ELSE NULL END AS days_overdue",
                [$asOfDate],
            )
            ->selectRaw(
                "CASE
                    WHEN customer_ledger_entries.base_debit_amount <= customer_ledger_entries.base_credit_amount THEN NULL
                    WHEN DATEDIFF(?, COALESCE(customer_open_items.due_date, customer_open_items.document_date)) <= 0 THEN 'current'
                    WHEN DATEDIFF(?, COALESCE(customer_open_items.due_date, customer_open_items.document_date)) <= 30 THEN 'days_1_30'
                    WHEN DATEDIFF(?, COALESCE(customer_open_items.due_date, customer_open_items.document_date)) <= 60 THEN 'days_31_60'
                    WHEN DATEDIFF(?, COALESCE(customer_open_items.due_date, customer_open_items.document_date)) <= 90 THEN 'days_61_90'
                    WHEN DATEDIFF(?, COALESCE(customer_open_items.due_date, customer_open_items.document_date)) <= 120 THEN 'days_91_120'
                    ELSE 'days_over_120'
                END AS bucket_key",
                [
                    $asOfDate,
                    $asOfDate,
                    $asOfDate,
                    $asOfDate,
                    $asOfDate,
                ],
            );

        $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
            branchColumn: 'customer_open_items.branch_id',
        );

        $query
            ->when(
                $branchId !== null,
                static fn (EloquentBuilder $query): EloquentBuilder =>
                    $query->where(
                        'customer_open_items.branch_id',
                        $branchId,
                    ),
            )
            ->when(
                $customerId !== null,
                static fn (EloquentBuilder $query): EloquentBuilder =>
                    $query->where(
                        'customer_open_items.customer_id',
                        $customerId,
                    ),
            )
            ->when(
                $currencyCode !== null,
                static fn (EloquentBuilder $query): EloquentBuilder =>
                    $query->where(
                        'customer_open_items.currency_code',
                        $currencyCode,
                    ),
            );

        if ($search !== '') {
            $query->where(
                static function (EloquentBuilder $query) use (
                    $search,
                    $includeDocumentSearch,
                ): void {
                    $query
                        ->where(
                            'customers.name',
                            'like',
                            "%{$search}%",
                        )
                        ->orWhere(
                            'customers.code',
                            'like',
                            "%{$search}%",
                        );

                    if ($includeDocumentSearch) {
                        $query
                            ->orWhere(
                                'customer_open_items.document_number',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'customer_ledger_entries.reference',
                                'like',
                                "%{$search}%",
                            )
                            ->orWhere(
                                'customer_ledger_entries.journal_reference',
                                'like',
                                "%{$search}%",
                            );
                    }
                },
            );
        }

        return $query;
    }

    private function allocationTotalsQuery(
        int $tenantId,
        string $asOfDate,
    ): QueryBuilder {
        $receivable = DB::table('customer_open_item_allocations')
            ->selectRaw(
                'receivable_open_item_id AS open_item_id, amount AS allocated_amount, receivable_base_amount AS base_allocated_amount',
            )
            ->where('tenant_id', $tenantId)
            ->whereDate('posting_date', '<=', $asOfDate)
            ->where(
                static function (QueryBuilder $query) use ($asOfDate): void {
                    $query
                        ->where('status', 'applied')
                        ->orWhere(
                            static function (QueryBuilder $query) use ($asOfDate): void {
                                $query
                                    ->where('status', 'reversed')
                                    ->whereDate(
                                        'reversal_posting_date',
                                        '>',
                                        $asOfDate,
                                    );
                            },
                        );
                },
            );

        $credits = DB::table('customer_open_item_allocations')
            ->selectRaw(
                'credit_open_item_id AS open_item_id, amount AS allocated_amount, credit_base_amount AS base_allocated_amount',
            )
            ->where('tenant_id', $tenantId)
            ->whereDate('posting_date', '<=', $asOfDate)
            ->where(
                static function (QueryBuilder $query) use ($asOfDate): void {
                    $query
                        ->where('status', 'applied')
                        ->orWhere(
                            static function (QueryBuilder $query) use ($asOfDate): void {
                                $query
                                    ->where('status', 'reversed')
                                    ->whereDate(
                                        'reversal_posting_date',
                                        '>',
                                        $asOfDate,
                                    );
                            },
                        );
                },
            );

        return DB::query()
            ->fromSub(
                $receivable->unionAll($credits),
                'allocation_rows',
            )
            ->select('open_item_id')
            ->selectRaw('SUM(allocated_amount) AS allocated_amount')
            ->selectRaw('SUM(base_allocated_amount) AS base_allocated_amount')
            ->groupBy('open_item_id');
    }

    private function ledgerBalanceQuery(
        User $actor,
        string $asOfDate,
        ?int $branchId,
        ?int $customerId,
        ?string $currencyCode,
    ): EloquentBuilder {
        $query = \App\Models\CustomerLedgerEntry::query()
            ->select('customer_id')
            ->selectRaw(
                'SUM(base_debit_amount - base_credit_amount) AS ledger_balance',
            )
            ->whereDate('posting_date', '<=', $asOfDate)
            ->groupBy('customer_id');

        $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
            branchColumn: 'customer_ledger_entries.branch_id',
        );

        return $query
            ->when(
                $branchId !== null,
                static fn (EloquentBuilder $query): EloquentBuilder =>
                    $query->where('branch_id', $branchId),
            )
            ->when(
                $customerId !== null,
                static fn (EloquentBuilder $query): EloquentBuilder =>
                    $query->where('customer_id', $customerId),
            )
            ->when(
                $currencyCode !== null,
                static fn (EloquentBuilder $query): EloquentBuilder =>
                    $query->where('currency_code', $currencyCode),
            );
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function dashboard(
        array $context,
        User $actor,
    ): array {
        $items = DB::query()->fromSub(
            $this->historicalItemQuery(
                actor: $actor,
                asOfDate: (string) $context['as_of_date'],
                branchId: $context['branch_id'],
                customerId: $context['customer_id'],
                currencyCode: $context['currency_code'],
                search: (string) $context['search'],
                includeDocumentSearch: false,
            ),
            'aging_items',
        );

        $row = $items
            ->selectRaw(
                'SUM(CASE WHEN is_receivable = 1 THEN historical_base_outstanding_amount ELSE 0 END) AS total_receivable',
            )
            ->selectRaw(
                'SUM(CASE WHEN is_receivable = 0 THEN historical_base_outstanding_amount ELSE 0 END) AS unapplied_credit',
            )
            ->selectRaw(
                "SUM(CASE WHEN is_receivable = 1 AND bucket_key <> 'current' THEN historical_base_outstanding_amount ELSE 0 END) AS overdue_receivable",
            )
            ->selectRaw(
                'COUNT(DISTINCT customer_id) AS customer_count',
            )
            ->selectRaw(
                'SUM(CASE WHEN is_receivable = 1 AND item_type = ? THEN 1 ELSE 0 END) AS open_invoice_count',
                ['invoice'],
            )
            ->first();

        $receivable = $this->money($row?->total_receivable ?? 0);
        $credit = $this->money($row?->unapplied_credit ?? 0);
        $overdue = $this->money($row?->overdue_receivable ?? 0);
        $ratio = $receivable->isZero()
            ? BigDecimal::zero()
            : $overdue
                ->multipliedBy(BigDecimal::of('100'))
                ->dividedBy(
                    $receivable,
                    2,
                    RoundingMode::HALF_UP,
                );

        return [
            'total_receivable' => $this->decimal($receivable),
            'unapplied_credit' => $this->decimal($credit),
            'net_receivable' => $this->decimal(
                $receivable->minus($credit),
            ),
            'overdue_receivable' => $this->decimal($overdue),
            'overdue_ratio' => $ratio->__toString(),
            'customer_count' => (int) ($row?->customer_count ?? 0),
            'open_invoice_count' => (int) ($row?->open_invoice_count ?? 0),
        ];
    }

    private function summaryTotals(QueryBuilder $query): array
    {
        $row = DB::query()
            ->fromSub($query, 'summary_totals')
            ->selectRaw('SUM(total_receivable) AS total_receivable')
            ->selectRaw('SUM(unapplied_credit) AS unapplied_credit')
            ->selectRaw('SUM(net_outstanding) AS net_outstanding')
            ->selectRaw('SUM(ledger_balance) AS ledger_balance')
            ->selectRaw('SUM(difference) AS difference')
            ->selectRaw('SUM(current_amount) AS current_amount')
            ->selectRaw('SUM(days_1_30_amount) AS days_1_30_amount')
            ->selectRaw('SUM(days_31_60_amount) AS days_31_60_amount')
            ->selectRaw('SUM(days_61_90_amount) AS days_61_90_amount')
            ->selectRaw('SUM(days_91_120_amount) AS days_91_120_amount')
            ->selectRaw('SUM(days_over_120_amount) AS days_over_120_amount')
            ->first();

        return [
            'total_receivable' => $this->decimalString($row?->total_receivable),
            'unapplied_credit' => $this->decimalString($row?->unapplied_credit),
            'net_outstanding' => $this->decimalString($row?->net_outstanding),
            'ledger_balance' => $this->decimalString($row?->ledger_balance),
            'difference' => $this->decimalString($row?->difference),
            'current' => $this->decimalString($row?->current_amount),
            'days_1_30' => $this->decimalString($row?->days_1_30_amount),
            'days_31_60' => $this->decimalString($row?->days_31_60_amount),
            'days_61_90' => $this->decimalString($row?->days_61_90_amount),
            'days_91_120' => $this->decimalString($row?->days_91_120_amount),
            'days_over_120' => $this->decimalString($row?->days_over_120_amount),
        ];
    }

    private function detailTotals(QueryBuilder $query): array
    {
        $row = $query
            ->selectRaw(
                'SUM(CASE WHEN is_receivable = 1 THEN historical_base_outstanding_amount ELSE 0 END) AS total_receivable',
            )
            ->selectRaw(
                'SUM(CASE WHEN is_receivable = 0 THEN historical_base_outstanding_amount ELSE 0 END) AS unapplied_credit',
            )
            ->selectRaw(
                'SUM(CASE WHEN is_receivable = 1 THEN historical_base_outstanding_amount ELSE -historical_base_outstanding_amount END) AS net_outstanding',
            )
            ->selectRaw(
                "SUM(CASE WHEN is_receivable = 1 AND bucket_key = 'current' THEN historical_base_outstanding_amount ELSE 0 END) AS current_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN is_receivable = 1 AND bucket_key = 'days_1_30' THEN historical_base_outstanding_amount ELSE 0 END) AS days_1_30_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN is_receivable = 1 AND bucket_key = 'days_31_60' THEN historical_base_outstanding_amount ELSE 0 END) AS days_31_60_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN is_receivable = 1 AND bucket_key = 'days_61_90' THEN historical_base_outstanding_amount ELSE 0 END) AS days_61_90_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN is_receivable = 1 AND bucket_key = 'days_91_120' THEN historical_base_outstanding_amount ELSE 0 END) AS days_91_120_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN is_receivable = 1 AND bucket_key = 'days_over_120' THEN historical_base_outstanding_amount ELSE 0 END) AS days_over_120_amount",
            )
            ->first();

        return [
            'total_receivable' => $this->decimalString($row?->total_receivable),
            'unapplied_credit' => $this->decimalString($row?->unapplied_credit),
            'net_outstanding' => $this->decimalString($row?->net_outstanding),
            'current' => $this->decimalString($row?->current_amount),
            'days_1_30' => $this->decimalString($row?->days_1_30_amount),
            'days_31_60' => $this->decimalString($row?->days_31_60_amount),
            'days_61_90' => $this->decimalString($row?->days_61_90_amount),
            'days_91_120' => $this->decimalString($row?->days_91_120_amount),
            'days_over_120' => $this->decimalString($row?->days_over_120_amount),
        ];
    }

    private function openInvoiceTotals(QueryBuilder $query): array
    {
        $row = $query
            ->selectRaw('COUNT(*) AS invoice_count')
            ->selectRaw('COUNT(DISTINCT customer_id) AS customer_count')
            ->selectRaw('SUM(original_amount) AS original_amount')
            ->selectRaw('SUM(historical_allocated_amount) AS allocated_amount')
            ->selectRaw('SUM(historical_outstanding_amount) AS outstanding_amount')
            ->selectRaw('SUM(base_original_amount) AS base_original_amount')
            ->selectRaw('SUM(historical_base_allocated_amount) AS base_allocated_amount')
            ->selectRaw('SUM(historical_base_outstanding_amount) AS base_outstanding_amount')
            ->selectRaw(
                'SUM(CASE WHEN days_overdue > 0 THEN historical_base_outstanding_amount ELSE 0 END) AS overdue_base_amount',
            )
            ->first();

        return [
            'invoice_count' => (int) ($row?->invoice_count ?? 0),
            'customer_count' => (int) ($row?->customer_count ?? 0),
            'original_amount' => $this->decimalString($row?->original_amount),
            'allocated_amount' => $this->decimalString($row?->allocated_amount),
            'outstanding_amount' => $this->decimalString($row?->outstanding_amount),
            'base_original_amount' => $this->decimalString($row?->base_original_amount),
            'base_allocated_amount' => $this->decimalString($row?->base_allocated_amount),
            'base_outstanding_amount' => $this->decimalString($row?->base_outstanding_amount),
            'overdue_base_amount' => $this->decimalString($row?->overdue_base_amount),
        ];
    }

    private function normalizeSummaryRow(object $row): array
    {
        return [
            'customer' => [
                'id' => (int) $row->customer_id,
                'code' => (string) $row->customer_code,
                'name' => (string) $row->customer_name,
                'status' => (string) $row->customer_status,
                'customer_type' => (string) $row->customer_type,
            ],
            'total_receivable' => $this->decimalString($row->total_receivable),
            'unapplied_credit' => $this->decimalString($row->unapplied_credit),
            'net_outstanding' => $this->decimalString($row->net_outstanding),
            'ledger_balance' => $this->decimalString($row->ledger_balance),
            'difference' => $this->decimalString($row->difference),
            'open_invoice_count' => (int) $row->open_invoice_count,
            'buckets' => [
                'current' => $this->decimalString($row->current_amount),
                'days_1_30' => $this->decimalString($row->days_1_30_amount),
                'days_31_60' => $this->decimalString($row->days_31_60_amount),
                'days_61_90' => $this->decimalString($row->days_61_90_amount),
                'days_91_120' => $this->decimalString($row->days_91_120_amount),
                'days_over_120' => $this->decimalString($row->days_over_120_amount),
            ],
        ];
    }

    private function normalizeOpenItemRow(object $item): array
    {
        $isReceivable = (int) $item->is_receivable === 1;
        $bucketKey = $isReceivable
            ? (string) $item->bucket_key
            : null;

        return [
            'id' => (int) $item->open_item_id,
            'ledger_entry_id' => (int) $item->customer_ledger_entry_id,
            'branch' => [
                'id' => (int) $item->branch_id,
                'code' => (string) $item->branch_code,
                'name' => (string) $item->branch_name,
            ],
            'customer' => [
                'id' => (int) $item->customer_id,
                'code' => (string) $item->customer_code,
                'name' => (string) $item->customer_name,
                'status' => (string) $item->customer_status,
                'customer_type' => (string) $item->customer_type,
            ],
            'item_type' => (string) $item->item_type,
            'item_type_label' => $this->accountsReceivableRegistry
                ->openItemTypeLabel((string) $item->item_type),
            'entry_type' => (string) $item->entry_type,
            'entry_type_label' => $this->accountsReceivableRegistry
                ->ledgerEntryTypeLabel((string) $item->entry_type),
            'balance_side' => $isReceivable
                ? 'receivable'
                : 'credit',
            'source_type' => (string) $item->source_type,
            'source_id' => (int) $item->source_id,
            'document_number' => $item->document_number !== null
                ? (string) $item->document_number
                : null,
            'reference' => (string) $item->reference,
            'journal_reference' => (string) $item->journal_reference,
            'description' => (string) ($item->description ?? ''),
            'document_date' => (string) $item->document_date,
            'posting_date' => (string) $item->posting_date,
            'due_date' => $item->due_date !== null
                ? (string) $item->due_date
                : null,
            'currency_code' => (string) $item->currency_code,
            'exchange_rate' => $this->rateString($item->exchange_rate),
            'original_amount' => $this->decimalString($item->original_amount),
            'historical_allocated_amount' => $this->decimalString($item->historical_allocated_amount),
            'outstanding_amount' => $this->decimalString($item->historical_outstanding_amount),
            'base_original_amount' => $this->decimalString($item->base_original_amount),
            'historical_base_allocated_amount' => $this->decimalString($item->historical_base_allocated_amount),
            'base_outstanding_amount' => $this->decimalString($item->historical_base_outstanding_amount),
            'days_overdue' => $isReceivable
                ? (int) $item->days_overdue
                : null,
            'bucket_key' => $bucketKey,
            'bucket_label' => $bucketKey !== null
                ? $this->bucketRegistry->label($bucketKey)
                : 'Unapplied Credit',
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function agingContext(array $filters, User $actor): array
    {
        return $this->context(
            filters: $filters,
            actor: $actor,
            sorts: self::AGING_SORTS,
            defaultSort: 'net_outstanding',
            defaultDirection: 'desc',
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function openInvoiceContext(array $filters, User $actor): array
    {
        return $this->context(
            filters: $filters,
            actor: $actor,
            sorts: self::OPEN_INVOICE_SORTS,
            defaultSort: 'due_date',
            defaultDirection: 'asc',
        );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    private function customerContext(
        Customer $customer,
        array $filters,
        User $actor,
    ): array {
        $this->ensureCustomerContext($customer, $actor);
        $filters['customer_id'] = (int) $customer->getKey();

        return $this->agingContext($filters, $actor);
    }

    /**
     * @param array<string, mixed> $filters
     * @param list<string> $sorts
     * @return array<string, mixed>
     */
    private function context(
        array $filters,
        User $actor,
        array $sorts,
        string $defaultSort,
        string $defaultDirection,
    ): array {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        if ((int) $actor->tenant_id !== $tenantId) {
            throw new LogicException(
                'The report user does not belong to the active tenant.',
            );
        }

        $asOfDate = $this->date(
            value: $filters['as_of_date'] ?? null,
            timezone: $tenant->timezone,
        );

        $branchId = $this->nullableId($filters['branch_id'] ?? null);

        $accessibleBranch = $branchId !== null
            ? $this->branchAccessService->findAccessibleBranch(
                user: $actor,
                branchId: $branchId,
                requireActive: false,
            )
            : null;

        if (
            $branchId !== null
            && !($accessibleBranch instanceof Branch)
        ) {
            throw ValidationException::withMessages([
                'branch_id' => [
                    'The selected branch is unavailable or outside your access.',
                ],
            ]);
        }

        $customerId = $this->nullableId($filters['customer_id'] ?? null);

        if ($customerId !== null) {
            $customer = Customer::withTrashed()
                ->whereKey($customerId)
                ->first();

            if (!$customer instanceof Customer) {
                throw ValidationException::withMessages([
                    'customer_id' => [
                        'The selected customer is unavailable.',
                    ],
                ]);
            }
        }

        $currencyCode = $this->currencyCode(
            $filters['currency_code'] ?? null,
        );

        $search = trim((string) ($filters['search'] ?? ''));
        $sort = mb_strtolower(trim((string) ($filters['sort'] ?? $defaultSort)));
        $direction = mb_strtolower(trim((string) ($filters['direction'] ?? $defaultDirection)));
        $perPage = $this->perPage($filters['per_page'] ?? 25);

        if (!in_array($sort, $sorts, true)) {
            $sort = $defaultSort;
        }

        if (!in_array($direction, ['asc', 'desc'], true)) {
            $direction = $defaultDirection;
        }

        return [
            'as_of_date' => $asOfDate,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'currency_code' => $currencyCode,
            'search' => mb_substr($search, 0, 160),
            'sort' => $sort,
            'direction' => $direction,
            'per_page' => $perPage,
        ];
    }

    private function ensureCustomerContext(
        Customer $customer,
        User $actor,
    ): void {
        $tenantId = (int) $this->tenantContext
            ->tenant()
            ->getKey();

        if (
            (int) $customer->tenant_id !== $tenantId
            || (int) $actor->tenant_id !== $tenantId
        ) {
            throw new LogicException(
                'Customer report context crossed a tenant boundary.',
            );
        }
    }

    /** @return array<string, mixed> */
    private function customerReference(Customer $customer): array
    {
        return [
            'id' => (int) $customer->getKey(),
            'code' => $customer->code,
            'name' => $customer->name,
            'status' => $customer->status,
            'customer_type' => $customer->customer_type,
            'contact_person' => $customer->contact_person,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'payment_terms_days' => (int) $customer->payment_terms_days,
            'credit_limit' => (string) $customer->credit_limit,
        ];
    }

    private function summarySortColumn(string $sort): string
    {
        return match ($sort) {
            'customer_name' => 'customers.name',
            'total_receivable' => 'total_receivable',
            'unapplied_credit' => 'unapplied_credit',
            'ledger_balance' => 'ledger_balance',
            'difference' => 'difference',
            'current' => 'current_amount',
            'days_1_30' => 'days_1_30_amount',
            'days_31_60' => 'days_31_60_amount',
            'days_61_90' => 'days_61_90_amount',
            'days_91_120' => 'days_91_120_amount',
            'days_over_120' => 'days_over_120_amount',
            default => 'net_outstanding',
        };
    }

    private function openInvoiceSortColumn(string $sort): string
    {
        return match ($sort) {
            'customer_name' => 'aging_items.customer_name',
            'document_number' => 'aging_items.document_number',
            'document_date' => 'aging_items.document_date',
            'original_amount' => 'aging_items.original_amount',
            'outstanding_amount' => 'aging_items.historical_outstanding_amount',
            'days_overdue' => 'aging_items.days_overdue',
            default => 'aging_items.due_date',
        };
    }

    private function date(mixed $value, string $timezone): string
    {
        if ($value === null || $value === '') {
            return CarbonImmutable::now($timezone)->toDateString();
        }

        if (!is_string($value)) {
            throw ValidationException::withMessages([
                'as_of_date' => [
                    'The as-of date must use the YYYY-MM-DD format.',
                ],
            ]);
        }

        $value = trim($value);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, $timezone);

        if (
            !$date instanceof CarbonImmutable
            || $date->toDateString() !== $value
        ) {
            throw ValidationException::withMessages([
                'as_of_date' => [
                    'The as-of date must use the YYYY-MM-DD format.',
                ],
            ]);
        }

        return $value;
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        return $id === false ? null : (int) $id;
    }

    private function currencyCode(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $currencyCode = mb_strtoupper(trim((string) $value));

        if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
            throw ValidationException::withMessages([
                'currency_code' => [
                    'The currency must be a valid three-letter code.',
                ],
            ]);
        }

        return $currencyCode;
    }

    private function perPage(mixed $value): int
    {
        $perPage = filter_var($value, FILTER_VALIDATE_INT);

        return in_array($perPage, [10, 15, 25, 50, 100], true)
            ? (int) $perPage
            : 25;
    }

    /** @return array<string, int|null> */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }

    private function baseCurrencyCode(): string
    {
        return mb_strtoupper(
            (string) $this->tenantContext
                ->tenant()
                ->currency_code,
        );
    }

    private function money(mixed $value): BigDecimal
    {
        return BigDecimal::of((string) ($value ?? '0'))
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            );
    }

    private function decimal(BigDecimal $value): string
    {
        return $value
            ->toScale(
                self::MONEY_SCALE,
                RoundingMode::HALF_UP,
            )
            ->__toString();
    }

    private function decimalString(mixed $value): string
    {
        return $this->decimal($this->money($value));
    }

    private function rateString(mixed $value): string
    {
        return BigDecimal::of((string) ($value ?? '0'))
            ->toScale(8, RoundingMode::HALF_UP)
            ->__toString();
    }
}