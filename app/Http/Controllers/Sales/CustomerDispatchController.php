<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\IndexCustomerDispatchRequest;
use App\Http\Requests\Sales\ReverseCustomerDispatchRequest;
use App\Http\Requests\Sales\StoreCustomerDispatchRequest;
use App\Http\Requests\Sales\UpdateCustomerDispatchRequest;
use App\Models\Branch;
use App\Models\CustomerDispatch;
use App\Models\CustomerDispatchLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderAllocation;
use App\Models\SalesOrderAllocationLine;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organisation\BranchAccessService;
use App\Services\Sales\CustomerDispatchService;
use App\Support\Responses\CommonResponseService;
use App\Support\Sales\CustomerDispatchStatusRegistry;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CustomerDispatchController extends Controller
{
    private const SCALE = 6;

    public function __construct(
        private readonly CustomerDispatchService $dispatchService,
        private readonly CustomerDispatchStatusRegistry $statusRegistry,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexCustomerDispatchRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            CustomerDispatch::class,
        );

        $actor = $this->actor($request);
        $validated = $request->validated();

        $search = (string) (
            $validated['search'] ?? ''
        );

        $branchId = isset(
            $validated['branch_id'],
        )
            ? (int) $validated[
                'branch_id'
            ]
            : null;

        $status = (string) (
            $validated['status'] ?? ''
        );

        $dateFrom = (string) (
            $validated[
                'dispatch_date_from'
            ] ?? ''
        );

        $dateTo = (string) (
            $validated[
                'dispatch_date_to'
            ] ?? ''
        );

        $sort = (string) (
            $validated['sort']
                ?? 'created_at'
        );

        $direction = (string) (
            $validated['direction']
                ?? 'desc'
        );

        $perPage = (int) (
            $validated['per_page']
                ?? 15
        );

        $query =
            CustomerDispatch::query()
                ->with([
                    'branch:id,name,code,status',

                    'warehouse:id,branch_id,name,code,status',

                    'createdBy:id,name',

                    'postedBy:id,name',

                    'reversedBy:id,name',
                ]);

        $this->branchAccessService
            ->scopeQuery(
                query: $query,
                user: $actor,
                branchColumn:
                    'customer_dispatches.branch_id',
            );

        $dispatches = $query
            ->when(
                $search !== '',
                static function (
                    Builder $dispatchQuery,
                ) use (
                    $search,
                ): void {
                    $dispatchQuery->where(
                        static function (
                            Builder $searchQuery,
                        ) use (
                            $search,
                        ): void {
                            $searchQuery
                                ->where(
                                    'dispatch_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'sales_order_number',
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
                                    'tracking_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'vehicle_number',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            )
            ->when(
                $branchId !== null,
                static fn (
                    Builder $dispatchQuery,
                ): Builder =>
                    $dispatchQuery->where(
                        'branch_id',
                        $branchId,
                    ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $dispatchQuery,
                ): Builder =>
                    $dispatchQuery->where(
                        'status',
                        $status,
                    ),
            )
            ->when(
                $dateFrom !== '',
                static fn (
                    Builder $dispatchQuery,
                ): Builder =>
                    $dispatchQuery->whereDate(
                        'dispatch_date',
                        '>=',
                        $dateFrom,
                    ),
            )
            ->when(
                $dateTo !== '',
                static fn (
                    Builder $dispatchQuery,
                ): Builder =>
                    $dispatchQuery->whereDate(
                        'dispatch_date',
                        '<=',
                        $dateTo,
                    ),
            )
            ->orderBy(
                $sort,
                $direction,
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Dispatches/Index',
            [
                'dispatches' => [
                    'data' =>
                        $dispatches
                            ->getCollection()
                            ->map(
                                fn (
                                    CustomerDispatch $dispatch,
                                ): array =>
                                    $this
                                        ->summaryData(
                                            dispatch:
                                                $dispatch,

                                            actor:
                                                $actor,
                                        ),
                            )
                            ->values()
                            ->all(),

                    'meta' => [
                        'current_page' =>
                            $dispatches
                                ->currentPage(),

                        'last_page' =>
                            $dispatches
                                ->lastPage(),

                        'per_page' =>
                            $dispatches
                                ->perPage(),

                        'from' =>
                            $dispatches
                                ->firstItem(),

                        'to' =>
                            $dispatches
                                ->lastItem(),

                        'total' =>
                            $dispatches
                                ->total(),
                    ],
                ],

                'filters' => [
                    'search' =>
                        $search,

                    'branch_id' =>
                        $branchId,

                    'status' =>
                        $status,

                    'dispatch_date_from' =>
                        $dateFrom,

                    'dispatch_date_to' =>
                        $dateTo,

                    'sort' =>
                        $sort,

                    'direction' =>
                        $direction,

                    'per_page' =>
                        $perPage,
                ],

                'branches' =>
                    $this->branches(
                        $actor,
                    ),

                'statuses' =>
                    $this->statusRegistry
                        ->options(),

                'can' => [
                    'create' =>
                        $actor->can(
                            'create',
                            CustomerDispatch::class,
                        ),
                ],
            ],
        );
    }

    public function create(
        Request $request,
    ): Response {
        Gate::authorize(
            'create',
            CustomerDispatch::class,
        );

        $actor = $this->actor($request);

        $salesOrderId =
            $request->integer(
                'sales_order_id',
            );

        $selectedOrder =
            $salesOrderId > 0
                ? $this
                    ->dispatchableOrder(
                        salesOrderId:
                            $salesOrderId,

                        actor:
                            $actor,
                    )
                : null;

        return Inertia::render(
            'Dispatches/Create',
            [
                'salesOrders' =>
                    $this
                        ->dispatchableOrders(
                            $actor,
                        ),

                'selectedSalesOrder' =>
                    $selectedOrder,

                'defaults' => [
                    'dispatch_date' =>
                        CarbonImmutable::now(
                            $this
                                ->tenantContext
                                ->tenant()
                                ->timezone,
                        )->format('Y-m-d'),
                ],
            ],
        );
    }

    public function store(
        StoreCustomerDispatchRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            CustomerDispatch::class,
        );

        $actor = $this->actor($request);

        $dispatch =
            $this->dispatchService
                ->create(
                    data:
                        $request->validated(),

                    actor:
                        $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Dispatch draft created successfully.',

                data:
                    $this->responseData(
                        $dispatch,
                    ),

                redirectTo:
                    route(
                        'dispatches.show',
                        $dispatch,
                    ),
            );
    }

    public function show(
        Request $request,
        CustomerDispatch $customerDispatch,
    ): Response {
        Gate::authorize(
            'view',
            $customerDispatch,
        );

        $actor = $this->actor($request);

        $customerDispatch->load([
            'salesOrder:id,document_number,status',

            'salesOrderAllocation:id,revision,status',

            'branch:id,name,code,status',

            'warehouse:id,branch_id,name,code,status',

            'customer:id,name,code,status',

            'lines.stockLedgerEntry',

            'lines.reversalStockLedgerEntry',

            'createdBy:id,name',

            'postedBy:id,name',

            'reversedBy:id,name',
        ]);

        return Inertia::render(
            'Dispatches/Show',
            [
                'dispatch' =>
                    $this->detailData(
                        dispatch:
                            $customerDispatch,

                        actor:
                            $actor,
                    ),
            ],
        );
    }

    public function edit(
        Request $request,
        CustomerDispatch $customerDispatch,
    ): Response {
        Gate::authorize(
            'update',
            $customerDispatch,
        );

        $actor = $this->actor($request);

        $customerDispatch->load([
            'salesOrder.lines',
            'lines',
        ]);

        return Inertia::render(
            'Dispatches/Edit',
            [
                'dispatch' =>
                    $this
                        ->formDispatchData(
                            $customerDispatch,
                        ),

                'selectedSalesOrder' =>
                    $this
                        ->dispatchableOrder(
                            salesOrderId:
                                (int) $customerDispatch
                                    ->sales_order_id,

                            actor:
                                $actor,

                            editingDispatchId:
                                (int) $customerDispatch
                                    ->getKey(),
                        ),
            ],
        );
    }

    public function update(
        UpdateCustomerDispatchRequest $request,
        CustomerDispatch $customerDispatch,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $customerDispatch,
        );

        $actor = $this->actor($request);

        $dispatch =
            $this->dispatchService
                ->update(
                    dispatch:
                        $customerDispatch,

                    data:
                        $request->validated(),

                    actor:
                        $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Dispatch draft updated successfully.',

                data:
                    $this->responseData(
                        $dispatch,
                    ),

                redirectTo:
                    route(
                        'dispatches.show',
                        $dispatch,
                    ),
            );
    }

    public function destroy(
        Request $request,
        CustomerDispatch $customerDispatch,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $customerDispatch,
        );

        $actor = $this->actor($request);

        $this->dispatchService->delete(
            dispatch:
                $customerDispatch,

            actor:
                $actor,
        );

        return $this->responseService
            ->success(
                message:
                    'Dispatch draft deleted successfully.',

                redirectTo:
                    route(
                        'dispatches.index',
                    ),
            );
    }

    public function post(
        Request $request,
        CustomerDispatch $customerDispatch,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'post',
            $customerDispatch,
        );

        $actor = $this->actor($request);

        $dispatch =
            $this->dispatchService
                ->post(
                    dispatch:
                        $customerDispatch,

                    actor:
                        $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Dispatch posted and inventory issued successfully.',

                data:
                    $this->responseData(
                        $dispatch,
                    ),

                redirectTo:
                    route(
                        'dispatches.show',
                        $dispatch,
                    ),
            );
    }

    public function reverse(
        ReverseCustomerDispatchRequest $request,
        CustomerDispatch $customerDispatch,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'reverse',
            $customerDispatch,
        );

        $actor = $this->actor($request);

        $dispatch =
            $this->dispatchService
                ->reverse(
                    dispatch:
                        $customerDispatch,

                    reason:
                        (string) $request
                            ->validated(
                                'reversal_reason',
                            ),

                    actor:
                        $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Dispatch reversed and inventory reservation restored.',

                data:
                    $this->responseData(
                        $dispatch,
                    ),

                redirectTo:
                    route(
                        'dispatches.show',
                        $dispatch,
                    ),
            );
    }

    public function print(
        Request $request,
        CustomerDispatch $customerDispatch,
    ): Response {
        Gate::authorize(
            'print',
            $customerDispatch,
        );

        $actor = $this->actor($request);

        $customerDispatch->load([
            'branch:id,name,code,address,phone,email',

            'warehouse:id,name,code,address',

            'lines',

            'createdBy:id,name',

            'postedBy:id,name',

            'reversedBy:id,name',
        ]);

        $tenant =
            $this->tenantContext
                ->tenant();

        return Inertia::render(
            'Dispatches/Print/DispatchNote',
            [
                'dispatch' =>
                    $this->detailData(
                        dispatch:
                            $customerDispatch,

                        actor:
                            $actor,
                    ),

                'company' => [
                    'name' =>
                        $tenant->name,

                    'code' =>
                        $tenant->code,

                    'email' =>
                        $tenant->email,

                    'phone' =>
                        $tenant->phone,

                    'address' =>
                        $tenant->address,
                ],
            ],
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dispatchableOrders(
        User $actor,
    ): array {
        $query = SalesOrder::query()
            ->whereIn(
                'status',
                [
                    'allocated',
                    'partially_allocated',
                    'partially_dispatched',
                ],
            )
            ->whereHas(
                'activeAllocation',
            )
            ->whereHas(
                'lines',
                static fn (
                    Builder $lineQuery,
                ): Builder =>
                    $lineQuery
                        ->whereColumn(
                            'allocated_quantity',
                            '>',
                            'dispatched_quantity',
                        ),
            )
            ->whereNotExists(
                static function (
                    $draftQuery,
                ): void {
                    $draftQuery
                        ->selectRaw('1')
                        ->from(
                            'customer_dispatches',
                        )
                        ->whereColumn(
                            'customer_dispatches.sales_order_id',
                            'sales_orders.id',
                        )
                        ->where(
                            'customer_dispatches.status',
                            'draft',
                        )
                        ->whereNull(
                            'customer_dispatches.deleted_at',
                        );
                },
            )
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        $this->branchAccessService
            ->scopeQuery(
                query:
                    $query,

                user:
                    $actor,

                branchColumn:
                    'sales_orders.branch_id',
            );

        return $query
            ->limit(500)
            ->get([
                'id',
                'document_number',
                'order_date',
                'customer_name',
                'customer_code',
                'branch_id',
                'warehouse_id',
                'status',
            ])
            ->map(
                static fn (
                    SalesOrder $order,
                ): array => [
                    'id' =>
                        (int) $order
                            ->getKey(),

                    'document_number' =>
                        $order
                            ->document_number,

                    'order_date' =>
                        $order
                            ->order_date
                            ?->format(
                                'Y-m-d',
                            ),

                    'customer_name' =>
                        $order
                            ->customer_name,

                    'customer_code' =>
                        $order
                            ->customer_code,

                    'status' =>
                        $order->status,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatchableOrder(
        int $salesOrderId,
        User $actor,
        ?int $editingDispatchId = null,
    ): array {
        $query = SalesOrder::query()
            ->whereKey($salesOrderId)
            ->whereIn(
                'status',
                [
                    'allocated',
                    'partially_allocated',
                    'partially_dispatched',
                ],
            )
            ->with([
                'branch:id,name,code,status',

                'warehouse:id,name,code,status',

                'activeAllocation.lines.reservation',

                'lines' =>
                    static fn (
                        $lineQuery,
                    ) => $lineQuery
                        ->orderBy(
                            'line_number',
                        ),
            ]);

        $this->branchAccessService
            ->scopeQuery(
                query:
                    $query,

                user:
                    $actor,

                branchColumn:
                    'sales_orders.branch_id',
            );

        $order = $query
            ->firstOrFail();

        if (
            !$order->activeAllocation
            instanceof SalesOrderAllocation
        ) {
            abort(
                422,
                'The Sales Order does not have an active allocation.',
            );
        }

        $conflictingDraft =
            CustomerDispatch::query()
                ->where(
                    'sales_order_id',
                    $order->getKey(),
                )
                ->where(
                    'status',
                    'draft',
                )
                ->when(
                    $editingDispatchId
                        !== null,

                    static fn (
                        Builder $draftQuery,
                    ): Builder =>
                        $draftQuery->where(
                            'id',
                            '!=',
                            $editingDispatchId,
                        ),
                )
                ->exists();

        abort_if(
            $conflictingDraft,
            422,
            'This Sales Order already has an editable dispatch draft.',
        );

        $draftQuantities =
            CustomerDispatchLine::query()
                ->whereHas(
                    'dispatch',
                    static function (
                        Builder $dispatchQuery,
                    ) use (
                        $order,
                        $editingDispatchId,
                    ): void {
                        $dispatchQuery
                            ->where(
                                'sales_order_id',
                                $order->getKey(),
                            )
                            ->where(
                                'status',
                                'draft',
                            )
                            ->when(
                                $editingDispatchId
                                    !== null,

                                static fn (
                                    Builder $draftQuery,
                                ): Builder =>
                                    $draftQuery
                                        ->where(
                                            'id',
                                            '!=',
                                            $editingDispatchId,
                                        ),
                            );
                    },
                )
                ->selectRaw(
                    'sales_order_line_id, SUM(dispatched_quantity) AS quantity',
                )
                ->groupBy(
                    'sales_order_line_id',
                )
                ->pluck(
                    'quantity',
                    'sales_order_line_id',
                );

        $allocationLines =
            $order
                ->activeAllocation
                ->lines
                ->keyBy(
                    'sales_order_line_id',
                );

        return [
            'id' =>
                (int) $order->getKey(),

            'document_number' =>
                $order->document_number,

            'order_date' =>
                $order->order_date
                    ?->format('Y-m-d'),

            'requested_delivery_date' =>
                $order
                    ->requested_delivery_date
                    ?->format('Y-m-d'),

            'status' =>
                $order->status,

            'customer_name' =>
                $order->customer_name,

            'customer_code' =>
                $order->customer_code,

            'customer_contact_person' =>
                $order
                    ->customer_contact_person,

            'customer_phone' =>
                $order->customer_phone,

            'shipping_address' =>
                $order->shipping_address,

            'delivery_instructions' =>
                $order
                    ->delivery_instructions,

            'branch' =>
                $order->branch
                instanceof Branch
                    ? [
                        'id' =>
                            (int) $order
                                ->branch
                                ->getKey(),

                        'name' =>
                            $order
                                ->branch
                                ->name,

                        'code' =>
                            $order
                                ->branch
                                ->code,
                    ]
                    : null,

            'warehouse' =>
                $order->warehouse
                instanceof Warehouse
                    ? [
                        'id' =>
                            (int) $order
                                ->warehouse
                                ->getKey(),

                        'name' =>
                            $order
                                ->warehouse
                                ->name,

                        'code' =>
                            $order
                                ->warehouse
                                ->code,
                    ]
                    : null,

            'allocation_revision' =>
                (int) $order
                    ->activeAllocation
                    ->revision,

            'lines' =>
                $order->lines
                    ->map(
                        function (
                            SalesOrderLine $line,
                        ) use (
                            $allocationLines,
                            $draftQuantities,
                        ): array {
                            $allocationLine =
                                $allocationLines
                                    ->get(
                                        (int) $line
                                            ->getKey(),
                                    );

                            if (
                                !$allocationLine
                                instanceof SalesOrderAllocationLine
                            ) {
                                return [];
                            }

                            $remaining =
                                BigDecimal::of(
                                    (string) $line
                                        ->allocated_quantity,
                                )
                                    ->minus(
                                        BigDecimal::of(
                                            (string) $line
                                                ->dispatched_quantity,
                                        ),
                                    )
                                    ->minus(
                                        BigDecimal::of(
                                            (string) (
                                                $draftQuantities[
                                                    $line->getKey()
                                                ] ?? '0'
                                            ),
                                        ),
                                    );

                            return [
                                'id' =>
                                    (int) $line
                                        ->getKey(),

                                'line_number' =>
                                    (int) $line
                                        ->line_number,

                                'product_name' =>
                                    $line
                                        ->product_name,

                                'product_sku' =>
                                    $line
                                        ->product_sku,

                                'product_type' =>
                                    $line
                                        ->product_type,

                                'unit_name' =>
                                    $line
                                        ->unit_name,

                                'unit_code' =>
                                    $line
                                        ->unit_code,

                                'description' =>
                                    $line
                                        ->description,

                                'ordered_quantity' =>
                                    (string) $line
                                        ->ordered_quantity,

                                'allocated_quantity' =>
                                    (string) $line
                                        ->allocated_quantity,

                                'already_dispatched_quantity' =>
                                    (string) $line
                                        ->dispatched_quantity,

                                'remaining_dispatchable_quantity' =>
                                    $remaining
                                        ->toScale(
                                            self::SCALE,
                                            RoundingMode::HalfUp,
                                        )
                                        ->__toString(),

                                'reservation_outstanding_quantity' =>
                                    $allocationLine
                                        ->reservation
                                    !== null
                                        ? $allocationLine
                                            ->reservation
                                            ->outstandingQuantity()
                                        : null,
                            ];
                        },
                    )
                    ->filter(
                        static fn (
                            array $line,
                        ): bool =>
                            $line !== [],
                    )
                    ->filter(
                        static fn (
                            array $line,
                        ): bool =>
                            BigDecimal::of(
                                (string) $line[
                                    'remaining_dispatchable_quantity'
                                ],
                            )->isGreaterThan(
                                BigDecimal::zero(),
                            ),
                    )
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryData(
        CustomerDispatch $dispatch,
        User $actor,
    ): array {
        return [
            'id' =>
                (int) $dispatch->getKey(),

            'dispatch_number' =>
                $dispatch
                    ->dispatch_number,

            'dispatch_date' =>
                $dispatch
                    ->dispatch_date
                    ?->format('Y-m-d'),

            'sales_order_number' =>
                $dispatch
                    ->sales_order_number,

            'customer_name' =>
                $dispatch->customer_name,

            'customer_code' =>
                $dispatch->customer_code,

            'status' =>
                $dispatch->status,

            'status_label' =>
                $this->statusRegistry
                    ->label(
                        $dispatch->status,
                    ),

            'tracking_number' =>
                $dispatch
                    ->tracking_number,

            'branch' =>
                $dispatch->branch
                instanceof Branch
                    ? [
                        'id' =>
                            (int) $dispatch
                                ->branch
                                ->getKey(),

                        'name' =>
                            $dispatch
                                ->branch
                                ->name,

                        'code' =>
                            $dispatch
                                ->branch
                                ->code,
                    ]
                    : null,

            'warehouse' =>
                $dispatch->warehouse
                instanceof Warehouse
                    ? [
                        'id' =>
                            (int) $dispatch
                                ->warehouse
                                ->getKey(),

                        'name' =>
                            $dispatch
                                ->warehouse
                                ->name,

                        'code' =>
                            $dispatch
                                ->warehouse
                                ->code,
                    ]
                    : null,

            'created_at' =>
                $dispatch
                    ->created_at
                    ?->toIso8601String(),

            'can' =>
                $this->permissions(
                    dispatch:
                        $dispatch,

                    actor:
                        $actor,
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(
        CustomerDispatch $dispatch,
        User $actor,
    ): array {
        return [
            ...$this->summaryData(
                dispatch:
                    $dispatch,

                actor:
                    $actor,
            ),

            'sales_order_id' =>
                (int) $dispatch
                    ->sales_order_id,

            'sales_order_allocation_id' =>
                (int) $dispatch
                    ->sales_order_allocation_id,

            'allocation_revision' =>
                (int) (
                    $dispatch
                        ->salesOrderAllocation
                        ?->revision
                    ?? 0
                ),

            'branch_id' =>
                (int) $dispatch
                    ->branch_id,

            'warehouse_id' =>
                $dispatch->warehouse_id
                !== null
                    ? (int) $dispatch
                        ->warehouse_id
                    : null,

            'customer_id' =>
                (int) $dispatch
                    ->customer_id,

            'customer_contact_person' =>
                $dispatch
                    ->customer_contact_person,

            'customer_phone' =>
                $dispatch->customer_phone,

            'shipping_address' =>
                $dispatch
                    ->shipping_address,

            'delivery_instructions' =>
                $dispatch
                    ->delivery_instructions,

            'carrier_name' =>
                $dispatch->carrier_name,

            'vehicle_number' =>
                $dispatch->vehicle_number,

            'notes' =>
                $dispatch->notes,

            'posted_at' =>
                $dispatch->posted_at
                    ?->toIso8601String(),

            'reversed_at' =>
                $dispatch->reversed_at
                    ?->toIso8601String(),

            'reversal_reason' =>
                $dispatch
                    ->reversal_reason,

            'created_by' =>
                $this->userData(
                    $dispatch->createdBy,
                ),

            'posted_by' =>
                $this->userData(
                    $dispatch->postedBy,
                ),

            'reversed_by' =>
                $this->userData(
                    $dispatch->reversedBy,
                ),

            'lines' =>
                $dispatch->lines
                    ->map(
                        static fn (
                            CustomerDispatchLine $line,
                        ): array => [
                            'id' =>
                                (int) $line
                                    ->getKey(),

                            'line_number' =>
                                (int) $line
                                    ->line_number,

                            'sales_order_line_id' =>
                                (int) $line
                                    ->sales_order_line_id,

                            'product_name' =>
                                $line
                                    ->product_name,

                            'product_sku' =>
                                $line
                                    ->product_sku,

                            'product_type' =>
                                $line
                                    ->product_type,

                            'unit_name' =>
                                $line
                                    ->unit_name,

                            'unit_code' =>
                                $line
                                    ->unit_code,

                            'description' =>
                                $line
                                    ->description,

                            'dispatched_quantity' =>
                                (string) $line
                                    ->dispatched_quantity,

                            'unit_cost' =>
                                (string) $line
                                    ->unit_cost,

                            'total_cost' =>
                                (string) $line
                                    ->total_cost,

                            'stock_ledger_entry_id' =>
                                $line
                                    ->stock_ledger_entry_id
                                !== null
                                    ? (int) $line
                                        ->stock_ledger_entry_id
                                    : null,

                            'reversal_stock_ledger_entry_id' =>
                                $line
                                    ->reversal_stock_ledger_entry_id
                                !== null
                                    ? (int) $line
                                        ->reversal_stock_ledger_entry_id
                                    : null,
                        ],
                    )
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formDispatchData(
        CustomerDispatch $dispatch,
    ): array {
        return [
            'id' =>
                (int) $dispatch->getKey(),

            'sales_order_id' =>
                (int) $dispatch
                    ->sales_order_id,

            'dispatch_date' =>
                $dispatch
                    ->dispatch_date
                    ?->format('Y-m-d'),

            'shipping_address' =>
                $dispatch
                    ->shipping_address,

            'delivery_instructions' =>
                $dispatch
                    ->delivery_instructions,

            'carrier_name' =>
                $dispatch->carrier_name,

            'vehicle_number' =>
                $dispatch->vehicle_number,

            'tracking_number' =>
                $dispatch
                    ->tracking_number,

            'notes' =>
                $dispatch->notes,

            'status' =>
                $dispatch->status,

            'lines' =>
                $dispatch->lines
                    ->map(
                        static fn (
                            CustomerDispatchLine $line,
                        ): array => [
                            'id' =>
                                (int) $line
                                    ->getKey(),

                            'sales_order_line_id' =>
                                (int) $line
                                    ->sales_order_line_id,

                            'dispatched_quantity' =>
                                (string) $line
                                    ->dispatched_quantity,

                            'description' =>
                                $line
                                    ->description,
                        ],
                    )
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function permissions(
        CustomerDispatch $dispatch,
        User $actor,
    ): array {
        return [
            'view' =>
                $actor->can(
                    'view',
                    $dispatch,
                ),

            'update' =>
                $actor->can(
                    'update',
                    $dispatch,
                ),

            'delete' =>
                $actor->can(
                    'delete',
                    $dispatch,
                ),

            'post' =>
                $actor->can(
                    'post',
                    $dispatch,
                ),

            'reverse' =>
                $actor->can(
                    'reverse',
                    $dispatch,
                ),

            'print' =>
                $actor->can(
                    'print',
                    $dispatch,
                ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function branches(
        User $actor,
    ): array {
        return $this->branchAccessService
            ->accessibleBranches(
                user:
                    $actor,

                activeOnly:
                    false,
            )
            ->map(
                static fn (
                    Branch $branch,
                ): array => [
                    'id' =>
                        (int) $branch
                            ->getKey(),

                    'name' =>
                        $branch->name,

                    'code' =>
                        $branch->code,

                    'status' =>
                        $branch->status,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function userData(
        ?User $user,
    ): ?array {
        if (
            !$user instanceof User
        ) {
            return null;
        }

        return [
            'id' =>
                (int) $user->getKey(),

            'name' =>
                $user->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(
        CustomerDispatch $dispatch,
    ): array {
        return [
            'id' =>
                (int) $dispatch->getKey(),

            'dispatch_number' =>
                $dispatch
                    ->dispatch_number,

            'status' =>
                $dispatch->status,
        ];
    }

    private function actor(
        Request $request,
    ): User {
        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        return $actor;
    }
}