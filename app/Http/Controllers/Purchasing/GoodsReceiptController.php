<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\IndexGoodsReceiptRequest;
use App\Http\Requests\Purchasing\ReverseGoodsReceiptRequest;
use App\Http\Requests\Purchasing\StoreGoodsReceiptRequest;
use App\Http\Requests\Purchasing\UpdateGoodsReceiptRequest;
use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organisation\BranchAccessService;
use App\Services\Purchasing\GoodsReceiptService;
use App\Support\Purchasing\GoodsReceiptInspectionStatusRegistry;
use App\Support\Purchasing\GoodsReceiptStatusRegistry;
use App\Support\Responses\CommonResponseService;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\SupplierDebitNote;

final class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptService $goodsReceiptService,
        private readonly GoodsReceiptStatusRegistry $statusRegistry,
        private readonly GoodsReceiptInspectionStatusRegistry $inspectionStatusRegistry,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexGoodsReceiptRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            GoodsReceipt::class,
        );

        $actor = $this->actor($request);
        $validated = $request->validated();

        $search = (string) (
            $validated['search'] ?? ''
        );

        $branchId = isset(
            $validated['branch_id'],
        )
            ? (int) $validated['branch_id']
            : null;

        $warehouseId = isset(
            $validated['warehouse_id'],
        )
            ? (int) $validated['warehouse_id']
            : null;

        $supplierId = isset(
            $validated['supplier_id'],
        )
            ? (int) $validated['supplier_id']
            : null;

        $purchaseOrderId = isset(
            $validated['purchase_order_id'],
        )
            ? (int) $validated[
                'purchase_order_id'
            ]
            : null;

        $status = (string) (
            $validated['status'] ?? ''
        );

        $inspectionStatus = (string) (
            $validated['inspection_status']
                ?? ''
        );

        $receiptDateFrom = (string) (
            $validated['receipt_date_from']
                ?? ''
        );

        $receiptDateTo = (string) (
            $validated['receipt_date_to']
                ?? ''
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
            $validated['per_page'] ?? 15
        );

        $query = GoodsReceipt::query()
            ->with([
                'purchaseOrder:id,document_number,status',
                'branch:id,name,code,status',
                'warehouse:id,branch_id,name,code,status',
                'supplier:id,name,code,status',
                'createdBy:id,name',
                'postedBy:id,name',
                'reversedBy:id,name',
            ]);

        $this->branchAccessService
            ->scopeQuery(
                query: $query,
                user: $actor,
                branchColumn:
                    'goods_receipts.branch_id',
            );

        $goodsReceipts = $query
            ->when(
                $search !== '',
                static function (
                    Builder $receiptQuery,
                ) use ($search): void {
                    $receiptQuery->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'receipt_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'purchase_order_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'supplier_name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'supplier_code',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'supplier_delivery_note',
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
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery
                    ->where(
                        'branch_id',
                        $branchId,
                    ),
            )
            ->when(
                $warehouseId !== null,
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery
                    ->where(
                        'warehouse_id',
                        $warehouseId,
                    ),
            )
            ->when(
                $supplierId !== null,
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery
                    ->where(
                        'supplier_id',
                        $supplierId,
                    ),
            )
            ->when(
                $purchaseOrderId !== null,
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery
                    ->where(
                        'purchase_order_id',
                        $purchaseOrderId,
                    ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery
                    ->where(
                        'status',
                        $status,
                    ),
            )
            ->when(
                $inspectionStatus !== '',
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery
                    ->where(
                        'inspection_status',
                        $inspectionStatus,
                    ),
            )
            ->when(
                $receiptDateFrom !== '',
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery
                    ->whereDate(
                        'receipt_date',
                        '>=',
                        $receiptDateFrom,
                    ),
            )
            ->when(
                $receiptDateTo !== '',
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery
                    ->whereDate(
                        'receipt_date',
                        '<=',
                        $receiptDateTo,
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
            'GoodsReceipts/Index',
            [
                'goodsReceipts' => [
                    'data' => $goodsReceipts
                        ->getCollection()
                        ->map(
                            fn (
                                GoodsReceipt $goodsReceipt,
                            ): array => $this
                                ->summaryData(
                                    goodsReceipt:
                                        $goodsReceipt,
                                    actor: $actor,
                                ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $goodsReceipts
                                ->currentPage(),

                        'last_page' =>
                            $goodsReceipts
                                ->lastPage(),

                        'per_page' =>
                            $goodsReceipts
                                ->perPage(),

                        'from' =>
                            $goodsReceipts
                                ->firstItem(),

                        'to' =>
                            $goodsReceipts
                                ->lastItem(),

                        'total' =>
                            $goodsReceipts
                                ->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'branch_id' => $branchId,
                    'warehouse_id' =>
                        $warehouseId,
                    'supplier_id' =>
                        $supplierId,
                    'purchase_order_id' =>
                        $purchaseOrderId,
                    'status' => $status,
                    'inspection_status' =>
                        $inspectionStatus,
                    'receipt_date_from' =>
                        $receiptDateFrom,
                    'receipt_date_to' =>
                        $receiptDateTo,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                ...$this->indexOptions(
                    $actor,
                ),

                'can' => [
                    'create' => $actor->can(
                        'create',
                        GoodsReceipt::class,
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
            GoodsReceipt::class,
        );

        $actor = $this->actor($request);

        $selectedPurchaseOrderId =
            $this->queryId(
                $request,
                'purchase_order_id',
            );

        return Inertia::render(
            'GoodsReceipts/Create',
            $this->formOptions(
                actor: $actor,
                selectedPurchaseOrderId:
                    $selectedPurchaseOrderId,
            ),
        );
    }

    public function store(
        StoreGoodsReceiptRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            GoodsReceipt::class,
        );

        $actor = $this->actor($request);

        $goodsReceipt =
            $this->goodsReceiptService
                ->create(
                    data:
                        $request->validated(),
                    actor: $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Goods Receipt created successfully.',
                data: [
                    'id' =>
                        (int) $goodsReceipt
                            ->getKey(),
                    'status' =>
                        $goodsReceipt->status,
                    'receipt_number' =>
                        $goodsReceipt
                            ->receipt_number,
                ],
                redirectTo: route(
                    'goods-receipts.show',
                    $goodsReceipt,
                ),
            );
    }

    public function show(
        Request $request,
        GoodsReceipt $goodsReceipt,
    ): Response {
        Gate::authorize(
            'view',
            $goodsReceipt,
        );

        $actor = $this->actor($request);

        $goodsReceipt->load([
            'purchaseOrder:id,document_number,status,order_date,expected_delivery_date,currency_code,total_amount',
            'branch:id,name,code,status,address',
            'warehouse:id,branch_id,name,code,status,address',
            'supplier:id,name,code,status',
            'lines.purchaseOrderLine',
            'lines.product:id,name,sku,status',
            'lines.unit:id,name,code,symbol,status',
            'createdBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'documentNumberAllocation',
            'stockLedgerEntries.product:id,name,sku',
            'stockLedgerEntries.unit:id,name,code',
            'stockLedgerEntries.createdBy:id,name',
        ]);

        return Inertia::render(
            'GoodsReceipts/Show',
            [
                'goodsReceipt' =>
                    $this->detailData(
                        goodsReceipt:
                            $goodsReceipt,
                        actor: $actor,
                    ),
            ],
        );
    }

    public function edit(
        Request $request,
        GoodsReceipt $goodsReceipt,
    ): Response {
        Gate::authorize(
            'update',
            $goodsReceipt,
        );

        $actor = $this->actor($request);

        $goodsReceipt->load([
            'lines',
        ]);

        return Inertia::render(
            'GoodsReceipts/Edit',
            [
                ...$this->formOptions(
                    actor: $actor,
                    selectedPurchaseOrderId:
                        (int) $goodsReceipt
                            ->purchase_order_id,
                ),

                'goodsReceipt' =>
                    $this->formData(
                        $goodsReceipt,
                    ),
            ],
        );
    }

    public function update(
        UpdateGoodsReceiptRequest $request,
        GoodsReceipt $goodsReceipt,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $goodsReceipt,
        );

        $actor = $this->actor($request);

        $goodsReceipt =
            $this->goodsReceiptService
                ->update(
                    goodsReceipt:
                        $goodsReceipt,
                    data:
                        $request->validated(),
                    actor: $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Goods Receipt updated successfully.',
                data: [
                    'id' =>
                        (int) $goodsReceipt
                            ->getKey(),
                    'status' =>
                        $goodsReceipt->status,
                    'receipt_number' =>
                        $goodsReceipt
                            ->receipt_number,
                ],
                redirectTo: route(
                    'goods-receipts.show',
                    $goodsReceipt,
                ),
            );
    }

    public function post(
        Request $request,
        GoodsReceipt $goodsReceipt,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'post',
            $goodsReceipt,
        );

        $actor = $this->actor($request);

        $goodsReceipt =
            $this->goodsReceiptService
                ->post(
                    goodsReceipt:
                        $goodsReceipt,
                    actor: $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Goods Receipt posted successfully.',
                data: [
                    'id' =>
                        (int) $goodsReceipt
                            ->getKey(),
                    'status' =>
                        $goodsReceipt->status,
                    'receipt_number' =>
                        $goodsReceipt
                            ->receipt_number,
                ],
                redirectTo: route(
                    'goods-receipts.show',
                    $goodsReceipt,
                ),
            );
    }

    public function reverse(
        ReverseGoodsReceiptRequest $request,
        GoodsReceipt $goodsReceipt,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'reverse',
            $goodsReceipt,
        );

        $actor = $this->actor($request);

        $goodsReceipt =
            $this->goodsReceiptService
                ->reverse(
                    goodsReceipt:
                        $goodsReceipt,
                    reason: (string) $request
                        ->validated(
                            'reversal_reason',
                        ),
                    actor: $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Goods Receipt reversed successfully.',
                data: [
                    'id' =>
                        (int) $goodsReceipt
                            ->getKey(),
                    'status' =>
                        $goodsReceipt->status,
                    'receipt_number' =>
                        $goodsReceipt
                            ->receipt_number,
                ],
                redirectTo: route(
                    'goods-receipts.show',
                    $goodsReceipt,
                ),
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function indexOptions(
        User $actor,
    ): array {
        $branches =
            $this->branchAccessService
                ->accessibleBranches(
                    user: $actor,
                    activeOnly: false,
                );

        $branchIds = $branches
            ->pluck('id')
            ->map(
                static fn (
                    mixed $id,
                ): int => (int) $id,
            )
            ->all();

        $warehouses = Warehouse::query()
            ->whereIn(
                'branch_id',
                $branchIds,
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'branch_id',
                'name',
                'code',
                'status',
            ]);

        $suppliers = Supplier::query()
            ->whereHas(
                'purchaseOrders.goodsReceipts',
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'code',
                'status',
            ]);

        $purchaseOrderQuery =
            PurchaseOrder::query()
                ->whereHas(
                    'goodsReceipts',
                )
                ->orderByDesc(
                    'order_date',
                )
                ->orderByDesc('id');

        $this->branchAccessService
            ->scopeQuery(
                query: $purchaseOrderQuery,
                user: $actor,
                branchColumn:
                    'purchase_orders.branch_id',
            );

        $purchaseOrders =
            $purchaseOrderQuery->get([
                'id',
                'document_number',
                'supplier_name',
                'status',
            ]);

        return [
            'branchOptions' =>
                $branches
                    ->map(
                        static fn (
                            Branch $branch,
                        ): array => [
                            'value' =>
                                (int) $branch
                                    ->getKey(),
                            'label' =>
                                "{$branch->name} ({$branch->code})",
                            'status' =>
                                $branch->status,
                        ],
                    )
                    ->values()
                    ->all(),

            'warehouseOptions' =>
                $warehouses
                    ->map(
                        static fn (
                            Warehouse $warehouse,
                        ): array => [
                            'value' =>
                                (int) $warehouse
                                    ->getKey(),
                            'branch_id' =>
                                (int) $warehouse
                                    ->branch_id,
                            'label' =>
                                "{$warehouse->name} ({$warehouse->code})",
                            'status' =>
                                $warehouse->status,
                        ],
                    )
                    ->values()
                    ->all(),

            'supplierOptions' =>
                $suppliers
                    ->map(
                        static fn (
                            Supplier $supplier,
                        ): array => [
                            'value' =>
                                (int) $supplier
                                    ->getKey(),
                            'label' =>
                                "{$supplier->name} ({$supplier->code})",
                            'status' =>
                                $supplier->status,
                        ],
                    )
                    ->values()
                    ->all(),

            'purchaseOrderFilterOptions' =>
                $purchaseOrders
                    ->map(
                        static fn (
                            PurchaseOrder $purchaseOrder,
                        ): array => [
                            'value' =>
                                (int) $purchaseOrder
                                    ->getKey(),
                            'label' =>
                                ($purchaseOrder
                                    ->document_number
                                    ?? "PO #{$purchaseOrder->getKey()}")
                                . " — {$purchaseOrder->supplier_name}",
                            'status' =>
                                $purchaseOrder->status,
                        ],
                    )
                    ->values()
                    ->all(),

            'statusOptions' =>
                $this->statusRegistry
                    ->options(),

            'inspectionStatusOptions' =>
                $this
                    ->inspectionStatusRegistry
                    ->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(
        User $actor,
        ?int $selectedPurchaseOrderId = null,
    ): array {
        $tenant =
            $this->tenantContext
                ->tenant();

        $query = PurchaseOrder::query()
            ->with([
                'branch:id,name,code,status',
                'warehouse:id,branch_id,name,code,status',
                'supplier:id,name,code,status',
                'lines.product:id,name,sku,product_type,status',
                'lines.unit:id,name,code,symbol,allow_decimal,decimal_places,status',
            ])
            ->where(
                static function (
                    Builder $purchaseOrderQuery,
                ) use (
                    $selectedPurchaseOrderId,
                ): void {
                    $purchaseOrderQuery
                        ->where(
                            static function (
                                Builder $eligibleQuery,
                            ): void {
                                $eligibleQuery
                                    ->whereIn(
                                        'status',
                                        [
                                            'approved',
                                            'partially_received',
                                        ],
                                    )
                                    ->whereHas(
                                        'lines',
                                        static fn (
                                            Builder $lineQuery,
                                        ): Builder => $lineQuery
                                            ->whereColumn(
                                                'received_quantity',
                                                '<',
                                                'ordered_quantity',
                                            ),
                                    );
                            },
                        );

                    if (
                        $selectedPurchaseOrderId
                        !== null
                    ) {
                        $purchaseOrderQuery
                            ->orWhereKey(
                                $selectedPurchaseOrderId,
                            );
                    }
                },
            )
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        $this->branchAccessService
            ->scopeQuery(
                query: $query,
                user: $actor,
                branchColumn:
                    'purchase_orders.branch_id',
            );

        $purchaseOrders = $query->get();

        return [
            'purchaseOrderOptions' =>
                $purchaseOrders
                    ->map(
                        fn (
                            PurchaseOrder $purchaseOrder,
                        ): array => $this
                            ->purchaseOrderOption(
                                $purchaseOrder,
                            ),
                    )
                    ->values()
                    ->all(),

            'inspectionStatusOptions' =>
                $this
                    ->inspectionStatusRegistry
                    ->options(),

            'defaults' => [
                'receipt_date' =>
                    CarbonImmutable::now(
                        $tenant->timezone,
                    )->format('Y-m-d'),

                'inspection_status' =>
                    'not_required',

                'selected_purchase_order_id' =>
                    $selectedPurchaseOrderId,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseOrderOption(
        PurchaseOrder $purchaseOrder,
    ): array {
        return [
            'value' =>
                (int) $purchaseOrder
                    ->getKey(),

            'label' =>
                ($purchaseOrder
                    ->document_number
                    ?? "PO #{$purchaseOrder->getKey()}")
                . " — {$purchaseOrder->supplier_name}",

            'document_number' =>
                $purchaseOrder
                    ->document_number,

            'status' =>
                $purchaseOrder->status,

            'order_date' =>
                $purchaseOrder
                    ->order_date
                    ?->format('Y-m-d'),

            'expected_delivery_date' =>
                $purchaseOrder
                    ->expected_delivery_date
                    ?->format('Y-m-d'),

            'currency_code' =>
                $purchaseOrder
                    ->currency_code,

            'branch' => [
                'id' =>
                    (int) $purchaseOrder
                        ->branch_id,

                'name' =>
                    $purchaseOrder
                        ->branch
                        ->name,

                'code' =>
                    $purchaseOrder
                        ->branch
                        ->code,
            ],

            'warehouse' =>
                $purchaseOrder->warehouse
                    !== null
                    ? [
                        'id' =>
                            (int) $purchaseOrder
                                ->warehouse_id,

                        'name' =>
                            $purchaseOrder
                                ->warehouse
                                ->name,

                        'code' =>
                            $purchaseOrder
                                ->warehouse
                                ->code,
                    ]
                    : null,

            'supplier' => [
                'id' =>
                    (int) $purchaseOrder
                        ->supplier_id,

                'name' =>
                    $purchaseOrder
                        ->supplier_name,

                'code' =>
                    $purchaseOrder
                        ->supplier_code,
            ],

            'lines' =>
                $purchaseOrder->lines
                    ->filter(
                        static function (
                            PurchaseOrderLine $line,
                        ): bool {
                            return BigDecimal::of(
                                (string) $line
                                    ->received_quantity,
                            )->isLessThan(
                                BigDecimal::of(
                                    (string) $line
                                        ->ordered_quantity,
                                ),
                            );
                        },
                    )
                    ->map(
                        fn (
                            PurchaseOrderLine $line,
                        ): array => $this
                            ->purchaseOrderLineOption(
                                $line,
                            ),
                    )
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseOrderLineOption(
        PurchaseOrderLine $line,
    ): array {
        $ordered = BigDecimal::of(
            (string) $line
                ->ordered_quantity,
        );

        $received = BigDecimal::of(
            (string) $line
                ->received_quantity,
        );

        $outstanding = $ordered
            ->minus($received)
            ->toScale(
                6,
                RoundingMode::HalfUp,
            );

        $netAmount = BigDecimal::of(
            (string) $line
                ->gross_amount,
        )->minus(
            BigDecimal::of(
                (string) $line
                    ->discount_amount,
            ),
        );

        $unitCost = $ordered->isZero()
            ? BigDecimal::zero()
                ->toScale(6)
            : $netAmount->dividedBy(
                $ordered,
                6,
                RoundingMode::HalfUp,
            );

        return [
            'id' =>
                (int) $line->getKey(),

            'line_number' =>
                (int) $line->line_number,

            'product_id' =>
                (int) $line->product_id,

            'product_name' =>
                $line->product_name,

            'product_sku' =>
                $line->product_sku,

            'product_type' =>
                $line->product_type,

            'unit_id' =>
                (int) $line->unit_id,

            'unit_name' =>
                $line->unit_name,

            'unit_code' =>
                $line->unit_code,

            'unit_allows_decimal' =>
                (bool) $line->unit
                    ->allow_decimal,

            'unit_decimal_places' =>
                (int) $line->unit
                    ->decimal_places,

            'ordered_quantity' =>
                $ordered->__toString(),

            'received_quantity' =>
                $received->__toString(),

            'outstanding_quantity' =>
                $outstanding->__toString(),

            'provisional_unit_cost' =>
                $unitCost->__toString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryData(
        GoodsReceipt $goodsReceipt,
        User $actor,
    ): array {
        return [
            'id' =>
                (int) $goodsReceipt
                    ->getKey(),

            'receipt_number' =>
                $goodsReceipt
                    ->receipt_number,

            'receipt_date' =>
                $goodsReceipt
                    ->receipt_date
                    ?->format('Y-m-d'),

            'supplier_delivery_note' =>
                $goodsReceipt
                    ->supplier_delivery_note,

            'purchase_order_id' =>
                (int) $goodsReceipt
                    ->purchase_order_id,

            'purchase_order_number' =>
                $goodsReceipt
                    ->purchase_order_number,

            'supplier_name' =>
                $goodsReceipt
                    ->supplier_name,

            'supplier_code' =>
                $goodsReceipt
                    ->supplier_code,

            'status' =>
                $goodsReceipt->status,

            'status_label' =>
                $this->statusRegistry
                    ->label(
                        $goodsReceipt
                            ->status,
                    ),

            'inspection_status' =>
                $goodsReceipt
                    ->inspection_status,

            'inspection_status_label' =>
                $this
                    ->inspectionStatusRegistry
                    ->label(
                        $goodsReceipt
                            ->inspection_status,
                    ),

            'total_received_quantity' =>
                (string) $goodsReceipt
                    ->total_received_quantity,

            'total_accepted_quantity' =>
                (string) $goodsReceipt
                    ->total_accepted_quantity,

            'total_rejected_quantity' =>
                (string) $goodsReceipt
                    ->total_rejected_quantity,

            'total_inventory_value' =>
                (string) $goodsReceipt
                    ->total_inventory_value,

            'branch' => [
                'id' =>
                    (int) $goodsReceipt
                        ->branch
                        ->getKey(),

                'name' =>
                    $goodsReceipt
                        ->branch
                        ->name,

                'code' =>
                    $goodsReceipt
                        ->branch
                        ->code,
            ],

            'warehouse' =>
                $goodsReceipt->warehouse
                    !== null
                    ? [
                        'id' =>
                            (int) $goodsReceipt
                                ->warehouse
                                ->getKey(),

                        'branch_id' =>
                            (int) $goodsReceipt
                                ->warehouse
                                ->branch_id,

                        'name' =>
                            $goodsReceipt
                                ->warehouse
                                ->name,

                        'code' =>
                            $goodsReceipt
                                ->warehouse
                                ->code,
                    ]
                    : null,

            'created_by' =>
                $this->userData(
                    $goodsReceipt
                        ->createdBy,
                ),

            'posted_by' =>
                $this->userData(
                    $goodsReceipt
                        ->postedBy,
                ),

            'reversed_by' =>
                $this->userData(
                    $goodsReceipt
                        ->reversedBy,
                ),

            'created_at' =>
                $goodsReceipt
                    ->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $goodsReceipt
                    ->updated_at
                    ?->toIso8601String(),

            'posted_at' =>
                $goodsReceipt
                    ->posted_at
                    ?->toIso8601String(),

            'reversed_at' =>
                $goodsReceipt
                    ->reversed_at
                    ?->toIso8601String(),

            'can' =>
                $this->actionPermissions(
                    actor: $actor,
                    goodsReceipt:
                        $goodsReceipt,
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(
        GoodsReceipt $goodsReceipt,
        User $actor,
    ): array {
        return [
            ...$this->summaryData(
                goodsReceipt:
                    $goodsReceipt,
                actor: $actor,
            ),

            'branch_id' =>
                (int) $goodsReceipt
                    ->branch_id,

            'warehouse_id' =>
                $goodsReceipt
                    ->warehouse_id !== null
                    ? (int) $goodsReceipt
                        ->warehouse_id
                    : null,

            'supplier_id' =>
                (int) $goodsReceipt
                    ->supplier_id,

                    'can_view_supplier_debit_notes' =>
    $actor->can(
        'viewAny',
        SupplierDebitNote::class,
    ),

            'can_view_purchase_returns' =>
    $actor->can(
        'purchase_returns.view',
    ),

'can_create_purchase_return' =>
    $this->canCreatePurchaseReturn(
        actor: $actor,
        goodsReceipt: $goodsReceipt,
    ),        

            'can_create_supplier_invoice' =>
                $this->canCreateSupplierInvoice(
                    actor: $actor,
                    goodsReceipt: $goodsReceipt,
                ),        

            'document_number_allocation_id' =>
                $goodsReceipt
                    ->document_number_allocation_id
                    !== null
                    ? (int) $goodsReceipt
                        ->document_number_allocation_id
                    : null,

            'notes' =>
                $goodsReceipt->notes,

            'reversal_reason' =>
                $goodsReceipt
                    ->reversal_reason,

            'purchase_order' => [
                'id' =>
                    (int) $goodsReceipt
                        ->purchaseOrder
                        ->getKey(),

                'document_number' =>
                    $goodsReceipt
                        ->purchaseOrder
                        ->document_number,

                'status' =>
                    $goodsReceipt
                        ->purchaseOrder
                        ->status,

                'order_date' =>
                    $goodsReceipt
                        ->purchaseOrder
                        ->order_date
                        ?->format('Y-m-d'),

                'expected_delivery_date' =>
                    $goodsReceipt
                        ->purchaseOrder
                        ->expected_delivery_date
                        ?->format('Y-m-d'),

                'currency_code' =>
                    $goodsReceipt
                        ->purchaseOrder
                        ->currency_code,

                'total_amount' =>
                    (string) $goodsReceipt
                        ->purchaseOrder
                        ->total_amount,
            ],

            'lines' =>
                $goodsReceipt->lines
                    ->map(
                        fn (
                            GoodsReceiptLine $line,
                        ): array => $this
                            ->lineData($line),
                    )
                    ->values()
                    ->all(),

            'stock_ledger_entries' =>
                $goodsReceipt
                    ->stockLedgerEntries
                    ->map(
                        fn (
                            StockLedgerEntry $entry,
                        ): array => $this
                            ->stockEntryData(
                                $entry,
                            ),
                    )
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(
        GoodsReceipt $goodsReceipt,
    ): array {
        return [
            'id' =>
                (int) $goodsReceipt
                    ->getKey(),

            'purchase_order_id' =>
                (int) $goodsReceipt
                    ->purchase_order_id,

            'receipt_number' =>
                $goodsReceipt
                    ->receipt_number,

            'receipt_date' =>
                $goodsReceipt
                    ->receipt_date
                    ?->format('Y-m-d'),

            'supplier_delivery_note' =>
                $goodsReceipt
                    ->supplier_delivery_note,

            'inspection_status' =>
                $goodsReceipt
                    ->inspection_status,

            'notes' =>
                $goodsReceipt->notes,

            'status' =>
                $goodsReceipt->status,

            'lines' =>
                $goodsReceipt->lines
                    ->map(
                        static fn (
                            GoodsReceiptLine $line,
                        ): array => [
                            'id' =>
                                (int) $line
                                    ->getKey(),

                            'include' => true,

                            'purchase_order_line_id' =>
                                (int) $line
                                    ->purchase_order_line_id,

                            'receipt_quantity' =>
                                (string) $line
                                    ->receipt_quantity,

                            'accepted_quantity' =>
                                (string) $line
                                    ->accepted_quantity,

                            'rejected_quantity' =>
                                (string) $line
                                    ->rejected_quantity,

                            'batch_number' =>
                                $line
                                    ->batch_number
                                ?? '',

                            'manufacturing_date' =>
                                $line
                                    ->manufacturing_date
                                    ?->format('Y-m-d')
                                ?? '',

                            'expiry_date' =>
                                $line
                                    ->expiry_date
                                    ?->format('Y-m-d')
                                ?? '',

                            'serial_numbers' =>
                                $line
                                    ->serial_numbers
                                ?? [],

                            'storage_location' =>
                                $line
                                    ->storage_location
                                ?? '',

                            'variance_reason' =>
                                $line
                                    ->variance_reason
                                ?? '',
                        ],
                    )
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lineData(
        GoodsReceiptLine $line,
    ): array {
        return [
            'id' =>
                (int) $line->getKey(),

            'line_number' =>
                (int) $line->line_number,

            'purchase_order_line_id' =>
                (int) $line
                    ->purchase_order_line_id,

            'product_id' =>
                (int) $line->product_id,

            'unit_id' =>
                (int) $line->unit_id,

            'product_name' =>
                $line->product_name,

            'product_sku' =>
                $line->product_sku,

            'product_type' =>
                $line->product_type,

            'unit_name' =>
                $line->unit_name,

            'unit_code' =>
                $line->unit_code,

            'ordered_quantity_snapshot' =>
                (string) $line
                    ->ordered_quantity_snapshot,

            'previously_received_quantity_snapshot' =>
                (string) $line
                    ->previously_received_quantity_snapshot,

            'receipt_quantity' =>
                (string) $line
                    ->receipt_quantity,

            'accepted_quantity' =>
                (string) $line
                    ->accepted_quantity,

            'rejected_quantity' =>
                (string) $line
                    ->rejected_quantity,

            'unit_cost' =>
                (string) $line
                    ->unit_cost,

            'total_cost' =>
                (string) $line
                    ->total_cost,

            'batch_number' =>
                $line->batch_number,

            'manufacturing_date' =>
                $line
                    ->manufacturing_date
                    ?->format('Y-m-d'),

            'expiry_date' =>
                $line
                    ->expiry_date
                    ?->format('Y-m-d'),

            'serial_numbers' =>
                $line->serial_numbers
                ?? [],

            'storage_location' =>
                $line->storage_location,

            'variance_reason' =>
                $line->variance_reason,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stockEntryData(
        StockLedgerEntry $entry,
    ): array {
        return [
            'id' =>
                (int) $entry->getKey(),

            'movement_type' =>
                $entry->movement_type,

            'document_number' =>
                $entry->document_number,

            'occurred_at' =>
                $entry
                    ->occurred_at
                    ?->toIso8601String(),

            'product_name' =>
                $entry->product->name,

            'product_sku' =>
                $entry->product->sku,

            'unit_code' =>
                $entry->unit->code,

            'quantity_in' =>
                (string) $entry
                    ->quantity_in,

            'quantity_out' =>
                (string) $entry
                    ->quantity_out,

            'unit_cost' =>
                (string) $entry
                    ->unit_cost,

            'total_cost' =>
                (string) $entry
                    ->total_cost,

            'balance_quantity' =>
                (string) $entry
                    ->balance_quantity,

            'balance_value' =>
                (string) $entry
                    ->balance_value,

            'created_by' =>
                $this->userData(
                    $entry->createdBy,
                ),
        ];
    }

    /**
     * @return array{
     *     view: bool,
     *     update: bool,
     *     delete: bool,
     *     post: bool,
     *     reverse: bool
     * }
     */
    private function actionPermissions(
        User $actor,
        GoodsReceipt $goodsReceipt,
    ): array {
        return [
            'view' => $actor->can(
                'view',
                $goodsReceipt,
            ),

            'update' => $actor->can(
                'update',
                $goodsReceipt,
            ),

            'delete' => $actor->can(
                'delete',
                $goodsReceipt,
            ),

            'post' => $actor->can(
                'post',
                $goodsReceipt,
            ),

            'reverse' => $actor->can(
                'reverse',
                $goodsReceipt,
            ),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string
     * }|null
     */
    private function userData(
        ?User $user,
    ): ?array {
        if (!$user instanceof User) {
            return null;
        }

        return [
            'id' =>
                (int) $user->getKey(),

            'name' =>
                $user->name,
        ];
    }

    private function queryId(
        Request $request,
        string $field,
    ): ?int {
        $value = $request->query($field);

        if (
            is_int($value)
            && $value > 0
        ) {
            return $value;
        }

        if (
            is_string($value)
            && preg_match(
                '/^[1-9]\d*$/',
                trim($value),
            ) === 1
        ) {
            return (int) trim($value);
        }

        return null;
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

    public function destroy(
        Request $request,
        GoodsReceipt $goodsReceipt,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $goodsReceipt,
        );

        $actor = $this->actor($request);

        $this->goodsReceiptService
            ->delete(
                goodsReceipt: $goodsReceipt,
                actor: $actor,
            );

        return $this->responseService
            ->success(
                message:
                    'Goods Receipt deleted successfully.',
                redirectTo: route(
                    'goods-receipts.index',
                ),
            );
    }

    private function canCreateSupplierInvoice(
        User $actor,
        GoodsReceipt $goodsReceipt,
    ): bool {
        if (
            !$actor->can(
                'supplier_invoices.create',
            )
        ) {
            return false;
        }

        if (!$goodsReceipt->isPosted()) {
            return false;
        }

        return $goodsReceipt
            ->lines()
            ->whereColumn(
                'goods_receipt_lines.accepted_quantity',
                '>',
                'goods_receipt_lines.invoiced_quantity',
            )
            ->exists();
    }

    private function canCreatePurchaseReturn(
        User $actor,
        GoodsReceipt $goodsReceipt,
    ): bool {
        if (
            !$actor->can(
                'purchase_returns.create',
            )
        ) {
            return false;
        }

        if (!$goodsReceipt->isPosted()) {
            return false;
        }

        return $goodsReceipt
            ->lines()
            ->where(
                'product_type',
                '!=',
                'service',
            )
            ->whereRaw(
                'goods_receipt_lines.accepted_quantity > goods_receipt_lines.returned_quantity + goods_receipt_lines.return_reserved_quantity',
            )
            ->exists();
    }
}
