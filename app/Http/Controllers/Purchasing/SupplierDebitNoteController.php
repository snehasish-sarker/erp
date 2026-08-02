<?php

declare(strict_types=1);

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\CancelSupplierDebitNoteRequest;
use App\Http\Requests\Purchasing\IndexSupplierDebitNoteRequest;
use App\Http\Requests\Purchasing\ReverseSupplierDebitNoteRequest;
use App\Http\Requests\Purchasing\StoreSupplierDebitNoteRequest;
use App\Http\Requests\Purchasing\UpdateSupplierDebitNoteRequest;
use App\Models\Branch;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnLine;
use App\Models\Supplier;
use App\Models\SupplierDebitNote;
use App\Models\SupplierDebitNoteAllocation;
use App\Models\SupplierDebitNoteLine;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use App\Models\User;
use App\Services\Organisation\BranchAccessService;
use App\Services\Purchasing\SupplierDebitNoteService;
use App\Support\Purchasing\SupplierDebitNoteStatusRegistry;
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

final class SupplierDebitNoteController extends Controller
{
    private const SCALE = 6;

    public function __construct(
        private readonly SupplierDebitNoteService $supplierDebitNoteService,
        private readonly SupplierDebitNoteStatusRegistry $statusRegistry,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexSupplierDebitNoteRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            SupplierDebitNote::class,
        );

        $actor = $this->actor($request);
        $validated = $request->validated();

        $search = (string) (
            $validated['search'] ?? ''
        );

        $branchId = $this->validatedId(
            validated: $validated,
            field: 'branch_id',
        );

        $supplierId = $this->validatedId(
            validated: $validated,
            field: 'supplier_id',
        );

        $purchaseReturnId = $this->validatedId(
            validated: $validated,
            field: 'purchase_return_id',
        );

        $supplierInvoiceId = $this->validatedId(
            validated: $validated,
            field: 'supplier_invoice_id',
        );

        $purchaseOrderId = $this->validatedId(
            validated: $validated,
            field: 'purchase_order_id',
        );

        $goodsReceiptId = $this->validatedId(
            validated: $validated,
            field: 'goods_receipt_id',
        );

        $status = (string) (
            $validated['status'] ?? ''
        );

        $debitNoteDateFrom = (string) (
            $validated['debit_note_date_from']
                ?? ''
        );

        $debitNoteDateTo = (string) (
            $validated['debit_note_date_to']
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

        $query = SupplierDebitNote::query()
            ->with([
                'branch:id,name,code,status',
                'supplier:id,name,code,status',
                'purchaseReturn:id,return_number,status',
                'supplierInvoice:id,document_number,supplier_invoice_number,status',
                'purchaseOrder:id,document_number,status',
                'goodsReceipt:id,receipt_number,status',
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
                    'supplier_debit_notes.branch_id',
            );

        $supplierDebitNotes = $query
            ->when(
                $search !== '',
                static function (
                    Builder $debitNoteQuery,
                ) use ($search): void {
                    $debitNoteQuery->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'debit_note_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'purchase_return_number',
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
                                    'goods_receipt_number',
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
                                    'reason',
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
                    Builder $debitNoteQuery,
                ): Builder => $debitNoteQuery
                    ->where(
                        'branch_id',
                        $branchId,
                    ),
            )
            ->when(
                $supplierId !== null,
                static fn (
                    Builder $debitNoteQuery,
                ): Builder => $debitNoteQuery
                    ->where(
                        'supplier_id',
                        $supplierId,
                    ),
            )
            ->when(
                $purchaseReturnId !== null,
                static fn (
                    Builder $debitNoteQuery,
                ): Builder => $debitNoteQuery
                    ->where(
                        'purchase_return_id',
                        $purchaseReturnId,
                    ),
            )
            ->when(
                $supplierInvoiceId !== null,
                static fn (
                    Builder $debitNoteQuery,
                ): Builder => $debitNoteQuery
                    ->where(
                        'supplier_invoice_id',
                        $supplierInvoiceId,
                    ),
            )
            ->when(
                $purchaseOrderId !== null,
                static fn (
                    Builder $debitNoteQuery,
                ): Builder => $debitNoteQuery
                    ->where(
                        'purchase_order_id',
                        $purchaseOrderId,
                    ),
            )
            ->when(
                $goodsReceiptId !== null,
                static fn (
                    Builder $debitNoteQuery,
                ): Builder => $debitNoteQuery
                    ->where(
                        'goods_receipt_id',
                        $goodsReceiptId,
                    ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $debitNoteQuery,
                ): Builder => $debitNoteQuery
                    ->where(
                        'status',
                        $status,
                    ),
            )
            ->when(
                $debitNoteDateFrom !== '',
                static fn (
                    Builder $debitNoteQuery,
                ): Builder => $debitNoteQuery
                    ->whereDate(
                        'debit_note_date',
                        '>=',
                        $debitNoteDateFrom,
                    ),
            )
            ->when(
                $debitNoteDateTo !== '',
                static fn (
                    Builder $debitNoteQuery,
                ): Builder => $debitNoteQuery
                    ->whereDate(
                        'debit_note_date',
                        '<=',
                        $debitNoteDateTo,
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
            'SupplierDebitNotes/Index',
            [
                'supplierDebitNotes' => [
                    'data' => $supplierDebitNotes
                        ->getCollection()
                        ->map(
                            fn (
                                SupplierDebitNote $supplierDebitNote,
                            ): array => $this->summaryData(
                                supplierDebitNote:
                                    $supplierDebitNote,
                                actor: $actor,
                            ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $supplierDebitNotes
                                ->currentPage(),

                        'last_page' =>
                            $supplierDebitNotes
                                ->lastPage(),

                        'per_page' =>
                            $supplierDebitNotes
                                ->perPage(),

                        'from' =>
                            $supplierDebitNotes
                                ->firstItem(),

                        'to' =>
                            $supplierDebitNotes
                                ->lastItem(),

                        'total' =>
                            $supplierDebitNotes
                                ->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'branch_id' => $branchId,
                    'supplier_id' => $supplierId,
                    'purchase_return_id' =>
                        $purchaseReturnId,
                    'supplier_invoice_id' =>
                        $supplierInvoiceId,
                    'purchase_order_id' =>
                        $purchaseOrderId,
                    'goods_receipt_id' =>
                        $goodsReceiptId,
                    'status' => $status,
                    'debit_note_date_from' =>
                        $debitNoteDateFrom,
                    'debit_note_date_to' =>
                        $debitNoteDateTo,
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
                        SupplierDebitNote::class,
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
            SupplierDebitNote::class,
        );

        $actor = $this->actor($request);

        return Inertia::render(
            'SupplierDebitNotes/Create',
            $this->formOptions(
                actor: $actor,
                selectedPurchaseReturnId:
                    $this->queryId(
                        request: $request,
                        field:
                            'purchase_return_id',
                    ),
            ),
        );
    }

    public function store(
        StoreSupplierDebitNoteRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            SupplierDebitNote::class,
        );

        $supplierDebitNote =
            $this->supplierDebitNoteService
                ->create(
                    data:
                        $request->validated(),

                    actor:
                        $this->actor($request),
                );

        return $this->responseService
            ->success(
                message:
                    'Supplier Debit Note created successfully.',

                data:
                    $this->responseData(
                        $supplierDebitNote,
                    ),

                redirectTo: route(
                    'supplier-debit-notes.show',
                    $supplierDebitNote,
                ),
            );
    }

    public function show(
        Request $request,
        SupplierDebitNote $supplierDebitNote,
    ): Response {
        Gate::authorize(
            'view',
            $supplierDebitNote,
        );

        $actor = $this->actor($request);

        $supplierDebitNote->load([
            'purchaseReturn',
            'supplierInvoice',
            'purchaseOrder',
            'goodsReceipt',
            'branch:id,name,code,status,address',
            'supplier:id,name,code,status',
            'documentNumberAllocation',
            'lines.purchaseReturnLine',
            'lines.supplierInvoiceLine',
            'lines.product:id,name,sku,status',
            'lines.unit:id,name,code,symbol,status',
            'allocations.supplierInvoice',
            'createdBy:id,name',
            'submittedBy:id,name',
            'approvedBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'cancelledBy:id,name',
        ]);

        return Inertia::render(
            'SupplierDebitNotes/Show',
            [
                'supplierDebitNote' =>
                    $this->detailData(
                        supplierDebitNote:
                            $supplierDebitNote,
                        actor: $actor,
                    ),
            ],
        );
    }

    public function edit(
        Request $request,
        SupplierDebitNote $supplierDebitNote,
    ): Response {
        Gate::authorize(
            'update',
            $supplierDebitNote,
        );

        $actor = $this->actor($request);

        $supplierDebitNote->load([
            'lines',
        ]);

        return Inertia::render(
            'SupplierDebitNotes/Edit',
            [
                ...$this->formOptions(
                    actor: $actor,
                    selectedPurchaseReturnId:
                        (int) $supplierDebitNote
                            ->purchase_return_id,
                ),

                'supplierDebitNote' =>
                    $this->formData(
                        $supplierDebitNote,
                    ),
            ],
        );
    }

    public function update(
        UpdateSupplierDebitNoteRequest $request,
        SupplierDebitNote $supplierDebitNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $supplierDebitNote,
        );

        $supplierDebitNote =
            $this->supplierDebitNoteService
                ->update(
                    supplierDebitNote:
                        $supplierDebitNote,

                    data:
                        $request->validated(),

                    actor:
                        $this->actor($request),
                );

        return $this->responseService
            ->success(
                message:
                    'Supplier Debit Note updated successfully.',

                data:
                    $this->responseData(
                        $supplierDebitNote,
                    ),

                redirectTo: route(
                    'supplier-debit-notes.show',
                    $supplierDebitNote,
                ),
            );
    }

    public function destroy(
        Request $request,
        SupplierDebitNote $supplierDebitNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $supplierDebitNote,
        );

        $this->supplierDebitNoteService
            ->delete(
                supplierDebitNote:
                    $supplierDebitNote,

                actor:
                    $this->actor($request),
            );

        return $this->responseService
            ->success(
                message:
                    'Supplier Debit Note deleted successfully.',

                redirectTo: route(
                    'supplier-debit-notes.index',
                ),
            );
    }

    public function submit(
        Request $request,
        SupplierDebitNote $supplierDebitNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'submit',
            $supplierDebitNote,
        );

        $supplierDebitNote =
            $this->supplierDebitNoteService
                ->submit(
                    supplierDebitNote:
                        $supplierDebitNote,

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            supplierDebitNote:
                $supplierDebitNote,

            message:
                'Supplier Debit Note submitted successfully.',
        );
    }

    public function returnToDraft(
        Request $request,
        SupplierDebitNote $supplierDebitNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'returnToDraft',
            $supplierDebitNote,
        );

        $supplierDebitNote =
            $this->supplierDebitNoteService
                ->returnToDraft(
                    supplierDebitNote:
                        $supplierDebitNote,

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            supplierDebitNote:
                $supplierDebitNote,

            message:
                'Supplier Debit Note returned to draft successfully.',
        );
    }

    public function approve(
        Request $request,
        SupplierDebitNote $supplierDebitNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'approve',
            $supplierDebitNote,
        );

        $supplierDebitNote =
            $this->supplierDebitNoteService
                ->approve(
                    supplierDebitNote:
                        $supplierDebitNote,

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            supplierDebitNote:
                $supplierDebitNote,

            message:
                'Supplier Debit Note approved successfully.',
        );
    }

    public function cancel(
        CancelSupplierDebitNoteRequest $request,
        SupplierDebitNote $supplierDebitNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'cancel',
            $supplierDebitNote,
        );

        $supplierDebitNote =
            $this->supplierDebitNoteService
                ->cancel(
                    supplierDebitNote:
                        $supplierDebitNote,

                    reason:
                        (string) $request
                            ->validated(
                                'cancellation_reason',
                            ),

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            supplierDebitNote:
                $supplierDebitNote,

            message:
                'Supplier Debit Note cancelled successfully.',
        );
    }

    public function post(
        Request $request,
        SupplierDebitNote $supplierDebitNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'post',
            $supplierDebitNote,
        );

        $supplierDebitNote =
            $this->supplierDebitNoteService
                ->post(
                    supplierDebitNote:
                        $supplierDebitNote,

                    actor:
                        $this->actor($request),
                );

        return $this->workflowResponse(
            supplierDebitNote:
                $supplierDebitNote,

            message:
                'Supplier Debit Note posted successfully.',
        );
    }

    public function reverse(
        ReverseSupplierDebitNoteRequest $request,
        SupplierDebitNote $supplierDebitNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'reverse',
            $supplierDebitNote,
        );

        $supplierDebitNote =
            $this->supplierDebitNoteService
                ->reverse(
                    supplierDebitNote:
                        $supplierDebitNote,

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
            supplierDebitNote:
                $supplierDebitNote,

            message:
                'Supplier Debit Note reversed successfully.',
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
                'supplierDebitNotes',
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

        $purchaseReturns = PurchaseReturn::query()
            ->whereIn(
                'branch_id',
                $branchIds,
            )
            ->whereHas(
                'supplierDebitNote',
            )
            ->orderByDesc(
                'return_date',
            )
            ->orderByDesc('id')
            ->get([
                'id',
                'branch_id',
                'supplier_id',
                'purchase_order_id',
                'goods_receipt_id',
                'supplier_invoice_id',
                'return_number',
                'status',
            ]);

        $supplierInvoices = SupplierInvoice::query()
            ->whereIn(
                'branch_id',
                $branchIds,
            )
            ->whereHas(
                'supplierDebitNotes',
            )
            ->orderByDesc(
                'invoice_date',
            )
            ->orderByDesc('id')
            ->get([
                'id',
                'branch_id',
                'supplier_id',
                'purchase_order_id',
                'document_number',
                'supplier_invoice_number',
                'status',
            ]);

        $purchaseOrders = PurchaseOrder::query()
            ->whereIn(
                'branch_id',
                $branchIds,
            )
            ->whereHas(
                'supplierDebitNotes',
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

        $goodsReceipts = GoodsReceipt::query()
            ->whereIn(
                'branch_id',
                $branchIds,
            )
            ->whereHas(
                'supplierDebitNotes',
            )
            ->orderByDesc(
                'receipt_date',
            )
            ->orderByDesc('id')
            ->get([
                'id',
                'purchase_order_id',
                'branch_id',
                'supplier_id',
                'receipt_number',
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

            'purchaseReturns' =>
                $purchaseReturns
                    ->map(
                        static fn (
                            PurchaseReturn $purchaseReturn,
                        ): array => [
                            'id' =>
                                (int) $purchaseReturn
                                    ->getKey(),

                            'branch_id' =>
                                (int) $purchaseReturn
                                    ->branch_id,

                            'supplier_id' =>
                                (int) $purchaseReturn
                                    ->supplier_id,

                            'purchase_order_id' =>
                                (int) $purchaseReturn
                                    ->purchase_order_id,

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

                            'status' =>
                                $purchaseReturn
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

                            'branch_id' =>
                                (int) $supplierInvoice
                                    ->branch_id,

                            'supplier_id' =>
                                (int) $supplierInvoice
                                    ->supplier_id,

                            'purchase_order_id' =>
                                (int) $supplierInvoice
                                    ->purchase_order_id,

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
        ?int $selectedPurchaseReturnId,
    ): array {
        $tenant = $this->tenantContext
            ->tenant();

        $purchaseReturnQuery =
            PurchaseReturn::query()
                ->with([
                    'branch:id,name,code,status',
                    'supplier:id,name,code,status',
                    'purchaseOrder:id,document_number,status,order_date,currency_code,exchange_rate',
                    'goodsReceipt:id,receipt_number,receipt_date,status',
                    'supplierInvoice:id,document_number,supplier_invoice_number,status',
                    'lines.product:id,name,sku,status',
                    'lines.unit:id,name,code,symbol,status',
                ])
                ->where(
                    'status',
                    'posted',
                )
                ->where(
                    static function (
                        Builder $query,
                    ) use (
                        $selectedPurchaseReturnId,
                    ): void {
                        $query->whereDoesntHave(
                            'supplierDebitNote',
                        );

                        if (
                            $selectedPurchaseReturnId
                            !== null
                        ) {
                            $query->orWhereKey(
                                $selectedPurchaseReturnId,
                            );
                        }
                    },
                );

        $this->branchAccessService
            ->scopeQuery(
                query: $purchaseReturnQuery,
                user: $actor,
                branchColumn:
                    'purchase_returns.branch_id',
            );

        $purchaseReturns =
            $purchaseReturnQuery
                ->orderByDesc(
                    'return_date',
                )
                ->orderByDesc('id')
                ->get();

        $eligibleInvoices =
            $this->eligibleSupplierInvoices(
                purchaseReturns:
                    $purchaseReturns,
            );

        $today = now(
            $tenant->timezone,
        )->toDateString();

        return [
            'purchaseReturns' =>
                $purchaseReturns
                    ->map(
                        fn (
                            PurchaseReturn $purchaseReturn,
                        ): array => $this
                            ->purchaseReturnOption(
                                purchaseReturn:
                                    $purchaseReturn,

                                supplierInvoices:
                                    $this
                                        ->invoiceOptionsForReturn(
                                            purchaseReturn:
                                                $purchaseReturn,

                                            eligibleInvoices:
                                                $eligibleInvoices,
                                        ),
                            ),
                    )
                    ->values()
                    ->all(),

            'selectedPurchaseReturnId' =>
                $selectedPurchaseReturnId,

            'defaults' => [
                'debit_note_date' => $today,
                'posting_date' => $today,
            ],
        ];
    }

    /**
     * @param Collection<int, PurchaseReturn> $purchaseReturns
     * @return Collection<int, SupplierInvoice>
     */
    private function eligibleSupplierInvoices(
        Collection $purchaseReturns,
    ): Collection {
        if ($purchaseReturns->isEmpty()) {
            return collect();
        }

        $branchIds = $purchaseReturns
            ->pluck('branch_id')
            ->map(
                static fn (
                    mixed $id,
                ): int => (int) $id,
            )
            ->unique()
            ->values()
            ->all();

        $supplierIds = $purchaseReturns
            ->pluck('supplier_id')
            ->map(
                static fn (
                    mixed $id,
                ): int => (int) $id,
            )
            ->unique()
            ->values()
            ->all();

        $purchaseOrderIds = $purchaseReturns
            ->pluck('purchase_order_id')
            ->map(
                static fn (
                    mixed $id,
                ): int => (int) $id,
            )
            ->unique()
            ->values()
            ->all();

        return SupplierInvoice::query()
            ->with([
                'lines.product:id,name,sku',
                'lines.unit:id,name,code',
            ])
            ->whereIn(
                'branch_id',
                $branchIds,
            )
            ->whereIn(
                'supplier_id',
                $supplierIds,
            )
            ->whereIn(
                'purchase_order_id',
                $purchaseOrderIds,
            )
            ->whereIn(
                'status',
                [
                    'approved',
                    'posted',
                ],
            )
            ->orderByDesc(
                'invoice_date',
            )
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param Collection<int, SupplierInvoice> $eligibleInvoices
     * @return list<array<string, mixed>>
     */
    private function invoiceOptionsForReturn(
        PurchaseReturn $purchaseReturn,
        Collection $eligibleInvoices,
    ): array {
        return $eligibleInvoices
            ->filter(
                static fn (
                    SupplierInvoice $supplierInvoice,
                ): bool =>
                    (int) $supplierInvoice
                        ->branch_id
                    === (int) $purchaseReturn
                        ->branch_id
                    && (int) $supplierInvoice
                        ->supplier_id
                    === (int) $purchaseReturn
                        ->supplier_id
                    && (int) $supplierInvoice
                        ->purchase_order_id
                    === (int) $purchaseReturn
                        ->purchase_order_id,
            )
            ->sortByDesc(
                static fn (
                    SupplierInvoice $supplierInvoice,
                ): int =>
                    (int) $supplierInvoice
                        ->getKey()
                    === (int) (
                        $purchaseReturn
                            ->supplier_invoice_id
                        ?? 0
                    )
                        ? 1
                        : 0,
            )
            ->map(
                fn (
                    SupplierInvoice $supplierInvoice,
                ): array => $this
                    ->supplierInvoiceOption(
                        $supplierInvoice,
                    ),
            )
            ->values()
            ->all();
    }

    /**
     * @param list<array<string, mixed>> $supplierInvoices
     * @return array<string, mixed>
     */
    private function purchaseReturnOption(
        PurchaseReturn $purchaseReturn,
        array $supplierInvoices,
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

            'status' =>
                $purchaseReturn
                    ->status,

            'branch_id' =>
                (int) $purchaseReturn
                    ->branch_id,

            'branch_name' =>
                $purchaseReturn
                    ->branch
                    ->name,

            'branch_code' =>
                $purchaseReturn
                    ->branch
                    ->code,

            'supplier_id' =>
                (int) $purchaseReturn
                    ->supplier_id,

            'supplier_name' =>
                $purchaseReturn
                    ->supplier_name,

            'supplier_code' =>
                $purchaseReturn
                    ->supplier_code,

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

            'source_supplier_invoice_id' =>
                $purchaseReturn
                    ->supplier_invoice_id
                !== null
                    ? (int) $purchaseReturn
                        ->supplier_invoice_id
                    : null,

            'source_supplier_invoice_number' =>
                $purchaseReturn
                    ->supplier_invoice_number,

            'currency_code' =>
                $purchaseReturn
                    ->purchaseOrder
                    ->currency_code,

            'exchange_rate' =>
                (string) $purchaseReturn
                    ->purchaseOrder
                    ->exchange_rate,

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

            'supplier_invoices' =>
                $supplierInvoices,

            'lines' =>
                $purchaseReturn
                    ->lines
                    ->map(
                        fn (
                            PurchaseReturnLine $line,
                        ): array => $this
                            ->purchaseReturnLineOption(
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
    private function purchaseReturnLineOption(
        PurchaseReturnLine $line,
    ): array {
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

            'unit_id' =>
                (int) $line->unit_id,

            'unit_name' =>
                $line->unit_name,

            'unit_code' =>
                $line->unit_code,

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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function supplierInvoiceOption(
        SupplierInvoice $supplierInvoice,
    ): array {
        $available =
            $supplierInvoice
                ->availableDebitNoteAmount()
                ->toScale(
                    self::SCALE,
                    RoundingMode::HALF_UP,
                );

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

            'status' =>
                $supplierInvoice
                    ->status,

            'currency_code' =>
                $supplierInvoice
                    ->currency_code,

            'exchange_rate' =>
                (string) $supplierInvoice
                    ->exchange_rate,

            'total_amount' =>
                (string) $supplierInvoice
                    ->total_amount,

            'debit_note_reserved_amount' =>
                (string) $supplierInvoice
                    ->debit_note_reserved_amount,

            'debited_amount' =>
                (string) $supplierInvoice
                    ->debited_amount,

            'available_debit_note_amount' =>
                $available->__toString(),

            'lines' =>
                $supplierInvoice
                    ->lines
                    ->map(
                        static fn (
                            SupplierInvoiceLine $line,
                        ): array => [
                            'id' =>
                                (int) $line
                                    ->getKey(),

                            'product_id' =>
                                (int) $line
                                    ->product_id,

                            'product_name' =>
                                $line->product
                                    ->name,

                            'product_sku' =>
                                $line->product
                                    ->sku,

                            'unit_id' =>
                                (int) $line
                                    ->unit_id,

                            'unit_name' =>
                                $line->unit
                                    ->name,

                            'unit_code' =>
                                $line->unit
                                    ->code,
                        ],
                    )
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryData(
        SupplierDebitNote $supplierDebitNote,
        User $actor,
    ): array {
        return [
            'id' =>
                (int) $supplierDebitNote
                    ->getKey(),

            'debit_note_number' =>
                $supplierDebitNote
                    ->debit_note_number,

            'debit_note_date' =>
                $supplierDebitNote
                    ->debit_note_date
                    ?->format('Y-m-d'),

            'posting_date' =>
                $supplierDebitNote
                    ->posting_date
                    ?->format('Y-m-d'),

            'currency_code' =>
                $supplierDebitNote
                    ->currency_code,

            'exchange_rate' =>
                (string) $supplierDebitNote
                    ->exchange_rate,

            'purchase_return_id' =>
                (int) $supplierDebitNote
                    ->purchase_return_id,

            'purchase_return_number' =>
                $supplierDebitNote
                    ->purchase_return_number,

            'supplier_invoice_id' =>
                $supplierDebitNote
                    ->supplier_invoice_id
                !== null
                    ? (int) $supplierDebitNote
                        ->supplier_invoice_id
                    : null,

            'supplier_invoice_number' =>
                $supplierDebitNote
                    ->supplier_invoice_number,

            'purchase_order_id' =>
                (int) $supplierDebitNote
                    ->purchase_order_id,

            'purchase_order_number' =>
                $supplierDebitNote
                    ->purchase_order_number,

            'goods_receipt_id' =>
                (int) $supplierDebitNote
                    ->goods_receipt_id,

            'goods_receipt_number' =>
                $supplierDebitNote
                    ->goods_receipt_number,

            'branch' => [
                'id' =>
                    (int) $supplierDebitNote
                        ->branch
                        ->getKey(),

                'name' =>
                    $supplierDebitNote
                        ->branch
                        ->name,

                'code' =>
                    $supplierDebitNote
                        ->branch
                        ->code,
            ],

            'supplier' => [
                'id' =>
                    (int) $supplierDebitNote
                        ->supplier
                        ->getKey(),

                'name' =>
                    $supplierDebitNote
                        ->supplier_name,

                'code' =>
                    $supplierDebitNote
                        ->supplier_code,
            ],

            'gross_amount' =>
                (string) $supplierDebitNote
                    ->gross_amount,

            'discount_amount' =>
                (string) $supplierDebitNote
                    ->discount_amount,

            'subtotal' =>
                (string) $supplierDebitNote
                    ->subtotal,

            'tax_amount' =>
                (string) $supplierDebitNote
                    ->tax_amount,

            'total_amount' =>
                (string) $supplierDebitNote
                    ->total_amount,

            'allocated_amount' =>
                (string) $supplierDebitNote
                    ->allocated_amount,

            'unallocated_amount' =>
                (string) $supplierDebitNote
                    ->unallocated_amount,

            'status' =>
                $supplierDebitNote
                    ->status,

            'status_label' =>
                $this->statusRegistry
                    ->label(
                        $supplierDebitNote
                            ->status,
                    ),

            'created_at' =>
                $supplierDebitNote
                    ->created_at
                    ?->toIso8601String(),

            'created_by' =>
                $this->userData(
                    $supplierDebitNote
                        ->createdBy,
                ),

            'can' =>
                $this->actionPermissions(
                    actor: $actor,
                    supplierDebitNote:
                        $supplierDebitNote,
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(
        SupplierDebitNote $supplierDebitNote,
        User $actor,
    ): array {
        return [
            ...$this->summaryData(
                supplierDebitNote:
                    $supplierDebitNote,
                actor: $actor,
            ),

            'branch_id' =>
                (int) $supplierDebitNote
                    ->branch_id,

            'supplier_id' =>
                (int) $supplierDebitNote
                    ->supplier_id,

            'document_number_allocation_id' =>
                $supplierDebitNote
                    ->document_number_allocation_id
                !== null
                    ? (int) $supplierDebitNote
                        ->document_number_allocation_id
                    : null,

            'source_purchase_return_revision' =>
                (int) $supplierDebitNote
                    ->source_purchase_return_revision,

            'purchase_return_supplier_value' =>
                (string) $supplierDebitNote
                    ->purchase_return_supplier_value,

            'purchase_return_inventory_value' =>
                (string) $supplierDebitNote
                    ->purchase_return_inventory_value,

            'purchase_return_cost_variance' =>
                (string) $supplierDebitNote
                    ->purchase_return_cost_variance,

            'supplier_reference' =>
                $supplierDebitNote
                    ->supplier_reference,

            'reason' =>
                $supplierDebitNote
                    ->reason,

            'notes' =>
                $supplierDebitNote
                    ->notes,

            'revision' =>
                (int) $supplierDebitNote
                    ->revision,

            'submitted_at' =>
                $supplierDebitNote
                    ->submitted_at
                    ?->toIso8601String(),

            'approved_at' =>
                $supplierDebitNote
                    ->approved_at
                    ?->toIso8601String(),

            'posted_at' =>
                $supplierDebitNote
                    ->posted_at
                    ?->toIso8601String(),

            'accounting_posting_reference' =>
                $supplierDebitNote
                    ->accounting_posting_reference,

            'reversal_posting_date' =>
                $supplierDebitNote
                    ->reversal_posting_date
                    ?->format('Y-m-d'),

            'reversed_at' =>
                $supplierDebitNote
                    ->reversed_at
                    ?->toIso8601String(),

            'reversal_reason' =>
                $supplierDebitNote
                    ->reversal_reason,

            'accounting_reversal_reference' =>
                $supplierDebitNote
                    ->accounting_reversal_reference,

            'cancelled_at' =>
                $supplierDebitNote
                    ->cancelled_at
                    ?->toIso8601String(),

            'cancellation_reason' =>
                $supplierDebitNote
                    ->cancellation_reason,

            'submitted_by' =>
                $this->userData(
                    $supplierDebitNote
                        ->submittedBy,
                ),

            'approved_by' =>
                $this->userData(
                    $supplierDebitNote
                        ->approvedBy,
                ),

            'posted_by' =>
                $this->userData(
                    $supplierDebitNote
                        ->postedBy,
                ),

            'reversed_by' =>
                $this->userData(
                    $supplierDebitNote
                        ->reversedBy,
                ),

            'cancelled_by' =>
                $this->userData(
                    $supplierDebitNote
                        ->cancelledBy,
                ),

            'lines' =>
                $supplierDebitNote
                    ->lines
                    ->map(
                        fn (
                            SupplierDebitNoteLine $line,
                        ): array => $this
                            ->lineData($line),
                    )
                    ->values()
                    ->all(),

            'allocations' =>
                $supplierDebitNote
                    ->allocations
                    ->map(
                        fn (
                            SupplierDebitNoteAllocation $allocation,
                        ): array => $this
                            ->allocationData(
                                $allocation,
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
        SupplierDebitNote $supplierDebitNote,
    ): array {
        return [
            'id' =>
                (int) $supplierDebitNote
                    ->getKey(),

            'purchase_return_id' =>
                (int) $supplierDebitNote
                    ->purchase_return_id,

            'supplier_invoice_id' =>
                $supplierDebitNote
                    ->supplier_invoice_id
                !== null
                    ? (int) $supplierDebitNote
                        ->supplier_invoice_id
                    : null,

            'debit_note_number' =>
                $supplierDebitNote
                    ->debit_note_number,

            'debit_note_date' =>
                $supplierDebitNote
                    ->debit_note_date
                    ?->format('Y-m-d'),

            'posting_date' =>
                $supplierDebitNote
                    ->posting_date
                    ?->format('Y-m-d'),

            'supplier_reference' =>
                $supplierDebitNote
                    ->supplier_reference
                ?? '',

            'reason' =>
                $supplierDebitNote
                    ->reason,

            'notes' =>
                $supplierDebitNote
                    ->notes
                ?? '',

            'status' =>
                $supplierDebitNote
                    ->status,

            'lines' =>
                $supplierDebitNote
                    ->lines
                    ->map(
                        static fn (
                            SupplierDebitNoteLine $line,
                        ): array => [
                            'purchase_return_line_id' =>
                                (int) $line
                                    ->purchase_return_line_id,

                            'supplier_invoice_line_id' =>
                                $line
                                    ->supplier_invoice_line_id
                                !== null
                                    ? (int) $line
                                        ->supplier_invoice_line_id
                                    : null,

                            'return_quantity' =>
                                (string) $line
                                    ->return_quantity,

                            'unit_price' =>
                                (string) $line
                                    ->unit_price,

                            'discount_per_unit' =>
                                BigDecimal::of(
                                    (string) $line
                                        ->discount_amount,
                                )
                                    ->dividedBy(
                                        BigDecimal::of(
                                            (string) $line
                                                ->return_quantity,
                                        ),
                                        self::SCALE,
                                        RoundingMode::HALF_UP,
                                    )
                                    ->__toString(),

                            'tax_rate' =>
                                (string) $line
                                    ->tax_rate,

                            'description' =>
                                $line
                                    ->description
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
        SupplierDebitNoteLine $line,
    ): array {
        return [
            'id' =>
                (int) $line->getKey(),

            'line_number' =>
                (int) $line->line_number,

            'purchase_return_line_id' =>
                (int) $line
                    ->purchase_return_line_id,

            'supplier_invoice_line_id' =>
                $line
                    ->supplier_invoice_line_id
                !== null
                    ? (int) $line
                        ->supplier_invoice_line_id
                    : null,

            'product_id' =>
                (int) $line->product_id,

            'unit_id' =>
                (int) $line->unit_id,

            'product_name' =>
                $line->product_name,

            'product_sku' =>
                $line->product_sku,

            'unit_name' =>
                $line->unit_name,

            'unit_code' =>
                $line->unit_code,

            'return_quantity' =>
                (string) $line
                    ->return_quantity,

            'unit_price' =>
                (string) $line
                    ->unit_price,

            'gross_amount' =>
                (string) $line
                    ->gross_amount,

            'discount_amount' =>
                (string) $line
                    ->discount_amount,

            'subtotal' =>
                (string) $line
                    ->subtotal,

            'tax_rate' =>
                (string) $line
                    ->tax_rate,

            'tax_amount' =>
                (string) $line
                    ->tax_amount,

            'total_amount' =>
                (string) $line
                    ->total_amount,

            'purchase_return_supplier_unit_cost' =>
                (string) $line
                    ->purchase_return_supplier_unit_cost,

            'purchase_return_supplier_total_cost' =>
                (string) $line
                    ->purchase_return_supplier_total_cost,

            'purchase_return_inventory_unit_cost' =>
                (string) $line
                    ->purchase_return_inventory_unit_cost,

            'purchase_return_inventory_total_cost' =>
                (string) $line
                    ->purchase_return_inventory_total_cost,

            'purchase_return_cost_variance' =>
                (string) $line
                    ->purchase_return_cost_variance,

            'description' =>
                $line->description,

            'notes' =>
                $line->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function allocationData(
        SupplierDebitNoteAllocation $allocation,
    ): array {
        return [
            'id' =>
                (int) $allocation
                    ->getKey(),

            'supplier_invoice_id' =>
                (int) $allocation
                    ->supplier_invoice_id,

            'supplier_invoice_number' =>
                $allocation
                    ->supplierInvoice
                    ->supplier_invoice_number,

            'document_number' =>
                $allocation
                    ->supplierInvoice
                    ->document_number,

            'amount' =>
                (string) $allocation
                    ->amount,

            'status' =>
                $allocation->status,

            'reserved_at' =>
                $allocation
                    ->reserved_at
                    ?->toIso8601String(),

            'applied_at' =>
                $allocation
                    ->applied_at
                    ?->toIso8601String(),

            'reversed_at' =>
                $allocation
                    ->reversed_at
                    ?->toIso8601String(),

            'cancelled_at' =>
                $allocation
                    ->cancelled_at
                    ?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function actionPermissions(
        User $actor,
        SupplierDebitNote $supplierDebitNote,
    ): array {
        return [
            'view' =>
                $actor->can(
                    'view',
                    $supplierDebitNote,
                ),

            'update' =>
                $actor->can(
                    'update',
                    $supplierDebitNote,
                ),

            'delete' =>
                $actor->can(
                    'delete',
                    $supplierDebitNote,
                ),

            'submit' =>
                $actor->can(
                    'submit',
                    $supplierDebitNote,
                ),

            'return_to_draft' =>
                $actor->can(
                    'returnToDraft',
                    $supplierDebitNote,
                ),

            'approve' =>
                $actor->can(
                    'approve',
                    $supplierDebitNote,
                ),

            'cancel' =>
                $actor->can(
                    'cancel',
                    $supplierDebitNote,
                ),

            'post' =>
                $actor->can(
                    'post',
                    $supplierDebitNote,
                ),

            'reverse' =>
                $actor->can(
                    'reverse',
                    $supplierDebitNote,
                ),
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function responseData(
        SupplierDebitNote $supplierDebitNote,
    ): array {
        return [
            'id' =>
                (int) $supplierDebitNote
                    ->getKey(),

            'status' =>
                $supplierDebitNote
                    ->status,

            'debit_note_number' =>
                $supplierDebitNote
                    ->debit_note_number,
        ];
    }

    private function workflowResponse(
        SupplierDebitNote $supplierDebitNote,
        string $message,
    ): JsonResponse|RedirectResponse {
        return $this->responseService
            ->success(
                message: $message,

                data:
                    $this->responseData(
                        $supplierDebitNote,
                    ),

                redirectTo: route(
                    'supplier-debit-notes.show',
                    $supplierDebitNote,
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
}