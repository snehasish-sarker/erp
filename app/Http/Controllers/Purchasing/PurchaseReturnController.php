<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\CancelPurchaseReturnRequest;
use App\Http\Requests\Purchasing\IndexPurchaseReturnRequest;
use App\Http\Requests\Purchasing\ReversePurchaseReturnRequest;
use App\Http\Requests\Purchasing\StorePurchaseReturnRequest;
use App\Http\Requests\Purchasing\UpdatePurchaseReturnRequest;
use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnLine;
use App\Models\StockLedgerEntry;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceMatch;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organisation\BranchAccessService;
use App\Services\Purchasing\PurchaseReturnService;
use App\Support\Purchasing\PurchaseReturnStatusRegistry;
use App\Support\Responses\CommonResponseService;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\SupplierDebitNote;

final class PurchaseReturnController extends Controller
{
    private const SCALE = 6;

    public function __construct(
        private readonly PurchaseReturnService $purchaseReturnService,
        private readonly PurchaseReturnStatusRegistry $statusRegistry,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexPurchaseReturnRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            PurchaseReturn::class,
        );

        $actor = $this->actor($request);
        $validated = $request->validated();

        $search = (string) (
            $validated['search'] ?? ''
        );

        $branchId = $this->validatedId(
            $validated,
            'branch_id',
        );

        $warehouseId = $this->validatedId(
            $validated,
            'warehouse_id',
        );

        $supplierId = $this->validatedId(
            $validated,
            'supplier_id',
        );

        $purchaseOrderId =
            $this->validatedId(
                $validated,
                'purchase_order_id',
            );

        $goodsReceiptId =
            $this->validatedId(
                $validated,
                'goods_receipt_id',
            );

        $supplierInvoiceId =
            $this->validatedId(
                $validated,
                'supplier_invoice_id',
            );

        $status = (string) (
            $validated['status'] ?? ''
        );

        $returnDateFrom = (string) (
            $validated['return_date_from']
                ?? ''
        );

        $returnDateTo = (string) (
            $validated['return_date_to']
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

        $query = PurchaseReturn::query()
            ->with([
                'branch:id,name,code,status',
                'warehouse:id,branch_id,name,code,status',
                'supplier:id,name,code,status',
                'purchaseOrder:id,document_number,status',
                'goodsReceipt:id,receipt_number,status',
                'supplierInvoice:id,supplier_invoice_number,status',
                'createdBy:id,name',
                'submittedBy:id,name',
                'approvedBy:id,name',
                'postedBy:id,name',
                'reversedBy:id,name',
                'cancelledBy:id,name',
            ]);

        $this->branchAccessService
            ->scopeQuery(
                query: $query,
                user: $actor,
                branchColumn:
                    'purchase_returns.branch_id',
            );

        $purchaseReturns = $query
            ->when(
                $search !== '',
                static function (
                    Builder $returnQuery,
                ) use ($search): void {
                    $returnQuery->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'return_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'purchase_order_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'goods_receipt_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'supplier_invoice_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'supplier_reference',
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
                                    'return_reason',
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
                    Builder $returnQuery,
                ): Builder => $returnQuery
                    ->where(
                        'branch_id',
                        $branchId,
                    ),
            )
            ->when(
                $warehouseId !== null,
                static fn (
                    Builder $returnQuery,
                ): Builder => $returnQuery
                    ->where(
                        'warehouse_id',
                        $warehouseId,
                    ),
            )
            ->when(
                $supplierId !== null,
                static fn (
                    Builder $returnQuery,
                ): Builder => $returnQuery
                    ->where(
                        'supplier_id',
                        $supplierId,
                    ),
            )
            ->when(
                $purchaseOrderId !== null,
                static fn (
                    Builder $returnQuery,
                ): Builder => $returnQuery
                    ->where(
                        'purchase_order_id',
                        $purchaseOrderId,
                    ),
            )
            ->when(
                $goodsReceiptId !== null,
                static fn (
                    Builder $returnQuery,
                ): Builder => $returnQuery
                    ->where(
                        'goods_receipt_id',
                        $goodsReceiptId,
                    ),
            )
            ->when(
                $supplierInvoiceId !== null,
                static fn (
                    Builder $returnQuery,
                ): Builder => $returnQuery
                    ->where(
                        'supplier_invoice_id',
                        $supplierInvoiceId,
                    ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $returnQuery,
                ): Builder => $returnQuery
                    ->where(
                        'status',
                        $status,
                    ),
            )
            ->when(
                $returnDateFrom !== '',
                static fn (
                    Builder $returnQuery,
                ): Builder => $returnQuery
                    ->whereDate(
                        'return_date',
                        '>=',
                        $returnDateFrom,
                    ),
            )
            ->when(
                $returnDateTo !== '',
                static fn (
                    Builder $returnQuery,
                ): Builder => $returnQuery
                    ->whereDate(
                        'return_date',
                        '<=',
                        $returnDateTo,
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
            'PurchaseReturns/Index',
            [
                'purchaseReturns' => [
                    'data' => $purchaseReturns
                        ->getCollection()
                        ->map(
                            fn (
                                PurchaseReturn $purchaseReturn,
                            ): array => $this
                                ->summaryData(
                                    purchaseReturn:
                                        $purchaseReturn,
                                    actor: $actor,
                                ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $purchaseReturns
                                ->currentPage(),

                        'last_page' =>
                            $purchaseReturns
                                ->lastPage(),

                        'per_page' =>
                            $purchaseReturns
                                ->perPage(),

                        'from' =>
                            $purchaseReturns
                                ->firstItem(),

                        'to' =>
                            $purchaseReturns
                                ->lastItem(),

                        'total' =>
                            $purchaseReturns
                                ->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'branch_id' => $branchId,
                    'warehouse_id' =>
                        $warehouseId,
                    'supplier_id' => $supplierId,
                    'purchase_order_id' =>
                        $purchaseOrderId,
                    'goods_receipt_id' =>
                        $goodsReceiptId,
                    'supplier_invoice_id' =>
                        $supplierInvoiceId,
                    'status' => $status,
                    'return_date_from' =>
                        $returnDateFrom,
                    'return_date_to' =>
                        $returnDateTo,
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
                        PurchaseReturn::class,
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
            PurchaseReturn::class,
        );

        $actor = $this->actor($request);

        return Inertia::render(
            'PurchaseReturns/Create',
            $this->formOptions(
                actor: $actor,

                selectedGoodsReceiptId:
                    $this->queryId(
                        request: $request,
                        field:
                            'goods_receipt_id',
                    ),
            ),
        );
    }

    public function store(
        StorePurchaseReturnRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            PurchaseReturn::class,
        );

        $purchaseReturn =
            $this->purchaseReturnService
                ->create(
                    data:
                        $request->validated(),

                    actor:
                        $this->actor($request),
                );

        return $this->responseService
            ->success(
                message:
                    'Purchase Return created successfully.',

                data:
                    $this->responseData(
                        $purchaseReturn,
                    ),

                redirectTo: route(
                    'purchase-returns.show',
                    $purchaseReturn,
                ),
            );
    }

    public function show(
        Request $request,
        PurchaseReturn $purchaseReturn,
    ): Response {
        Gate::authorize(
            'view',
            $purchaseReturn,
        );

        $actor = $this->actor($request);

        $purchaseReturn->load([
            'purchaseOrder:id,document_number,status,order_date,currency_code,total_amount',
            'goodsReceipt:id,receipt_number,receipt_date,status,total_accepted_quantity,total_inventory_value',
            'supplierInvoice:id,supplier_invoice_number,document_number,status,total_amount',
            'branch:id,name,code,status,address',
            'warehouse:id,branch_id,name,code,status,address',
            'supplier:id,name,code,status',
            'documentNumberAllocation',
            'lines.goodsReceiptLine',
            'lines.purchaseOrderLine',
            'lines.product:id,name,sku,status',
            'lines.unit:id,name,code,symbol,status',
            'createdBy:id,name',
            'submittedBy:id,name',
            'approvedBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'cancelledBy:id,name',
            'stockLedgerEntries.product:id,name,sku',
            'stockLedgerEntries.unit:id,name,code',
            'stockLedgerEntries.createdBy:id,name',
            'supplierDebitNote:id,purchase_return_id,debit_note_number,status',
        ]);

        return Inertia::render(
            'PurchaseReturns/Show',
            [
                'purchaseReturn' =>
                    $this->detailData(
                        purchaseReturn:
                            $purchaseReturn,
                        actor: $actor,
                    ),
            ],
        );
    }

    public function edit(
        Request $request,
        PurchaseReturn $purchaseReturn,
    ): Response {
        Gate::authorize(
            'update',
            $purchaseReturn,
        );

        $actor = $this->actor($request);

        $purchaseReturn->load([
            'lines',
        ]);

        return Inertia::render(
            'PurchaseReturns/Edit',
            [
                ...$this->formOptions(
                    actor: $actor,

                    selectedGoodsReceiptId:
                        (int) $purchaseReturn
                            ->goods_receipt_id,
                ),

                'purchaseReturn' =>
                    $this->formData(
                        $purchaseReturn,
                    ),
            ],
        );
    }

    public function update(
        UpdatePurchaseReturnRequest $request,
        PurchaseReturn $purchaseReturn,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $purchaseReturn,
        );

        $purchaseReturn =
            $this->purchaseReturnService
                ->update(
                    purchaseReturn:
                        $purchaseReturn,

                    data:
                        $request->validated(),

                    actor:
                        $this->actor($request),
                );

        return $this->responseService
            ->success(
                message:
                    'Purchase Return updated successfully.',

                data:
                    $this->responseData(
                        $purchaseReturn,
                    ),

                redirectTo: route(
                    'purchase-returns.show',
                    $purchaseReturn,
                ),
            );
    }

    public function destroy(
        Request $request,
        PurchaseReturn $purchaseReturn,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $purchaseReturn,
        );

        $this->purchaseReturnService
            ->delete(
                purchaseReturn:
                    $purchaseReturn,

                actor:
                    $this->actor($request),
            );

        return $this->responseService
            ->success(
                message:
                    'Purchase Return deleted successfully.',

                redirectTo: route(
                    'purchase-returns.index',
                ),
            );
    }

    public function submit(
        Request $request,
        PurchaseReturn $purchaseReturn,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'submit',
            $purchaseReturn,
        );

        $purchaseReturn =
            $this->purchaseReturnService
                ->submit(
                    purchaseReturn:
                        $purchaseReturn,

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            purchaseReturn:
                $purchaseReturn,

            message:
                'Purchase Return submitted successfully.',
        );
    }

    public function returnToDraft(
        Request $request,
        PurchaseReturn $purchaseReturn,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'returnToDraft',
            $purchaseReturn,
        );

        $purchaseReturn =
            $this->purchaseReturnService
                ->returnToDraft(
                    purchaseReturn:
                        $purchaseReturn,

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            purchaseReturn:
                $purchaseReturn,

            message:
                'Purchase Return returned to draft successfully.',
        );
    }

    public function approve(
        Request $request,
        PurchaseReturn $purchaseReturn,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'approve',
            $purchaseReturn,
        );

        $purchaseReturn =
            $this->purchaseReturnService
                ->approve(
                    purchaseReturn:
                        $purchaseReturn,

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            purchaseReturn:
                $purchaseReturn,

            message:
                'Purchase Return approved and quantities reserved successfully.',
        );
    }

    public function cancel(
        CancelPurchaseReturnRequest $request,
        PurchaseReturn $purchaseReturn,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'cancel',
            $purchaseReturn,
        );

        $purchaseReturn =
            $this->purchaseReturnService
                ->cancel(
                    purchaseReturn:
                        $purchaseReturn,

                    reason:
                        (string) $request
                            ->validated(
                                'cancellation_reason',
                            ),

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            purchaseReturn:
                $purchaseReturn,

            message:
                'Purchase Return cancelled successfully.',
        );
    }

    public function post(
        Request $request,
        PurchaseReturn $purchaseReturn,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'post',
            $purchaseReturn,
        );

        $purchaseReturn =
            $this->purchaseReturnService
                ->post(
                    purchaseReturn:
                        $purchaseReturn,

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            purchaseReturn:
                $purchaseReturn,

            message:
                'Purchase Return posted to inventory successfully.',
        );
    }

    public function reverse(
        ReversePurchaseReturnRequest $request,
        PurchaseReturn $purchaseReturn,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'reverse',
            $purchaseReturn,
        );

        $purchaseReturn =
            $this->purchaseReturnService
                ->reverse(
                    purchaseReturn:
                        $purchaseReturn,

                    reversalPostingDate:
                        (string) $request
                            ->validated(
                                'reversal_posting_date',
                            ),

                    reason:
                        (string) $request
                            ->validated(
                                'reversal_reason',
                            ),

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            purchaseReturn:
                $purchaseReturn,

            message:
                'Purchase Return reversed successfully.',
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

        $warehouseIds =
            PurchaseReturn::query()
                ->whereIn(
                    'branch_id',
                    $branchIds,
                )
                ->whereNotNull(
                    'warehouse_id',
                )
                ->distinct()
                ->pluck('warehouse_id')
                ->map(
                    static fn (
                        mixed $id,
                    ): int => (int) $id,
                )
                ->all();

        $warehouses = Warehouse::query()
            ->whereIn(
                'id',
                $warehouseIds,
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
                'purchaseReturns',
                static fn (
                    Builder $query,
                ): Builder => $query->whereIn(
                    'branch_id',
                    $branchIds,
                ),
            )
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'code',
                'status',
            ]);

        $purchaseOrders =
            PurchaseOrder::query()
                ->whereIn(
                    'branch_id',
                    $branchIds,
                )
                ->whereHas(
                    'purchaseReturns',
                )
                ->orderByDesc(
                    'order_date',
                )
                ->orderByDesc('id')
                ->get([
                    'id',
                    'branch_id',
                    'supplier_id',
                    'document_number',
                    'status',
                ]);

        $goodsReceipts =
            GoodsReceipt::query()
                ->whereIn(
                    'branch_id',
                    $branchIds,
                )
                ->whereHas(
                    'purchaseReturns',
                )
                ->orderByDesc(
                    'receipt_date',
                )
                ->orderByDesc('id')
                ->get([
                    'id',
                    'purchase_order_id',
                    'branch_id',
                    'warehouse_id',
                    'supplier_id',
                    'receipt_number',
                    'status',
                ]);

        $supplierInvoices =
            SupplierInvoice::query()
                ->whereIn(
                    'branch_id',
                    $branchIds,
                )
                ->whereHas(
                    'purchaseReturns',
                )
                ->orderByDesc(
                    'invoice_date',
                )
                ->orderByDesc('id')
                ->get([
                    'id',
                    'purchase_order_id',
                    'branch_id',
                    'supplier_id',
                    'document_number',
                    'supplier_invoice_number',
                    'status',
                ]);

        return [
            'branches' => $branches
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
                ->all(),

            'warehouses' => $warehouses
                ->map(
                    static fn (
                        Warehouse $warehouse,
                    ): array => [
                        'id' =>
                            (int) $warehouse
                                ->getKey(),

                        'branch_id' =>
                            (int) $warehouse
                                ->branch_id,

                        'name' =>
                            $warehouse->name,

                        'code' =>
                            $warehouse->code,

                        'status' =>
                            $warehouse->status,
                    ],
                )
                ->values()
                ->all(),

            'suppliers' => $suppliers
                ->map(
                    static fn (
                        Supplier $supplier,
                    ): array => [
                        'id' =>
                            (int) $supplier
                                ->getKey(),

                        'name' =>
                            $supplier->name,

                        'code' =>
                            $supplier->code,

                        'status' =>
                            $supplier->status,
                    ],
                )
                ->values()
                ->all(),

            'purchaseOrders' =>
                $purchaseOrders
                    ->map(
                        static fn (
                            PurchaseOrder $purchaseOrder,
                        ): array => [
                            'id' =>
                                (int) $purchaseOrder
                                    ->getKey(),

                            'branch_id' =>
                                (int) $purchaseOrder
                                    ->branch_id,

                            'supplier_id' =>
                                (int) $purchaseOrder
                                    ->supplier_id,

                            'document_number' =>
                                $purchaseOrder
                                    ->document_number,

                            'status' =>
                                $purchaseOrder
                                    ->status,
                        ],
                    )
                    ->values()
                    ->all(),

            'goodsReceipts' =>
                $goodsReceipts
                    ->map(
                        static fn (
                            GoodsReceipt $goodsReceipt,
                        ): array => [
                            'id' =>
                                (int) $goodsReceipt
                                    ->getKey(),

                            'purchase_order_id' =>
                                (int) $goodsReceipt
                                    ->purchase_order_id,

                            'branch_id' =>
                                (int) $goodsReceipt
                                    ->branch_id,

                            'warehouse_id' =>
                                $goodsReceipt
                                    ->warehouse_id
                                !== null
                                    ? (int) $goodsReceipt
                                        ->warehouse_id
                                    : null,

                            'supplier_id' =>
                                (int) $goodsReceipt
                                    ->supplier_id,

                            'receipt_number' =>
                                $goodsReceipt
                                    ->receipt_number,

                            'status' =>
                                $goodsReceipt
                                    ->status,
                        ],
                    )
                    ->values()
                    ->all(),

            'supplierInvoices' =>
                $supplierInvoices
                    ->map(
                        static fn (
                            SupplierInvoice $supplierInvoice,
                        ): array => [
                            'id' =>
                                (int) $supplierInvoice
                                    ->getKey(),

                            'purchase_order_id' =>
                                (int) $supplierInvoice
                                    ->purchase_order_id,

                            'branch_id' =>
                                (int) $supplierInvoice
                                    ->branch_id,

                            'supplier_id' =>
                                (int) $supplierInvoice
                                    ->supplier_id,

                            'document_number' =>
                                $supplierInvoice
                                    ->document_number,

                            'supplier_invoice_number' =>
                                $supplierInvoice
                                    ->supplier_invoice_number,

                            'status' =>
                                $supplierInvoice
                                    ->status,
                        ],
                    )
                    ->values()
                    ->all(),

            'statuses' =>
                $this->statusRegistry
                    ->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(
        User $actor,
        ?int $selectedGoodsReceiptId,
    ): array {
        $tenant =
            $this->tenantContext
                ->tenant();

        $receiptQuery =
            GoodsReceipt::query()
                ->with([
                    'branch:id,name,code,status',
                    'warehouse:id,branch_id,name,code,status',
                    'supplier:id,name,code,status',
                    'purchaseOrder:id,document_number,status,order_date,currency_code',
                    'lines.unit:id,name,code,symbol,status,allow_decimal,decimal_places',
                ])
                ->where(
                    'status',
                    'posted',
                )
                ->where(
                    static function (
                        Builder $query,
                    ) use (
                        $selectedGoodsReceiptId,
                    ): void {
                        $query->whereHas(
                            'lines',
                            static fn (
                                Builder $lineQuery,
                            ): Builder => $lineQuery
                                ->where(
                                    'product_type',
                                    '!=',
                                    'service',
                                )
                                ->whereRaw(
                                    'goods_receipt_lines.accepted_quantity > goods_receipt_lines.returned_quantity + goods_receipt_lines.return_reserved_quantity',
                                ),
                        );

                        if (
                            $selectedGoodsReceiptId
                            !== null
                        ) {
                            $query->orWhereKey(
                                $selectedGoodsReceiptId,
                            );
                        }
                    },
                );

        $this->branchAccessService
            ->scopeQuery(
                query: $receiptQuery,
                user: $actor,
                branchColumn:
                    'goods_receipts.branch_id',
            );

        $goodsReceipts =
            $receiptQuery
                ->orderByDesc(
                    'receipt_date',
                )
                ->orderByDesc('id')
                ->get();

        $receiptIds = $goodsReceipts
            ->pluck('id')
            ->map(
                static fn (
                    mixed $id,
                ): int => (int) $id,
            )
            ->all();

        $invoiceOptionsByReceipt =
            $this
                ->supplierInvoiceOptionsByReceipt(
                    receiptIds:
                        $receiptIds,
                );

        $today = now(
            $tenant->timezone,
        )->toDateString();

        return [
            'goodsReceipts' =>
                $goodsReceipts
                    ->map(
                        fn (
                            GoodsReceipt $goodsReceipt,
                        ): array => $this
                            ->goodsReceiptOption(
                                goodsReceipt:
                                    $goodsReceipt,

                                supplierInvoiceOptions:
                                    $invoiceOptionsByReceipt
                                        ->get(
                                            (int) $goodsReceipt
                                                ->getKey(),
                                            [],
                                        ),
                            ),
                    )
                    ->values()
                    ->all(),

            'selectedGoodsReceiptId' =>
                $selectedGoodsReceiptId,

            'defaults' => [
                'return_date' => $today,
                'posting_date' => $today,
            ],
        ];
    }

    /**
     * @param list<int> $receiptIds
     * @return Collection<int, list<array<string, mixed>>>
     */
    private function supplierInvoiceOptionsByReceipt(
        array $receiptIds,
    ): Collection {
        if ($receiptIds === []) {
            return collect();
        }

        $matches =
            SupplierInvoiceMatch::query()
                ->whereIn(
                    'goods_receipt_id',
                    $receiptIds,
                )
                ->orderBy('id')
                ->get([
                    'goods_receipt_id',
                    'supplier_invoice_id',
                ]);

        $invoiceIds = $matches
            ->pluck('supplier_invoice_id')
            ->map(
                static fn (
                    mixed $id,
                ): int => (int) $id,
            )
            ->unique()
            ->values()
            ->all();

        $invoices =
            SupplierInvoice::query()
                ->whereIn(
                    'id',
                    $invoiceIds,
                )
                ->whereIn(
                    'status',
                    [
                        'validated',
                        'approved',
                        'posted',
                    ],
                )
                ->get([
                    'id',
                    'document_number',
                    'supplier_invoice_number',
                    'invoice_date',
                    'status',
                    'total_amount',
                ])
                ->keyBy('id');

        return $matches
            ->groupBy(
                'goods_receipt_id',
            )
            ->map(
                static function (
                    Collection $receiptMatches,
                ) use ($invoices): array {
                    return $receiptMatches
                        ->map(
                            static function (
                                SupplierInvoiceMatch $match,
                            ) use ($invoices): ?array {
                                $invoice =
                                    $invoices->get(
                                        (int) $match
                                            ->supplier_invoice_id,
                                    );

                                if (
                                    !$invoice
                                        instanceof SupplierInvoice
                                ) {
                                    return null;
                                }

                                return [
                                    'id' =>
                                        (int) $invoice
                                            ->getKey(),

                                    'document_number' =>
                                        $invoice
                                            ->document_number,

                                    'supplier_invoice_number' =>
                                        $invoice
                                            ->supplier_invoice_number,

                                    'invoice_date' =>
                                        $invoice
                                            ->invoice_date
                                            ?->format(
                                                'Y-m-d',
                                            ),

                                    'status' =>
                                        $invoice
                                            ->status,

                                    'total_amount' =>
                                        (string) $invoice
                                            ->total_amount,
                                ];
                            },
                        )
                        ->filter()
                        ->unique('id')
                        ->values()
                        ->all();
                },
            );
    }

    /**
     * @param list<array<string, mixed>> $supplierInvoiceOptions
     * @return array<string, mixed>
     */
    private function goodsReceiptOption(
        GoodsReceipt $goodsReceipt,
        array $supplierInvoiceOptions,
    ): array {
        return [
            'id' =>
                (int) $goodsReceipt
                    ->getKey(),

            'purchase_order_id' =>
                (int) $goodsReceipt
                    ->purchase_order_id,

            'purchase_order_number' =>
                $goodsReceipt
                    ->purchase_order_number,

            'receipt_number' =>
                $goodsReceipt
                    ->receipt_number,

            'receipt_date' =>
                $goodsReceipt
                    ->receipt_date
                    ?->format('Y-m-d'),

            'branch_id' =>
                (int) $goodsReceipt
                    ->branch_id,

            'branch_name' =>
                $goodsReceipt
                    ->branch
                    ->name,

            'branch_code' =>
                $goodsReceipt
                    ->branch
                    ->code,

            'warehouse_id' =>
                $goodsReceipt
                    ->warehouse_id
                !== null
                    ? (int) $goodsReceipt
                        ->warehouse_id
                    : null,

            'warehouse_name' =>
                $goodsReceipt
                    ->warehouse
                    ?->name,

            'warehouse_code' =>
                $goodsReceipt
                    ->warehouse
                    ?->code,

            'supplier_id' =>
                (int) $goodsReceipt
                    ->supplier_id,

            'supplier_name' =>
                $goodsReceipt
                    ->supplier_name,

            'supplier_code' =>
                $goodsReceipt
                    ->supplier_code,

            'supplier_invoices' =>
                $supplierInvoiceOptions,

            'lines' =>
                $goodsReceipt
                    ->lines
                    ->filter(
                        fn (
                            GoodsReceiptLine $line,
                        ): bool => $line
                            ->product_type
                            !== 'service'
                            && $this
                                ->returnableQuantity(
                                    $line,
                                )
                                ->isPositive(),
                    )
                    ->map(
                        fn (
                            GoodsReceiptLine $line,
                        ): array => $this
                            ->goodsReceiptLineOption(
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
    private function goodsReceiptLineOption(
        GoodsReceiptLine $line,
    ): array {
        return [
            'id' =>
                (int) $line->getKey(),

            'purchase_order_line_id' =>
                (int) $line
                    ->purchase_order_line_id,

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

            'allow_decimal' =>
                (bool) $line
                    ->unit
                    ->allow_decimal,

            'decimal_places' =>
                (int) $line
                    ->unit
                    ->decimal_places,

            'accepted_quantity' =>
                (string) $line
                    ->accepted_quantity,

            'return_reserved_quantity' =>
                (string) $line
                    ->return_reserved_quantity,

            'returned_quantity' =>
                (string) $line
                    ->returned_quantity,

            'returnable_quantity' =>
                $this
                    ->returnableQuantity(
                        $line,
                    )
                    ->__toString(),

            'supplier_unit_cost' =>
                (string) $line
                    ->unit_cost,

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
        ];
    }

    private function returnableQuantity(
        GoodsReceiptLine $line,
    ): BigDecimal {
        return BigDecimal::of(
            (string) $line
                ->accepted_quantity,
        )
            ->minus(
                BigDecimal::of(
                    (string) $line
                        ->returned_quantity,
                ),
            )
            ->minus(
                BigDecimal::of(
                    (string) $line
                        ->return_reserved_quantity,
                ),
            )
            ->toScale(
                self::SCALE,
                RoundingMode::HALF_UP,
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryData(
        PurchaseReturn $purchaseReturn,
        User $actor,
    ): array {
        return [
            'id' =>
                (int) $purchaseReturn
                    ->getKey(),

            'return_number' =>
                $purchaseReturn
                    ->return_number,

            'return_date' =>
                $purchaseReturn
                    ->return_date
                    ?->format('Y-m-d'),

            'posting_date' =>
                $purchaseReturn
                    ->posting_date
                    ?->format('Y-m-d'),

            'purchase_order_id' =>
                (int) $purchaseReturn
                    ->purchase_order_id,

            'purchase_order_number' =>
                $purchaseReturn
                    ->purchase_order_number,

            'goods_receipt_id' =>
                (int) $purchaseReturn
                    ->goods_receipt_id,

            'goods_receipt_number' =>
                $purchaseReturn
                    ->goods_receipt_number,

            'supplier_invoice_id' =>
                $purchaseReturn
                    ->supplier_invoice_id
                !== null
                    ? (int) $purchaseReturn
                        ->supplier_invoice_id
                    : null,

            'supplier_invoice_number' =>
                $purchaseReturn
                    ->supplier_invoice_number,

            'branch' => [
                'id' =>
                    (int) $purchaseReturn
                        ->branch
                        ->getKey(),

                'name' =>
                    $purchaseReturn
                        ->branch
                        ->name,

                'code' =>
                    $purchaseReturn
                        ->branch
                        ->code,
            ],

            'warehouse' =>
                $purchaseReturn
                    ->warehouse
                !== null
                    ? [
                        'id' =>
                            (int) $purchaseReturn
                                ->warehouse
                                ->getKey(),

                        'name' =>
                            $purchaseReturn
                                ->warehouse
                                ->name,

                        'code' =>
                            $purchaseReturn
                                ->warehouse
                                ->code,
                    ]
                    : null,

            'supplier' => [
                'id' =>
                    (int) $purchaseReturn
                        ->supplier
                        ->getKey(),

                'name' =>
                    $purchaseReturn
                        ->supplier_name,

                'code' =>
                    $purchaseReturn
                        ->supplier_code,
            ],

            'total_return_quantity' =>
                (string) $purchaseReturn
                    ->total_return_quantity,

            'total_supplier_value' =>
                (string) $purchaseReturn
                    ->total_supplier_value,

            'total_inventory_value' =>
                (string) $purchaseReturn
                    ->total_inventory_value,

            'total_cost_variance' =>
                (string) $purchaseReturn
                    ->total_cost_variance,

            'status' =>
                $purchaseReturn->status,

            'status_label' =>
                $this->statusRegistry
                    ->label(
                        $purchaseReturn
                            ->status,
                    ),

            'created_at' =>
                $purchaseReturn
                    ->created_at
                    ?->toIso8601String(),

            'created_by' =>
                $this->userData(
                    $purchaseReturn
                        ->createdBy,
                ),

            'can' =>
                $this->actionPermissions(
                    actor: $actor,
                    purchaseReturn:
                        $purchaseReturn,
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(
        PurchaseReturn $purchaseReturn,
        User $actor,
    ): array {
        return [
            ...$this->summaryData(
                purchaseReturn:
                    $purchaseReturn,
                actor: $actor,
            ),

            'branch_id' =>
                (int) $purchaseReturn
                    ->branch_id,

            'warehouse_id' =>
                $purchaseReturn
                    ->warehouse_id
                !== null
                    ? (int) $purchaseReturn
                        ->warehouse_id
                    : null,

            'supplier_id' =>
                (int) $purchaseReturn
                    ->supplier_id,

            'supplier_debit_note' =>
                $this->supplierDebitNoteReference(
                    $purchaseReturn,
                ),

            'can_view_supplier_debit_notes' =>
                $actor->can(
                    'viewAny',
                    SupplierDebitNote::class,
                ),

            'can_create_supplier_debit_note' =>
                $this->canCreateSupplierDebitNote(
                    actor: $actor,
                    purchaseReturn: $purchaseReturn,
                ),        

            'document_number_allocation_id' =>
                $purchaseReturn
                    ->document_number_allocation_id
                !== null
                    ? (int) $purchaseReturn
                        ->document_number_allocation_id
                    : null,

            'supplier_reference' =>
                $purchaseReturn
                    ->supplier_reference,

            'return_reason' =>
                $purchaseReturn
                    ->return_reason,

            'notes' =>
                $purchaseReturn->notes,

            'revision' =>
                (int) $purchaseReturn
                    ->revision,

            'submitted_at' =>
                $purchaseReturn
                    ->submitted_at
                    ?->toIso8601String(),

            'approved_at' =>
                $purchaseReturn
                    ->approved_at
                    ?->toIso8601String(),

            'posted_at' =>
                $purchaseReturn
                    ->posted_at
                    ?->toIso8601String(),

            'reversal_posting_date' =>
                $purchaseReturn
                    ->reversal_posting_date
                    ?->format('Y-m-d'),

            'reversed_at' =>
                $purchaseReturn
                    ->reversed_at
                    ?->toIso8601String(),

            'reversal_reason' =>
                $purchaseReturn
                    ->reversal_reason,

            'cancelled_at' =>
                $purchaseReturn
                    ->cancelled_at
                    ?->toIso8601String(),

            'cancellation_reason' =>
                $purchaseReturn
                    ->cancellation_reason,

            'submitted_by' =>
                $this->userData(
                    $purchaseReturn
                        ->submittedBy,
                ),

            'approved_by' =>
                $this->userData(
                    $purchaseReturn
                        ->approvedBy,
                ),

            'posted_by' =>
                $this->userData(
                    $purchaseReturn
                        ->postedBy,
                ),

            'reversed_by' =>
                $this->userData(
                    $purchaseReturn
                        ->reversedBy,
                ),

            'cancelled_by' =>
                $this->userData(
                    $purchaseReturn
                        ->cancelledBy,
                ),

            'lines' =>
                $purchaseReturn
                    ->lines
                    ->map(
                        fn (
                            PurchaseReturnLine $line,
                        ): array => $this
                            ->lineData($line),
                    )
                    ->values()
                    ->all(),

            'stock_ledger_entries' =>
                $purchaseReturn
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
        PurchaseReturn $purchaseReturn,
    ): array {
        return [
            'id' =>
                (int) $purchaseReturn
                    ->getKey(),

            'goods_receipt_id' =>
                (int) $purchaseReturn
                    ->goods_receipt_id,

            'supplier_invoice_id' =>
                $purchaseReturn
                    ->supplier_invoice_id
                !== null
                    ? (int) $purchaseReturn
                        ->supplier_invoice_id
                    : null,

            'return_number' =>
                $purchaseReturn
                    ->return_number,

            'return_date' =>
                $purchaseReturn
                    ->return_date
                    ?->format('Y-m-d'),

            'posting_date' =>
                $purchaseReturn
                    ->posting_date
                    ?->format('Y-m-d'),

            'supplier_reference' =>
                $purchaseReturn
                    ->supplier_reference
                ?? '',

            'return_reason' =>
                $purchaseReturn
                    ->return_reason,

            'notes' =>
                $purchaseReturn->notes
                ?? '',

            'status' =>
                $purchaseReturn->status,

            'lines' =>
                $purchaseReturn
                    ->lines
                    ->map(
                        static fn (
                            PurchaseReturnLine $line,
                        ): array => [
                            'include' => true,

                            'goods_receipt_line_id' =>
                                (int) $line
                                    ->goods_receipt_line_id,

                            'return_quantity' =>
                                (string) $line
                                    ->return_quantity,

                            'return_reason' =>
                                $line
                                    ->return_reason
                                ?? '',

                            'notes' =>
                                $line->notes
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
        PurchaseReturnLine $line,
    ): array {
        return [
            'id' =>
                (int) $line->getKey(),

            'line_number' =>
                (int) $line->line_number,

            'goods_receipt_line_id' =>
                (int) $line
                    ->goods_receipt_line_id,

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

            'accepted_quantity_snapshot' =>
                (string) $line
                    ->accepted_quantity_snapshot,

            'previously_returned_quantity_snapshot' =>
                (string) $line
                    ->previously_returned_quantity_snapshot,

            'previously_reserved_quantity_snapshot' =>
                (string) $line
                    ->previously_reserved_quantity_snapshot,

            'returnable_quantity_snapshot' =>
                (string) $line
                    ->returnable_quantity_snapshot,

            'return_quantity' =>
                (string) $line
                    ->return_quantity,

            'supplier_unit_cost' =>
                (string) $line
                    ->supplier_unit_cost,

            'supplier_total_cost' =>
                (string) $line
                    ->supplier_total_cost,

            'inventory_unit_cost' =>
                (string) $line
                    ->inventory_unit_cost,

            'inventory_total_cost' =>
                (string) $line
                    ->inventory_total_cost,

            'cost_variance_amount' =>
                (string) $line
                    ->cost_variance_amount,

            'batch_number' =>
                $line->batch_number,

            'serial_numbers' =>
                $line->serial_numbers
                ?? [],

            'return_reason' =>
                $line->return_reason,

            'notes' =>
                $line->notes,
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
     * @return array<string, bool>
     */
    private function actionPermissions(
        User $actor,
        PurchaseReturn $purchaseReturn,
    ): array {
        return [
            'view' =>
                $actor->can(
                    'view',
                    $purchaseReturn,
                ),

            'update' =>
                $actor->can(
                    'update',
                    $purchaseReturn,
                ),

            'delete' =>
                $actor->can(
                    'delete',
                    $purchaseReturn,
                ),

            'submit' =>
                $actor->can(
                    'submit',
                    $purchaseReturn,
                ),

            'return_to_draft' =>
                $actor->can(
                    'returnToDraft',
                    $purchaseReturn,
                ),

            'approve' =>
                $actor->can(
                    'approve',
                    $purchaseReturn,
                ),

            'cancel' =>
                $actor->can(
                    'cancel',
                    $purchaseReturn,
                ),

            'post' =>
                $actor->can(
                    'post',
                    $purchaseReturn,
                ),

            'reverse' =>
                $actor->can(
                    'reverse',
                    $purchaseReturn,
                ),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function responseData(
        PurchaseReturn $purchaseReturn,
    ): array {
        return [
            'id' =>
                (int) $purchaseReturn
                    ->getKey(),

            'status' =>
                $purchaseReturn->status,

            'return_number' =>
                $purchaseReturn
                    ->return_number,
        ];
    }

    private function workflowResponse(
        PurchaseReturn $purchaseReturn,
        string $message,
    ): JsonResponse|RedirectResponse {
        return $this->responseService
            ->success(
                message: $message,

                data:
                    $this->responseData(
                        $purchaseReturn,
                    ),

                redirectTo: route(
                    'purchase-returns.show',
                    $purchaseReturn,
                ),
            );
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function validatedId(
        array $validated,
        string $field,
    ): ?int {
        return isset($validated[$field])
            ? (int) $validated[$field]
            : null;
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

    /**
     * @return array{
     *     id: int,
     *     debit_note_number: string|null,
     *     status: string
     * }|null
     */
    private function supplierDebitNoteReference(
        PurchaseReturn $purchaseReturn,
    ): ?array {
        $supplierDebitNote =
            $purchaseReturn
                ->supplierDebitNote;

        if (
            !$supplierDebitNote
            instanceof SupplierDebitNote
        ) {
            return null;
        }

        return [
            'id' =>
                (int) $supplierDebitNote
                    ->getKey(),

            'debit_note_number' =>
                $supplierDebitNote
                    ->debit_note_number,

            'status' =>
                $supplierDebitNote
                    ->status,
        ];
    }

    private function canCreateSupplierDebitNote(
        User $actor,
        PurchaseReturn $purchaseReturn,
    ): bool {
        if (
            !$actor->can(
                'create',
                SupplierDebitNote::class,
            )
        ) {
            return false;
        }

        if (!$purchaseReturn->isPosted()) {
            return false;
        }

        return $purchaseReturn
            ->supplierDebitNote
            === null;
    }
}
