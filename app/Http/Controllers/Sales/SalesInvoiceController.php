<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\IndexSalesInvoiceRequest;
use App\Http\Requests\Sales\ReverseSalesInvoiceRequest;
use App\Http\Requests\Sales\StoreSalesInvoiceRequest;
use App\Http\Requests\Sales\UpdateSalesInvoiceRequest;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceDispatchAllocation;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Services\Accounting\CustomerBalanceService;
use App\Services\Organisation\BranchAccessService;
use App\Services\Sales\SalesInvoiceService;
use App\Support\Responses\CommonResponseService;
use App\Support\Sales\SalesInvoiceStatusRegistry;
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

final class SalesInvoiceController extends Controller
{
    private const SCALE = 6;

    public function __construct(
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly SalesInvoiceStatusRegistry $statusRegistry,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
        private readonly CustomerBalanceService $customerBalanceService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexSalesInvoiceRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            SalesInvoice::class,
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

        $customerId = isset(
            $validated['customer_id'],
        )
            ? (int) $validated['customer_id']
            : null;

        $status = (string) (
            $validated['status'] ?? ''
        );

        $from = (string) (
            $validated['posting_date_from']
                ?? ''
        );

        $to = (string) (
            $validated['posting_date_to']
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
            $validated['per_page']
                ?? 15
        );

        $query = SalesInvoice::query()
            ->with([
                'branch:id,name,code,status',
                'customer:id,name,code,status',
                'createdBy:id,name',
            ]);

        $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
            branchColumn:
                'sales_invoices.branch_id',
        );

        $invoices = $query
            ->when(
                $search !== '',
                static function (
                    Builder $query,
                ) use (
                    $search,
                ): void {
                    $query->where(
                        static function (
                            Builder $searchQuery,
                        ) use (
                            $search,
                        ): void {
                            $searchQuery
                                ->where(
                                    'invoice_number',
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
                                );
                        },
                    );
                },
            )
            ->when(
                $branchId !== null,
                static fn (
                    Builder $query,
                ): Builder =>
                    $query->where(
                        'branch_id',
                        $branchId,
                    ),
            )
            ->when(
                $customerId !== null,
                static fn (
                    Builder $query,
                ): Builder =>
                    $query->where(
                        'customer_id',
                        $customerId,
                    ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $query,
                ): Builder =>
                    $query->where(
                        'status',
                        $status,
                    ),
            )
            ->when(
                $from !== '',
                static fn (
                    Builder $query,
                ): Builder =>
                    $query->whereDate(
                        'posting_date',
                        '>=',
                        $from,
                    ),
            )
            ->when(
                $to !== '',
                static fn (
                    Builder $query,
                ): Builder =>
                    $query->whereDate(
                        'posting_date',
                        '<=',
                        $to,
                    ),
            )
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'SalesInvoices/Index',
            [
                'salesInvoices' => [
                    'data' =>
                        $invoices
                            ->getCollection()
                            ->map(
                                fn (
                                    SalesInvoice $invoice,
                                ): array =>
                                    $this->summaryData(
                                        $invoice,
                                        $actor,
                                    ),
                            )
                            ->values()
                            ->all(),

                    'meta' => [
                        'current_page' =>
                            $invoices
                                ->currentPage(),

                        'last_page' =>
                            $invoices
                                ->lastPage(),

                        'per_page' =>
                            $invoices
                                ->perPage(),

                        'from' =>
                            $invoices
                                ->firstItem(),

                        'to' =>
                            $invoices
                                ->lastItem(),

                        'total' =>
                            $invoices
                                ->total(),
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

                'branches' => $this->branches(
                    $actor,
                ),

                'customers' => Customer::query()
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->limit(1000)
                    ->get([
                        'id',
                        'name',
                        'code',
                    ])
                    ->map(
                        static fn (
                            Customer $customer,
                        ): array => [
                            'id' =>
                                (int) $customer
                                    ->getKey(),

                            'name' =>
                                $customer->name,

                            'code' =>
                                $customer->code,
                        ],
                    )
                    ->values()
                    ->all(),

                'statuses' =>
                    $this->statusRegistry
                        ->options(),

                'can' => [
                    'create' =>
                        $actor->can(
                            'create',
                            SalesInvoice::class,
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
            SalesInvoice::class,
        );

        $actor = $this->actor($request);

        $salesOrderId = $request->integer(
            'sales_order_id',
        );

        $selected = $salesOrderId > 0
            ? $this->invoiceableOrder(
                $salesOrderId,
                $actor,
            )
            : null;

        $tenant = $this->tenantContext
            ->tenant();

        $today = CarbonImmutable::now(
            $tenant->timezone,
        );

        return Inertia::render(
            'SalesInvoices/Create',
            [
                'salesOrders' =>
                    $this->invoiceableOrders(
                        $actor,
                    ),

                'selectedSalesOrder' =>
                    $selected,

                'defaults' => [
                    'invoice_date' =>
                        $today->format('Y-m-d'),

                    'posting_date' =>
                        $today->format('Y-m-d'),

                    'due_date' =>
                        $selected !== null
                            ? $today
                                ->addDays(
                                    (int) $selected[
                                        'payment_terms_days'
                                    ],
                                )
                                ->format('Y-m-d')
                            : $today->format(
                                'Y-m-d',
                            ),
                ],
            ],
        );
    }

    public function store(
        StoreSalesInvoiceRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            SalesInvoice::class,
        );

        $invoice =
            $this->salesInvoiceService
                ->create(
                    data:
                        $request->validated(),

                    actor:
                        $this->actor($request),
                );

        return $this->responseService
            ->success(
                message:
                    'Sales Invoice draft created successfully.',

                data:
                    $this->responseData(
                        $invoice,
                    ),

                redirectTo:
                    route(
                        'sales-invoices.show',
                        $invoice,
                    ),
            );
    }

    public function show(
        Request $request,
        SalesInvoice $salesInvoice,
    ): Response {
        Gate::authorize(
            'view',
            $salesInvoice,
        );

        $actor = $this->actor($request);

        $salesInvoice->load([
            'branch:id,name,code,status',
            'customer:id,name,code,status',
            'salesOrder:id,document_number,status',
            'lines.dispatchAllocations.customerDispatchLine.dispatch:id,dispatch_number,dispatch_date,status',
            'createdBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'openItem',
        ]);

        return Inertia::render(
            'SalesInvoices/Show',
            [
                'salesInvoice' =>
                    $this->detailData(
                        $salesInvoice,
                        $actor,
                    ),
            ],
        );
    }

    public function edit(
        Request $request,
        SalesInvoice $salesInvoice,
    ): Response {
        Gate::authorize(
            'update',
            $salesInvoice,
        );

        $actor = $this->actor($request);

        return Inertia::render(
            'SalesInvoices/Edit',
            [
                'salesInvoice' =>
                    $this->formData(
                        $salesInvoice,
                    ),

                'selectedSalesOrder' =>
                    $this->invoiceableOrder(
                        salesOrderId:
                            (int) $salesInvoice
                                ->sales_order_id,

                        actor:
                            $actor,

                        editingInvoiceId:
                            (int) $salesInvoice
                                ->getKey(),
                    ),
            ],
        );
    }

    public function update(
        UpdateSalesInvoiceRequest $request,
        SalesInvoice $salesInvoice,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $salesInvoice,
        );

        $invoice =
            $this->salesInvoiceService
                ->update(
                    salesInvoice:
                        $salesInvoice,

                    data:
                        $request->validated(),

                    actor:
                        $this->actor($request),
                );

        return $this->responseService
            ->success(
                message:
                    'Sales Invoice draft updated successfully.',

                data:
                    $this->responseData(
                        $invoice,
                    ),

                redirectTo:
                    route(
                        'sales-invoices.show',
                        $invoice,
                    ),
            );
    }

    public function destroy(
        Request $request,
        SalesInvoice $salesInvoice,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $salesInvoice,
        );

        $this->salesInvoiceService
            ->delete(
                salesInvoice:
                    $salesInvoice,

                actor:
                    $this->actor($request),
            );

        return $this->responseService
            ->success(
                message:
                    'Sales Invoice draft deleted successfully.',

                redirectTo:
                    route(
                        'sales-invoices.index',
                    ),
            );
    }

    public function post(
        Request $request,
        SalesInvoice $salesInvoice,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'post',
            $salesInvoice,
        );

        $invoice =
            $this->salesInvoiceService
                ->post(
                    salesInvoice:
                        $salesInvoice,

                    actor:
                        $this->actor($request),
                );

        return $this->responseService
            ->success(
                message:
                    'Sales Invoice posted to Accounts Receivable and the General Ledger.',

                data:
                    $this->responseData(
                        $invoice,
                    ),

                redirectTo:
                    route(
                        'sales-invoices.show',
                        $invoice,
                    ),
            );
    }

    public function reverse(
        ReverseSalesInvoiceRequest $request,
        SalesInvoice $salesInvoice,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'reverse',
            $salesInvoice,
        );

        $invoice =
            $this->salesInvoiceService
                ->reverse(
                    salesInvoice:
                        $salesInvoice,

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

        return $this->responseService
            ->success(
                message:
                    'Sales Invoice reversed successfully.',

                data:
                    $this->responseData(
                        $invoice,
                    ),

                redirectTo:
                    route(
                        'sales-invoices.show',
                        $invoice,
                    ),
            );
    }

    public function print(
        Request $request,
        SalesInvoice $salesInvoice,
    ): Response {
        Gate::authorize(
            'print',
            $salesInvoice,
        );

        $actor = $this->actor($request);

        $salesInvoice->load([
            'branch:id,name,code,address,phone,email',
            'lines',
            'createdBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'openItem',
        ]);

        $tenant = $this->tenantContext
            ->tenant();

        return Inertia::render(
            'SalesInvoices/Print/SalesInvoice',
            [
                'salesInvoice' =>
                    $this->detailData(
                        $salesInvoice,
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
    private function invoiceableOrders(
        User $actor,
    ): array {
        $query = SalesOrder::query()
            ->whereIn(
                'status',
                [
                    'partially_dispatched',
                    'dispatched',
                    'partially_invoiced',
                ],
            )
            ->whereHas(
                'lines',
                static fn (
                    Builder $lineQuery,
                ): Builder =>
                    $lineQuery
                        ->whereColumn(
                            'dispatched_quantity',
                            '>',
                            'invoiced_quantity',
                        ),
            )
            ->whereNotExists(
                static function (
                    $draftQuery,
                ): void {
                    $draftQuery
                        ->selectRaw('1')
                        ->from('sales_invoices')
                        ->whereColumn(
                            'sales_invoices.sales_order_id',
                            'sales_orders.id',
                        )
                        ->where(
                            'sales_invoices.status',
                            'draft',
                        )
                        ->whereNull(
                            'sales_invoices.deleted_at',
                        );
                },
            )
            ->orderByDesc('order_date')
            ->orderByDesc('id');

        $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
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
                'status',
            ])
            ->map(
                static fn (
                    SalesOrder $order,
                ): array => [
                    'id' =>
                        (int) $order->getKey(),

                    'document_number' =>
                        $order->document_number,

                    'order_date' =>
                        $order
                            ->order_date
                            ?->format('Y-m-d'),

                    'customer_name' =>
                        $order->customer_name,

                    'customer_code' =>
                        $order->customer_code,

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
    private function invoiceableOrder(
        int $salesOrderId,
        User $actor,
        ?int $editingInvoiceId = null,
    ): array {
        $query = SalesOrder::query()
            ->whereKey($salesOrderId)
            ->whereIn(
                'status',
                [
                    'partially_dispatched',
                    'dispatched',
                    'partially_invoiced',
                ],
            )
            ->with([
                'branch:id,name,code,status',
                'customer:id,name,code,credit_limit,status',

                'lines' =>
                    static fn (
                        $lineQuery,
                    ) => $lineQuery
                        ->orderBy(
                            'line_number',
                        ),
            ]);

        $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
            branchColumn:
                'sales_orders.branch_id',
        );

        $order = $query->firstOrFail();

        $conflictingDraft = SalesInvoice::query()
            ->where(
                'sales_order_id',
                $order->getKey(),
            )
            ->where(
                'status',
                'draft',
            )
            ->when(
                $editingInvoiceId !== null,
                static fn (
                    Builder $query,
                ): Builder =>
                    $query->where(
                        'id',
                        '!=',
                        $editingInvoiceId,
                    ),
            )
            ->exists();

        abort_if(
            $conflictingDraft,
            422,
            'This Sales Order already has an editable Sales Invoice draft.',
        );

        $currentInvoiceQuantities =
            $editingInvoiceId === null
                ? collect()
                : SalesInvoiceLine::query()
                    ->where(
                        'sales_invoice_id',
                        $editingInvoiceId,
                    )
                    ->pluck(
                        'invoiced_quantity',
                        'sales_order_line_id',
                    );

        $customer = $order->customer;

        return [
            'id' =>
                (int) $order->getKey(),

            'document_number' =>
                $order->document_number,

            'order_date' =>
                $order
                    ->order_date
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

            'customer_email' =>
                $order->customer_email,

            'customer_phone' =>
                $order->customer_phone,

            'billing_address' =>
                $order->billing_address,

            'shipping_address' =>
                $order->shipping_address,

            'payment_terms_days' =>
                (int) $order
                    ->payment_terms_days,

            'credit_limit' =>
                (string) $order
                    ->credit_limit_snapshot,

            'current_base_outstanding' =>
                $customer instanceof Customer
                    ? $this
                        ->customerBalanceService
                        ->baseOutstanding(
                            $customer,
                        )
                    : '0.000000',

            'currency_code' =>
                $order->currency_code,

            'exchange_rate' =>
                (string) $order
                    ->exchange_rate,

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

            'lines' =>
                $order->lines
                    ->map(
                        function (
                            SalesOrderLine $line,
                        ) use (
                            $currentInvoiceQuantities,
                        ): array {
                            $current =
                                BigDecimal::of(
                                    (string) (
                                        $currentInvoiceQuantities[
                                            $line->getKey()
                                        ] ?? '0'
                                    ),
                                );

                            $remaining =
                                BigDecimal::of(
                                    (string) $line
                                        ->dispatched_quantity,
                                )
                                    ->minus(
                                        BigDecimal::of(
                                            (string) $line
                                                ->invoiced_quantity,
                                        ),
                                    )
                                    ->plus($current);

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

                                'dispatched_quantity' =>
                                    (string) $line
                                        ->dispatched_quantity,

                                'already_invoiced_quantity' =>
                                    (string) $line
                                        ->invoiced_quantity,

                                'remaining_invoiceable_quantity' =>
                                    $remaining
                                        ->toScale(
                                            self::SCALE,
                                            RoundingMode::HALF_UP,
                                        )
                                        ->__toString(),

                                'unit_price' =>
                                    (string) $line
                                        ->unit_price,

                                'discount_amount' =>
                                    (string) $line
                                        ->discount_amount,

                                'tax_rate' =>
                                    (string) $line
                                        ->tax_rate,
                            ];
                        },
                    )
                    ->filter(
                        static fn (
                            array $line,
                        ): bool =>
                            BigDecimal::of(
                                $line[
                                    'remaining_invoiceable_quantity'
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
        SalesInvoice $invoice,
        User $actor,
    ): array {
        return [
            'id' =>
                (int) $invoice->getKey(),

            'invoice_number' =>
                $invoice->invoice_number,

            'invoice_date' =>
                $invoice
                    ->invoice_date
                    ?->format('Y-m-d'),

            'posting_date' =>
                $invoice
                    ->posting_date
                    ?->format('Y-m-d'),

            'due_date' =>
                $invoice
                    ->due_date
                    ?->format('Y-m-d'),

            'sales_order_number' =>
                $invoice
                    ->sales_order_number,

            'customer_name' =>
                $invoice->customer_name,

            'customer_code' =>
                $invoice->customer_code,

            'currency_code' =>
                $invoice->currency_code,

            'total_amount' =>
                (string) $invoice
                    ->total_amount,

            'status' =>
                $invoice->status,

            'status_label' =>
                $this->statusRegistry
                    ->label(
                        $invoice->status,
                    ),

            'branch' =>
                $invoice->branch
                instanceof Branch
                    ? [
                        'id' =>
                            (int) $invoice
                                ->branch
                                ->getKey(),

                        'name' =>
                            $invoice
                                ->branch
                                ->name,

                        'code' =>
                            $invoice
                                ->branch
                                ->code,
                    ]
                    : null,

            'created_at' =>
                $invoice
                    ->created_at
                    ?->toIso8601String(),

            'can' =>
                $this->permissions(
                    $invoice,
                    $actor,
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(
        SalesInvoice $invoice,
        User $actor,
    ): array {
        return [
            ...$this->summaryData(
                $invoice,
                $actor,
            ),

            'branch_id' =>
                (int) $invoice->branch_id,

            'customer_id' =>
                (int) $invoice->customer_id,

            'sales_order_id' =>
                (int) $invoice
                    ->sales_order_id,

            'customer_type' =>
                $invoice->customer_type,

            'customer_contact_person' =>
                $invoice
                    ->customer_contact_person,

            'customer_email' =>
                $invoice->customer_email,

            'customer_phone' =>
                $invoice->customer_phone,

            'customer_tax_number' =>
                $invoice
                    ->customer_tax_number,

            'billing_address' =>
                $invoice->billing_address,

            'shipping_address' =>
                $invoice->shipping_address,

            'payment_terms_days' =>
                (int) $invoice
                    ->payment_terms_days,

            'credit_limit_snapshot' =>
                (string) $invoice
                    ->credit_limit_snapshot,

            'exchange_rate' =>
                (string) $invoice
                    ->exchange_rate,

            'subtotal' =>
                (string) $invoice->subtotal,

            'discount_amount' =>
                (string) $invoice
                    ->discount_amount,

            'tax_amount' =>
                (string) $invoice
                    ->tax_amount,

            'shipping_amount' =>
                (string) $invoice
                    ->shipping_amount,

            'other_charges' =>
                (string) $invoice
                    ->other_charges,

            'total_cost' =>
                (string) $invoice
                    ->total_cost,

            'notes' =>
                $invoice->notes,

            'revision' =>
                (int) $invoice->revision,

            'posted_at' =>
                $invoice
                    ->posted_at
                    ?->toIso8601String(),

            'accounting_posting_reference' =>
                $invoice
                    ->accounting_posting_reference,

            'reversal_posting_date' =>
                $invoice
                    ->reversal_posting_date
                    ?->format('Y-m-d'),

            'reversed_at' =>
                $invoice
                    ->reversed_at
                    ?->toIso8601String(),

            'reversal_reason' =>
                $invoice->reversal_reason,

            'accounting_reversal_reference' =>
                $invoice
                    ->accounting_reversal_reference,

            'created_by' =>
                $this->userData(
                    $invoice->createdBy,
                ),

            'posted_by' =>
                $this->userData(
                    $invoice->postedBy,
                ),

            'reversed_by' =>
                $this->userData(
                    $invoice->reversedBy,
                ),

            'open_item' =>
                $invoice->openItem !== null
                    ? [
                        'id' =>
                            (int) $invoice
                                ->openItem
                                ->getKey(),

                        'status' =>
                            $invoice
                                ->openItem
                                ->status,

                        'original_amount' =>
                            (string) $invoice
                                ->openItem
                                ->original_amount,

                        'allocated_amount' =>
                            (string) $invoice
                                ->openItem
                                ->allocated_amount,

                        'outstanding_amount' =>
                            (string) $invoice
                                ->openItem
                                ->outstanding_amount,

                        'base_outstanding_amount' =>
                            (string) $invoice
                                ->openItem
                                ->base_outstanding_amount,
                    ]
                    : null,

            'lines' =>
                $invoice->lines
                    ->map(
                        static fn (
                            SalesInvoiceLine $line,
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

                            'invoiced_quantity' =>
                                (string) $line
                                    ->invoiced_quantity,

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

                            'unit_cost' =>
                                (string) $line
                                    ->unit_cost,

                            'total_cost' =>
                                (string) $line
                                    ->total_cost,

                            'dispatch_allocations' =>
                                $line
                                    ->dispatchAllocations
                                    ->map(
                                        static fn (
                                            SalesInvoiceDispatchAllocation $allocation,
                                        ): array => [
                                            'id' =>
                                                (int) $allocation
                                                    ->getKey(),

                                            'dispatch_number' =>
                                                $allocation
                                                    ->customerDispatchLine
                                                    ?->dispatch
                                                    ?->dispatch_number,

                                            'dispatch_date' =>
                                                $allocation
                                                    ->customerDispatchLine
                                                    ?->dispatch
                                                    ?->dispatch_date
                                                    ?->format(
                                                        'Y-m-d',
                                                    ),

                                            'allocated_quantity' =>
                                                (string) $allocation
                                                    ->allocated_quantity,

                                            'unit_cost' =>
                                                (string) $allocation
                                                    ->unit_cost,

                                            'total_cost' =>
                                                (string) $allocation
                                                    ->total_cost,
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
    private function formData(
        SalesInvoice $invoice,
    ): array {
        $invoice->load('lines');

        return [
            'id' =>
                (int) $invoice->getKey(),

            'sales_order_id' =>
                (int) $invoice
                    ->sales_order_id,

            'invoice_date' =>
                $invoice
                    ->invoice_date
                    ?->format('Y-m-d'),

            'posting_date' =>
                $invoice
                    ->posting_date
                    ?->format('Y-m-d'),

            'due_date' =>
                $invoice
                    ->due_date
                    ?->format('Y-m-d'),

            'billing_address' =>
                $invoice
                    ->billing_address,

            'shipping_address' =>
                $invoice
                    ->shipping_address,

            'shipping_amount' =>
                (string) $invoice
                    ->shipping_amount,

            'other_charges' =>
                (string) $invoice
                    ->other_charges,

            'notes' =>
                $invoice->notes,

            'status' =>
                $invoice->status,

            'revision' =>
                (int) $invoice->revision,

            'lines' =>
                $invoice->lines
                    ->map(
                        static fn (
                            SalesInvoiceLine $line,
                        ): array => [
                            'id' =>
                                (int) $line
                                    ->getKey(),

                            'sales_order_line_id' =>
                                (int) $line
                                    ->sales_order_line_id,

                            'invoiced_quantity' =>
                                (string) $line
                                    ->invoiced_quantity,

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
        SalesInvoice $invoice,
        User $actor,
    ): array {
        return [
            'view' =>
                $actor->can(
                    'view',
                    $invoice,
                ),

            'update' =>
                $actor->can(
                    'update',
                    $invoice,
                ),

            'delete' =>
                $actor->can(
                    'delete',
                    $invoice,
                ),

            'post' =>
                $actor->can(
                    'post',
                    $invoice,
                ),

            'reverse' =>
                $actor->can(
                    'reverse',
                    $invoice,
                ),

            'print' =>
                $actor->can(
                    'print',
                    $invoice,
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
                user: $actor,
                activeOnly: false,
            )
            ->map(
                static fn (
                    Branch $branch,
                ): array => [
                    'id' =>
                        (int) $branch->getKey(),

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
        return $user instanceof User
            ? [
                'id' =>
                    (int) $user->getKey(),

                'name' =>
                    $user->name,
            ]
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(
        SalesInvoice $invoice,
    ): array {
        return [
            'id' =>
                (int) $invoice->getKey(),

            'invoice_number' =>
                $invoice->invoice_number,

            'status' =>
                $invoice->status,
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