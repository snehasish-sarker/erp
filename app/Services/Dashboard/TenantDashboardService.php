<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\CustomerOpenItem;
use App\Models\CustomerReceipt;
use App\Models\InventoryBalance;
use App\Models\ProductWarehouseSetting;
use App\Models\PurchaseOrder;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SupplierInvoice;
use App\Models\SupplierOpenItem;
use App\Models\SupplierPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Services\Saas\SaasEntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use LogicException;

final class TenantDashboardService
{
    private const OPEN_ITEM_STATUSES = [
        'open',
        'partially_settled',
    ];

    public function __construct(
        private readonly BranchAccessService $branchAccessService,
        private readonly SaasEntitlementService $saasEntitlementService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(User $actor): array
    {
        $tenant = $actor->tenant()->first();

        if (!$tenant instanceof Tenant) {
            throw new LogicException(
                'The authenticated user is not attached to a tenant.',
            );
        }

        $tenantTimezone = $tenant->timezone;
        $timezone = is_string($tenantTimezone)
            && trim($tenantTimezone) !== ''
                ? $tenantTimezone
                : (string) config('app.timezone', 'UTC');

        $now = CarbonImmutable::now($timezone);
        $monthStart = $now->startOfMonth();
        $monthEnd = $now->endOfMonth();

        $branches = $this->branchAccessService
            ->accessibleBranches($actor, false);

        $branchIds = $branches
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $features = [
            'sales' => $this->saasEntitlementService->featureEnabled(
                $tenant,
                'sales.module',
            ),
            'purchasing' => $this->saasEntitlementService->featureEnabled(
                $tenant,
                'purchasing.module',
            ),
            'inventory' => $this->saasEntitlementService->featureEnabled(
                $tenant,
                'inventory.module',
            ),
            'receivables' => $this->saasEntitlementService->featureEnabled(
                $tenant,
                'accounts_receivable.module',
            ),
            'payables' => $this->saasEntitlementService->featureEnabled(
                $tenant,
                'accounts_payable.module',
            ),
            'master_data' => $this->saasEntitlementService->featureEnabled(
                $tenant,
                'master_data.module',
            ),
        ];

        $visibility = [
            'sales' => $features['sales']
                && (
                    $actor->can('sales_orders.view')
                    || $actor->can('sales_invoices.view')
                ),
            'purchasing' => $features['purchasing']
                && (
                    $actor->can('purchase_orders.view')
                    || $actor->can('supplier_invoices.view')
                ),
            'inventory' => $features['inventory']
                && $actor->can('inventory.view'),
            'inventory_cost' => $features['inventory']
                && $actor->can('inventory.view')
                && $actor->can('inventory.view_cost'),
            'receivables' => $features['receivables']
                && $actor->can('reports.receivables'),
            'payables' => $features['payables']
                && $actor->can('reports.payables'),
        ];

        $metrics = [];

        if (
            $features['sales']
            && $actor->can('sales_invoices.view')
        ) {
            $salesTotal = $this->baseMoneyTotal(
                query: SalesInvoice::query()
                    ->where('status', 'posted')
                    ->whereBetween(
                        'posting_date',
                        [
                            $monthStart->toDateString(),
                            $monthEnd->toDateString(),
                        ],
                    ),
                actor: $actor,
                expression: 'total_amount * exchange_rate',
            );

            $salesCount = $this->scoped(
                SalesInvoice::query()
                    ->where('status', 'posted')
                    ->whereBetween(
                        'posting_date',
                        [
                            $monthStart->toDateString(),
                            $monthEnd->toDateString(),
                        ],
                    ),
                $actor,
            )->count();

            $metrics[] = $this->moneyMetric(
                key: 'sales_month',
                label: 'Sales this month',
                value: $salesTotal,
                hint: "{$salesCount} posted invoice" . ($salesCount === 1 ? '' : 's'),
                href: route('sales-invoices.index', absolute: false),
                tone: 'success',
            );
        }

        if (
            $features['purchasing']
            && $actor->can('supplier_invoices.view')
        ) {
            $purchaseTotal = $this->baseMoneyTotal(
                query: SupplierInvoice::query()
                    ->where('status', 'posted')
                    ->whereBetween(
                        'posting_date',
                        [
                            $monthStart->toDateString(),
                            $monthEnd->toDateString(),
                        ],
                    ),
                actor: $actor,
                expression: 'total_amount * exchange_rate',
            );

            $purchaseCount = $this->scoped(
                SupplierInvoice::query()
                    ->where('status', 'posted')
                    ->whereBetween(
                        'posting_date',
                        [
                            $monthStart->toDateString(),
                            $monthEnd->toDateString(),
                        ],
                    ),
                $actor,
            )->count();

            $metrics[] = $this->moneyMetric(
                key: 'purchases_month',
                label: 'Purchases this month',
                value: $purchaseTotal,
                hint: "{$purchaseCount} posted invoice" . ($purchaseCount === 1 ? '' : 's'),
                href: route('supplier-invoices.index', absolute: false),
                tone: 'info',
            );
        }

        if ($visibility['receivables']) {
            $receivableQuery = CustomerOpenItem::query()
                ->where('item_type', 'invoice')
                ->whereIn('status', self::OPEN_ITEM_STATUSES);

            $receivableTotal = $this->baseMoneyTotal(
                query: clone $receivableQuery,
                actor: $actor,
                expression: 'base_outstanding_amount',
            );

            $overdueTotal = $this->baseMoneyTotal(
                query: (clone $receivableQuery)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', $now->toDateString()),
                actor: $actor,
                expression: 'base_outstanding_amount',
            );

            $metrics[] = $this->moneyMetric(
                key: 'receivables',
                label: 'Receivables outstanding',
                value: $receivableTotal,
                hint: $this->isZero($overdueTotal)
                    ? 'No overdue receivables'
                    : 'Overdue: ' . $this->compactMoney($overdueTotal, $tenant->currency_code),
                href: route('reports.accounts-receivable.aging', absolute: false),
                tone: $this->isZero($overdueTotal) ? 'neutral' : 'warning',
            );
        }

        if ($visibility['payables']) {
            $payableQuery = SupplierOpenItem::query()
                ->where('item_type', 'invoice')
                ->whereIn('status', self::OPEN_ITEM_STATUSES);

            $payableTotal = $this->baseMoneyTotal(
                query: clone $payableQuery,
                actor: $actor,
                expression: 'base_outstanding_amount',
            );

            $overdueTotal = $this->baseMoneyTotal(
                query: (clone $payableQuery)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', $now->toDateString()),
                actor: $actor,
                expression: 'base_outstanding_amount',
            );

            $metrics[] = $this->moneyMetric(
                key: 'payables',
                label: 'Payables outstanding',
                value: $payableTotal,
                hint: $this->isZero($overdueTotal)
                    ? 'No overdue payables'
                    : 'Overdue: ' . $this->compactMoney($overdueTotal, $tenant->currency_code),
                href: route('reports.accounts-payable.aging', absolute: false),
                tone: $this->isZero($overdueTotal) ? 'neutral' : 'warning',
            );
        }

        if ($visibility['inventory']) {
            $stockPositions = $this->scoped(
                InventoryBalance::query()
                    ->where('quantity_on_hand', '!=', 0),
                $actor,
            )->count();

            $reservedPositions = $this->scoped(
                InventoryBalance::query()
                    ->where('quantity_reserved', '>', 0),
                $actor,
            )->count();

            $lowStockPositions = $this->lowStockPositions($actor);

            if ($visibility['inventory_cost']) {
                $inventoryValue = $this->baseMoneyTotal(
                    query: InventoryBalance::query(),
                    actor: $actor,
                    expression: 'inventory_value',
                );

                $metrics[] = $this->moneyMetric(
                    key: 'inventory_value',
                    label: 'Inventory value',
                    value: $inventoryValue,
                    hint: "{$stockPositions} stock positions",
                    href: route('inventory.index', absolute: false),
                    tone: 'neutral',
                );
            } else {
                $metrics[] = $this->numberMetric(
                    key: 'stock_positions',
                    label: 'Stock positions',
                    value: $stockPositions,
                    hint: "{$reservedPositions} with reservations",
                    href: route('inventory.index', absolute: false),
                    tone: 'neutral',
                );
            }

            $metrics[] = $this->numberMetric(
                key: 'low_stock',
                label: 'Low-stock positions',
                value: $lowStockPositions,
                hint: $lowStockPositions === 0
                    ? 'No reorder alerts'
                    : 'At or below reorder level',
                href: route('inventory.index', absolute: false),
                tone: $lowStockPositions === 0 ? 'success' : 'danger',
            );
        }

        if (
            $features['sales']
            && $actor->can('sales_orders.view')
        ) {
            $openSalesOrders = $this->scoped(
                SalesOrder::query()
                    ->whereNotIn('status', ['closed', 'cancelled']),
                $actor,
            )->count();

            $metrics[] = $this->numberMetric(
                key: 'open_sales_orders',
                label: 'Open sales orders',
                value: $openSalesOrders,
                hint: 'All non-final sales orders',
                href: route('sales-orders.index', absolute: false),
                tone: 'neutral',
            );
        }

        if (
            $features['purchasing']
            && $actor->can('purchase_orders.view')
        ) {
            $openPurchaseOrders = $this->scoped(
                PurchaseOrder::query()
                    ->whereNotIn('status', ['closed', 'cancelled']),
                $actor,
            )->count();

            $metrics[] = $this->numberMetric(
                key: 'open_purchase_orders',
                label: 'Open purchase orders',
                value: $openPurchaseOrders,
                hint: 'All non-final purchase orders',
                href: route('purchase-orders.index', absolute: false),
                tone: 'neutral',
            );
        }

        return [
            'generated_at' => $now->toIso8601String(),
            'tenant' => [
                'name' => $tenant->name,
                'code' => $tenant->code,
                'currency_code' => $tenant->currency_code,
                'timezone' => $timezone,
            ],
            'period' => [
                'label' => $monthStart->format('F Y'),
                'start' => $monthStart->toDateString(),
                'end' => $monthEnd->toDateString(),
            ],
            'branch_scope' => [
                'mode' => $this->branchAccessService->hasCompanyWideAccess($actor)
                    ? 'company'
                    : 'assigned',
                'label' => $this->branchScopeLabel($actor, $branches),
                'branch_count' => count($branchIds),
            ],
            'visibility' => $visibility,
            'metrics' => $metrics,
            'trend' => $this->monthlyTrend(
                actor: $actor,
                tenant: $tenant,
                now: $now,
                canViewSales: $features['sales']
                    && $actor->can('sales_invoices.view'),
                canViewPurchases: $features['purchasing']
                    && $actor->can('supplier_invoices.view'),
            ),
            'action_items' => $this->actionItems(
                actor: $actor,
                features: $features,
            ),
            'recent_documents' => $this->recentDocuments(
                actor: $actor,
                features: $features,
            ),
            'quick_links' => $this->quickLinks(
                actor: $actor,
                features: $features,
            ),
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param Builder<TModel> $query
     * @return Builder<TModel>
     */
    private function scoped(Builder $query, User $actor): Builder
    {
        return $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
        );
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param Builder<TModel> $query
     */
    private function baseMoneyTotal(
        Builder $query,
        User $actor,
        string $expression,
    ): string {
        $query = $this->scoped($query, $actor);

        $row = $query
            ->selectRaw("COALESCE(SUM({$expression}), 0) AS aggregate_total")
            ->first();

        return $this->decimalString(
            $row?->getAttribute('aggregate_total'),
        );
    }

    private function lowStockPositions(User $actor): int
    {
        $query = ProductWarehouseSetting::query()
            ->leftJoin(
                'inventory_balances',
                static function ($join): void {
                    $join
                        ->on(
                            'inventory_balances.tenant_id',
                            '=',
                            'product_warehouse_settings.tenant_id',
                        )
                        ->on(
                            'inventory_balances.branch_id',
                            '=',
                            'product_warehouse_settings.branch_id',
                        )
                        ->on(
                            'inventory_balances.warehouse_id',
                            '=',
                            'product_warehouse_settings.warehouse_id',
                        )
                        ->on(
                            'inventory_balances.product_id',
                            '=',
                            'product_warehouse_settings.product_id',
                        );
                },
            )
            ->where('product_warehouse_settings.status', 'active')
            ->where('product_warehouse_settings.reorder_level', '>', 0)
            ->whereRaw(
                'COALESCE(inventory_balances.quantity_on_hand, 0) <= product_warehouse_settings.reorder_level',
            );

        return $this->branchAccessService
            ->scopeQuery(
                query: $query,
                user: $actor,
                branchColumn: 'product_warehouse_settings.branch_id',
            )
            ->distinct()
            ->count('product_warehouse_settings.id');
    }

    /**
     * @return list<array{
     *     period: string,
     *     label: string,
     *     sales: string|null,
     *     purchases: string|null
     * }>
     */
    private function monthlyTrend(
        User $actor,
        Tenant $tenant,
        CarbonImmutable $now,
        bool $canViewSales,
        bool $canViewPurchases,
    ): array {
        if (!$canViewSales && !$canViewPurchases) {
            return [];
        }

        $firstMonth = $now->startOfMonth()->subMonths(5);
        $lastMonth = $now->endOfMonth();

        /** @var Collection<string, mixed> $sales */
        $sales = collect();
        /** @var Collection<string, mixed> $purchases */
        $purchases = collect();

        if ($canViewSales) {
            $sales = $this->scoped(
                SalesInvoice::query()
                    ->where('status', 'posted')
                    ->whereBetween(
                        'posting_date',
                        [
                            $firstMonth->toDateString(),
                            $lastMonth->toDateString(),
                        ],
                    ),
                $actor,
            )
                ->selectRaw("DATE_FORMAT(posting_date, '%Y-%m') AS period")
                ->selectRaw('COALESCE(SUM(total_amount * exchange_rate), 0) AS total')
                ->groupBy('period')
                ->get()
                ->mapWithKeys(
                    static fn ($row): array => [
                        (string) $row->getAttribute('period') =>
                            $row->getAttribute('total'),
                    ],
                );
        }

        if ($canViewPurchases) {
            $purchases = $this->scoped(
                SupplierInvoice::query()
                    ->where('status', 'posted')
                    ->whereBetween(
                        'posting_date',
                        [
                            $firstMonth->toDateString(),
                            $lastMonth->toDateString(),
                        ],
                    ),
                $actor,
            )
                ->selectRaw("DATE_FORMAT(posting_date, '%Y-%m') AS period")
                ->selectRaw('COALESCE(SUM(total_amount * exchange_rate), 0) AS total')
                ->groupBy('period')
                ->get()
                ->mapWithKeys(
                    static fn ($row): array => [
                        (string) $row->getAttribute('period') =>
                            $row->getAttribute('total'),
                    ],
                );
        }

        $points = [];

        for ($offset = 0; $offset < 6; $offset++) {
            $month = $firstMonth->addMonths($offset);
            $key = $month->format('Y-m');

            $points[] = [
                'period' => $key,
                'label' => $month->format('M'),
                'sales' => $canViewSales
                    ? $this->decimalString($sales->get($key))
                    : null,
                'purchases' => $canViewPurchases
                    ? $this->decimalString($purchases->get($key))
                    : null,
            ];
        }

        return $points;
    }

    /**
     * @param array<string, bool> $features
     * @return list<array{
     *     key: string,
     *     label: string,
     *     count: int,
     *     href: string,
     *     tone: string
     * }>
     */
    private function actionItems(User $actor, array $features): array
    {
        $items = [];

        if ($features['sales'] && $actor->can('sales_orders.approve')) {
            $count = $this->scoped(
                SalesOrder::query()->where('status', 'submitted'),
                $actor,
            )->count();

            if ($count > 0) {
                $items[] = [
                    'key' => 'sales_orders_approval',
                    'label' => 'Sales orders awaiting approval',
                    'count' => $count,
                    'href' => route('sales-orders.index', ['status' => 'submitted'], false),
                    'tone' => 'warning',
                ];
            }
        }

        if ($features['purchasing'] && $actor->can('purchase_orders.approve')) {
            $count = $this->scoped(
                PurchaseOrder::query()->where('status', 'submitted'),
                $actor,
            )->count();

            if ($count > 0) {
                $items[] = [
                    'key' => 'purchase_orders_approval',
                    'label' => 'Purchase orders awaiting approval',
                    'count' => $count,
                    'href' => route('purchase-orders.index', ['status' => 'submitted'], false),
                    'tone' => 'warning',
                ];
            }
        }

        if ($features['purchasing'] && $actor->can('supplier_invoices.post')) {
            $count = $this->scoped(
                SupplierInvoice::query()->where('status', 'approved'),
                $actor,
            )->count();

            if ($count > 0) {
                $items[] = [
                    'key' => 'supplier_invoices_posting',
                    'label' => 'Supplier invoices ready to post',
                    'count' => $count,
                    'href' => route('supplier-invoices.index', ['status' => 'approved'], false),
                    'tone' => 'info',
                ];
            }
        }

        if ($features['receivables'] && $actor->can('customer_receipts.post')) {
            $count = $this->scoped(
                CustomerReceipt::query()->where('status', 'approved'),
                $actor,
            )->count();

            if ($count > 0) {
                $items[] = [
                    'key' => 'customer_receipts_posting',
                    'label' => 'Customer receipts ready to post',
                    'count' => $count,
                    'href' => route('customer-receipts.index', ['status' => 'approved'], false),
                    'tone' => 'info',
                ];
            }
        }

        if ($features['payables'] && $actor->can('supplier_payments.post')) {
            $count = $this->scoped(
                SupplierPayment::query()->where('status', 'approved'),
                $actor,
            )->count();

            if ($count > 0) {
                $items[] = [
                    'key' => 'supplier_payments_posting',
                    'label' => 'Supplier payments ready to post',
                    'count' => $count,
                    'href' => route('supplier-payments.index', ['status' => 'approved'], false),
                    'tone' => 'info',
                ];
            }
        }

        return $items;
    }

    /**
     * @param array<string, bool> $features
     * @return list<array<string, mixed>>
     */
    private function recentDocuments(User $actor, array $features): array
    {
        $documents = collect();

        if ($features['sales'] && $actor->can('sales_orders.view')) {
            $rows = $this->scoped(
                SalesOrder::query(),
                $actor,
            )
                ->latest('updated_at')
                ->limit(4)
                ->get([
                    'id',
                    'document_number',
                    'order_date',
                    'currency_code',
                    'total_amount',
                    'status',
                    'updated_at',
                ]);

            foreach ($rows as $row) {
                $documents->push([
                    'key' => 'sales_order_' . $row->getKey(),
                    'type' => 'Sales Order',
                    'number' => $row->document_number ?: 'Draft #' . $row->getKey(),
                    'date' => $row->order_date?->format('Y-m-d'),
                    'status' => $row->status,
                    'amount' => (string) $row->total_amount,
                    'currency_code' => $row->currency_code,
                    'href' => route(
                        'sales-orders.show',
                        ['salesOrder' => $row->getKey()],
                        false,
                    ),
                    'updated_at' => $row->updated_at?->toIso8601String(),
                    'updated_timestamp' => $row->updated_at?->getTimestamp() ?? 0,
                ]);
            }
        }

        if ($features['purchasing'] && $actor->can('purchase_orders.view')) {
            $rows = $this->scoped(
                PurchaseOrder::query(),
                $actor,
            )
                ->latest('updated_at')
                ->limit(4)
                ->get([
                    'id',
                    'document_number',
                    'order_date',
                    'currency_code',
                    'total_amount',
                    'status',
                    'updated_at',
                ]);

            foreach ($rows as $row) {
                $documents->push([
                    'key' => 'purchase_order_' . $row->getKey(),
                    'type' => 'Purchase Order',
                    'number' => $row->document_number ?: 'Draft #' . $row->getKey(),
                    'date' => $row->order_date?->format('Y-m-d'),
                    'status' => $row->status,
                    'amount' => (string) $row->total_amount,
                    'currency_code' => $row->currency_code,
                    'href' => route(
                        'purchase-orders.show',
                        ['purchaseOrder' => $row->getKey()],
                        false,
                    ),
                    'updated_at' => $row->updated_at?->toIso8601String(),
                    'updated_timestamp' => $row->updated_at?->getTimestamp() ?? 0,
                ]);
            }
        }

        if ($features['sales'] && $actor->can('sales_invoices.view')) {
            $rows = $this->scoped(
                SalesInvoice::query(),
                $actor,
            )
                ->latest('updated_at')
                ->limit(4)
                ->get([
                    'id',
                    'invoice_number',
                    'invoice_date',
                    'currency_code',
                    'total_amount',
                    'status',
                    'updated_at',
                ]);

            foreach ($rows as $row) {
                $documents->push([
                    'key' => 'sales_invoice_' . $row->getKey(),
                    'type' => 'Sales Invoice',
                    'number' => $row->invoice_number ?: 'Draft #' . $row->getKey(),
                    'date' => $row->invoice_date?->format('Y-m-d'),
                    'status' => $row->status,
                    'amount' => (string) $row->total_amount,
                    'currency_code' => $row->currency_code,
                    'href' => route(
                        'sales-invoices.show',
                        ['salesInvoice' => $row->getKey()],
                        false,
                    ),
                    'updated_at' => $row->updated_at?->toIso8601String(),
                    'updated_timestamp' => $row->updated_at?->getTimestamp() ?? 0,
                ]);
            }
        }

        if ($features['purchasing'] && $actor->can('supplier_invoices.view')) {
            $rows = $this->scoped(
                SupplierInvoice::query(),
                $actor,
            )
                ->latest('updated_at')
                ->limit(4)
                ->get([
                    'id',
                    'document_number',
                    'invoice_date',
                    'currency_code',
                    'total_amount',
                    'status',
                    'updated_at',
                ]);

            foreach ($rows as $row) {
                $documents->push([
                    'key' => 'supplier_invoice_' . $row->getKey(),
                    'type' => 'Supplier Invoice',
                    'number' => $row->document_number ?: 'Draft #' . $row->getKey(),
                    'date' => $row->invoice_date?->format('Y-m-d'),
                    'status' => $row->status,
                    'amount' => (string) $row->total_amount,
                    'currency_code' => $row->currency_code,
                    'href' => route(
                        'supplier-invoices.show',
                        ['supplierInvoice' => $row->getKey()],
                        false,
                    ),
                    'updated_at' => $row->updated_at?->toIso8601String(),
                    'updated_timestamp' => $row->updated_at?->getTimestamp() ?? 0,
                ]);
            }
        }

        return $documents
            ->sortByDesc('updated_timestamp')
            ->take(8)
            ->map(static function (array $document): array {
                unset($document['updated_timestamp']);

                return $document;
            })
            ->values()
            ->all();
    }

    /**
     * @param array<string, bool> $features
     * @return list<array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     href: string
     * }>
     */
    private function quickLinks(User $actor, array $features): array
    {
        $links = [];

        if ($features['sales'] && $actor->can('sales_orders.create')) {
            $links[] = [
                'key' => 'new_sales_order',
                'label' => 'New sales order',
                'description' => 'Create a customer sales order.',
                'href' => route('sales-orders.create', absolute: false),
            ];
        }

        if ($features['purchasing'] && $actor->can('purchase_orders.create')) {
            $links[] = [
                'key' => 'new_purchase_order',
                'label' => 'New purchase order',
                'description' => 'Create a supplier purchase order.',
                'href' => route('purchase-orders.create', absolute: false),
            ];
        }

        if ($features['receivables'] && $actor->can('customer_receipts.create')) {
            $links[] = [
                'key' => 'new_customer_receipt',
                'label' => 'Record customer receipt',
                'description' => 'Capture an incoming customer receipt.',
                'href' => route('customer-receipts.create', absolute: false),
            ];
        }

        if ($features['payables'] && $actor->can('supplier_payments.create')) {
            $links[] = [
                'key' => 'new_supplier_payment',
                'label' => 'Record supplier payment',
                'description' => 'Prepare an outgoing supplier payment.',
                'href' => route('supplier-payments.create', absolute: false),
            ];
        }

        if ($features['inventory'] && $actor->can('inventory.view')) {
            $links[] = [
                'key' => 'view_inventory',
                'label' => 'View inventory',
                'description' => 'Review stock by warehouse and product.',
                'href' => route('inventory.index', absolute: false),
            ];
        }

        if ($features['master_data'] && $actor->can('products.create')) {
            $links[] = [
                'key' => 'new_product',
                'label' => 'New product',
                'description' => 'Add a product to the catalogue.',
                'href' => route('products.create', absolute: false),
            ];
        }

        return array_slice($links, 0, 6);
    }

    /**
     * @param Collection<int, \App\Models\Branch> $branches
     */
    private function branchScopeLabel(User $actor, Collection $branches): string
    {
        if ($this->branchAccessService->hasCompanyWideAccess($actor)) {
            return 'All accessible branches';
        }

        $branch = $branches->first();

        if ($branch === null) {
            return 'No branch access';
        }

        return $branch->name . ' (' . $branch->code . ')';
    }

    /**
     * @return array<string, mixed>
     */
    private function moneyMetric(
        string $key,
        string $label,
        string $value,
        string $hint,
        string $href,
        string $tone,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'format' => 'money',
            'hint' => $hint,
            'href' => $href,
            'tone' => $tone,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function numberMetric(
        string $key,
        string $label,
        int $value,
        string $hint,
        string $href,
        string $tone,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'value' => $value,
            'format' => 'number',
            'hint' => $hint,
            'href' => $href,
            'tone' => $tone,
        ];
    }

    private function decimalString(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        return (string) $value;
    }


    private function isZero(string $value): bool
    {
        return abs((float) $value) < 0.0000005;
    }

    private function compactMoney(string $value, string $currencyCode): string
    {
        return mb_strtoupper($currencyCode)
            . ' '
            . number_format((float) $value, 2, '.', ',');
    }
}
