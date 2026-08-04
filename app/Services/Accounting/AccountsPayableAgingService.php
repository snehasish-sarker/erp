<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Branch;
use App\Models\Supplier;
use App\Models\SupplierOpenItem;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\AccountsPayableAgingBucketRegistry;
use App\Support\Accounting\AccountsPayableRegistry;
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

final class AccountsPayableAgingService
{
    private const MONEY_SCALE = 6;

    private const RATE_SCALE = 8;

    /**
     * @var list<string>
     */
    private const SORTS = [
        'supplier_name',
        'total_payable',
        'unapplied_credit',
        'net_outstanding',
        'current',
        'days_1_30',
        'days_31_60',
        'days_61_90',
        'days_91_120',
        'days_over_120',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly AccountsPayableAgingBucketRegistry $bucketRegistry,
        private readonly AccountsPayableRegistry $accountsPayableRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *     as_of_date: string,
     *     branch_id: int|null,
     *     supplier_id: int|null,
     *     currency_code: string|null,
     *     search: string,
     *     sort: string,
     *     direction: string,
     *     per_page: int
     * }
     */
    public function normalizeExportFilters(
        array $filters,
        User $actor,
    ): array {
        return $this->context(
            filters: $filters,
            actor: $actor,
        );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function exportSummaryTotalRows(
        array $filters,
        User $actor,
    ): int {
        $context = $this->context(
            filters: $filters,
            actor: $actor,
        );

        return DB::query()
            ->fromSub(
                $this->summaryExportQuery(
                    context: $context,
                    actor: $actor,
                ),
                'aging_export_summary',
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
        $context = $this->context(
            filters: $filters,
            actor: $actor,
        );

        $sortColumn = $this->summarySortColumn(
            $context['sort'],
        );

        return $this->summaryExportQuery(
            context: $context,
            actor: $actor,
        )
            ->orderBy(
                $sortColumn,
                $context['direction'],
            )
            ->orderBy('aging_items.supplier_name')
            ->orderBy('aging_items.supplier_id')
            ->cursor()
            ->map(
                fn (object $row): array =>
                    $this->normalizeSummaryExportRow(
                        $row,
                    ),
            );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildPrintableSummary(
        array $filters,
        User $actor,
    ): array {
        $context = $this->context(
            filters: $filters,
            actor: $actor,
        );

        $summaryQuery = $this->summaryExportQuery(
            context: $context,
            actor: $actor,
        );

        $sortColumn = $this->summarySortColumn(
            $context['sort'],
        );

        $rows = (clone $summaryQuery)
            ->orderBy(
                $sortColumn,
                $context['direction'],
            )
            ->orderBy('aging_items.supplier_name')
            ->orderBy('aging_items.supplier_id')
            ->get()
            ->map(
                fn (object $row): array =>
                    $this->normalizeSummaryExportRow(
                        $row,
                    ),
            )
            ->values()
            ->all();

        return [
            'filters' => $context,
            'base_currency_code' => mb_strtoupper(
                (string) $this->tenantContext
                    ->tenant()
                    ->currency_code,
            ),
            'buckets' => $this->bucketRegistry->options(),
            'totals' => $this->summaryTotals(
                clone $summaryQuery,
            ),
            'suppliers' => $rows,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function exportSupplierDetailTotalRows(
        Supplier $supplier,
        array $filters,
        User $actor,
    ): int {
        return $this->supplierDetailExportQuery(
            supplier: $supplier,
            filters: $filters,
            actor: $actor,
        )->count();
    }

    /**
     * @param array<string, mixed> $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function exportSupplierDetailRows(
        Supplier $supplier,
        array $filters,
        User $actor,
    ): LazyCollection {
        return $this->supplierDetailExportQuery(
            supplier: $supplier,
            filters: $filters,
            actor: $actor,
        )
            ->orderByDesc('aging_items.is_payable')
            ->orderByRaw(
                'COALESCE(aging_items.due_date, aging_items.document_date) ASC',
            )
            ->orderBy('aging_items.document_date')
            ->orderBy('aging_items.open_item_id')
            ->cursor()
            ->map(
                fn (object $item): array =>
                    $this->normalizeDetailExportRow(
                        $item,
                    ),
            );
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildPrintableSupplierDetail(
        Supplier $supplier,
        array $filters,
        User $actor,
    ): array {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureSupplierContext(
            supplier: $supplier,
            actor: $actor,
            tenantId: $tenantId,
        );

        $filters['supplier_id'] =
            (int) $supplier->getKey();

        $context = $this->context(
            filters: $filters,
            actor: $actor,
        );

        $itemQuery = $this->supplierDetailExportQuery(
            supplier: $supplier,
            filters: $context,
            actor: $actor,
        );

        $items = (clone $itemQuery)
            ->orderByDesc('aging_items.is_payable')
            ->orderByRaw(
                'COALESCE(aging_items.due_date, aging_items.document_date) ASC',
            )
            ->orderBy('aging_items.document_date')
            ->orderBy('aging_items.open_item_id')
            ->get()
            ->map(
                fn (object $item): array =>
                    $this->normalizeDetailExportRow(
                        $item,
                    ),
            )
            ->values()
            ->all();

        return [
            'supplier' => [
                'id' => (int) $supplier->getKey(),
                'code' => $supplier->code,
                'name' => $supplier->name,
                'status' => $supplier->status,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'payment_terms_days' =>
                    (int) $supplier->payment_terms_days,
            ],
            'filters' => $context,
            'base_currency_code' => mb_strtoupper(
                (string) $tenant->currency_code,
            ),
            'buckets' => $this->bucketRegistry->options(),
            'summary' => $this->detailTotals(
                clone $itemQuery,
            ),
            'currencies' => $this->currencyBreakdowns(
                actor: $actor,
                asOfDate: $context['as_of_date'],
                branchId: $context['branch_id'],
                supplierIds: [
                    (int) $supplier->getKey(),
                ],
                currencyCode: $context['currency_code'],
                search: $context['search'],
            )[(int) $supplier->getKey()] ?? [],
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildSummary(
        array $filters,
        User $actor,
    ): array {
        $context = $this->context(
            filters: $filters,
            actor: $actor,
        );

        $itemQuery = $this->historicalItemQuery(
            actor: $actor,
            asOfDate: $context['as_of_date'],
            branchId: $context['branch_id'],
            supplierId: $context['supplier_id'],
            currencyCode: $context['currency_code'],
            search: $context['search'],
        );

        $summaryQuery = DB::query()
            ->fromSub(
                $itemQuery,
                'aging_items',
            )
            ->select([
                'aging_items.supplier_id',
                'aging_items.supplier_code',
                'aging_items.supplier_name',
                'aging_items.supplier_status',
            ])
            ->selectRaw(
                'SUM(CASE WHEN aging_items.is_payable = 1 THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS total_payable',
            )
            ->selectRaw(
                'SUM(CASE WHEN aging_items.is_payable = 0 THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS unapplied_credit',
            )
            ->selectRaw(
                'SUM(CASE WHEN aging_items.is_payable = 1 THEN aging_items.historical_base_outstanding_amount ELSE -aging_items.historical_base_outstanding_amount END) AS net_outstanding',
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'current' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS current_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_1_30' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_1_30_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_31_60' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_31_60_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_61_90' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_61_90_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_91_120' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_91_120_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_over_120' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_over_120_amount",
            )
            ->groupBy([
                'aging_items.supplier_id',
                'aging_items.supplier_code',
                'aging_items.supplier_name',
                'aging_items.supplier_status',
            ]);

        $overallTotals = $this->summaryTotals(
            clone $summaryQuery,
        );

        $sortColumn = $this->summarySortColumn(
            $context['sort'],
        );

        /** @var LengthAwarePaginator<int, object> $supplierRows */
        $supplierRows = $summaryQuery
            ->orderBy(
                $sortColumn,
                $context['direction'],
            )
            ->orderBy('aging_items.supplier_name')
            ->orderBy('aging_items.supplier_id')
            ->paginate(
                $context['per_page'],
            )
            ->withQueryString();

        $supplierIds = $supplierRows
            ->getCollection()
            ->map(
                static fn (object $row): int =>
                    (int) $row->supplier_id,
            )
            ->all();

        $currencyBreakdowns = $this->currencyBreakdowns(
            actor: $actor,
            asOfDate: $context['as_of_date'],
            branchId: $context['branch_id'],
            supplierIds: $supplierIds,
            currencyCode: $context['currency_code'],
            search: $context['search'],
        );

        $data = $supplierRows
            ->getCollection()
            ->map(
                function (object $row) use (
                    $currencyBreakdowns,
                ): array {
                    $supplierId = (int) $row->supplier_id;

                    return [
                        'supplier' => [
                            'id' => $supplierId,
                            'code' => (string) $row->supplier_code,
                            'name' => (string) $row->supplier_name,
                            'status' => (string) $row->supplier_status,
                        ],
                        'total_payable' =>
                            $this->decimalString(
                                $row->total_payable,
                            ),
                        'unapplied_credit' =>
                            $this->decimalString(
                                $row->unapplied_credit,
                            ),
                        'net_outstanding' =>
                            $this->decimalString(
                                $row->net_outstanding,
                            ),
                        'buckets' => [
                            'current' =>
                                $this->decimalString(
                                    $row->current_amount,
                                ),
                            'days_1_30' =>
                                $this->decimalString(
                                    $row->days_1_30_amount,
                                ),
                            'days_31_60' =>
                                $this->decimalString(
                                    $row->days_31_60_amount,
                                ),
                            'days_61_90' =>
                                $this->decimalString(
                                    $row->days_61_90_amount,
                                ),
                            'days_91_120' =>
                                $this->decimalString(
                                    $row->days_91_120_amount,
                                ),
                            'days_over_120' =>
                                $this->decimalString(
                                    $row->days_over_120_amount,
                                ),
                        ],
                        'currencies' =>
                            $currencyBreakdowns[
                                $supplierId
                            ] ?? [],
                    ];
                },
            )
            ->values()
            ->all();

        return [
            'filters' => $context,
            'base_currency_code' => mb_strtoupper(
                (string) $this->tenantContext
                    ->tenant()
                    ->currency_code,
            ),
            'buckets' => $this->bucketRegistry->options(),
            'totals' => $overallTotals,
            'suppliers' => [
                'data' => $data,
                'meta' => [
                    'current_page' =>
                        $supplierRows->currentPage(),
                    'last_page' =>
                        $supplierRows->lastPage(),
                    'per_page' =>
                        $supplierRows->perPage(),
                    'from' =>
                        $supplierRows->firstItem(),
                    'to' =>
                        $supplierRows->lastItem(),
                    'total' =>
                        $supplierRows->total(),
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function buildSupplierDetail(
        Supplier $supplier,
        array $filters,
        User $actor,
    ): array {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        $this->ensureSupplierContext(
            supplier: $supplier,
            actor: $actor,
            tenantId: $tenantId,
        );

        $filters['supplier_id'] =
            (int) $supplier->getKey();

        $context = $this->context(
            filters: $filters,
            actor: $actor,
        );

        $itemQuery = DB::query()
            ->fromSub(
                $this->historicalItemQuery(
                    actor: $actor,
                    asOfDate:
                        $context['as_of_date'],
                    branchId:
                        $context['branch_id'],
                    supplierId:
                        (int) $supplier->getKey(),
                    currencyCode:
                        $context['currency_code'],
                    search:
                        $context['search'],
                ),
                'aging_items',
            );

        $summary = $this->detailTotals(
            clone $itemQuery,
        );

        /** @var LengthAwarePaginator<int, object> $items */
        $items = $itemQuery
            ->orderByDesc('aging_items.is_payable')
            ->orderByRaw(
                'COALESCE(aging_items.due_date, aging_items.document_date) ASC',
            )
            ->orderBy('aging_items.document_date')
            ->orderBy('aging_items.open_item_id')
            ->paginate(
                $context['per_page'],
            )
            ->withQueryString();

        $data = $items
            ->getCollection()
            ->map(
                function (object $item): array {
                    $isPayable =
                        (int) $item->is_payable === 1;

                    $daysOverdue = $isPayable
                        ? (int) $item->days_overdue
                        : null;

                    return [
                        'id' =>
                            (int) $item->open_item_id,
                        'ledger_entry_id' =>
                            (int) $item
                                ->supplier_ledger_entry_id,
                        'branch' => [
                            'id' =>
                                (int) $item->branch_id,
                            'code' =>
                                (string) $item->branch_code,
                            'name' =>
                                (string) $item->branch_name,
                        ],
                        'item_type' =>
                            (string) $item->item_type,
                        'item_type_label' =>
                            $this
                                ->accountsPayableRegistry
                                ->openItemTypeLabel(
                                    (string) $item
                                        ->item_type,
                                ),
                        'entry_type' =>
                            (string) $item->entry_type,
                        'entry_type_label' =>
                            $this
                                ->accountsPayableRegistry
                                ->ledgerEntryTypeLabel(
                                    (string) $item
                                        ->entry_type,
                                ),
                        'balance_side' => $isPayable
                            ? 'payable'
                            : 'credit',
                        'source_type' =>
                            (string) $item->source_type,
                        'source_id' =>
                            (int) $item->source_id,
                        'document_number' =>
                            $item->document_number,
                        'document_date' =>
                            (string) $item
                                ->document_date,
                        'posting_date' =>
                            (string) $item
                                ->posting_date,
                        'due_date' =>
                            $item->due_date,
                        'currency_code' =>
                            (string) $item
                                ->currency_code,
                        'exchange_rate' =>
                            $this->rateString(
                                $item->exchange_rate,
                            ),
                        'original_amount' =>
                            $this->decimalString(
                                $item->original_amount,
                            ),
                        'historical_allocated_amount' =>
                            $this->decimalString(
                                $item
                                    ->historical_allocated_amount,
                            ),
                        'outstanding_amount' =>
                            $this->decimalString(
                                $item
                                    ->historical_outstanding_amount,
                            ),
                        'base_original_amount' =>
                            $this->decimalString(
                                $item
                                    ->base_original_amount,
                            ),
                        'historical_base_allocated_amount' =>
                            $this->decimalString(
                                $item
                                    ->historical_base_allocated_amount,
                            ),
                        'base_outstanding_amount' =>
                            $this->decimalString(
                                $item
                                    ->historical_base_outstanding_amount,
                            ),
                        'days_overdue' =>
                            $daysOverdue,
                        'bucket_key' => $isPayable
                            ? (string) $item
                                ->bucket_key
                            : null,
                        'bucket_label' => $isPayable
                            ? $this
                                ->bucketRegistry
                                ->label(
                                    (string) $item
                                        ->bucket_key,
                                )
                            : 'Unapplied Credit',
                    ];
                },
            )
            ->values()
            ->all();

        return [
            'supplier' => [
                'id' => (int) $supplier->getKey(),
                'code' => $supplier->code,
                'name' => $supplier->name,
                'status' => $supplier->status,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'payment_terms_days' =>
                    (int) $supplier
                        ->payment_terms_days,
            ],
            'filters' => $context,
            'base_currency_code' => mb_strtoupper(
                (string) $tenant->currency_code,
            ),
            'buckets' =>
                $this->bucketRegistry->options(),
            'summary' => $summary,
            'currencies' =>
                $this->currencyBreakdowns(
                    actor: $actor,
                    asOfDate:
                        $context['as_of_date'],
                    branchId:
                        $context['branch_id'],
                    supplierIds: [
                        (int) $supplier->getKey(),
                    ],
                    currencyCode:
                        $context['currency_code'],
                    search:
                        $context['search'],
                )[
                    (int) $supplier->getKey()
                ] ?? [],
            'items' => [
                'data' => $data,
                'meta' => [
                    'current_page' =>
                        $items->currentPage(),
                    'last_page' =>
                        $items->lastPage(),
                    'per_page' =>
                        $items->perPage(),
                    'from' =>
                        $items->firstItem(),
                    'to' =>
                        $items->lastItem(),
                    'total' =>
                        $items->total(),
                ],
            ],
        ];
    }

    /**
     * @param array{
     *     as_of_date: string,
     *     branch_id: int|null,
     *     supplier_id: int|null,
     *     currency_code: string|null,
     *     search: string,
     *     sort: string,
     *     direction: string,
     *     per_page: int
     * } $context
     */
    private function summaryExportQuery(
        array $context,
        User $actor,
    ): QueryBuilder {
        $itemQuery = $this->historicalItemQuery(
            actor: $actor,
            asOfDate:
                $context['as_of_date'],
            branchId:
                $context['branch_id'],
            supplierId:
                $context['supplier_id'],
            currencyCode:
                $context['currency_code'],
            search:
                $context['search'],
        );

        return DB::query()
            ->fromSub(
                $itemQuery,
                'aging_items',
            )
            ->select([
                'aging_items.supplier_id',
                'aging_items.supplier_code',
                'aging_items.supplier_name',
                'aging_items.supplier_status',
            ])
            ->selectRaw(
                'SUM(CASE WHEN aging_items.is_payable = 1 THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS total_payable',
            )
            ->selectRaw(
                'SUM(CASE WHEN aging_items.is_payable = 0 THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS unapplied_credit',
            )
            ->selectRaw(
                'SUM(CASE WHEN aging_items.is_payable = 1 THEN aging_items.historical_base_outstanding_amount ELSE -aging_items.historical_base_outstanding_amount END) AS net_outstanding',
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'current' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS current_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_1_30' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_1_30_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_31_60' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_31_60_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_61_90' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_61_90_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_91_120' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_91_120_amount",
            )
            ->selectRaw(
                "SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_over_120' THEN aging_items.historical_base_outstanding_amount ELSE 0 END) AS days_over_120_amount",
            )
            ->groupBy([
                'aging_items.supplier_id',
                'aging_items.supplier_code',
                'aging_items.supplier_name',
                'aging_items.supplier_status',
            ]);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function supplierDetailExportQuery(
        Supplier $supplier,
        array $filters,
        User $actor,
    ): QueryBuilder {
        $tenant = $this->tenantContext->tenant();

        $this->ensureSupplierContext(
            supplier: $supplier,
            actor: $actor,
            tenantId:
                (int) $tenant->getKey(),
        );

        $filters['supplier_id'] =
            (int) $supplier->getKey();

        $context = $this->context(
            filters: $filters,
            actor: $actor,
        );

        return DB::query()->fromSub(
            $this->historicalItemQuery(
                actor: $actor,
                asOfDate:
                    $context['as_of_date'],
                branchId:
                    $context['branch_id'],
                supplierId:
                    (int) $supplier->getKey(),
                currencyCode:
                    $context['currency_code'],
                search:
                    $context['search'],
            ),
            'aging_items',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeSummaryExportRow(
        object $row,
    ): array {
        return [
            'supplier_id' =>
                (int) $row->supplier_id,
            'supplier_code' =>
                (string) $row->supplier_code,
            'supplier_name' =>
                (string) $row->supplier_name,
            'supplier_status' =>
                (string) $row->supplier_status,
            'total_payable' =>
                $this->decimalString(
                    $row->total_payable,
                ),
            'unapplied_credit' =>
                $this->decimalString(
                    $row->unapplied_credit,
                ),
            'net_outstanding' =>
                $this->decimalString(
                    $row->net_outstanding,
                ),
            'current' =>
                $this->decimalString(
                    $row->current_amount,
                ),
            'days_1_30' =>
                $this->decimalString(
                    $row->days_1_30_amount,
                ),
            'days_31_60' =>
                $this->decimalString(
                    $row->days_31_60_amount,
                ),
            'days_61_90' =>
                $this->decimalString(
                    $row->days_61_90_amount,
                ),
            'days_91_120' =>
                $this->decimalString(
                    $row->days_91_120_amount,
                ),
            'days_over_120' =>
                $this->decimalString(
                    $row->days_over_120_amount,
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeDetailExportRow(
        object $item,
    ): array {
        $isPayable =
            (int) $item->is_payable === 1;

        $daysOverdue = $isPayable
            ? (int) $item->days_overdue
            : null;

        return [
            'id' =>
                (int) $item->open_item_id,
            'ledger_entry_id' =>
                (int) $item
                    ->supplier_ledger_entry_id,
            'branch_id' =>
                (int) $item->branch_id,
            'branch_code' =>
                (string) $item->branch_code,
            'branch_name' =>
                (string) $item->branch_name,
            'item_type' =>
                (string) $item->item_type,
            'item_type_label' =>
                $this
                    ->accountsPayableRegistry
                    ->openItemTypeLabel(
                        (string) $item
                            ->item_type,
                    ),
            'entry_type' =>
                (string) $item->entry_type,
            'entry_type_label' =>
                $this
                    ->accountsPayableRegistry
                    ->ledgerEntryTypeLabel(
                        (string) $item
                            ->entry_type,
                    ),
            'balance_side' => $isPayable
                ? 'payable'
                : 'credit',
            'source_type' =>
                (string) $item->source_type,
            'source_id' =>
                (int) $item->source_id,
            'document_number' =>
                $item->document_number,
            'document_date' =>
                (string) $item
                    ->document_date,
            'posting_date' =>
                (string) $item
                    ->posting_date,
            'due_date' =>
                $item->due_date,
            'currency_code' =>
                (string) $item
                    ->currency_code,
            'exchange_rate' =>
                $this->rateString(
                    $item->exchange_rate,
                ),
            'original_amount' =>
                $this->decimalString(
                    $item->original_amount,
                ),
            'historical_allocated_amount' =>
                $this->decimalString(
                    $item
                        ->historical_allocated_amount,
                ),
            'outstanding_amount' =>
                $this->decimalString(
                    $item
                        ->historical_outstanding_amount,
                ),
            'base_original_amount' =>
                $this->decimalString(
                    $item
                        ->base_original_amount,
                ),
            'historical_base_allocated_amount' =>
                $this->decimalString(
                    $item
                        ->historical_base_allocated_amount,
                ),
            'base_outstanding_amount' =>
                $this->decimalString(
                    $item
                        ->historical_base_outstanding_amount,
                ),
            'days_overdue' =>
                $daysOverdue,
            'bucket_key' => $isPayable
                ? (string) $item
                    ->bucket_key
                : null,
            'bucket_label' => $isPayable
                ? $this
                    ->bucketRegistry
                    ->label(
                        (string) $item
                            ->bucket_key,
                    )
                : 'Unapplied Credit',
        ];
    }

        private function historicalItemQuery(
        User $actor,
        string $asOfDate,
        ?int $branchId,
        ?int $supplierId,
        ?string $currencyCode,
        string $search,
    ): QueryBuilder {
        $payableAllocations = DB::table(
            'supplier_open_item_allocations',
        )
            ->select('payable_open_item_id')
            ->selectRaw(
                'COALESCE(SUM(amount), 0) AS allocated_amount',
            )
            ->selectRaw(
                'COALESCE(SUM(payable_base_amount), 0) AS base_allocated_amount',
            )
            ->whereDate(
                'posting_date',
                '<=',
                $asOfDate,
            )
            ->where(
                static function (
                    QueryBuilder $query,
                ) use ($asOfDate): void {
                    $query
                        ->whereNull(
                            'reversal_posting_date',
                        )
                        ->orWhereDate(
                            'reversal_posting_date',
                            '>',
                            $asOfDate,
                        );
                },
            )
            ->groupBy('payable_open_item_id');

        $creditAllocations = DB::table(
            'supplier_open_item_allocations',
        )
            ->select('credit_open_item_id')
            ->selectRaw(
                'COALESCE(SUM(amount), 0) AS allocated_amount',
            )
            ->selectRaw(
                'COALESCE(SUM(credit_base_amount), 0) AS base_allocated_amount',
            )
            ->whereDate(
                'posting_date',
                '<=',
                $asOfDate,
            )
            ->where(
                static function (
                    QueryBuilder $query,
                ) use ($asOfDate): void {
                    $query
                        ->whereNull(
                            'reversal_posting_date',
                        )
                        ->orWhereDate(
                            'reversal_posting_date',
                            '>',
                            $asOfDate,
                        );
                },
            )
            ->groupBy('credit_open_item_id');

        /** @var EloquentBuilder<SupplierOpenItem> $query */
        $query = SupplierOpenItem::query()
            ->join(
                'supplier_ledger_entries AS aging_ledger_entries',
                'aging_ledger_entries.id',
                '=',
                'supplier_open_items.supplier_ledger_entry_id',
            )
            ->join(
                'suppliers AS aging_suppliers',
                'aging_suppliers.id',
                '=',
                'supplier_open_items.supplier_id',
            )
            ->join(
                'branches AS aging_branches',
                'aging_branches.id',
                '=',
                'supplier_open_items.branch_id',
            )
            ->leftJoinSub(
                $payableAllocations,
                'aging_payable_allocations',
                static function ($join): void {
                    $join->on(
                        'aging_payable_allocations.payable_open_item_id',
                        '=',
                        'supplier_open_items.id',
                    );
                },
            )
            ->leftJoinSub(
                $creditAllocations,
                'aging_credit_allocations',
                static function ($join): void {
                    $join->on(
                        'aging_credit_allocations.credit_open_item_id',
                        '=',
                        'supplier_open_items.id',
                    );
                },
            )
            ->leftJoin(
                'supplier_ledger_entries AS aging_effective_reversals',
                static function ($join) use ($asOfDate): void {
                    $join
                        ->on(
                            'aging_effective_reversals.reversal_of_id',
                            '=',
                            'aging_ledger_entries.id',
                        )
                        ->whereDate(
                            'aging_effective_reversals.posting_date',
                            '<=',
                            $asOfDate,
                        );
                },
            )
            ->whereDate(
                'supplier_open_items.posting_date',
                '<=',
                $asOfDate,
            )
            ->whereNull(
                'aging_effective_reversals.id',
            )
            ->when(
                $branchId !== null,
                static fn (
                    EloquentBuilder $itemQuery,
                ): EloquentBuilder => $itemQuery->where(
                    'supplier_open_items.branch_id',
                    $branchId,
                ),
            )
            ->when(
                $supplierId !== null,
                static fn (
                    EloquentBuilder $itemQuery,
                ): EloquentBuilder => $itemQuery->where(
                    'supplier_open_items.supplier_id',
                    $supplierId,
                ),
            )
            ->when(
                $currencyCode !== null,
                static fn (
                    EloquentBuilder $itemQuery,
                ): EloquentBuilder => $itemQuery->where(
                    'supplier_open_items.currency_code',
                    $currencyCode,
                ),
            )
            ->when(
                $search !== '',
                static function (
                    EloquentBuilder $itemQuery,
                ) use ($search): void {
                    $itemQuery->where(
                        static function (
                            EloquentBuilder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'aging_suppliers.name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'aging_suppliers.code',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'supplier_open_items.document_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'aging_ledger_entries.reference',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'aging_ledger_entries.journal_reference',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            );

        $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
            branchColumn:
                'supplier_open_items.branch_id',
        );

        $query
            ->select([
                'supplier_open_items.id AS open_item_id',
                'supplier_open_items.supplier_ledger_entry_id',
                'supplier_open_items.branch_id',
                'supplier_open_items.supplier_id',
                'supplier_open_items.item_type',
                'supplier_open_items.source_type',
                'supplier_open_items.source_id',
                'supplier_open_items.document_number',
                'supplier_open_items.document_date',
                'supplier_open_items.posting_date',
                'supplier_open_items.due_date',
                'supplier_open_items.currency_code',
                'supplier_open_items.exchange_rate',
                'supplier_open_items.original_amount',
                'supplier_open_items.base_original_amount',
                'aging_ledger_entries.entry_type',
                'aging_suppliers.code AS supplier_code',
                'aging_suppliers.name AS supplier_name',
                'aging_suppliers.status AS supplier_status',
                'aging_branches.code AS branch_code',
                'aging_branches.name AS branch_name',
            ])
            ->selectRaw(
                'CASE WHEN aging_ledger_entries.credit_amount > 0 THEN 1 ELSE 0 END AS is_payable',
            )
            ->selectRaw(
                'CASE WHEN aging_ledger_entries.credit_amount > 0 THEN COALESCE(aging_payable_allocations.allocated_amount, 0) ELSE COALESCE(aging_credit_allocations.allocated_amount, 0) END AS historical_allocated_amount',
            )
            ->selectRaw(
                'GREATEST(supplier_open_items.original_amount - CASE WHEN aging_ledger_entries.credit_amount > 0 THEN COALESCE(aging_payable_allocations.allocated_amount, 0) ELSE COALESCE(aging_credit_allocations.allocated_amount, 0) END, 0) AS historical_outstanding_amount',
            )
            ->selectRaw(
                'CASE WHEN aging_ledger_entries.credit_amount > 0 THEN COALESCE(aging_payable_allocations.base_allocated_amount, 0) ELSE COALESCE(aging_credit_allocations.base_allocated_amount, 0) END AS historical_base_allocated_amount',
            )
            ->selectRaw(
                'GREATEST(supplier_open_items.base_original_amount - CASE WHEN aging_ledger_entries.credit_amount > 0 THEN COALESCE(aging_payable_allocations.base_allocated_amount, 0) ELSE COALESCE(aging_credit_allocations.base_allocated_amount, 0) END, 0) AS historical_base_outstanding_amount',
            )
            ->selectRaw(
                'DATEDIFF(?, COALESCE(supplier_open_items.due_date, supplier_open_items.document_date)) AS days_overdue',
                [$asOfDate],
            )
            ->selectRaw(
                "CASE
                    WHEN aging_ledger_entries.credit_amount <= 0 THEN 'unapplied_credit'
                    WHEN DATEDIFF(?, COALESCE(supplier_open_items.due_date, supplier_open_items.document_date)) <= 0 THEN 'current'
                    WHEN DATEDIFF(?, COALESCE(supplier_open_items.due_date, supplier_open_items.document_date)) <= 30 THEN 'days_1_30'
                    WHEN DATEDIFF(?, COALESCE(supplier_open_items.due_date, supplier_open_items.document_date)) <= 60 THEN 'days_31_60'
                    WHEN DATEDIFF(?, COALESCE(supplier_open_items.due_date, supplier_open_items.document_date)) <= 90 THEN 'days_61_90'
                    WHEN DATEDIFF(?, COALESCE(supplier_open_items.due_date, supplier_open_items.document_date)) <= 120 THEN 'days_91_120'
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

        return DB::query()
            ->fromSub(
                $query->toBase(),
                'historical_items',
            )
            ->where(
                'historical_items.historical_base_outstanding_amount',
                '>',
                0,
            )
            ->select('historical_items.*');
    }

    /**
     * @param QueryBuilder $summaryQuery
     * @return array<string, string>
     */
    private function summaryTotals(
        QueryBuilder $summaryQuery,
    ): array {
        $row = DB::query()
            ->fromSub(
                $summaryQuery,
                'supplier_aging_summary',
            )
            ->selectRaw(
                'COALESCE(SUM(total_payable), 0) AS total_payable',
            )
            ->selectRaw(
                'COALESCE(SUM(unapplied_credit), 0) AS unapplied_credit',
            )
            ->selectRaw(
                'COALESCE(SUM(net_outstanding), 0) AS net_outstanding',
            )
            ->selectRaw(
                'COALESCE(SUM(current_amount), 0) AS current_amount',
            )
            ->selectRaw(
                'COALESCE(SUM(days_1_30_amount), 0) AS days_1_30_amount',
            )
            ->selectRaw(
                'COALESCE(SUM(days_31_60_amount), 0) AS days_31_60_amount',
            )
            ->selectRaw(
                'COALESCE(SUM(days_61_90_amount), 0) AS days_61_90_amount',
            )
            ->selectRaw(
                'COALESCE(SUM(days_91_120_amount), 0) AS days_91_120_amount',
            )
            ->selectRaw(
                'COALESCE(SUM(days_over_120_amount), 0) AS days_over_120_amount',
            )
            ->first();

        return [
            'total_payable' =>
                $this->decimalString($row?->total_payable),

            'unapplied_credit' =>
                $this->decimalString($row?->unapplied_credit),

            'net_outstanding' =>
                $this->decimalString($row?->net_outstanding),

            'current' =>
                $this->decimalString($row?->current_amount),

            'days_1_30' =>
                $this->decimalString($row?->days_1_30_amount),

            'days_31_60' =>
                $this->decimalString($row?->days_31_60_amount),

            'days_61_90' =>
                $this->decimalString($row?->days_61_90_amount),

            'days_91_120' =>
                $this->decimalString($row?->days_91_120_amount),

            'days_over_120' =>
                $this->decimalString($row?->days_over_120_amount),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function detailTotals(
        QueryBuilder $itemQuery,
    ): array {
        $row = $itemQuery
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN aging_items.is_payable = 1 THEN aging_items.historical_base_outstanding_amount ELSE 0 END), 0) AS total_payable',
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN aging_items.is_payable = 0 THEN aging_items.historical_base_outstanding_amount ELSE 0 END), 0) AS unapplied_credit',
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN aging_items.is_payable = 1 THEN aging_items.historical_base_outstanding_amount ELSE -aging_items.historical_base_outstanding_amount END), 0) AS net_outstanding',
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'current' THEN aging_items.historical_base_outstanding_amount ELSE 0 END), 0) AS current_amount",
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_1_30' THEN aging_items.historical_base_outstanding_amount ELSE 0 END), 0) AS days_1_30_amount",
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_31_60' THEN aging_items.historical_base_outstanding_amount ELSE 0 END), 0) AS days_31_60_amount",
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_61_90' THEN aging_items.historical_base_outstanding_amount ELSE 0 END), 0) AS days_61_90_amount",
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_91_120' THEN aging_items.historical_base_outstanding_amount ELSE 0 END), 0) AS days_91_120_amount",
            )
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN aging_items.is_payable = 1 AND aging_items.bucket_key = 'days_over_120' THEN aging_items.historical_base_outstanding_amount ELSE 0 END), 0) AS days_over_120_amount",
            )
            ->first();

        return [
            'total_payable' =>
                $this->decimalString($row?->total_payable),

            'unapplied_credit' =>
                $this->decimalString($row?->unapplied_credit),

            'net_outstanding' =>
                $this->decimalString($row?->net_outstanding),

            'current' =>
                $this->decimalString($row?->current_amount),

            'days_1_30' =>
                $this->decimalString($row?->days_1_30_amount),

            'days_31_60' =>
                $this->decimalString($row?->days_31_60_amount),

            'days_61_90' =>
                $this->decimalString($row?->days_61_90_amount),

            'days_91_120' =>
                $this->decimalString($row?->days_91_120_amount),

            'days_over_120' =>
                $this->decimalString($row?->days_over_120_amount),
        ];
    }

    /**
     * @param list<int> $supplierIds
     * @return array<int, list<array<string, string>>>
     */
    private function currencyBreakdowns(
        User $actor,
        string $asOfDate,
        ?int $branchId,
        array $supplierIds,
        ?string $currencyCode,
        string $search,
    ): array {
        if ($supplierIds === []) {
            return [];
        }

        $query = DB::query()
            ->fromSub(
                $this->historicalItemQuery(
                    actor: $actor,
                    asOfDate: $asOfDate,
                    branchId: $branchId,
                    supplierId: null,
                    currencyCode: $currencyCode,
                    search: $search,
                ),
                'aging_currency_items',
            )
            ->whereIn(
                'aging_currency_items.supplier_id',
                $supplierIds,
            )
            ->select([
                'aging_currency_items.supplier_id',
                'aging_currency_items.currency_code',
            ])
            ->selectRaw(
                'SUM(CASE WHEN aging_currency_items.is_payable = 1 THEN aging_currency_items.historical_outstanding_amount ELSE 0 END) AS total_payable',
            )
            ->selectRaw(
                'SUM(CASE WHEN aging_currency_items.is_payable = 0 THEN aging_currency_items.historical_outstanding_amount ELSE 0 END) AS unapplied_credit',
            )
            ->selectRaw(
                'SUM(CASE WHEN aging_currency_items.is_payable = 1 THEN aging_currency_items.historical_outstanding_amount ELSE -aging_currency_items.historical_outstanding_amount END) AS net_outstanding',
            )
            ->selectRaw(
                'SUM(CASE WHEN aging_currency_items.is_payable = 1 THEN aging_currency_items.historical_base_outstanding_amount ELSE 0 END) AS base_total_payable',
            )
            ->selectRaw(
                'SUM(CASE WHEN aging_currency_items.is_payable = 0 THEN aging_currency_items.historical_base_outstanding_amount ELSE 0 END) AS base_unapplied_credit',
            )
            ->selectRaw(
                'SUM(CASE WHEN aging_currency_items.is_payable = 1 THEN aging_currency_items.historical_base_outstanding_amount ELSE -aging_currency_items.historical_base_outstanding_amount END) AS base_net_outstanding',
            )
            ->groupBy([
                'aging_currency_items.supplier_id',
                'aging_currency_items.currency_code',
            ])
            ->orderBy('aging_currency_items.supplier_id')
            ->orderBy('aging_currency_items.currency_code')
            ->get();

        $breakdowns = [];

        foreach ($query as $row) {
            $supplierId = (int) $row->supplier_id;

            $breakdowns[$supplierId][] = [
                'currency_code' =>
                    (string) $row->currency_code,

                'total_payable' =>
                    $this->decimalString(
                        $row->total_payable,
                    ),

                'unapplied_credit' =>
                    $this->decimalString(
                        $row->unapplied_credit,
                    ),

                'net_outstanding' =>
                    $this->decimalString(
                        $row->net_outstanding,
                    ),

                'base_total_payable' =>
                    $this->decimalString(
                        $row->base_total_payable,
                    ),

                'base_unapplied_credit' =>
                    $this->decimalString(
                        $row->base_unapplied_credit,
                    ),

                'base_net_outstanding' =>
                    $this->decimalString(
                        $row->base_net_outstanding,
                    ),
            ];
        }

        return $breakdowns;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{
     *     as_of_date: string,
     *     branch_id: int|null,
     *     supplier_id: int|null,
     *     currency_code: string|null,
     *     search: string,
     *     sort: string,
     *     direction: string,
     *     per_page: int
     * }
     */
    private function context(
        array $filters,
        User $actor,
    ): array {
        $tenant = $this->tenantContext->tenant();
        $tenantId = (int) $tenant->getKey();

        if ((int) $actor->tenant_id !== $tenantId) {
            throw new LogicException(
                'The Accounts Payable Aging user does not belong to the active tenant.',
            );
        }

        $asOfDate = $this->dateString(
            value: $filters['as_of_date'] ?? null,
            default: CarbonImmutable::now(
                $tenant->timezone,
            )->toDateString(),
            timezone: $tenant->timezone,
        );

        $branchId = $this->optionalId(
            $filters['branch_id'] ?? null,
            'branch_id',
        );

        if ($branchId !== null) {
            $branch = $this->branchAccessService
                ->findAccessibleBranch(
                    user: $actor,
                    branchId: $branchId,
                    requireActive: false,
                );

            if (!$branch instanceof Branch) {
                throw ValidationException::withMessages([
                    'branch_id' => [
                        'The selected branch is unavailable or outside your access scope.',
                    ],
                ]);
            }
        }

        $supplierId = $this->optionalId(
            $filters['supplier_id'] ?? null,
            'supplier_id',
        );

        if ($supplierId !== null) {
            $supplier = Supplier::withTrashed()
                ->whereKey($supplierId)
                ->first();

            if (!$supplier instanceof Supplier) {
                throw ValidationException::withMessages([
                    'supplier_id' => [
                        'The selected supplier is unavailable.',
                    ],
                ]);
            }
        }

        $currencyCode = $this->optionalCurrencyCode(
            $filters['currency_code'] ?? null,
        );

        $sort = trim(
            (string) (
                $filters['sort']
                ?? 'net_outstanding'
            ),
        );

        if (
            !in_array(
                $sort,
                self::SORTS,
                true,
            )
        ) {
            $sort = 'net_outstanding';
        }

        $direction = mb_strtolower(
            trim(
                (string) (
                    $filters['direction']
                    ?? 'desc'
                ),
            ),
        );

        return [
            'as_of_date' => $asOfDate,
            'branch_id' => $branchId,
            'supplier_id' => $supplierId,
            'currency_code' => $currencyCode,

            'search' => trim(
                (string) (
                    $filters['search']
                    ?? ''
                ),
            ),

            'sort' => $sort,

            'direction' => in_array(
                $direction,
                [
                    'asc',
                    'desc',
                ],
                true,
            )
                ? $direction
                : 'desc',

            'per_page' => $this->perPage(
                $filters['per_page'] ?? 25,
            ),
        ];
    }

    private function summarySortColumn(
        string $sort,
    ): string {
        return match ($sort) {
            'supplier_name' =>
                'aging_items.supplier_name',

            'total_payable' =>
                'total_payable',

            'unapplied_credit' =>
                'unapplied_credit',

            'net_outstanding' =>
                'net_outstanding',

            'current' =>
                'current_amount',

            'days_1_30' =>
                'days_1_30_amount',

            'days_31_60' =>
                'days_31_60_amount',

            'days_61_90' =>
                'days_61_90_amount',

            'days_91_120' =>
                'days_91_120_amount',

            'days_over_120' =>
                'days_over_120_amount',

            default =>
                'net_outstanding',
        };
    }

    private function dateString(
        mixed $value,
        string $default,
        string $timezone,
    ): string {
        if (
            $value === null
            || trim((string) $value) === ''
        ) {
            return $default;
        }

        try {
            return CarbonImmutable::parse(
                (string) $value,
                $timezone,
            )->toDateString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'as_of_date' => [
                    'The aging date must be valid.',
                ],
            ]);
        }
    }

    private function optionalId(
        mixed $value,
        string $field,
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        $id = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ],
        );

        if ($id === false) {
            throw ValidationException::withMessages([
                $field => [
                    'The selected identifier is invalid.',
                ],
            ]);
        }

        return (int) $id;
    }

    private function optionalCurrencyCode(
        mixed $value,
    ): ?string {
        if (
            $value === null
            || trim((string) $value) === ''
        ) {
            return null;
        }

        $currencyCode = mb_strtoupper(
            trim((string) $value),
        );

        if (
            preg_match(
                '/^[A-Z]{3}$/',
                $currencyCode,
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                'currency_code' => [
                    'The currency code must contain exactly three letters.',
                ],
            ]);
        }

        return $currencyCode;
    }

    private function perPage(mixed $value): int
    {
        $perPage = (int) $value;

        return in_array(
            $perPage,
            [
                10,
                15,
                25,
                50,
                100,
            ],
            true,
        )
            ? $perPage
            : 25;
    }

    private function decimalString(
        mixed $value,
    ): string {
        return BigDecimal::of(
            (string) ($value ?? '0'),
        )->toScale(
            self::MONEY_SCALE,
            RoundingMode::HALF_UP,
        )->__toString();
    }

    private function rateString(
        mixed $value,
    ): string {
        return BigDecimal::of(
            (string) ($value ?? '0'),
        )->toScale(
            self::RATE_SCALE,
            RoundingMode::HALF_UP,
        )->__toString();
    }

    private function ensureSupplierContext(
        Supplier $supplier,
        User $actor,
        int $tenantId,
    ): void {
        if (
            (int) $supplier->tenant_id
                !== $tenantId
            || (int) $actor->tenant_id
                !== $tenantId
        ) {
            throw new LogicException(
                'The Accounts Payable Aging context contains records from different tenants.',
            );
        }
    }
}