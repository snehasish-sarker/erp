<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\CancelSupplierInvoiceRequest;
use App\Http\Requests\Purchasing\DisputeSupplierInvoiceRequest;
use App\Http\Requests\Purchasing\IndexSupplierInvoiceRequest;
use App\Http\Requests\Purchasing\ReverseSupplierInvoiceRequest;
use App\Http\Requests\Purchasing\StoreSupplierInvoiceRequest;
use App\Http\Requests\Purchasing\UpdateSupplierInvoiceRequest;
use App\Models\GoodsReceiptLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use App\Models\SupplierInvoiceMatch;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Services\Purchasing\SupplierInvoiceService;
use App\Support\Purchasing\SupplierInvoiceMatchStatusRegistry;
use App\Support\Purchasing\SupplierInvoiceStatusRegistry;
use App\Support\Responses\CommonResponseService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\Tenancy\TenantContext;
use App\Models\SupplierDebitNote;

final class SupplierInvoiceController extends Controller
{
    public function __construct(
    private readonly SupplierInvoiceService $supplierInvoiceService,
    private readonly SupplierInvoiceStatusRegistry $statusRegistry,
    private readonly SupplierInvoiceMatchStatusRegistry $matchStatusRegistry,
    private readonly BranchAccessService $branchAccessService,
    private readonly TenantContext $tenantContext,
    private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexSupplierInvoiceRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            SupplierInvoice::class,
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

        $matchStatus = (string) (
            $validated['match_status'] ?? ''
        );

        $invoiceDateFrom = (string) (
            $validated['invoice_date_from']
                ?? ''
        );

        $invoiceDateTo = (string) (
            $validated['invoice_date_to']
                ?? ''
        );

        $dueDateFrom = (string) (
            $validated['due_date_from']
                ?? ''
        );

        $dueDateTo = (string) (
            $validated['due_date_to']
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

        $query = SupplierInvoice::query()
            ->with([
                'branch:id,name,code,status',
                'supplier:id,name,code,status',
                'purchaseOrder:id,document_number,status',
                'createdBy:id,name',
                'validatedBy:id,name',
                'approvedBy:id,name',
                'postedBy:id,name',
            ]);

        $this->branchAccessService
            ->scopeQuery(
                query: $query,
                user: $actor,
                branchColumn:
                    'supplier_invoices.branch_id',
            );

        $supplierInvoices = $query
            ->when(
                $search !== '',
                static function (
                    Builder $invoiceQuery,
                ) use ($search): void {
                    $invoiceQuery->where(
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
                                    'supplier_invoice_number',
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
                    Builder $invoiceQuery,
                ): Builder => $invoiceQuery
                    ->where(
                        'branch_id',
                        $branchId,
                    ),
            )
            ->when(
                $supplierId !== null,
                static fn (
                    Builder $invoiceQuery,
                ): Builder => $invoiceQuery
                    ->where(
                        'supplier_id',
                        $supplierId,
                    ),
            )
            ->when(
                $purchaseOrderId !== null,
                static fn (
                    Builder $invoiceQuery,
                ): Builder => $invoiceQuery
                    ->where(
                        'purchase_order_id',
                        $purchaseOrderId,
                    ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $invoiceQuery,
                ): Builder => $invoiceQuery
                    ->where(
                        'status',
                        $status,
                    ),
            )
            ->when(
                $matchStatus !== '',
                static fn (
                    Builder $invoiceQuery,
                ): Builder => $invoiceQuery
                    ->where(
                        'match_status',
                        $matchStatus,
                    ),
            )
            ->when(
                $invoiceDateFrom !== '',
                static fn (
                    Builder $invoiceQuery,
                ): Builder => $invoiceQuery
                    ->whereDate(
                        'invoice_date',
                        '>=',
                        $invoiceDateFrom,
                    ),
            )
            ->when(
                $invoiceDateTo !== '',
                static fn (
                    Builder $invoiceQuery,
                ): Builder => $invoiceQuery
                    ->whereDate(
                        'invoice_date',
                        '<=',
                        $invoiceDateTo,
                    ),
            )
            ->when(
                $dueDateFrom !== '',
                static fn (
                    Builder $invoiceQuery,
                ): Builder => $invoiceQuery
                    ->whereDate(
                        'due_date',
                        '>=',
                        $dueDateFrom,
                    ),
            )
            ->when(
                $dueDateTo !== '',
                static fn (
                    Builder $invoiceQuery,
                ): Builder => $invoiceQuery
                    ->whereDate(
                        'due_date',
                        '<=',
                        $dueDateTo,
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
            'SupplierInvoices/Index',
            [
                'supplierInvoices' => [
                    'data' => $supplierInvoices
                        ->getCollection()
                        ->map(
                            fn (
                                SupplierInvoice $invoice,
                            ): array => $this
                                ->summaryData(
                                    supplierInvoice:
                                        $invoice,
                                    actor: $actor,
                                ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $supplierInvoices
                                ->currentPage(),

                        'last_page' =>
                            $supplierInvoices
                                ->lastPage(),

                        'per_page' =>
                            $supplierInvoices
                                ->perPage(),

                        'from' =>
                            $supplierInvoices
                                ->firstItem(),

                        'to' =>
                            $supplierInvoices
                                ->lastItem(),

                        'total' =>
                            $supplierInvoices
                                ->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'branch_id' => $branchId,
                    'supplier_id' => $supplierId,
                    'purchase_order_id' =>
                        $purchaseOrderId,
                    'status' => $status,
                    'match_status' => $matchStatus,
                    'invoice_date_from' =>
                        $invoiceDateFrom,
                    'invoice_date_to' =>
                        $invoiceDateTo,
                    'due_date_from' =>
                        $dueDateFrom,
                    'due_date_to' =>
                        $dueDateTo,
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
                        SupplierInvoice::class,
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
            SupplierInvoice::class,
        );

        $actor = $this->actor($request);

        return Inertia::render(
            'SupplierInvoices/Create',
            $this->formOptions(
                actor: $actor,
                selectedPurchaseOrderId:
                    $this->queryId(
                        request: $request,
                        field:
                            'purchase_order_id',
                    ),
            ),
        );
    }

    public function store(
        StoreSupplierInvoiceRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            SupplierInvoice::class,
        );

        $supplierInvoice =
            $this->supplierInvoiceService
                ->create(
                    data:
                        $request->validated(),
                    actor:
                        $this->actor($request),
                );

        return $this->responseService
            ->success(
                message:
                    'Supplier Invoice created successfully.',

                data:
                    $this->responseData(
                        $supplierInvoice,
                    ),

                redirectTo: route(
                    'supplier-invoices.show',
                    $supplierInvoice,
                ),
            );
    }

    public function show(
        Request $request,
        SupplierInvoice $supplierInvoice,
    ): Response {
        Gate::authorize(
            'view',
            $supplierInvoice,
        );

        $actor = $this->actor($request);

        $supplierInvoice->load([
            'branch:id,name,code,status,address',
            'supplier:id,name,code,status',
            'purchaseOrder:id,document_number,status,order_date,expected_delivery_date,currency_code,total_amount',
            'documentNumberAllocation',
            'lines.purchaseOrderLine',
            'lines.product:id,name,sku,status',
            'lines.unit:id,name,code,symbol,status',
            'lines.matches.goodsReceipt:id,receipt_number,receipt_date,status',
            'lines.matches.goodsReceiptLine',
            'createdBy:id,name',
            'validatedBy:id,name',
            'approvedBy:id,name',
            'disputedBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'cancelledBy:id,name',
        ]);

        return Inertia::render(
            'SupplierInvoices/Show',
            [
                'supplierInvoice' =>
                    $this->detailData(
                        supplierInvoice:
                            $supplierInvoice,
                        actor: $actor,
                    ),
            ],
        );
    }

    public function edit(
        Request $request,
        SupplierInvoice $supplierInvoice,
    ): Response {
        Gate::authorize(
            'update',
            $supplierInvoice,
        );

        $actor = $this->actor($request);

        $supplierInvoice->load([
            'lines.matches',
        ]);

        return Inertia::render(
            'SupplierInvoices/Edit',
            [
                ...$this->formOptions(
                    actor: $actor,
                    selectedPurchaseOrderId:
                        (int) $supplierInvoice
                            ->purchase_order_id,
                ),

                'supplierInvoice' =>
                    $this->formData(
                        $supplierInvoice,
                    ),
            ],
        );
    }

    public function update(
        UpdateSupplierInvoiceRequest $request,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $supplierInvoice,
        );

        $supplierInvoice =
            $this->supplierInvoiceService
                ->update(
                    supplierInvoice:
                        $supplierInvoice,
                    data:
                        $request->validated(),
                    actor:
                        $this->actor($request),
                );

        return $this->responseService
            ->success(
                message:
                    'Supplier Invoice updated successfully.',

                data:
                    $this->responseData(
                        $supplierInvoice,
                    ),

                redirectTo: route(
                    'supplier-invoices.show',
                    $supplierInvoice,
                ),
            );
    }

    public function destroy(
        Request $request,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $supplierInvoice,
        );

        $this->supplierInvoiceService
            ->delete(
                supplierInvoice:
                    $supplierInvoice,
                actor:
                    $this->actor($request),
            );

        return $this->responseService
            ->success(
                message:
                    'Supplier Invoice deleted successfully.',

                redirectTo: route(
                    'supplier-invoices.index',
                ),
            );
    }

    public function validateInvoice(
        Request $request,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'validate',
            $supplierInvoice,
        );

        $supplierInvoice =
            $this->supplierInvoiceService
                ->validate(
                    supplierInvoice:
                        $supplierInvoice,
                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            supplierInvoice:
                $supplierInvoice,
            message:
                'Supplier Invoice validated successfully.',
        );
    }

    public function returnToDraft(
        Request $request,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'returnToDraft',
            $supplierInvoice,
        );

        $supplierInvoice =
            $this->supplierInvoiceService
                ->returnToDraft(
                    supplierInvoice:
                        $supplierInvoice,
                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            supplierInvoice:
                $supplierInvoice,
            message:
                'Supplier Invoice returned to draft successfully.',
        );
    }

    public function approve(
        Request $request,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'approve',
            $supplierInvoice,
        );

        $supplierInvoice =
            $this->supplierInvoiceService
                ->approve(
                    supplierInvoice:
                        $supplierInvoice,
                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            supplierInvoice:
                $supplierInvoice,
            message:
                'Supplier Invoice approved successfully.',
        );
    }

    public function dispute(
        DisputeSupplierInvoiceRequest $request,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'dispute',
            $supplierInvoice,
        );

        $supplierInvoice =
            $this->supplierInvoiceService
                ->dispute(
                    supplierInvoice:
                        $supplierInvoice,

                    reason: (string) $request
                        ->validated(
                            'dispute_reason',
                        ),

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            supplierInvoice:
                $supplierInvoice,
            message:
                'Supplier Invoice marked as disputed.',
        );
    }

    public function cancel(
        CancelSupplierInvoiceRequest $request,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'cancel',
            $supplierInvoice,
        );

        $supplierInvoice =
            $this->supplierInvoiceService
                ->cancel(
                    supplierInvoice:
                        $supplierInvoice,

                    reason: (string) $request
                        ->validated(
                            'cancellation_reason',
                        ),

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            supplierInvoice:
                $supplierInvoice,
            message:
                'Supplier Invoice cancelled successfully.',
        );
    }

    public function post(
        Request $request,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'post',
            $supplierInvoice,
        );

        $supplierInvoice =
            $this->supplierInvoiceService
                ->post(
                    supplierInvoice:
                        $supplierInvoice,
                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            supplierInvoice:
                $supplierInvoice,
            message:
                'Supplier Invoice posted successfully.',
        );
    }

    public function reverse(
        ReverseSupplierInvoiceRequest $request,
        SupplierInvoice $supplierInvoice,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'reverse',
            $supplierInvoice,
        );

        $supplierInvoice =
            $this->supplierInvoiceService
                ->reverse(
                    supplierInvoice:
                        $supplierInvoice,

                    reason: (string) $request
                        ->validated(
                            'reversal_reason',
                        ),

                    reversalPostingDate:
                        (string) $request
                            ->validated(
                                'reversal_posting_date',
                            ),

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            supplierInvoice:
                $supplierInvoice,
            message:
                'Supplier Invoice reversed successfully.',
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

        $suppliers = Supplier::query()
            ->whereHas(
                'supplierInvoices',
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
                    'supplierInvoices',
                )
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->get([
                    'id',
                    'branch_id',
                    'supplier_id',
                    'document_number',
                    'status',
                ]);

        return [
            'branches' => $branches
                ->map(
                    static fn (
                        $branch,
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

            'statuses' =>
                $this->statusRegistry
                    ->options(),

            'matchStatuses' =>
                $this->matchStatusRegistry
                    ->options(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(
        User $actor,
        ?int $selectedPurchaseOrderId,
    ): array {
        $branches =
            $this->branchAccessService
                ->accessibleBranches(
                    user: $actor,
                    activeOnly: true,
                );

        $query = PurchaseOrder::query()
            ->with([
                'branch:id,name,code,status',
                'supplier:id,name,code,status,payment_terms_days',
                'lines.product:id,name,sku,status',
                'lines.unit:id,name,code,symbol,status,allow_decimal,decimal_places',
                'lines.goodsReceiptLines.goodsReceipt:id,receipt_number,receipt_date,status',
            ])
            ->whereIn(
                'status',
                [
                    'approved',
                    'partially_received',
                    'received',
                    'closed',
                ],
            );

        $this->branchAccessService
            ->scopeQuery(
                query: $query,
                user: $actor,
                branchColumn:
                    'purchase_orders.branch_id',
            );

        $purchaseOrders = $query
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->get()
            ->filter(
                fn (
                    PurchaseOrder $purchaseOrder,
                ): bool => $this
                    ->purchaseOrderHasInvoiceAvailability(
                        $purchaseOrder,
                    )
                    || $selectedPurchaseOrderId
                        === (int) $purchaseOrder
                            ->getKey(),
            )
            ->values();

            $tenant = $this->tenantContext->tenant();

$today = now(
    $tenant->timezone,
)->toDateString();

        return [
    'branches' => $branches
        ->map(
            static fn (
                $branch,
            ): array => [
                'id' =>
                    (int) $branch
                        ->getKey(),

                'name' =>
                    $branch->name,

                'code' =>
                    $branch->code,
            ],
        )
        ->values()
        ->all(),

    'purchaseOrders' =>
        $purchaseOrders
            ->map(
                fn (
                    PurchaseOrder $purchaseOrder,
                ): array => $this
                    ->purchaseOrderOption(
                        $purchaseOrder,
                    ),
            )
            ->all(),

    'selectedPurchaseOrderId' =>
        $selectedPurchaseOrderId,

    'matchStatuses' =>
        $this->matchStatusRegistry
            ->options(),

    'defaults' => [
        'invoice_date' => $today,
        'posting_date' => $today,
        'other_charges' => '0.000000',
        'rounding_adjustment' => '0.000000',
    ],
];
    }

    private function purchaseOrderHasInvoiceAvailability(
        PurchaseOrder $purchaseOrder,
    ): bool {
        foreach (
            $purchaseOrder->lines
            as $line
        ) {
            if (
                !$line
                    instanceof PurchaseOrderLine
            ) {
                continue;
            }

            foreach (
                $line->goodsReceiptLines
                as $receiptLine
            ) {
                if (
                    !$receiptLine
                        instanceof GoodsReceiptLine
                ) {
                    continue;
                }

                if (
                    $receiptLine
                        ->goodsReceipt
                        ?->status
                    !== 'posted'
                ) {
                    continue;
                }

                if (
                    BigDecimal::of(
                        (string) $receiptLine
                            ->accepted_quantity,
                    )->isGreaterThan(
                        BigDecimal::of(
                            (string) $receiptLine
                                ->invoiced_quantity,
                        ),
                    )
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function purchaseOrderOption(
        PurchaseOrder $purchaseOrder,
    ): array {
        return [
            'id' =>
                (int) $purchaseOrder
                    ->getKey(),

            'branch_id' =>
                (int) $purchaseOrder
                    ->branch_id,

            'branch_name' =>
                $purchaseOrder
                    ->branch
                    ->name,

            'supplier_id' =>
                (int) $purchaseOrder
                    ->supplier_id,

            'supplier_name' =>
                $purchaseOrder
                    ->supplier_name,

            'supplier_code' =>
                $purchaseOrder
                    ->supplier_code,

            'document_number' =>
                $purchaseOrder
                    ->document_number,

            'order_date' =>
                $purchaseOrder
                    ->order_date
                    ?->format('Y-m-d'),

            'status' =>
                $purchaseOrder->status,

            'currency_code' =>
                $purchaseOrder
                    ->currency_code,

            'exchange_rate' =>
                (string) $purchaseOrder
                    ->exchange_rate,

            'payment_terms_days' =>
                (int) $purchaseOrder
                    ->supplier
                    ->payment_terms_days,

            'lines' =>
                $purchaseOrder
                    ->lines
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
        $receiptOptions = [];
        $receivedQuantity =
            BigDecimal::zero();
        $invoicedQuantity =
            BigDecimal::zero();

        foreach (
            $line->goodsReceiptLines
            as $receiptLine
        ) {
            if (
                !$receiptLine
                    instanceof GoodsReceiptLine
            ) {
                continue;
            }

            if (
                $receiptLine
                    ->goodsReceipt
                    ?->status
                !== 'posted'
            ) {
                continue;
            }

            $accepted = BigDecimal::of(
                (string) $receiptLine
                    ->accepted_quantity,
            );

            $invoiced = BigDecimal::of(
                (string) $receiptLine
                    ->invoiced_quantity,
            );

            $available =
                $accepted->minus($invoiced);

            $receivedQuantity =
                $receivedQuantity
                    ->plus($accepted);

            $invoicedQuantity =
                $invoicedQuantity
                    ->plus($invoiced);

            if (!$available->isPositive()) {
                continue;
            }

            $receiptOptions[] = [
                'goods_receipt_id' =>
                    (int) $receiptLine
                        ->goods_receipt_id,

                'goods_receipt_line_id' =>
                    (int) $receiptLine
                        ->getKey(),

                'receipt_number' =>
                    $receiptLine
                        ->goodsReceipt
                        ?->receipt_number,

                'receipt_date' =>
                    $receiptLine
                        ->goodsReceipt
                        ?->receipt_date
                        ?->format('Y-m-d'),

                'accepted_quantity' =>
                    $accepted
                        ->toScale(
                            6,
                            RoundingMode::HALF_UP,
                        )
                        ->__toString(),

                'invoiced_quantity' =>
                    $invoiced
                        ->toScale(
                            6,
                            RoundingMode::HALF_UP,
                        )
                        ->__toString(),

                'available_quantity' =>
                    $available
                        ->toScale(
                            6,
                            RoundingMode::HALF_UP,
                        )
                        ->__toString(),
            ];
        }

        return [
            'id' =>
                (int) $line->getKey(),

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

            'description' =>
                $line->description,

            'ordered_quantity' =>
                (string) $line
                    ->ordered_quantity,

            'received_quantity' =>
                $receivedQuantity
                    ->toScale(
                        6,
                        RoundingMode::HALF_UP,
                    )
                    ->__toString(),

            'previously_invoiced_quantity' =>
                $invoicedQuantity
                    ->toScale(
                        6,
                        RoundingMode::HALF_UP,
                    )
                    ->__toString(),

            'available_to_invoice_quantity' =>
                $receivedQuantity
                    ->minus(
                        $invoicedQuantity,
                    )
                    ->toScale(
                        6,
                        RoundingMode::HALF_UP,
                    )
                    ->__toString(),

            'unit_price' =>
                (string) $line->unit_price,

            'discount_amount' =>
                (string) $line
                    ->discount_amount,

            'tax_rate' =>
                (string) $line->tax_rate,

            'goods_receipt_lines' =>
                $receiptOptions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryData(
        SupplierInvoice $supplierInvoice,
        User $actor,
    ): array {
        return [
            'id' =>
                (int) $supplierInvoice
                    ->getKey(),

            'document_number' =>
                $supplierInvoice
                    ->document_number,

            'supplier_invoice_number' =>
                $supplierInvoice
                    ->supplier_invoice_number,

            'invoice_date' =>
                $supplierInvoice
                    ->invoice_date
                    ?->format('Y-m-d'),

            'posting_date' =>
                $supplierInvoice
                    ->posting_date
                    ?->format('Y-m-d'),

            'due_date' =>
                $supplierInvoice
                    ->due_date
                    ?->format('Y-m-d'),

            'purchase_order_number' =>
                $supplierInvoice
                    ->purchase_order_number,

            'branch' => [
                'id' =>
                    (int) $supplierInvoice
                        ->branch
                        ->getKey(),

                'name' =>
                    $supplierInvoice
                        ->branch
                        ->name,

                'code' =>
                    $supplierInvoice
                        ->branch
                        ->code,
            ],

            'supplier' => [
                'id' =>
                    (int) $supplierInvoice
                        ->supplier
                        ->getKey(),

                'name' =>
                    $supplierInvoice
                        ->supplier_name,

                'code' =>
                    $supplierInvoice
                        ->supplier_code,
            ],

            'currency_code' =>
                $supplierInvoice
                    ->currency_code,

            'total_amount' =>
                (string) $supplierInvoice
                    ->total_amount,

            'status' =>
                $supplierInvoice->status,

            'status_label' =>
                $this->statusRegistry
                    ->label(
                        $supplierInvoice
                            ->status,
                    ),

            'match_status' =>
                $supplierInvoice
                    ->match_status,

            'match_status_label' =>
                $this->matchStatusRegistry
                    ->label(
                        $supplierInvoice
                            ->match_status,
                    ),

            'created_at' =>
                $supplierInvoice
                    ->created_at
                    ?->toIso8601String(),

            'created_by' =>
                $this->userData(
                    $supplierInvoice
                        ->createdBy,
                ),

            'can' =>
                $this->actionPermissions(
                    actor: $actor,
                    supplierInvoice:
                        $supplierInvoice,
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(
        SupplierInvoice $supplierInvoice,
        User $actor,
    ): array {
        return [
            ...$this->summaryData(
                $supplierInvoice,
                $actor,
            ),

            'purchase_order_id' =>
                (int) $supplierInvoice
                    ->purchase_order_id,

            'supplier_id' =>
                (int) $supplierInvoice
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

            'branch_id' =>
                (int) $supplierInvoice
                    ->branch_id,

            'exchange_rate' =>
                (string) $supplierInvoice
                    ->exchange_rate,

            'subtotal_amount' =>
                (string) $supplierInvoice
                    ->subtotal_amount,

            'discount_amount' =>
                (string) $supplierInvoice
                    ->discount_amount,

            'tax_amount' =>
                (string) $supplierInvoice
                    ->tax_amount,

            'other_charges' =>
                (string) $supplierInvoice
                    ->other_charges,

            'rounding_adjustment' =>
                (string) $supplierInvoice
                    ->rounding_adjustment,

            'quantity_variance' =>
                (string) $supplierInvoice
                    ->quantity_variance,

            'price_variance_amount' =>
                (string) $supplierInvoice
                    ->price_variance_amount,

            'discount_variance_amount' =>
                (string) $supplierInvoice
                    ->discount_variance_amount,

            'tax_variance_amount' =>
                (string) $supplierInvoice
                    ->tax_variance_amount,

            'total_variance_amount' =>
                (string) $supplierInvoice
                    ->total_variance_amount,

            'notes' =>
                $supplierInvoice->notes,

            'matching_notes' =>
                $supplierInvoice
                    ->matching_notes,

            'revision' =>
                (int) $supplierInvoice
                    ->revision,

            'matching_reserved_at' =>
                $supplierInvoice
                    ->matching_reserved_at
                    ?->toIso8601String(),

            'validated_at' =>
                $supplierInvoice
                    ->validated_at
                    ?->toIso8601String(),

            'approved_at' =>
                $supplierInvoice
                    ->approved_at
                    ?->toIso8601String(),

            'disputed_at' =>
                $supplierInvoice
                    ->disputed_at
                    ?->toIso8601String(),

            'dispute_reason' =>
                $supplierInvoice
                    ->dispute_reason,

            'posted_at' =>
                $supplierInvoice
                    ->posted_at
                    ?->toIso8601String(),

            'accounting_posting_reference' =>
                $supplierInvoice
                    ->accounting_posting_reference,

            'reversal_posting_date' =>
                $supplierInvoice
                    ->reversal_posting_date
                    ?->format('Y-m-d'),

            'reversed_at' =>
                $supplierInvoice
                    ->reversed_at
                    ?->toIso8601String(),

            'reversal_reason' =>
                $supplierInvoice
                    ->reversal_reason,

            'accounting_reversal_reference' =>
                $supplierInvoice
                    ->accounting_reversal_reference,

            'cancelled_at' =>
                $supplierInvoice
                    ->cancelled_at
                    ?->toIso8601String(),

            'cancellation_reason' =>
                $supplierInvoice
                    ->cancellation_reason,

            'validated_by' =>
                $this->userData(
                    $supplierInvoice
                        ->validatedBy,
                ),

            'approved_by' =>
                $this->userData(
                    $supplierInvoice
                        ->approvedBy,
                ),

            'disputed_by' =>
                $this->userData(
                    $supplierInvoice
                        ->disputedBy,
                ),

            'posted_by' =>
                $this->userData(
                    $supplierInvoice
                        ->postedBy,
                ),

            'reversed_by' =>
                $this->userData(
                    $supplierInvoice
                        ->reversedBy,
                ),

            'cancelled_by' =>
                $this->userData(
                    $supplierInvoice
                        ->cancelledBy,
                ),

            'lines' =>
                $supplierInvoice
                    ->lines
                    ->map(
                        fn (
                            SupplierInvoiceLine $line,
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
        SupplierInvoice $supplierInvoice,
    ): array {
        return [
            'id' =>
                (int) $supplierInvoice
                    ->getKey(),

            'purchase_order_id' =>
                (int) $supplierInvoice
                    ->purchase_order_id,

            'supplier_invoice_number' =>
                $supplierInvoice
                    ->supplier_invoice_number,

            'invoice_date' =>
                $supplierInvoice
                    ->invoice_date
                    ?->format('Y-m-d'),

            'posting_date' =>
                $supplierInvoice
                    ->posting_date
                    ?->format('Y-m-d'),

            'due_date' =>
                $supplierInvoice
                    ->due_date
                    ?->format('Y-m-d'),

            'currency_code' =>
                $supplierInvoice
                    ->currency_code,

            'exchange_rate' =>
                (string) $supplierInvoice
                    ->exchange_rate,

            'other_charges' =>
                (string) $supplierInvoice
                    ->other_charges,

            'rounding_adjustment' =>
                (string) $supplierInvoice
                    ->rounding_adjustment,

            'notes' =>
                $supplierInvoice->notes,

            'matching_notes' =>
                $supplierInvoice
                    ->matching_notes,

            'lines' =>
                $supplierInvoice
                    ->lines
                    ->map(
                        static fn (
                            SupplierInvoiceLine $line,
                        ): array => [
                            'purchase_order_line_id' =>
                                (int) $line
                                    ->purchase_order_line_id,

                            'invoiced_quantity' =>
                                (string) $line
                                    ->invoiced_quantity,

                            'invoice_unit_price' =>
                                (string) $line
                                    ->invoice_unit_price,

                            'discount_amount' =>
                                (string) $line
                                    ->discount_amount,

                            'tax_rate' =>
                                (string) $line
                                    ->tax_rate,

                            'variance_reason' =>
                                $line
                                    ->variance_reason,

                            'matches' =>
                                $line
                                    ->matches
                                    ->map(
                                        static fn (
                                            SupplierInvoiceMatch $match,
                                        ): array => [
                                            'goods_receipt_line_id' =>
                                                (int) $match
                                                    ->goods_receipt_line_id,

                                            'matched_quantity' =>
                                                (string) $match
                                                    ->matched_quantity,
                                        ],
                                    )
                                    ->values()
                                    ->all(),
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
        SupplierInvoiceLine $line,
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

            'description' =>
                $line->description,

            'ordered_quantity_snapshot' =>
                (string) $line
                    ->ordered_quantity_snapshot,

            'received_quantity_snapshot' =>
                (string) $line
                    ->received_quantity_snapshot,

            'previously_invoiced_quantity_snapshot' =>
                (string) $line
                    ->previously_invoiced_quantity_snapshot,

            'available_to_invoice_quantity_snapshot' =>
                (string) $line
                    ->available_to_invoice_quantity_snapshot,

            'invoiced_quantity' =>
                (string) $line
                    ->invoiced_quantity,

            'matched_quantity' =>
                (string) $line
                    ->matched_quantity,

            'purchase_order_unit_price_snapshot' =>
                (string) $line
                    ->purchase_order_unit_price_snapshot,

            'invoice_unit_price' =>
                (string) $line
                    ->invoice_unit_price,

            'gross_amount' =>
                (string) $line
                    ->gross_amount,

            'discount_amount' =>
                (string) $line
                    ->discount_amount,

            'net_amount' =>
                (string) $line
                    ->net_amount,

            'tax_rate' =>
                (string) $line
                    ->tax_rate,

            'tax_amount' =>
                (string) $line
                    ->tax_amount,

            'line_total' =>
                (string) $line
                    ->line_total,

            'quantity_variance' =>
                (string) $line
                    ->quantity_variance,

            'price_variance_amount' =>
                (string) $line
                    ->price_variance_amount,

            'discount_variance_amount' =>
                (string) $line
                    ->discount_variance_amount,

            'tax_variance_amount' =>
                (string) $line
                    ->tax_variance_amount,

            'total_variance_amount' =>
                (string) $line
                    ->total_variance_amount,

            'match_status' =>
                $line->match_status,

            'match_status_label' =>
                $this->matchStatusRegistry
                    ->label(
                        $line->match_status,
                    ),

            'variance_reason' =>
                $line->variance_reason,

            'matches' =>
                $line
                    ->matches
                    ->map(
                        fn (
                            SupplierInvoiceMatch $match,
                        ): array => $this
                            ->matchData($match),
                    )
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function matchData(
        SupplierInvoiceMatch $match,
    ): array {
        return [
            'id' =>
                (int) $match->getKey(),

            'goods_receipt_id' =>
                (int) $match
                    ->goods_receipt_id,

            'goods_receipt_line_id' =>
                (int) $match
                    ->goods_receipt_line_id,

            'receipt_number' =>
                $match
                    ->goodsReceipt
                    ?->receipt_number,

            'receipt_date' =>
                $match
                    ->goodsReceipt
                    ?->receipt_date
                    ?->format('Y-m-d'),

            'matched_quantity' =>
                (string) $match
                    ->matched_quantity,

            'receipt_accepted_quantity_snapshot' =>
                (string) $match
                    ->receipt_accepted_quantity_snapshot,

            'previously_invoiced_quantity_snapshot' =>
                (string) $match
                    ->previously_invoiced_quantity_snapshot,

            'available_quantity_snapshot' =>
                (string) $match
                    ->available_quantity_snapshot,

            'purchase_order_unit_price_snapshot' =>
                (string) $match
                    ->purchase_order_unit_price_snapshot,

            'invoice_unit_price_snapshot' =>
                (string) $match
                    ->invoice_unit_price_snapshot,

            'price_variance_per_unit' =>
                (string) $match
                    ->price_variance_per_unit,

            'price_variance_amount' =>
                (string) $match
                    ->price_variance_amount,

            'matched_amount' =>
                (string) $match
                    ->matched_amount,
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function actionPermissions(
        User $actor,
        SupplierInvoice $supplierInvoice,
    ): array {
        return [
            'view' =>
                $actor->can(
                    'view',
                    $supplierInvoice,
                ),

            'update' =>
                $actor->can(
                    'update',
                    $supplierInvoice,
                ),

            'delete' =>
                $actor->can(
                    'delete',
                    $supplierInvoice,
                ),

            'validate' =>
                $actor->can(
                    'validate',
                    $supplierInvoice,
                ),

            'return_to_draft' =>
                $actor->can(
                    'returnToDraft',
                    $supplierInvoice,
                ),

            'approve' =>
                $actor->can(
                    'approve',
                    $supplierInvoice,
                ),

            'dispute' =>
                $actor->can(
                    'dispute',
                    $supplierInvoice,
                ),

            'cancel' =>
                $actor->can(
                    'cancel',
                    $supplierInvoice,
                ),

            'post' =>
                $actor->can(
                    'post',
                    $supplierInvoice,
                ),

            'reverse' =>
                $actor->can(
                    'reverse',
                    $supplierInvoice,
                ),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function responseData(
        SupplierInvoice $supplierInvoice,
    ): array {
        return [
            'id' =>
                (int) $supplierInvoice
                    ->getKey(),

            'status' =>
                $supplierInvoice->status,

            'match_status' =>
                $supplierInvoice
                    ->match_status,

            'document_number' =>
                $supplierInvoice
                    ->document_number,

            'supplier_invoice_number' =>
                $supplierInvoice
                    ->supplier_invoice_number,
        ];
    }

    private function workflowResponse(
        SupplierInvoice $supplierInvoice,
        string $message,
    ): JsonResponse|RedirectResponse {
        return $this->responseService
            ->success(
                message: $message,

                data:
                    $this->responseData(
                        $supplierInvoice,
                    ),

                redirectTo: route(
                    'supplier-invoices.show',
                    $supplierInvoice,
                ),
            );
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
}