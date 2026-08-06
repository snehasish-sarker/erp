<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CancelSalesOrderRequest;
use App\Http\Requests\Sales\IndexSalesOrderRequest;
use App\Http\Requests\Sales\StoreSalesOrderRequest;
use App\Http\Requests\Sales\UpdateSalesOrderRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductBranchSetting;
use App\Models\ProductWarehouseSetting;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organisation\BranchAccessService;
use App\Services\Sales\SalesOrderService;
use App\Support\Responses\CommonResponseService;
use App\Support\Sales\SalesOrderStatusRegistry;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderService $salesOrderService,
        private readonly SalesOrderStatusRegistry $statusRegistry,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexSalesOrderRequest $request,
    ): Response {
        Gate::authorize('viewAny', SalesOrder::class);

        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $validated = $request->validated();
        $search = (string) ($validated['search'] ?? '');
        $branchId = isset($validated['branch_id'])
            ? (int) $validated['branch_id']
            : null;
        $warehouseId = isset($validated['warehouse_id'])
            ? (int) $validated['warehouse_id']
            : null;
        $customerId = isset($validated['customer_id'])
            ? (int) $validated['customer_id']
            : null;
        $status = (string) ($validated['status'] ?? '');
        $orderDateFrom = (string) ($validated['order_date_from'] ?? '');
        $orderDateTo = (string) ($validated['order_date_to'] ?? '');
        $deliveryFrom = (string) (
            $validated['requested_delivery_from'] ?? ''
        );
        $deliveryTo = (string) (
            $validated['requested_delivery_to'] ?? ''
        );
        $sort = (string) ($validated['sort'] ?? 'created_at');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = SalesOrder::query()->with([
            'branch:id,name,code,status',
            'warehouse:id,branch_id,name,code,status',
            'customer:id,name,code,status',
            'createdBy:id,name',
        ]);

        $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
            branchColumn: 'sales_orders.branch_id',
        );

        $salesOrders = $query
            ->when(
                $search !== '',
                static function (Builder $orderQuery) use ($search): void {
                    $orderQuery->where(
                        static function (Builder $searchQuery) use ($search): void {
                            $searchQuery
                                ->where(
                                    'document_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'customer_name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'customer_code',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'customer_reference',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'notes',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            )
            ->when(
                $branchId !== null,
                static fn (Builder $orderQuery): Builder =>
                    $orderQuery->where('branch_id', $branchId),
            )
            ->when(
                $warehouseId !== null,
                static fn (Builder $orderQuery): Builder =>
                    $orderQuery->where('warehouse_id', $warehouseId),
            )
            ->when(
                $customerId !== null,
                static fn (Builder $orderQuery): Builder =>
                    $orderQuery->where('customer_id', $customerId),
            )
            ->when(
                $status !== '',
                static fn (Builder $orderQuery): Builder =>
                    $orderQuery->where('status', $status),
            )
            ->when(
                $orderDateFrom !== '',
                static fn (Builder $orderQuery): Builder =>
                    $orderQuery->whereDate(
                        'order_date',
                        '>=',
                        $orderDateFrom,
                    ),
            )
            ->when(
                $orderDateTo !== '',
                static fn (Builder $orderQuery): Builder =>
                    $orderQuery->whereDate(
                        'order_date',
                        '<=',
                        $orderDateTo,
                    ),
            )
            ->when(
                $deliveryFrom !== '',
                static fn (Builder $orderQuery): Builder =>
                    $orderQuery->whereDate(
                        'requested_delivery_date',
                        '>=',
                        $deliveryFrom,
                    ),
            )
            ->when(
                $deliveryTo !== '',
                static fn (Builder $orderQuery): Builder =>
                    $orderQuery->whereDate(
                        'requested_delivery_date',
                        '<=',
                        $deliveryTo,
                    ),
            )
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'SalesOrders/Index',
            [
                'salesOrders' => [
                    'data' => $salesOrders
                        ->getCollection()
                        ->map(
                            fn (SalesOrder $salesOrder): array =>
                                $this->summaryData(
                                    salesOrder: $salesOrder,
                                    actor: $actor,
                                ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' => $salesOrders->currentPage(),
                        'last_page' => $salesOrders->lastPage(),
                        'per_page' => $salesOrders->perPage(),
                        'from' => $salesOrders->firstItem(),
                        'to' => $salesOrders->lastItem(),
                        'total' => $salesOrders->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'customer_id' => $customerId,
                    'status' => $status,
                    'order_date_from' => $orderDateFrom,
                    'order_date_to' => $orderDateTo,
                    'requested_delivery_from' => $deliveryFrom,
                    'requested_delivery_to' => $deliveryTo,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                ...$this->indexOptions($actor),

                'can' => [
                    'create' => $actor->can(
                        'create',
                        SalesOrder::class,
                    ),
                ],
            ],
        );
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', SalesOrder::class);

        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        return Inertia::render(
            'SalesOrders/Create',
            $this->formOptions($actor),
        );
    }

    public function store(
        StoreSalesOrderRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('create', SalesOrder::class);

        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $salesOrder = $this->salesOrderService->create(
            data: $request->validated(),
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Sales order created successfully.',
            data: $this->responseData($salesOrder),
            redirectTo: route('sales-orders.show', $salesOrder),
        );
    }

    public function show(
        Request $request,
        SalesOrder $salesOrder,
    ): Response {
        Gate::authorize('view', $salesOrder);

        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $salesOrder->load([
            'branch:id,name,code,status,address',
            'warehouse:id,branch_id,name,code,status,address',
            'customer:id,name,code,status',
            'lines.product:id,name,sku,status',
            'lines.unit:id,name,code,symbol,status',
            'createdBy:id,name',
            'submittedBy:id,name',
            'approvedBy:id,name',
            'cancelledBy:id,name',
            'documentNumberAllocation',
        ]);

        return Inertia::render(
            'SalesOrders/Show',
            [
                'salesOrder' => $this->detailData(
                    salesOrder: $salesOrder,
                    actor: $actor,
                ),
            ],
        );
    }

    public function edit(
        Request $request,
        SalesOrder $salesOrder,
    ): Response {
        Gate::authorize('update', $salesOrder);

        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $salesOrder->load([
            'lines.product.baseUnit',
            'lines.unit',
        ]);

        return Inertia::render(
            'SalesOrders/Edit',
            [
                ...$this->formOptions(
                    actor: $actor,
                    salesOrder: $salesOrder,
                ),
                'salesOrder' => $this->formData($salesOrder),
            ],
        );
    }

    public function update(
        UpdateSalesOrderRequest $request,
        SalesOrder $salesOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $salesOrder);

        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $salesOrder = $this->salesOrderService->update(
            salesOrder: $salesOrder,
            data: $request->validated(),
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Sales order updated successfully.',
            data: $this->responseData($salesOrder),
            redirectTo: route('sales-orders.show', $salesOrder),
        );
    }

    public function destroy(
        Request $request,
        SalesOrder $salesOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('delete', $salesOrder);

        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $this->salesOrderService->delete(
            salesOrder: $salesOrder,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Sales order deleted successfully.',
            redirectTo: route('sales-orders.index'),
        );
    }

    public function submit(
        Request $request,
        SalesOrder $salesOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('submit', $salesOrder);

        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $salesOrder = $this->salesOrderService->submit(
            salesOrder: $salesOrder,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Sales order submitted successfully.',
            data: $this->responseData($salesOrder),
            redirectTo: route('sales-orders.show', $salesOrder),
        );
    }

    public function returnToDraft(
        Request $request,
        SalesOrder $salesOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('returnToDraft', $salesOrder);

        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $salesOrder = $this->salesOrderService->returnToDraft(
            salesOrder: $salesOrder,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Sales order returned to draft successfully.',
            data: $this->responseData($salesOrder),
            redirectTo: route('sales-orders.show', $salesOrder),
        );
    }

    public function approve(
        Request $request,
        SalesOrder $salesOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('approve', $salesOrder);

        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $salesOrder = $this->salesOrderService->approve(
            salesOrder: $salesOrder,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Sales order approved successfully.',
            data: $this->responseData($salesOrder),
            redirectTo: route('sales-orders.show', $salesOrder),
        );
    }

    public function cancel(
        CancelSalesOrderRequest $request,
        SalesOrder $salesOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('cancel', $salesOrder);

        $actor = $request->user();

        abort_unless($actor instanceof User, 401);

        $salesOrder = $this->salesOrderService->cancel(
            salesOrder: $salesOrder,
            reason: (string) $request->validated(
                'cancellation_reason',
            ),
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Sales order cancelled successfully.',
            data: $this->responseData($salesOrder),
            redirectTo: route('sales-orders.show', $salesOrder),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function indexOptions(User $actor): array
    {
        $branches = $this->branchAccessService
            ->accessibleBranches(
                user: $actor,
                activeOnly: false,
            );

        $branchIds = $branches
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $warehouses = Warehouse::query()
            ->whereIn('branch_id', $branchIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'branch_id',
                'name',
                'code',
                'status',
            ]);

        $customers = Customer::query()
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'code',
                'status',
            ]);

        return [
            'branches' => $branches
                ->map(fn (Branch $branch): array => $this->branchData($branch))
                ->values()
                ->all(),

            'warehouses' => $warehouses
                ->map(
                    fn (Warehouse $warehouse): array =>
                        $this->warehouseData($warehouse),
                )
                ->values()
                ->all(),

            'customers' => $customers
                ->map(
                    static fn (Customer $customer): array => [
                        'id' => (int) $customer->getKey(),
                        'name' => $customer->name,
                        'code' => $customer->code,
                        'status' => $customer->status,
                    ],
                )
                ->values()
                ->all(),

            'statuses' => $this->statusRegistry->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(
        User $actor,
        ?SalesOrder $salesOrder = null,
    ): array {
        $tenant = $this->tenantContext->tenant();
        $branches = $this->branchAccessService
            ->accessibleBranches(
                user: $actor,
                activeOnly: true,
            );

        $branchIds = $branches
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $warehouses = Warehouse::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'active')
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'branch_id',
                'name',
                'code',
                'status',
            ]);

        $customers = Customer::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $products = Product::query()
            ->where('status', 'active')
            ->where('is_sellable', true)
            ->whereHas(
                'branchSettings',
                static function (Builder $query) use ($branchIds): void {
                    $query
                        ->whereIn('branch_id', $branchIds)
                        ->where('status', 'active')
                        ->where('is_sellable', true);
                },
            )
            ->with([
                'baseUnit:id,name,code,symbol,allow_decimal,decimal_places,status',

                'branchSettings' =>
                    static function (Builder $query) use ($branchIds): void {
                        $query
                            ->whereIn('branch_id', $branchIds)
                            ->where('status', 'active')
                            ->where('is_sellable', true)
                            ->orderBy('branch_id');
                    },

                'warehouseSettings' =>
                    static function (Builder $query) use ($branchIds): void {
                        $query
                            ->whereIn('branch_id', $branchIds)
                            ->where('status', 'active')
                            ->orderBy('warehouse_id');
                    },
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return [
            'branches' => $branches
                ->map(fn (Branch $branch): array => $this->branchData($branch))
                ->values()
                ->all(),

            'warehouses' => $warehouses
                ->map(
                    fn (Warehouse $warehouse): array =>
                        $this->warehouseData($warehouse),
                )
                ->values()
                ->all(),

            'customers' => $customers
                ->map(
                    fn (Customer $customer): array =>
                        $this->customerOptionData($customer),
                )
                ->values()
                ->all(),

            'products' => $products
                ->map(
                    fn (Product $product): array =>
                        $this->productOptionData($product),
                )
                ->values()
                ->all(),

            'defaults' => [
                'order_date' => CarbonImmutable::now(
                    $tenant->timezone,
                )->format('Y-m-d'),
                'currency_code' => $tenant->currency_code,
                'exchange_rate' => '1.00000000',
                'shipping_amount' => '0.000000',
                'other_charges' => '0.000000',
            ],

            'can' => [
                'override_price' => $salesOrder instanceof SalesOrder
                    ? $actor->can('overridePrice', $salesOrder)
                    : $actor->can('sales_orders.override_price'),

                'override_discount' => $salesOrder instanceof SalesOrder
                    ? $actor->can('overrideDiscount', $salesOrder)
                    : $actor->can('sales_orders.override_discount'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryData(
        SalesOrder $salesOrder,
        User $actor,
    ): array {
        return [
            'id' => (int) $salesOrder->getKey(),
            'document_number' => $salesOrder->document_number,
            'order_date' => $salesOrder->order_date?->format('Y-m-d'),
            'requested_delivery_date' =>
                $salesOrder->requested_delivery_date?->format('Y-m-d'),
            'customer_reference' => $salesOrder->customer_reference,
            'customer_name' => $salesOrder->customer_name,
            'customer_code' => $salesOrder->customer_code,
            'currency_code' => $salesOrder->currency_code,
            'total_amount' => (string) $salesOrder->total_amount,
            'status' => $salesOrder->status,
            'status_label' => $this->statusRegistry->label(
                $salesOrder->status,
            ),
            'revision' => (int) $salesOrder->revision,
            'branch' => $salesOrder->branch instanceof Branch
                ? $this->branchData($salesOrder->branch)
                : null,
            'warehouse' => $salesOrder->warehouse instanceof Warehouse
                ? $this->warehouseData($salesOrder->warehouse)
                : null,
            'created_by' => $this->userData($salesOrder->createdBy),
            'created_at' => $salesOrder->created_at?->toIso8601String(),
            'can' => $this->actionPermissions(
                actor: $actor,
                salesOrder: $salesOrder,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(
        SalesOrder $salesOrder,
        User $actor,
    ): array {
        return [
            ...$this->summaryData(
                salesOrder: $salesOrder,
                actor: $actor,
            ),
            'branch_id' => (int) $salesOrder->branch_id,
            'warehouse_id' => $salesOrder->warehouse_id !== null
                ? (int) $salesOrder->warehouse_id
                : null,
            'customer_id' => (int) $salesOrder->customer_id,
            'exchange_rate' => (string) $salesOrder->exchange_rate,
            'customer_type' => $salesOrder->customer_type,
            'customer_contact_person' =>
                $salesOrder->customer_contact_person,
            'customer_email' => $salesOrder->customer_email,
            'customer_phone' => $salesOrder->customer_phone,
            'customer_tax_number' => $salesOrder->customer_tax_number,
            'billing_address' => $salesOrder->billing_address,
            'shipping_address' => $salesOrder->shipping_address,
            'payment_terms_days' => (int) $salesOrder->payment_terms_days,
            'credit_limit_snapshot' =>
                (string) $salesOrder->credit_limit_snapshot,
            'subtotal' => (string) $salesOrder->subtotal,
            'discount_amount' => (string) $salesOrder->discount_amount,
            'tax_amount' => (string) $salesOrder->tax_amount,
            'shipping_amount' => (string) $salesOrder->shipping_amount,
            'other_charges' => (string) $salesOrder->other_charges,
            'delivery_instructions' =>
                $salesOrder->delivery_instructions,
            'terms_and_conditions' =>
                $salesOrder->terms_and_conditions,
            'notes' => $salesOrder->notes,
            'submitted_at' => $salesOrder->submitted_at?->toIso8601String(),
            'approved_at' => $salesOrder->approved_at?->toIso8601String(),
            'cancelled_at' => $salesOrder->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $salesOrder->cancellation_reason,
            'submitted_by' => $this->userData($salesOrder->submittedBy),
            'approved_by' => $this->userData($salesOrder->approvedBy),
            'cancelled_by' => $this->userData($salesOrder->cancelledBy),
            'lines' => $salesOrder->lines
                ->map(
                    fn (SalesOrderLine $line): array =>
                        $this->lineData($line),
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(SalesOrder $salesOrder): array
    {
        return [
            'id' => (int) $salesOrder->getKey(),
            'branch_id' => (int) $salesOrder->branch_id,
            'warehouse_id' => $salesOrder->warehouse_id !== null
                ? (int) $salesOrder->warehouse_id
                : null,
            'customer_id' => (int) $salesOrder->customer_id,
            'document_number' => $salesOrder->document_number,
            'order_date' => $salesOrder->order_date?->format('Y-m-d'),
            'requested_delivery_date' =>
                $salesOrder->requested_delivery_date?->format('Y-m-d'),
            'customer_reference' => $salesOrder->customer_reference,
            'currency_code' => $salesOrder->currency_code,
            'exchange_rate' => (string) $salesOrder->exchange_rate,
            'billing_address' => $salesOrder->billing_address,
            'shipping_address' => $salesOrder->shipping_address,
            'payment_terms_days' => (int) $salesOrder->payment_terms_days,
            'shipping_amount' => (string) $salesOrder->shipping_amount,
            'other_charges' => (string) $salesOrder->other_charges,
            'delivery_instructions' =>
                $salesOrder->delivery_instructions,
            'terms_and_conditions' =>
                $salesOrder->terms_and_conditions,
            'notes' => $salesOrder->notes,
            'status' => $salesOrder->status,
            'revision' => (int) $salesOrder->revision,
            'lines' => $salesOrder->lines
                ->map(
                    static fn (SalesOrderLine $line): array => [
                        'id' => (int) $line->getKey(),
                        'product_id' => (int) $line->product_id,
                        'unit_id' => (int) $line->unit_id,
                        'description' => $line->description,
                        'ordered_quantity' =>
                            (string) $line->ordered_quantity,
                        'unit_price' => (string) $line->unit_price,
                        'discount_amount' =>
                            (string) $line->discount_amount,
                        'tax_rate' => (string) $line->tax_rate,
                        'gross_amount' => (string) $line->gross_amount,
                        'tax_amount' => (string) $line->tax_amount,
                        'line_total' => (string) $line->line_total,
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineData(SalesOrderLine $line): array
    {
        return [
            'id' => (int) $line->getKey(),
            'line_number' => (int) $line->line_number,
            'product_id' => (int) $line->product_id,
            'unit_id' => (int) $line->unit_id,
            'product_name' => $line->product_name,
            'product_sku' => $line->product_sku,
            'product_type' => $line->product_type,
            'unit_name' => $line->unit_name,
            'unit_code' => $line->unit_code,
            'description' => $line->description,
            'ordered_quantity' => (string) $line->ordered_quantity,
            'allocated_quantity' => (string) $line->allocated_quantity,
            'dispatched_quantity' => (string) $line->dispatched_quantity,
            'invoiced_quantity' => (string) $line->invoiced_quantity,
            'returned_quantity' => (string) $line->returned_quantity,
            'unit_price' => (string) $line->unit_price,
            'gross_amount' => (string) $line->gross_amount,
            'discount_amount' => (string) $line->discount_amount,
            'tax_rate' => (string) $line->tax_rate,
            'tax_amount' => (string) $line->tax_amount,
            'line_total' => (string) $line->line_total,
            'is_fully_allocated' => $line->isFullyAllocated(),
            'is_fully_dispatched' => $line->isFullyDispatched(),
            'is_fully_invoiced' => $line->isFullyInvoiced(),
            'has_outstanding_allocation_quantity' =>
                $line->hasOutstandingAllocationQuantity(),
            'has_outstanding_dispatch_quantity' =>
                $line->hasOutstandingDispatchQuantity(),
            'has_outstanding_invoice_quantity' =>
                $line->hasOutstandingInvoiceQuantity(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function actionPermissions(
        User $actor,
        SalesOrder $salesOrder,
    ): array {
        return [
            'view' => $actor->can('view', $salesOrder),
            'update' => $actor->can('update', $salesOrder),
            'delete' => $actor->can('delete', $salesOrder),
            'submit' => $actor->can('submit', $salesOrder),
            'return_to_draft' => $actor->can(
                'returnToDraft',
                $salesOrder,
            ),
            'approve' => $actor->can('approve', $salesOrder),
            'allocate' => $actor->can('allocate', $salesOrder),
            'cancel' => $actor->can('cancel', $salesOrder),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function customerOptionData(Customer $customer): array
    {
        return [
            'id' => (int) $customer->getKey(),
            'name' => $customer->name,
            'code' => $customer->code,
            'customer_type' => $customer->customer_type,
            'contact_person' => $customer->contact_person,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'tax_number' => $customer->tax_number,
            'billing_address' => $this->customerAddress(
                customer: $customer,
                shipping: false,
            ),
            'shipping_address' => $this->customerAddress(
                customer: $customer,
                shipping: true,
            ),
            'payment_terms_days' => (int) $customer->payment_terms_days,
            'credit_limit' => (string) $customer->credit_limit,
            'status' => $customer->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productOptionData(Product $product): array
    {
        return [
            'id' => (int) $product->getKey(),
            'name' => $product->name,
            'sku' => $product->sku,
            'product_type' => $product->product_type,
            'selling_price' => (string) $product->selling_price,
            'base_unit' => $product->baseUnit !== null
                ? [
                    'id' => (int) $product->baseUnit->getKey(),
                    'name' => $product->baseUnit->name,
                    'code' => $product->baseUnit->code,
                    'symbol' => $product->baseUnit->symbol,
                    'allow_decimal' =>
                        (bool) $product->baseUnit->allow_decimal,
                    'decimal_places' =>
                        (int) $product->baseUnit->decimal_places,
                ]
                : null,

            'branch_settings' => $product->branchSettings
                ->map(
                    static fn (
                        ProductBranchSetting $setting,
                    ): array => [
                        'branch_id' => (int) $setting->branch_id,
                        'selling_price' =>
                            $setting->effectiveSellingPrice($product),
                    ],
                )
                ->values()
                ->all(),

            'warehouse_settings' => $product->warehouseSettings
                ->map(
                    static fn (
                        ProductWarehouseSetting $setting,
                    ): array => [
                        'branch_id' => (int) $setting->branch_id,
                        'warehouse_id' => (int) $setting->warehouse_id,
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(SalesOrder $salesOrder): array
    {
        return [
            'id' => (int) $salesOrder->getKey(),
            'status' => $salesOrder->status,
            'document_number' => $salesOrder->document_number,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function branchData(Branch $branch): array
    {
        return [
            'id' => (int) $branch->getKey(),
            'name' => $branch->name,
            'code' => $branch->code,
            'status' => $branch->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function warehouseData(Warehouse $warehouse): array
    {
        return [
            'id' => (int) $warehouse->getKey(),
            'branch_id' => (int) $warehouse->branch_id,
            'name' => $warehouse->name,
            'code' => $warehouse->code,
            'status' => $warehouse->status,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userData(?User $user): ?array
    {
        if (!$user instanceof User) {
            return null;
        }

        return [
            'id' => (int) $user->getKey(),
            'name' => $user->name,
        ];
    }

    private function customerAddress(
        Customer $customer,
        bool $shipping,
    ): ?string {
        $prefix = $shipping
            ? 'shipping_'
            : 'billing_';

        $parts = array_values(
            array_filter(
                [
                    $customer->{$prefix . 'address_line_1'},
                    $customer->{$prefix . 'address_line_2'},
                    $customer->{$prefix . 'city'},
                    $customer->{$prefix . 'state'},
                    $customer->{$prefix . 'postal_code'},
                    $customer->{$prefix . 'country_code'},
                ],
                static fn (mixed $value): bool =>
                    is_string($value)
                    && trim($value) !== '',
            ),
        );

        return $parts === []
            ? null
            : implode(', ', $parts);
    }
}