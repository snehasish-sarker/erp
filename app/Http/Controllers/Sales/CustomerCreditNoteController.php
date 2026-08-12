<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CancelCustomerCreditNoteRequest;
use App\Http\Requests\Sales\IndexCustomerCreditNoteRequest;
use App\Http\Requests\Sales\ReverseCustomerCreditNoteRequest;
use App\Http\Requests\Sales\StoreCustomerCreditNoteRequest;
use App\Http\Requests\Sales\UpdateCustomerCreditNoteRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerCreditNoteDispatchAllocation;
use App\Models\CustomerCreditNoteLine;
use App\Models\CustomerOpenItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organisation\BranchAccessService;
use App\Services\Sales\CustomerCreditNoteService;
use App\Support\Responses\CommonResponseService;
use App\Support\Sales\CustomerCreditNoteStatusRegistry;
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

final class CustomerCreditNoteController extends Controller
{
    private const SCALE = 6;

    public function __construct(
        private readonly CustomerCreditNoteService $creditNoteService,
        private readonly CustomerCreditNoteStatusRegistry $statusRegistry,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexCustomerCreditNoteRequest $request,
    ): Response {
        Gate::authorize('viewAny', CustomerCreditNote::class);

        $actor = $this->actor($request);
        $validated = $request->validated();

        $search = (string) ($validated['search'] ?? '');
        $branchId = isset($validated['branch_id'])
            ? (int) $validated['branch_id']
            : null;
        $customerId = isset($validated['customer_id'])
            ? (int) $validated['customer_id']
            : null;
        $status = (string) ($validated['status'] ?? '');
        $from = (string) ($validated['posting_date_from'] ?? '');
        $to = (string) ($validated['posting_date_to'] ?? '');
        $sort = (string) ($validated['sort'] ?? 'created_at');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = CustomerCreditNote::query()->with([
            'branch:id,name,code,status',
            'warehouse:id,branch_id,name,code,status',
            'createdBy:id,name',
        ]);

        $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
            branchColumn: 'customer_credit_notes.branch_id',
        );

        $creditNotes = $query
            ->when(
                $search !== '',
                static function (Builder $query) use ($search): void {
                    $query->where(
                        static function (Builder $searchQuery) use ($search): void {
                            $searchQuery
                                ->where('credit_note_number', 'like', "%{$search}%")
                                ->orWhere('sales_invoice_number', 'like', "%{$search}%")
                                ->orWhere('sales_order_number', 'like', "%{$search}%")
                                ->orWhere('customer_name', 'like', "%{$search}%")
                                ->orWhere('customer_code', 'like', "%{$search}%")
                                ->orWhere('reason', 'like', "%{$search}%");
                        },
                    );
                },
            )
            ->when(
                $branchId !== null,
                static fn (Builder $query): Builder =>
                    $query->where('branch_id', $branchId),
            )
            ->when(
                $customerId !== null,
                static fn (Builder $query): Builder =>
                    $query->where('customer_id', $customerId),
            )
            ->when(
                $status !== '',
                static fn (Builder $query): Builder =>
                    $query->where('status', $status),
            )
            ->when(
                $from !== '',
                static fn (Builder $query): Builder =>
                    $query->whereDate('posting_date', '>=', $from),
            )
            ->when(
                $to !== '',
                static fn (Builder $query): Builder =>
                    $query->whereDate('posting_date', '<=', $to),
            )
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('SalesReturns/Index', [
            'creditNotes' => [
                'data' => $creditNotes
                    ->getCollection()
                    ->map(
                        fn (CustomerCreditNote $creditNote): array =>
                            $this->summaryData($creditNote, $actor),
                    )
                    ->values()
                    ->all(),
                'meta' => [
                    'current_page' => $creditNotes->currentPage(),
                    'last_page' => $creditNotes->lastPage(),
                    'per_page' => $creditNotes->perPage(),
                    'from' => $creditNotes->firstItem(),
                    'to' => $creditNotes->lastItem(),
                    'total' => $creditNotes->total(),
                ],
            ],
            'filters' => [
                'search' => $search,
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'status' => $status,
                'posting_date_from' => $from,
                'posting_date_to' => $to,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'branches' => $this->branches($actor),
            'customers' => Customer::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->limit(1000)
                ->get(['id', 'name', 'code'])
                ->map(
                    static fn (Customer $customer): array => [
                        'id' => (int) $customer->getKey(),
                        'name' => $customer->name,
                        'code' => $customer->code,
                    ],
                )
                ->values()
                ->all(),
            'statuses' => $this->statusRegistry->options(),
            'can' => [
                'create' => $actor->can('create', CustomerCreditNote::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', CustomerCreditNote::class);

        $actor = $this->actor($request);
        $invoiceId = $request->integer('sales_invoice_id');
        $selected = $invoiceId > 0
            ? $this->creditableInvoice($invoiceId, $actor)
            : null;

        $today = CarbonImmutable::now(
            $this->tenantContext->tenant()->timezone,
        )->format('Y-m-d');

        return Inertia::render('SalesReturns/Create', [
            'salesInvoices' => $this->creditableInvoices($actor),
            'selectedSalesInvoice' => $selected,
            'defaults' => [
                'credit_note_date' => $today,
                'posting_date' => $today,
            ],
        ]);
    }

    public function store(
        StoreCustomerCreditNoteRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('create', CustomerCreditNote::class);

        $creditNote = $this->creditNoteService->create(
            data: $request->validated(),
            actor: $this->actor($request),
        );

        return $this->responseService->success(
            message: 'Customer Credit Note draft created successfully.',
            data: $this->responseData($creditNote),
            redirectTo: route('sales-returns.show', $creditNote),
        );
    }

    public function show(
        Request $request,
        CustomerCreditNote $customerCreditNote,
    ): Response {
        Gate::authorize('view', $customerCreditNote);

        $actor = $this->actor($request);
        $this->loadDetailRelations($customerCreditNote);

        return Inertia::render('SalesReturns/Show', [
            'creditNote' => $this->detailData($customerCreditNote, $actor),
        ]);
    }

    public function edit(
        Request $request,
        CustomerCreditNote $customerCreditNote,
    ): Response {
        Gate::authorize('update', $customerCreditNote);

        $actor = $this->actor($request);
        $customerCreditNote->load('lines');

        return Inertia::render('SalesReturns/Edit', [
            'creditNote' => $this->formData($customerCreditNote),
            'selectedSalesInvoice' => $this->creditableInvoice(
                salesInvoiceId: (int) $customerCreditNote->sales_invoice_id,
                actor: $actor,
                editingCreditNoteId: (int) $customerCreditNote->getKey(),
            ),
        ]);
    }

    public function update(
        UpdateCustomerCreditNoteRequest $request,
        CustomerCreditNote $customerCreditNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $customerCreditNote);

        $creditNote = $this->creditNoteService->update(
            creditNote: $customerCreditNote,
            data: $request->validated(),
            actor: $this->actor($request),
        );

        return $this->responseService->success(
            message: 'Customer Credit Note draft updated successfully.',
            data: $this->responseData($creditNote),
            redirectTo: route('sales-returns.show', $creditNote),
        );
    }

    public function destroy(
        Request $request,
        CustomerCreditNote $customerCreditNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('delete', $customerCreditNote);

        $this->creditNoteService->delete(
            creditNote: $customerCreditNote,
            actor: $this->actor($request),
        );

        return $this->responseService->success(
            message: 'Customer Credit Note draft deleted successfully.',
            redirectTo: route('sales-returns.index'),
        );
    }

    public function submit(
        Request $request,
        CustomerCreditNote $customerCreditNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('submit', $customerCreditNote);

        $creditNote = $this->creditNoteService->submit(
            creditNote: $customerCreditNote,
            actor: $this->actor($request),
        );

        return $this->responseService->success(
            message: 'Customer Credit Note submitted successfully.',
            data: $this->responseData($creditNote),
            redirectTo: route('sales-returns.show', $creditNote),
        );
    }

    public function returnToDraft(
        Request $request,
        CustomerCreditNote $customerCreditNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('returnToDraft', $customerCreditNote);

        $creditNote = $this->creditNoteService->returnToDraft(
            creditNote: $customerCreditNote,
            actor: $this->actor($request),
        );

        return $this->responseService->success(
            message: 'Customer Credit Note returned to draft.',
            data: $this->responseData($creditNote),
            redirectTo: route('sales-returns.show', $creditNote),
        );
    }

    public function approve(
        Request $request,
        CustomerCreditNote $customerCreditNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('approve', $customerCreditNote);

        $creditNote = $this->creditNoteService->approve(
            creditNote: $customerCreditNote,
            actor: $this->actor($request),
        );

        return $this->responseService->success(
            message: 'Customer Credit Note approved successfully.',
            data: $this->responseData($creditNote),
            redirectTo: route('sales-returns.show', $creditNote),
        );
    }

    public function cancel(
        CancelCustomerCreditNoteRequest $request,
        CustomerCreditNote $customerCreditNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('cancel', $customerCreditNote);

        $creditNote = $this->creditNoteService->cancel(
            creditNote: $customerCreditNote,
            reason: (string) $request->validated('cancellation_reason'),
            actor: $this->actor($request),
        );

        return $this->responseService->success(
            message: 'Customer Credit Note cancelled successfully.',
            data: $this->responseData($creditNote),
            redirectTo: route('sales-returns.show', $creditNote),
        );
    }

    public function post(
        Request $request,
        CustomerCreditNote $customerCreditNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('post', $customerCreditNote);

        $creditNote = $this->creditNoteService->post(
            creditNote: $customerCreditNote,
            actor: $this->actor($request),
        );

        return $this->responseService->success(
            message: 'Customer Credit Note posted to Accounts Receivable and inventory successfully.',
            data: $this->responseData($creditNote),
            redirectTo: route('sales-returns.show', $creditNote),
        );
    }

    public function reverse(
        ReverseCustomerCreditNoteRequest $request,
        CustomerCreditNote $customerCreditNote,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('reverse', $customerCreditNote);

        $creditNote = $this->creditNoteService->reverse(
            creditNote: $customerCreditNote,
            reversalPostingDate: (string) $request->validated(
                'reversal_posting_date',
            ),
            reason: (string) $request->validated('reversal_reason'),
            actor: $this->actor($request),
        );

        return $this->responseService->success(
            message: 'Customer Credit Note reversed successfully.',
            data: $this->responseData($creditNote),
            redirectTo: route('sales-returns.show', $creditNote),
        );
    }

    public function print(
        Request $request,
        CustomerCreditNote $customerCreditNote,
    ): Response {
        Gate::authorize('print', $customerCreditNote);

        $actor = $this->actor($request);
        $this->loadDetailRelations($customerCreditNote);
        $tenant = $this->tenantContext->tenant();

        return Inertia::render('SalesReturns/Print/CustomerCreditNote', [
            'creditNote' => $this->detailData($customerCreditNote, $actor),
            'company' => [
                'name' => $tenant->name,
                'code' => $tenant->code,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'address' => $tenant->address,
            ],
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function creditableInvoices(User $actor): array
    {
        $query = SalesInvoice::query()
            ->where('status', 'posted')
            ->whereHas(
                'lines',
                static fn (Builder $lineQuery): Builder =>
                    $lineQuery->where(
                        static function (Builder $available): void {
                            $available
                                ->whereColumn(
                                    'credited_quantity',
                                    '<',
                                    'invoiced_quantity',
                                )
                                ->orWhereColumn(
                                    'credited_amount',
                                    '<',
                                    'line_total',
                                );
                        },
                    ),
            )
            ->whereNotExists(
                static function ($draftQuery): void {
                    $draftQuery
                        ->selectRaw('1')
                        ->from('customer_credit_notes')
                        ->whereColumn(
                            'customer_credit_notes.sales_invoice_id',
                            'sales_invoices.id',
                        )
                        ->whereNotNull('customer_credit_notes.draft_key')
                        ->whereNull('customer_credit_notes.deleted_at');
                },
            )
            ->orderByDesc('invoice_date')
            ->orderByDesc('id');

        $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
            branchColumn: 'sales_invoices.branch_id',
        );

        return $query
            ->limit(500)
            ->get([
                'id',
                'invoice_number',
                'invoice_date',
                'sales_order_number',
                'customer_name',
                'customer_code',
                'currency_code',
                'total_amount',
            ])
            ->map(
                static fn (SalesInvoice $invoice): array => [
                    'id' => (int) $invoice->getKey(),
                    'invoice_number' => $invoice->invoice_number,
                    'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
                    'sales_order_number' => $invoice->sales_order_number,
                    'customer_name' => $invoice->customer_name,
                    'customer_code' => $invoice->customer_code,
                    'currency_code' => $invoice->currency_code,
                    'total_amount' => (string) $invoice->total_amount,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function creditableInvoice(
        int $salesInvoiceId,
        User $actor,
        ?int $editingCreditNoteId = null,
    ): array {
        $query = SalesInvoice::query()
            ->whereKey($salesInvoiceId)
            ->where('status', 'posted')
            ->with([
                'branch:id,name,code,status',
                'salesOrder:id,document_number,status,warehouse_id',
                'salesOrder.warehouse:id,branch_id,name,code,status',
                'lines' => static fn ($lineQuery) =>
                    $lineQuery->orderBy('line_number'),
            ]);

        $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
            branchColumn: 'sales_invoices.branch_id',
        );

        $invoice = $query->firstOrFail();

        $conflictingDraft = CustomerCreditNote::query()
            ->where('sales_invoice_id', $invoice->getKey())
            ->whereNotNull('draft_key')
            ->when(
                $editingCreditNoteId !== null,
                static fn (Builder $query): Builder =>
                    $query->where('id', '!=', $editingCreditNoteId),
            )
            ->exists();

        abort_if(
            $conflictingDraft,
            422,
            'This Sales Invoice already has an editable Customer Credit Note draft.',
        );

        $invoiceOpenItem = CustomerOpenItem::query()
            ->where('source_type', $invoice->getMorphClass())
            ->where('source_id', $invoice->getKey())
            ->where('item_type', 'invoice')
            ->first();

        return [
            'id' => (int) $invoice->getKey(),
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
            'posting_date' => $invoice->posting_date?->format('Y-m-d'),
            'sales_order_number' => $invoice->sales_order_number,
            'status' => $invoice->status,
            'customer_name' => $invoice->customer_name,
            'customer_code' => $invoice->customer_code,
            'customer_contact_person' => $invoice->customer_contact_person,
            'customer_email' => $invoice->customer_email,
            'customer_phone' => $invoice->customer_phone,
            'customer_tax_number' => $invoice->customer_tax_number,
            'billing_address' => $invoice->billing_address,
            'shipping_address' => $invoice->shipping_address,
            'currency_code' => $invoice->currency_code,
            'exchange_rate' => (string) $invoice->exchange_rate,
            'total_amount' => (string) $invoice->total_amount,
            'open_item_outstanding' => $invoiceOpenItem instanceof CustomerOpenItem
                ? (string) $invoiceOpenItem->outstanding_amount
                : '0.000000',
            'branch' => $invoice->branch instanceof Branch
                ? [
                    'id' => (int) $invoice->branch->getKey(),
                    'name' => $invoice->branch->name,
                    'code' => $invoice->branch->code,
                ]
                : null,
            'warehouse' => $invoice->salesOrder?->warehouse instanceof Warehouse
                ? [
                    'id' => (int) $invoice->salesOrder->warehouse->getKey(),
                    'name' => $invoice->salesOrder->warehouse->name,
                    'code' => $invoice->salesOrder->warehouse->code,
                ]
                : null,
            'lines' => $invoice->lines
                ->map(
                    function (SalesInvoiceLine $line): array {
                        $remainingQuantity = BigDecimal::of(
                            (string) $line->invoiced_quantity,
                        )->minus(
                            BigDecimal::of((string) $line->credited_quantity),
                        );

                        $remainingAmount = BigDecimal::of(
                            (string) $line->line_total,
                        )->minus(
                            BigDecimal::of((string) $line->credited_amount),
                        );

                        return [
                            'id' => (int) $line->getKey(),
                            'line_number' => (int) $line->line_number,
                            'product_name' => $line->product_name,
                            'product_sku' => $line->product_sku,
                            'product_type' => $line->product_type,
                            'unit_name' => $line->unit_name,
                            'unit_code' => $line->unit_code,
                            'description' => $line->description,
                            'invoiced_quantity' => (string) $line->invoiced_quantity,
                            'credited_quantity' => (string) $line->credited_quantity,
                            'remaining_creditable_quantity' => $remainingQuantity
                                ->toScale(self::SCALE, RoundingMode::HalfUp)
                                ->__toString(),
                            'unit_price' => (string) $line->unit_price,
                            'gross_amount' => (string) $line->gross_amount,
                            'discount_amount' => (string) $line->discount_amount,
                            'tax_rate' => (string) $line->tax_rate,
                            'tax_amount' => (string) $line->tax_amount,
                            'line_total' => (string) $line->line_total,
                            'credited_amount' => (string) $line->credited_amount,
                            'remaining_creditable_amount' => $remainingAmount
                                ->toScale(self::SCALE, RoundingMode::HalfUp)
                                ->__toString(),
                        ];
                    },
                )
                ->filter(
                    static fn (array $line): bool =>
                        BigDecimal::of($line['remaining_creditable_quantity'])
                            ->isGreaterThan(BigDecimal::zero())
                        || BigDecimal::of($line['remaining_creditable_amount'])
                            ->isGreaterThan(BigDecimal::zero()),
                )
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryData(
        CustomerCreditNote $creditNote,
        User $actor,
    ): array {
        return [
            'id' => (int) $creditNote->getKey(),
            'credit_note_number' => $creditNote->credit_note_number,
            'credit_note_date' => $creditNote->credit_note_date?->format('Y-m-d'),
            'posting_date' => $creditNote->posting_date?->format('Y-m-d'),
            'sales_invoice_number' => $creditNote->sales_invoice_number,
            'sales_order_number' => $creditNote->sales_order_number,
            'customer_name' => $creditNote->customer_name,
            'customer_code' => $creditNote->customer_code,
            'currency_code' => $creditNote->currency_code,
            'total_amount' => (string) $creditNote->total_amount,
            'returned_quantity' => (string) $creditNote->returned_quantity,
            'status' => $creditNote->status,
            'status_label' => $this->statusRegistry->label($creditNote->status),
            'branch' => $creditNote->branch instanceof Branch
                ? [
                    'id' => (int) $creditNote->branch->getKey(),
                    'name' => $creditNote->branch->name,
                    'code' => $creditNote->branch->code,
                ]
                : null,
            'warehouse' => $creditNote->warehouse instanceof Warehouse
                ? [
                    'id' => (int) $creditNote->warehouse->getKey(),
                    'name' => $creditNote->warehouse->name,
                    'code' => $creditNote->warehouse->code,
                ]
                : null,
            'created_at' => $creditNote->created_at?->toIso8601String(),
            'can' => $this->permissions($creditNote, $actor),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(
        CustomerCreditNote $creditNote,
        User $actor,
    ): array {
        return [
            ...$this->summaryData($creditNote, $actor),
            'sales_invoice_id' => (int) $creditNote->sales_invoice_id,
            'sales_order_id' => (int) $creditNote->sales_order_id,
            'branch_id' => (int) $creditNote->branch_id,
            'warehouse_id' => $creditNote->warehouse_id !== null
                ? (int) $creditNote->warehouse_id
                : null,
            'customer_id' => (int) $creditNote->customer_id,
            'customer_type' => $creditNote->customer_type,
            'customer_contact_person' => $creditNote->customer_contact_person,
            'customer_email' => $creditNote->customer_email,
            'customer_phone' => $creditNote->customer_phone,
            'customer_tax_number' => $creditNote->customer_tax_number,
            'billing_address' => $creditNote->billing_address,
            'return_address' => $creditNote->return_address,
            'exchange_rate' => (string) $creditNote->exchange_rate,
            'gross_amount' => (string) $creditNote->gross_amount,
            'discount_amount' => (string) $creditNote->discount_amount,
            'subtotal' => (string) $creditNote->subtotal,
            'tax_amount' => (string) $creditNote->tax_amount,
            'quantity_credit_amount' => (string) $creditNote->quantity_credit_amount,
            'amount_only_credit_amount' => (string) $creditNote->amount_only_credit_amount,
            'inventory_return_value' => (string) $creditNote->inventory_return_value,
            'reason' => $creditNote->reason,
            'notes' => $creditNote->notes,
            'revision' => (int) $creditNote->revision,
            'submitted_at' => $creditNote->submitted_at?->toIso8601String(),
            'approved_at' => $creditNote->approved_at?->toIso8601String(),
            'posted_at' => $creditNote->posted_at?->toIso8601String(),
            'accounting_posting_reference' => $creditNote->accounting_posting_reference,
            'inventory_posting_reference' => $creditNote->inventory_posting_reference,
            'reversal_posting_date' => $creditNote->reversal_posting_date?->format('Y-m-d'),
            'reversed_at' => $creditNote->reversed_at?->toIso8601String(),
            'reversal_reason' => $creditNote->reversal_reason,
            'accounting_reversal_reference' => $creditNote->accounting_reversal_reference,
            'inventory_reversal_reference' => $creditNote->inventory_reversal_reference,
            'cancelled_at' => $creditNote->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $creditNote->cancellation_reason,
            'created_by' => $this->userData($creditNote->createdBy),
            'submitted_by' => $this->userData($creditNote->submittedBy),
            'approved_by' => $this->userData($creditNote->approvedBy),
            'posted_by' => $this->userData($creditNote->postedBy),
            'reversed_by' => $this->userData($creditNote->reversedBy),
            'cancelled_by' => $this->userData($creditNote->cancelledBy),
            'customer_open_item' => $creditNote->customerOpenItem !== null
                ? [
                    'id' => (int) $creditNote->customerOpenItem->getKey(),
                    'status' => $creditNote->customerOpenItem->status,
                    'original_amount' => (string) $creditNote->customerOpenItem->original_amount,
                    'allocated_amount' => (string) $creditNote->customerOpenItem->allocated_amount,
                    'outstanding_amount' => (string) $creditNote->customerOpenItem->outstanding_amount,
                ]
                : null,
            'automatic_allocation' => $creditNote->automaticAllocation !== null
                ? [
                    'id' => (int) $creditNote->automaticAllocation->getKey(),
                    'status' => $creditNote->automaticAllocation->status,
                    'amount' => (string) $creditNote->automaticAllocation->amount,
                    'posting_date' => $creditNote->automaticAllocation->posting_date?->format('Y-m-d'),
                ]
                : null,
            'lines' => $creditNote->lines
                ->map(
                    static fn (CustomerCreditNoteLine $line): array => [
                        'id' => (int) $line->getKey(),
                        'line_number' => (int) $line->line_number,
                        'sales_invoice_line_id' => (int) $line->sales_invoice_line_id,
                        'sales_order_line_id' => (int) $line->sales_order_line_id,
                        'line_type' => $line->line_type,
                        'product_name' => $line->product_name,
                        'product_sku' => $line->product_sku,
                        'product_type' => $line->product_type,
                        'unit_name' => $line->unit_name,
                        'unit_code' => $line->unit_code,
                        'description' => $line->description,
                        'credit_quantity' => (string) $line->credit_quantity,
                        'return_to_stock' => (bool) $line->return_to_stock,
                        'unit_price' => (string) $line->unit_price,
                        'gross_amount' => (string) $line->gross_amount,
                        'discount_amount' => (string) $line->discount_amount,
                        'subtotal' => (string) $line->subtotal,
                        'tax_rate' => (string) $line->tax_rate,
                        'tax_amount' => (string) $line->tax_amount,
                        'line_total' => (string) $line->line_total,
                        'unit_cost' => (string) $line->unit_cost,
                        'total_cost' => (string) $line->total_cost,
                        'stock_ledger_entry_id' => $line->stock_ledger_entry_id !== null
                            ? (int) $line->stock_ledger_entry_id
                            : null,
                        'reversal_stock_ledger_entry_id' => $line->reversal_stock_ledger_entry_id !== null
                            ? (int) $line->reversal_stock_ledger_entry_id
                            : null,
                        'dispatch_allocations' => $line->dispatchAllocations
                            ->map(
                                static fn (CustomerCreditNoteDispatchAllocation $allocation): array => [
                                    'id' => (int) $allocation->getKey(),
                                    'dispatch_number' => $allocation
                                        ->customerDispatchLine
                                        ?->dispatch
                                        ?->dispatch_number,
                                    'dispatch_date' => $allocation
                                        ->customerDispatchLine
                                        ?->dispatch
                                        ?->dispatch_date
                                        ?->format('Y-m-d'),
                                    'allocated_quantity' => (string) $allocation->allocated_quantity,
                                    'unit_cost' => (string) $allocation->unit_cost,
                                    'total_cost' => (string) $allocation->total_cost,
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
    private function formData(CustomerCreditNote $creditNote): array
    {
        return [
            'id' => (int) $creditNote->getKey(),
            'sales_invoice_id' => (int) $creditNote->sales_invoice_id,
            'credit_note_date' => $creditNote->credit_note_date?->format('Y-m-d'),
            'posting_date' => $creditNote->posting_date?->format('Y-m-d'),
            'return_address' => $creditNote->return_address,
            'reason' => $creditNote->reason,
            'notes' => $creditNote->notes,
            'status' => $creditNote->status,
            'revision' => (int) $creditNote->revision,
            'lines' => $creditNote->lines
                ->map(
                    static fn (CustomerCreditNoteLine $line): array => [
                        'id' => (int) $line->getKey(),
                        'sales_invoice_line_id' => (int) $line->sales_invoice_line_id,
                        'line_type' => $line->line_type,
                        'credit_quantity' => (string) $line->credit_quantity,
                        'credit_amount' => (string) $line->line_total,
                        'return_to_stock' => (bool) $line->return_to_stock,
                        'description' => $line->description,
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, bool> */
    private function permissions(
        CustomerCreditNote $creditNote,
        User $actor,
    ): array {
        return [
            'view' => $actor->can('view', $creditNote),
            'update' => $actor->can('update', $creditNote),
            'delete' => $actor->can('delete', $creditNote),
            'submit' => $actor->can('submit', $creditNote),
            'return_to_draft' => $actor->can('returnToDraft', $creditNote),
            'approve' => $actor->can('approve', $creditNote),
            'cancel' => $actor->can('cancel', $creditNote),
            'post' => $actor->can('post', $creditNote),
            'reverse' => $actor->can('reverse', $creditNote),
            'print' => $actor->can('print', $creditNote),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function branches(User $actor): array
    {
        return $this->branchAccessService
            ->accessibleBranches(user: $actor, activeOnly: false)
            ->map(
                static fn (Branch $branch): array => [
                    'id' => (int) $branch->getKey(),
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'status' => $branch->status,
                ],
            )
            ->values()
            ->all();
    }

    /** @return array{id: int, name: string}|null */
    private function userData(?User $user): ?array
    {
        return $user instanceof User
            ? [
                'id' => (int) $user->getKey(),
                'name' => $user->name,
            ]
            : null;
    }

    /** @return array<string, mixed> */
    private function responseData(CustomerCreditNote $creditNote): array
    {
        return [
            'id' => (int) $creditNote->getKey(),
            'credit_note_number' => $creditNote->credit_note_number,
            'status' => $creditNote->status,
        ];
    }

    private function loadDetailRelations(CustomerCreditNote $creditNote): void
    {
        $creditNote->load([
            'salesInvoice:id,invoice_number,status,total_amount,currency_code',
            'salesOrder:id,document_number,status',
            'branch:id,name,code,status',
            'warehouse:id,branch_id,name,code,status',
            'customer:id,name,code,status',
            'lines.dispatchAllocations.customerDispatchLine.dispatch:id,dispatch_number,dispatch_date,status',
            'lines.stockLedgerEntry',
            'lines.reversalStockLedgerEntry',
            'customerLedgerEntry',
            'customerOpenItem',
            'automaticAllocation',
            'createdBy:id,name',
            'submittedBy:id,name',
            'approvedBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'cancelledBy:id,name',
        ]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}