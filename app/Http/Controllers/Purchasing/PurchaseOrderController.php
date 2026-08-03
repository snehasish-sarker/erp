<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\CancelPurchaseOrderRequest;
use App\Http\Requests\Purchasing\IndexPurchaseOrderRequest;
use App\Http\Requests\Purchasing\StorePurchaseOrderRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseOrderRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBranchSetting;
use App\Models\ProductWarehouseSetting;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organisation\BranchAccessService;
use App\Services\Purchasing\PurchaseOrderService;
use App\Support\Purchasing\PurchaseOrderStatusRegistry;
use App\Support\Responses\CommonResponseService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\SupplierDebitNote;

final class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService,
        private readonly PurchaseOrderStatusRegistry $statusRegistry,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexPurchaseOrderRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            PurchaseOrder::class,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

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

        $status = (string) (
            $validated['status'] ?? ''
        );

        $orderDateFrom = (string) (
            $validated['order_date_from']
                ?? ''
        );

        $orderDateTo = (string) (
            $validated['order_date_to']
                ?? ''
        );

        $expectedDeliveryFrom = (string) (
            $validated['expected_delivery_from']
                ?? ''
        );

        $expectedDeliveryTo = (string) (
            $validated['expected_delivery_to']
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

        $query = PurchaseOrder::query()
            ->with([
                'branch:id,name,code,status',

                'warehouse:id,branch_id,name,code,status',

                'supplier:id,name,code,status',

                'createdBy:id,name',
            ]);

        $this->branchAccessService
            ->scopeQuery(
                query: $query,
                user: $actor,
                branchColumn:
                    'purchase_orders.branch_id',
            );

        $purchaseOrders = $query
            ->when(
                $search !== '',
                static function (
                    Builder $purchaseOrderQuery,
                ) use ($search): void {
                    $purchaseOrderQuery->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'document_number',
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
                                    'supplier_reference',
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
                    Builder $purchaseOrderQuery,
                ): Builder => $purchaseOrderQuery
                    ->where(
                        'branch_id',
                        $branchId,
                    ),
            )
            ->when(
                $warehouseId !== null,
                static fn (
                    Builder $purchaseOrderQuery,
                ): Builder => $purchaseOrderQuery
                    ->where(
                        'warehouse_id',
                        $warehouseId,
                    ),
            )
            ->when(
                $supplierId !== null,
                static fn (
                    Builder $purchaseOrderQuery,
                ): Builder => $purchaseOrderQuery
                    ->where(
                        'supplier_id',
                        $supplierId,
                    ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $purchaseOrderQuery,
                ): Builder => $purchaseOrderQuery
                    ->where(
                        'status',
                        $status,
                    ),
            )
            ->when(
                $orderDateFrom !== '',
                static fn (
                    Builder $purchaseOrderQuery,
                ): Builder => $purchaseOrderQuery
                    ->whereDate(
                        'order_date',
                        '>=',
                        $orderDateFrom,
                    ),
            )
            ->when(
                $orderDateTo !== '',
                static fn (
                    Builder $purchaseOrderQuery,
                ): Builder => $purchaseOrderQuery
                    ->whereDate(
                        'order_date',
                        '<=',
                        $orderDateTo,
                    ),
            )
            ->when(
                $expectedDeliveryFrom !== '',
                static fn (
                    Builder $purchaseOrderQuery,
                ): Builder => $purchaseOrderQuery
                    ->whereDate(
                        'expected_delivery_date',
                        '>=',
                        $expectedDeliveryFrom,
                    ),
            )
            ->when(
                $expectedDeliveryTo !== '',
                static fn (
                    Builder $purchaseOrderQuery,
                ): Builder => $purchaseOrderQuery
                    ->whereDate(
                        'expected_delivery_date',
                        '<=',
                        $expectedDeliveryTo,
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
            'PurchaseOrders/Index',
            [
                'purchaseOrders' => [
                    'data' => $purchaseOrders
                        ->getCollection()
                        ->map(
                            fn (
                                PurchaseOrder $purchaseOrder,
                            ): array => $this
                                ->summaryData(
                                    purchaseOrder:
                                        $purchaseOrder,

                                    actor: $actor,
                                ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $purchaseOrders
                                ->currentPage(),

                        'last_page' =>
                            $purchaseOrders
                                ->lastPage(),

                        'per_page' =>
                            $purchaseOrders
                                ->perPage(),

                        'from' =>
                            $purchaseOrders
                                ->firstItem(),

                        'to' =>
                            $purchaseOrders
                                ->lastItem(),

                        'total' =>
                            $purchaseOrders
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

                    'status' => $status,

                    'order_date_from' =>
                        $orderDateFrom,

                    'order_date_to' =>
                        $orderDateTo,

                    'expected_delivery_from' =>
                        $expectedDeliveryFrom,

                    'expected_delivery_to' =>
                        $expectedDeliveryTo,

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
                        PurchaseOrder::class,
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
            PurchaseOrder::class,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        return Inertia::render(
            'PurchaseOrders/Create',
            $this->formOptions($actor),
        );
    }

    public function store(
        StorePurchaseOrderRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            PurchaseOrder::class,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $purchaseOrder =
            $this->purchaseOrderService
                ->create(
                    data:
                        $request->validated(),

                    actor: $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Purchase order created successfully.',

                data: [
                    'id' => (int) $purchaseOrder
                        ->getKey(),

                    'status' =>
                        $purchaseOrder->status,

                    'document_number' =>
                        $purchaseOrder
                            ->document_number,
                ],

                redirectTo: route(
                    'purchase-orders.show',
                    $purchaseOrder,
                ),
            );
    }

    public function show(
        Request $request,
        PurchaseOrder $purchaseOrder,
    ): Response {
        Gate::authorize(
            'view',
            $purchaseOrder,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $purchaseOrder->load([
            'branch:id,name,code,status,address',

            'warehouse:id,branch_id,name,code,status,address',

            'supplier:id,name,code,status',

            'lines.product:id,name,sku,status',

            'lines.unit:id,name,code,symbol,status',

            'createdBy:id,name',
            'submittedBy:id,name',
            'approvedBy:id,name',
            'cancelledBy:id,name',

            'documentNumberAllocation',
        ]);

        return Inertia::render(
            'PurchaseOrders/Show',
            [
                'purchaseOrder' =>
                    $this->detailData(
                        purchaseOrder:
                            $purchaseOrder,

                        actor: $actor,
                    ),
            ],
        );
    }

    public function edit(
        Request $request,
        PurchaseOrder $purchaseOrder,
    ): Response {
        Gate::authorize(
            'update',
            $purchaseOrder,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $purchaseOrder->load([
            'lines.product.baseUnit',
            'lines.unit',
        ]);

        return Inertia::render(
            'PurchaseOrders/Edit',
            [
                ...$this->formOptions(
                    actor: $actor,
                    purchaseOrder:
                        $purchaseOrder,
                ),

                'purchaseOrder' =>
                    $this->formData(
                        $purchaseOrder,
                    ),
            ],
        );
    }

    public function update(
        UpdatePurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $purchaseOrder,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $purchaseOrder =
            $this->purchaseOrderService
                ->update(
                    purchaseOrder:
                        $purchaseOrder,

                    data:
                        $request->validated(),

                    actor: $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Purchase order updated successfully.',

                data: [
                    'id' => (int) $purchaseOrder
                        ->getKey(),

                    'status' =>
                        $purchaseOrder->status,

                    'document_number' =>
                        $purchaseOrder
                            ->document_number,
                ],

                redirectTo: route(
                    'purchase-orders.show',
                    $purchaseOrder,
                ),
            );
    }

    public function destroy(
        Request $request,
        PurchaseOrder $purchaseOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $purchaseOrder,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $this->purchaseOrderService
            ->delete(
                purchaseOrder:
                    $purchaseOrder,

                actor: $actor,
            );

        return $this->responseService
            ->success(
                message:
                    'Purchase order deleted successfully.',

                redirectTo: route(
                    'purchase-orders.index',
                ),
            );
    }

    public function submit(
        Request $request,
        PurchaseOrder $purchaseOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'submit',
            $purchaseOrder,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $purchaseOrder =
            $this->purchaseOrderService
                ->submit(
                    purchaseOrder:
                        $purchaseOrder,

                    actor: $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Purchase order submitted successfully.',

                data: [
                    'id' => (int) $purchaseOrder
                        ->getKey(),

                    'status' =>
                        $purchaseOrder->status,

                    'document_number' =>
                        $purchaseOrder
                            ->document_number,
                ],

                redirectTo: route(
                    'purchase-orders.show',
                    $purchaseOrder,
                ),
            );
    }

    public function returnToDraft(
        Request $request,
        PurchaseOrder $purchaseOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'returnToDraft',
            $purchaseOrder,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $purchaseOrder =
            $this->purchaseOrderService
                ->returnToDraft(
                    purchaseOrder:
                        $purchaseOrder,

                    actor: $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Purchase order returned to draft successfully.',

                data: [
                    'id' => (int) $purchaseOrder
                        ->getKey(),

                    'status' =>
                        $purchaseOrder->status,
                ],

                redirectTo: route(
                    'purchase-orders.show',
                    $purchaseOrder,
                ),
            );
    }

    public function approve(
        Request $request,
        PurchaseOrder $purchaseOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'approve',
            $purchaseOrder,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $purchaseOrder =
            $this->purchaseOrderService
                ->approve(
                    purchaseOrder:
                        $purchaseOrder,

                    actor: $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Purchase order approved successfully.',

                data: [
                    'id' => (int) $purchaseOrder
                        ->getKey(),

                    'status' =>
                        $purchaseOrder->status,

                    'document_number' =>
                        $purchaseOrder
                            ->document_number,
                ],

                redirectTo: route(
                    'purchase-orders.show',
                    $purchaseOrder,
                ),
            );
    }

    public function cancel(
        CancelPurchaseOrderRequest $request,
        PurchaseOrder $purchaseOrder,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'cancel',
            $purchaseOrder,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $purchaseOrder =
            $this->purchaseOrderService
                ->cancel(
                    purchaseOrder:
                        $purchaseOrder,

                    reason: (string) $request
                        ->validated(
                            'cancellation_reason',
                        ),

                    actor: $actor,
                );

        return $this->responseService
            ->success(
                message:
                    'Purchase order cancelled successfully.',

                data: [
                    'id' => (int) $purchaseOrder
                        ->getKey(),

                    'status' =>
                        $purchaseOrder->status,
                ],

                redirectTo: route(
                    'purchase-orders.show',
                    $purchaseOrder,
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
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'code',
                'status',
            ]);

        return [
            'branchOptions' => $branches
                ->map(
                    static fn (
                        Branch $branch,
                    ): array => [
                        'value' => (int) $branch
                            ->getKey(),

                        'label' =>
                            "{$branch->name} ({$branch->code})",

                        'status' =>
                            $branch->status,
                    ],
                )
                ->values()
                ->all(),

            'warehouseOptions' => $warehouses
                ->map(
                    static fn (
                        Warehouse $warehouse,
                    ): array => [
                        'value' => (int) $warehouse
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

            'supplierOptions' => $suppliers
                ->map(
                    static fn (
                        Supplier $supplier,
                    ): array => [
                        'value' => (int) $supplier
                            ->getKey(),

                        'label' =>
                            "{$supplier->name} ({$supplier->code})",

                        'status' =>
                            $supplier->status,
                    ],
                )
                ->values()
                ->all(),

            'statusOptions' =>
                $this->statusRegistry
                    ->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(
        User $actor,
        ?PurchaseOrder $purchaseOrder = null,
    ): array {
        $tenant = $this->tenantContext
            ->tenant();

        $currentBranchId =
            $purchaseOrder !== null
                ? (int) $purchaseOrder
                    ->branch_id
                : null;

        $currentWarehouseId =
            $purchaseOrder?->warehouse_id
                !== null
                    ? (int) $purchaseOrder
                        ->warehouse_id
                    : null;

        $currentSupplierId =
            $purchaseOrder !== null
                ? (int) $purchaseOrder
                    ->supplier_id
                : null;

        $currentProductIds =
            $purchaseOrder !== null
                ? $purchaseOrder->lines
                    ->pluck('product_id')
                    ->map(
                        static fn (
                            mixed $id,
                        ): int => (int) $id,
                    )
                    ->unique()
                    ->values()
                    ->all()
                : [];

        $branches =
            $this->branchAccessService
                ->scopeBranchQuery(
                    query: Branch::query(),
                    user: $actor,
                )
                ->where(
                    static function (
                        Builder $query,
                    ) use (
                        $currentBranchId,
                    ): void {
                        $query->where(
                            'status',
                            'active',
                        );

                        if (
                            $currentBranchId
                            !== null
                        ) {
                            $query->orWhereKey(
                                $currentBranchId,
                            );
                        }
                    },
                )
                ->orderBy('name')
                ->orderBy('id')
                ->get([
                    'id',
                    'name',
                    'code',
                    'status',
                    'address',
                ]);

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
            ->where(
                static function (
                    Builder $query,
                ) use (
                    $currentWarehouseId,
                ): void {
                    $query->where(
                        'status',
                        'active',
                    );

                    if (
                        $currentWarehouseId
                        !== null
                    ) {
                        $query->orWhereKey(
                            $currentWarehouseId,
                        );
                    }
                },
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'branch_id',
                'name',
                'code',
                'status',
                'is_default',
                'address',
            ]);

        $suppliers = Supplier::query()
            ->where(
                static function (
                    Builder $query,
                ) use (
                    $currentSupplierId,
                ): void {
                    $query->where(
                        'status',
                        'active',
                    );

                    if (
                        $currentSupplierId
                        !== null
                    ) {
                        $query->orWhereKey(
                            $currentSupplierId,
                        );
                    }
                },
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $activeBranchSettings =
            ProductBranchSetting::query()
                ->whereIn(
                    'branch_id',
                    $branchIds,
                )
                ->where(
                    'status',
                    'active',
                )
                ->where(
                    'is_purchasable',
                    true,
                )
                ->get([
                    'product_id',
                    'branch_id',
                ]);

        $availableProductIds =
            $activeBranchSettings
                ->pluck('product_id')
                ->map(
                    static fn (
                        mixed $id,
                    ): int => (int) $id,
                )
                ->merge(
                    $currentProductIds,
                )
                ->unique()
                ->values()
                ->all();

        $activeWarehouseSettings =
            ProductWarehouseSetting::query()
                ->whereIn(
                    'branch_id',
                    $branchIds,
                )
                ->whereIn(
                    'product_id',
                    $availableProductIds,
                )
                ->where(
                    'status',
                    'active',
                )
                ->get([
                    'product_id',
                    'branch_id',
                    'warehouse_id',
                ]);

        $products = Product::query()
            ->with([
                'baseUnit:id,name,code,symbol,allow_decimal,decimal_places,status',
            ])
            ->whereIn(
                'id',
                $availableProductIds,
            )
            ->where(
                static function (
                    Builder $query,
                ) use (
                    $currentProductIds,
                ): void {
                    $query->where(
                        static function (
                            Builder $activeQuery,
                        ): void {
                            $activeQuery
                                ->where(
                                    'status',
                                    'active',
                                )
                                ->where(
                                    'is_purchasable',
                                    true,
                                );
                        },
                    );

                    if (
                        $currentProductIds !== []
                    ) {
                        $query->orWhereIn(
                            'id',
                            $currentProductIds,
                        );
                    }
                },
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $branchIdsByProduct =
            $activeBranchSettings
                ->groupBy('product_id');

        $warehouseIdsByProduct =
            $activeWarehouseSettings
                ->groupBy('product_id');

        return [
            'branchOptions' => $branches
                ->map(
                    static fn (
                        Branch $branch,
                    ): array => [
                        'value' => (int) $branch
                            ->getKey(),

                        'label' =>
                            "{$branch->name} ({$branch->code})",

                        'status' =>
                            $branch->status,

                        'address' =>
                            $branch->address,
                    ],
                )
                ->values()
                ->all(),

            'warehouseOptions' => $warehouses
                ->map(
                    static fn (
                        Warehouse $warehouse,
                    ): array => [
                        'value' => (int) $warehouse
                            ->getKey(),

                        'branch_id' =>
                            (int) $warehouse
                                ->branch_id,

                        'label' =>
                            "{$warehouse->name} ({$warehouse->code})",

                        'status' =>
                            $warehouse->status,

                        'is_default' =>
                            (bool) $warehouse
                                ->is_default,

                        'address' =>
                            $warehouse->address,
                    ],
                )
                ->values()
                ->all(),

            'supplierOptions' => $suppliers
                ->map(
                    fn (
                        Supplier $supplier,
                    ): array => [
                        'value' => (int) $supplier
                            ->getKey(),

                        'label' =>
                            "{$supplier->name} ({$supplier->code})",

                        'name' =>
                            $supplier->name,

                        'code' =>
                            $supplier->code,

                        'status' =>
                            $supplier->status,

                        'payment_terms_days' =>
                            (int) $supplier
                                ->payment_terms_days,

                        'address' =>
                            $this->supplierAddress(
                                $supplier,
                            ),
                    ],
                )
                ->values()
                ->all(),

            'productOptions' => $products
                ->map(
                    static function (
                        Product $product,
                    ) use (
                        $branchIdsByProduct,
                        $warehouseIdsByProduct,
                    ): array {
                        $baseUnit =
                            $product->baseUnit;

                        return [
                            'value' => (int) $product
                                ->getKey(),

                            'label' =>
                                "{$product->name} ({$product->sku})",

                            'name' =>
                                $product->name,

                            'sku' =>
                                $product->sku,

                            'product_type' =>
                                $product
                                    ->product_type,

                            'status' =>
                                $product->status,

                            'is_purchasable' =>
                                (bool) $product
                                    ->is_purchasable,

                            'default_unit_price' =>
                                (string) $product
                                    ->cost_price,

                            'branch_ids' =>
                                $branchIdsByProduct
                                    ->get(
                                        $product
                                            ->getKey(),

                                        collect(),
                                    )
                                    ->pluck(
                                        'branch_id',
                                    )
                                    ->map(
                                        static fn (
                                            mixed $id,
                                        ): int =>
                                            (int) $id,
                                    )
                                    ->unique()
                                    ->values()
                                    ->all(),

                            'warehouse_ids' =>
                                $warehouseIdsByProduct
                                    ->get(
                                        $product
                                            ->getKey(),

                                        collect(),
                                    )
                                    ->pluck(
                                        'warehouse_id',
                                    )
                                    ->map(
                                        static fn (
                                            mixed $id,
                                        ): int =>
                                            (int) $id,
                                    )
                                    ->unique()
                                    ->values()
                                    ->all(),

                            'base_unit' => [
                                'id' => (int) $baseUnit
                                    ->getKey(),

                                'name' =>
                                    $baseUnit->name,

                                'code' =>
                                    $baseUnit->code,

                                'symbol' =>
                                    $baseUnit->symbol,

                                'allow_decimal' =>
                                    (bool) $baseUnit
                                        ->allow_decimal,

                                'decimal_places' =>
                                    (int) $baseUnit
                                        ->decimal_places,

                                'status' =>
                                    $baseUnit->status,
                            ],
                        ];
                    },
                )
                ->values()
                ->all(),

            'defaults' => [
                'order_date' =>
                    CarbonImmutable::now(
                        $tenant->timezone,
                    )->format('Y-m-d'),

                'currency_code' =>
                    $tenant->currency_code,

                'exchange_rate' =>
                    '1.00000000',

                'shipping_amount' =>
                    '0.000000',

                'other_charges' =>
                    '0.000000',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryData(
        PurchaseOrder $purchaseOrder,
        User $actor,
    ): array {
        return [
            'id' => (int) $purchaseOrder
                ->getKey(),

            'document_number' =>
                $purchaseOrder
                    ->document_number,

            'order_date' =>
                $purchaseOrder
                    ->order_date
                    ?->format('Y-m-d'),

            'expected_delivery_date' =>
                $purchaseOrder
                    ->expected_delivery_date
                    ?->format('Y-m-d'),

            'supplier_reference' =>
                $purchaseOrder
                    ->supplier_reference,

            'supplier_name' =>
                $purchaseOrder
                    ->supplier_name,

            'supplier_code' =>
                $purchaseOrder
                    ->supplier_code,

            'currency_code' =>
                $purchaseOrder
                    ->currency_code,

            'total_amount' =>
                (string) $purchaseOrder
                    ->total_amount,

            'status' =>
                $purchaseOrder->status,

            'status_label' =>
                $this->statusRegistry
                    ->label(
                        $purchaseOrder
                            ->status,
                    ),

            'revision' =>
                (int) $purchaseOrder
                    ->revision,

            'branch' =>
                $this->branchData(
                    $purchaseOrder->branch,
                ),

            'warehouse' =>
                $purchaseOrder->warehouse
                    !== null
                        ? $this->warehouseData(
                            $purchaseOrder
                                ->warehouse,
                        )
                        : null,

            'created_by' =>
                $purchaseOrder->createdBy
                    !== null
                        ? [
                            'id' => (int) $purchaseOrder
                                ->createdBy
                                ->getKey(),

                            'name' => $purchaseOrder
                                ->createdBy
                                ->name,
                        ]
                        : null,

            'created_at' =>
                $purchaseOrder
                    ->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $purchaseOrder
                    ->updated_at
                    ?->toIso8601String(),

            'can' =>
                $this->actionPermissions(
                    actor: $actor,
                    purchaseOrder:
                        $purchaseOrder,
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(
        PurchaseOrder $purchaseOrder,
        User $actor,
    ): array {
        return [
            ...$this->summaryData(
                purchaseOrder:
                    $purchaseOrder,

                actor: $actor,
            ),

            'branch_id' =>
                (int) $purchaseOrder
                    ->branch_id,

            'warehouse_id' =>
                $purchaseOrder
                    ->warehouse_id !== null
                        ? (int) $purchaseOrder
                            ->warehouse_id
                        : null,

            'supplier_id' =>
                (int) $purchaseOrder
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

            'document_number_allocation_id' =>
                $purchaseOrder
                    ->document_number_allocation_id
                    !== null
                        ? (int) $purchaseOrder
                            ->document_number_allocation_id
                        : null,

            'exchange_rate' =>
                (string) $purchaseOrder
                    ->exchange_rate,

            'supplier_contact_person' =>
                $purchaseOrder
                    ->supplier_contact_person,

            'supplier_email' =>
                $purchaseOrder
                    ->supplier_email,

            'supplier_phone' =>
                $purchaseOrder
                    ->supplier_phone,

            'supplier_tax_number' =>
                $purchaseOrder
                    ->supplier_tax_number,

            'supplier_address' =>
                $purchaseOrder
                    ->supplier_address,

            'delivery_address' =>
                $purchaseOrder
                    ->delivery_address,

            'payment_terms_days' =>
                (int) $purchaseOrder
                    ->payment_terms_days,

            'subtotal' =>
                (string) $purchaseOrder
                    ->subtotal,

            'discount_amount' =>
                (string) $purchaseOrder
                    ->discount_amount,

            'tax_amount' =>
                (string) $purchaseOrder
                    ->tax_amount,

            'shipping_amount' =>
                (string) $purchaseOrder
                    ->shipping_amount,

            'other_charges' =>
                (string) $purchaseOrder
                    ->other_charges,

            'terms_and_conditions' =>
                $purchaseOrder
                    ->terms_and_conditions,

            'notes' =>
                $purchaseOrder->notes,

            'submitted_at' =>
                $purchaseOrder
                    ->submitted_at
                    ?->toIso8601String(),

            'approved_at' =>
                $purchaseOrder
                    ->approved_at
                    ?->toIso8601String(),

            'cancelled_at' =>
                $purchaseOrder
                    ->cancelled_at
                    ?->toIso8601String(),

            'cancellation_reason' =>
                $purchaseOrder
                    ->cancellation_reason,

            'submitted_by' =>
                $this->userData(
                    $purchaseOrder
                        ->submittedBy,
                ),

            'approved_by' =>
                $this->userData(
                    $purchaseOrder
                        ->approvedBy,
                ),

            'cancelled_by' =>
                $this->userData(
                    $purchaseOrder
                        ->cancelledBy,
                ),

            'lines' =>
                $purchaseOrder->lines
                    ->map(
                        fn (
                            PurchaseOrderLine $line,
                        ): array => $this
                            ->lineData($line),
                    )
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(
        PurchaseOrder $purchaseOrder,
    ): array {
        return [
            'id' => (int) $purchaseOrder
                ->getKey(),

            'branch_id' =>
                (int) $purchaseOrder
                    ->branch_id,

            'warehouse_id' =>
                $purchaseOrder
                    ->warehouse_id !== null
                        ? (int) $purchaseOrder
                            ->warehouse_id
                        : null,

            'supplier_id' =>
                (int) $purchaseOrder
                    ->supplier_id,

            'document_number' =>
                $purchaseOrder
                    ->document_number,

            'order_date' =>
                $purchaseOrder
                    ->order_date
                    ?->format('Y-m-d'),

            'expected_delivery_date' =>
                $purchaseOrder
                    ->expected_delivery_date
                    ?->format('Y-m-d'),

            'supplier_reference' =>
                $purchaseOrder
                    ->supplier_reference,

            'currency_code' =>
                $purchaseOrder
                    ->currency_code,

            'exchange_rate' =>
                (string) $purchaseOrder
                    ->exchange_rate,

            'delivery_address' =>
                $purchaseOrder
                    ->delivery_address,

            'payment_terms_days' =>
                (int) $purchaseOrder
                    ->payment_terms_days,

            'shipping_amount' =>
                (string) $purchaseOrder
                    ->shipping_amount,

            'other_charges' =>
                (string) $purchaseOrder
                    ->other_charges,

            'terms_and_conditions' =>
                $purchaseOrder
                    ->terms_and_conditions,

            'notes' =>
                $purchaseOrder->notes,

            'status' =>
                $purchaseOrder->status,

            'revision' =>
                (int) $purchaseOrder
                    ->revision,

            'lines' =>
                $purchaseOrder->lines
                    ->map(
                        static fn (
                            PurchaseOrderLine $line,
                        ): array => [
                            'id' => (int) $line
                                ->getKey(),

                            'product_id' =>
                                (int) $line
                                    ->product_id,

                            'unit_id' =>
                                (int) $line
                                    ->unit_id,

                            'description' =>
                                $line
                                    ->description,

                            'ordered_quantity' =>
                                (string) $line
                                    ->ordered_quantity,

                            'unit_price' =>
                                (string) $line
                                    ->unit_price,

                            'discount_amount' =>
                                (string) $line
                                    ->discount_amount,

                            'tax_rate' =>
                                (string) $line
                                    ->tax_rate,

                            'gross_amount' =>
                                (string) $line
                                    ->gross_amount,

                            'tax_amount' =>
                                (string) $line
                                    ->tax_amount,

                            'line_total' =>
                                (string) $line
                                    ->line_total,
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
        PurchaseOrderLine $line,
    ): array {
        return [
            'id' =>
                (int) $line->getKey(),

            'line_number' =>
                (int) $line->line_number,

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

            'description' =>
                $line->description,

            'ordered_quantity' =>
                (string) $line
                    ->ordered_quantity,

            'received_quantity' =>
                (string) $line
                    ->received_quantity,

            'unit_price' =>
                (string) $line
                    ->unit_price,

            'gross_amount' =>
                (string) $line
                    ->gross_amount,

            'discount_amount' =>
                (string) $line
                    ->discount_amount,

            'tax_rate' =>
                (string) $line
                    ->tax_rate,

            'tax_amount' =>
                (string) $line
                    ->tax_amount,

            'line_total' =>
                (string) $line
                    ->line_total,

            'is_fully_received' =>
                $line->isFullyReceived(),

            'has_outstanding_quantity' =>
                $line
                    ->hasOutstandingQuantity(),
        ];
    }

    /**
     * @return array{
     *     view: bool,
     *     update: bool,
     *     delete: bool,
     *     submit: bool,
     *     return_to_draft: bool,
     *     approve: bool,
     *     cancel: bool,
     *     receive_goods: bool
     * }
     */
    private function actionPermissions(
        User $actor,
        PurchaseOrder $purchaseOrder,
    ): array {
        return [
            'view' => $actor->can(
                'view',
                $purchaseOrder,
            ),

            'update' => $actor->can(
                'update',
                $purchaseOrder,
            ),

            'delete' => $actor->can(
                'delete',
                $purchaseOrder,
            ),

            'submit' => $actor->can(
                'submit',
                $purchaseOrder,
            ),

            'return_to_draft' =>
                $actor->can(
                    'returnToDraft',
                    $purchaseOrder,
                ),

            'approve' => $actor->can(
                'approve',
                $purchaseOrder,
            ),

            'cancel' => $actor->can(
                'cancel',
                $purchaseOrder,
            ),

            'receive_goods' =>
                $purchaseOrder->isReceivable()
                && $actor->can(
                    'goods_receipts.create',
                ),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     status: string
     * }
     */
    private function branchData(
        Branch $branch,
    ): array {
        return [
            'id' =>
                (int) $branch->getKey(),

            'name' =>
                $branch->name,

            'code' =>
                $branch->code,

            'status' =>
                $branch->status,
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     branch_id: int,
     *     name: string,
     *     code: string,
     *     status: string
     * }
     */
    private function warehouseData(
        Warehouse $warehouse,
    ): array {
        return [
            'id' =>
                (int) $warehouse->getKey(),

            'branch_id' =>
                (int) $warehouse
                    ->branch_id,

            'name' =>
                $warehouse->name,

            'code' =>
                $warehouse->code,

            'status' =>
                $warehouse->status,
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

    private function supplierAddress(
        Supplier $supplier,
    ): ?string {
        $parts = array_values(
            array_filter(
                [
                    $supplier
                        ->address_line_1,

                    $supplier
                        ->address_line_2,

                    $supplier->city,
                    $supplier->state,

                    $supplier
                        ->postal_code,

                    $supplier
                        ->country_code,
                ],

                static fn (
                    mixed $value,
                ): bool => is_string($value)
                    && trim($value) !== '',
            ),
        );

        return $parts === []
            ? null
            : implode(
                ', ',
                $parts,
            );
    }
}
