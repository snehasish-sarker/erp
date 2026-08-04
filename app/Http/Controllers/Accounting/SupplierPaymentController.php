<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CancelSupplierPaymentRequest;
use App\Http\Requests\Accounting\IndexSupplierPaymentRequest;
use App\Http\Requests\Accounting\ReverseSupplierPaymentRequest;
use App\Http\Requests\Accounting\StoreSupplierPaymentRequest;
use App\Http\Requests\Accounting\UpdateSupplierPaymentRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierOpenItem;
use App\Models\SupplierPayment;
use App\Models\SupplierPaymentAllocation;
use App\Models\User;
use App\Services\Accounting\SupplierPaymentService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\SupplierPaymentMethodRegistry;
use App\Support\Accounting\SupplierPaymentStatusRegistry;
use App\Support\Responses\CommonResponseService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class SupplierPaymentController extends Controller
{
    public function __construct(
        private readonly SupplierPaymentService $supplierPaymentService,
        private readonly SupplierPaymentStatusRegistry $statusRegistry,
        private readonly SupplierPaymentMethodRegistry $methodRegistry,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexSupplierPaymentRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            SupplierPayment::class,
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

        $supplierId = $this->validatedId(
            $validated,
            'supplier_id',
        );

        $paymentAccountId = $this->validatedId(
            $validated,
            'payment_account_id',
        );

        $status = (string) (
            $validated['status'] ?? ''
        );

        $paymentMethod = (string) (
            $validated['payment_method'] ?? ''
        );

        $paymentDateFrom = (string) (
            $validated['payment_date_from'] ?? ''
        );

        $paymentDateTo = (string) (
            $validated['payment_date_to'] ?? ''
        );

        $sort = (string) (
            $validated['sort'] ?? 'created_at'
        );

        $direction = (string) (
            $validated['direction'] ?? 'desc'
        );

        $perPage = (int) (
            $validated['per_page'] ?? 15
        );

        $query = SupplierPayment::query()
            ->with([
                'branch:id,name,code,status',
                'supplier:id,name,code,status',
                'paymentAccount:id,code,name,status,control_type',
                'createdBy:id,name',
                'submittedBy:id,name',
                'approvedBy:id,name',
                'postedBy:id,name',
                'reversedBy:id,name',
                'cancelledBy:id,name',
            ]);

        $this->branchAccessService->scopeQuery(
            query: $query,
            user: $actor,
            branchColumn:
                'supplier_payments.branch_id',
        );

        $supplierPayments = $query
            ->when(
                $search !== '',
                static function (
                    Builder $paymentQuery,
                ) use ($search): void {
                    $paymentQuery->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'payment_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'payment_reference',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'cheque_number',
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
                                    'payment_account_code',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'payment_account_name',
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
                    Builder $paymentQuery,
                ): Builder => $paymentQuery->where(
                    'branch_id',
                    $branchId,
                ),
            )
            ->when(
                $supplierId !== null,
                static fn (
                    Builder $paymentQuery,
                ): Builder => $paymentQuery->where(
                    'supplier_id',
                    $supplierId,
                ),
            )
            ->when(
                $paymentAccountId !== null,
                static fn (
                    Builder $paymentQuery,
                ): Builder => $paymentQuery->where(
                    'payment_account_id',
                    $paymentAccountId,
                ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $paymentQuery,
                ): Builder => $paymentQuery->where(
                    'status',
                    $status,
                ),
            )
            ->when(
                $paymentMethod !== '',
                static fn (
                    Builder $paymentQuery,
                ): Builder => $paymentQuery->where(
                    'payment_method',
                    $paymentMethod,
                ),
            )
            ->when(
                $paymentDateFrom !== '',
                static fn (
                    Builder $paymentQuery,
                ): Builder => $paymentQuery->whereDate(
                    'payment_date',
                    '>=',
                    $paymentDateFrom,
                ),
            )
            ->when(
                $paymentDateTo !== '',
                static fn (
                    Builder $paymentQuery,
                ): Builder => $paymentQuery->whereDate(
                    'payment_date',
                    '<=',
                    $paymentDateTo,
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
            'SupplierPayments/Index',
            [
                'supplierPayments' => [
                    'data' => $supplierPayments
                        ->getCollection()
                        ->map(
                            fn (
                                SupplierPayment $payment,
                            ): array => $this->summaryData(
                                $payment,
                                $actor,
                            ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $supplierPayments
                                ->currentPage(),

                        'last_page' =>
                            $supplierPayments
                                ->lastPage(),

                        'per_page' =>
                            $supplierPayments
                                ->perPage(),

                        'from' =>
                            $supplierPayments
                                ->firstItem(),

                        'to' =>
                            $supplierPayments
                                ->lastItem(),

                        'total' =>
                            $supplierPayments
                                ->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'branch_id' => $branchId,
                    'supplier_id' => $supplierId,
                    'payment_account_id' =>
                        $paymentAccountId,
                    'status' => $status,
                    'payment_method' =>
                        $paymentMethod,
                    'payment_date_from' =>
                        $paymentDateFrom,
                    'payment_date_to' =>
                        $paymentDateTo,
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
                        SupplierPayment::class,
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
            SupplierPayment::class,
        );

        return Inertia::render(
            'SupplierPayments/Create',
            $this->formOptions(
                actor: $this->actor($request),

                selectedBranchId:
                    $this->queryId(
                        $request,
                        'branch_id',
                    ),

                selectedSupplierId:
                    $this->queryId(
                        $request,
                        'supplier_id',
                    ),

                selectedPaymentAccountId:
                    null,

                selectedOpenItemIds:
                    [],
            ),
        );
    }

    public function store(
        StoreSupplierPaymentRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            SupplierPayment::class,
        );

        $supplierPayment =
            $this->supplierPaymentService
                ->create(
                    data:
                        $request->validated(),

                    actor:
                        $this->actor($request),
                );

        return $this->responseService
            ->success(
                message:
                    'Supplier Payment created successfully.',

                data:
                    $this->responseData(
                        $supplierPayment,
                    ),

                redirectTo: route(
                    'supplier-payments.show',
                    $supplierPayment,
                ),
            );
    }

    public function show(
        Request $request,
        SupplierPayment $supplierPayment,
    ): Response {
        Gate::authorize(
            'view',
            $supplierPayment,
        );

        $actor = $this->actor($request);

        $supplierPayment->load([
            'branch:id,name,code,status,address',
            'supplier:id,name,code,status,email,phone',
            'paymentAccount:id,code,name,status,account_type,account_subtype,control_type',
            'documentNumberAllocation',
            'allocations.supplierInvoice:id,document_number,supplier_invoice_number,invoice_date,due_date,status,total_amount,currency_code',
            'allocations.supplierOpenItem',
            'allocations.supplierOpenItemAllocation',
            'createdBy:id,name',
            'submittedBy:id,name',
            'approvedBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'cancelledBy:id,name',
            'journalEntries.lines.account:id,code,name,system_key',
            'supplierLedgerEntries.openItem',
            'supplierOpenItems',
        ]);

        return Inertia::render(
            'SupplierPayments/Show',
            [
                'supplierPayment' =>
                    $this->detailData(
                        $supplierPayment,
                        $actor,
                    ),
            ],
        );
    }

    public function edit(
        Request $request,
        SupplierPayment $supplierPayment,
    ): Response {
        Gate::authorize(
            'update',
            $supplierPayment,
        );

        $actor = $this->actor($request);

        $supplierPayment->load(
            'allocations',
        );

        $selectedOpenItemIds =
            $supplierPayment
                ->allocations
                ->pluck(
                    'supplier_open_item_id',
                )
                ->map(
                    static fn (
                        mixed $id,
                    ): int => (int) $id,
                )
                ->all();

        return Inertia::render(
            'SupplierPayments/Edit',
            [
                ...$this->formOptions(
                    actor: $actor,

                    selectedBranchId:
                        (int) $supplierPayment
                            ->branch_id,

                    selectedSupplierId:
                        (int) $supplierPayment
                            ->supplier_id,

                    selectedPaymentAccountId:
                        (int) $supplierPayment
                            ->payment_account_id,

                    selectedOpenItemIds:
                        $selectedOpenItemIds,
                ),

                'supplierPayment' =>
                    $this->formData(
                        $supplierPayment,
                    ),
            ],
        );
    }

    public function update(
        UpdateSupplierPaymentRequest $request,
        SupplierPayment $supplierPayment,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $supplierPayment,
        );

        $supplierPayment =
            $this->supplierPaymentService
                ->update(
                    supplierPayment:
                        $supplierPayment,

                    data:
                        $request->validated(),

                    actor:
                        $this->actor($request),
                );

        return $this->responseService
            ->success(
                message:
                    'Supplier Payment updated successfully.',

                data:
                    $this->responseData(
                        $supplierPayment,
                    ),

                redirectTo: route(
                    'supplier-payments.show',
                    $supplierPayment,
                ),
            );
    }

    public function destroy(
        Request $request,
        SupplierPayment $supplierPayment,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $supplierPayment,
        );

        $this->supplierPaymentService
            ->delete(
                supplierPayment:
                    $supplierPayment,

                actor:
                    $this->actor($request),
            );

        return $this->responseService
            ->success(
                message:
                    'Supplier Payment deleted permanently.',

                redirectTo: route(
                    'supplier-payments.index',
                ),
            );
    }

    public function submit(
        Request $request,
        SupplierPayment $supplierPayment,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'submit',
            $supplierPayment,
        );

        return $this->workflowResponse(
            supplierPayment:
                $this->supplierPaymentService
                    ->submit(
                        supplierPayment:
                            $supplierPayment,

                        actor:
                            $this->actor($request),
                    ),

            message:
                'Supplier Payment submitted successfully.',
        );
    }

    public function returnToDraft(
        Request $request,
        SupplierPayment $supplierPayment,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'returnToDraft',
            $supplierPayment,
        );

        return $this->workflowResponse(
            supplierPayment:
                $this->supplierPaymentService
                    ->returnToDraft(
                        supplierPayment:
                            $supplierPayment,

                        actor:
                            $this->actor($request),
                    ),

            message:
                'Supplier Payment returned to draft successfully.',
        );
    }

    public function approve(
        Request $request,
        SupplierPayment $supplierPayment,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'approve',
            $supplierPayment,
        );

        return $this->workflowResponse(
            supplierPayment:
                $this->supplierPaymentService
                    ->approve(
                        supplierPayment:
                            $supplierPayment,

                        actor:
                            $this->actor($request),
                    ),

            message:
                'Supplier Payment approved successfully.',
        );
    }

    public function cancel(
        CancelSupplierPaymentRequest $request,
        SupplierPayment $supplierPayment,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'cancel',
            $supplierPayment,
        );

        return $this->workflowResponse(
            supplierPayment:
                $this->supplierPaymentService
                    ->cancel(
                        supplierPayment:
                            $supplierPayment,

                        reason:
                            (string) $request
                                ->validated(
                                    'cancellation_reason',
                                ),

                        actor:
                            $this->actor($request),
                    ),

            message:
                'Supplier Payment cancelled successfully.',
        );
    }

    public function post(
        Request $request,
        SupplierPayment $supplierPayment,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'post',
            $supplierPayment,
        );

        return $this->workflowResponse(
            supplierPayment:
                $this->supplierPaymentService
                    ->post(
                        supplierPayment:
                            $supplierPayment,

                        actor:
                            $this->actor($request),
                    ),

            message:
                'Supplier Payment posted successfully.',
        );
    }

    public function reverse(
        ReverseSupplierPaymentRequest $request,
        SupplierPayment $supplierPayment,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'reverse',
            $supplierPayment,
        );

        return $this->workflowResponse(
            supplierPayment:
                $this->supplierPaymentService
                    ->reverse(
                        supplierPayment:
                            $supplierPayment,

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
                    ),

            message:
                'Supplier Payment reversed successfully.',
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
            ->whereIn(
                'id',
                SupplierPayment::query()
                    ->whereIn(
                        'branch_id',
                        $branchIds,
                    )
                    ->select(
                        'supplier_id',
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

        $paymentAccounts = Account::query()
            ->whereIn(
                'id',
                SupplierPayment::query()
                    ->whereIn(
                        'branch_id',
                        $branchIds,
                    )
                    ->select(
                        'payment_account_id',
                    ),
            )
            ->whereIn(
                'control_type',
                [
                    'cash',
                    'bank',
                ],
            )
            ->orderBy('code')
            ->orderBy('id')
            ->get([
                'id',
                'code',
                'name',
                'status',
                'control_type',
            ]);

        return [
            'branches' => $branches
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
                ->all(),

            'suppliers' => $suppliers
                ->map(
                    static fn (
                        Supplier $supplier,
                    ): array => [
                        'id' =>
                            (int) $supplier->getKey(),

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

            'paymentAccounts' =>
                $paymentAccounts
                    ->map(
                        static fn (
                            Account $account,
                        ): array => [
                            'id' =>
                                (int) $account
                                    ->getKey(),

                            'code' =>
                                $account->code,

                            'name' =>
                                $account->name,

                            'status' =>
                                $account->status,

                            'control_type' =>
                                $account
                                    ->control_type,
                        ],
                    )
                    ->values()
                    ->all(),

            'statuses' =>
                $this->statusRegistry
                    ->options(),

            'paymentMethods' =>
                $this->methodRegistry
                    ->options(),
        ];
    }

    /**
     * @param list<int> $selectedOpenItemIds
     * @return array<string, mixed>
     */
    private function formOptions(
        User $actor,
        ?int $selectedBranchId,
        ?int $selectedSupplierId,
        ?int $selectedPaymentAccountId,
        array $selectedOpenItemIds,
    ): array {
        $tenant = $this->tenantContext
            ->tenant();

        $branches =
            $this->branchAccessService
                ->accessibleBranches(
                    user: $actor,
                    activeOnly: false,
                )
                ->filter(
                    static fn (
                        Branch $branch,
                    ): bool =>
                        $branch->status === 'active'
                        || (int) $branch->getKey()
                            === $selectedBranchId,
                )
                ->values();

        $branchIds = $branches
            ->pluck('id')
            ->map(
                static fn (
                    mixed $id,
                ): int => (int) $id,
            )
            ->all();

        $suppliers = Supplier::query()
            ->where(
                static function (
                    Builder $query,
                ) use (
                    $selectedSupplierId,
                ): void {
                    $query->where(
                        'status',
                        'active',
                    );

                    if (
                        $selectedSupplierId
                        !== null
                    ) {
                        $query->orWhereKey(
                            $selectedSupplierId,
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
                'payment_terms_days',
            ]);

        $paymentAccounts = Account::query()
            ->where(
                'account_type',
                'asset',
            )
            ->where(
                'is_group',
                false,
            )
            ->whereIn(
                'control_type',
                [
                    'cash',
                    'bank',
                ],
            )
            ->whereColumn(
                'account_subtype',
                'control_type',
            )
            ->where(
                static function (
                    Builder $query,
                ) use (
                    $selectedPaymentAccountId,
                ): void {
                    $query->where(
                        'status',
                        'active',
                    );

                    if (
                        $selectedPaymentAccountId
                        !== null
                    ) {
                        $query->orWhereKey(
                            $selectedPaymentAccountId,
                        );
                    }
                },
            )
            ->orderBy('code')
            ->orderBy('id')
            ->get([
                'id',
                'code',
                'name',
                'account_subtype',
                'control_type',
                'status',
            ]);

        $invoiceMorphClass =
            (new SupplierInvoice())
                ->getMorphClass();

        $openItemQuery =
            SupplierOpenItem::query()
                ->with([
                    'supplier:id,name,code,status',
                    'source',
                ])
                ->whereIn(
                    'branch_id',
                    $branchIds,
                )
                ->where(
                    'item_type',
                    'invoice',
                )
                ->where(
                    'source_type',
                    $invoiceMorphClass,
                )
                ->where(
                    static function (
                        Builder $query,
                    ) use (
                        $selectedOpenItemIds,
                    ): void {
                        $query->where(
                            static function (
                                Builder $availableQuery,
                            ): void {
                                $availableQuery
                                    ->whereIn(
                                        'status',
                                        [
                                            'open',
                                            'partially_settled',
                                        ],
                                    )
                                    ->where(
                                        'outstanding_amount',
                                        '>',
                                        0,
                                    );
                            },
                        );

                        if (
                            $selectedOpenItemIds
                            !== []
                        ) {
                            $query->orWhereIn(
                                'id',
                                $selectedOpenItemIds,
                            );
                        }
                    },
                );

        $this->branchAccessService
            ->scopeQuery(
                query: $openItemQuery,
                user: $actor,
                branchColumn:
                    'supplier_open_items.branch_id',
            );

        /**
         * @var Collection<int, SupplierOpenItem>
         *     $openItems
         */
        $openItems = $openItemQuery
            ->orderBy('due_date')
            ->orderBy('document_date')
            ->orderBy('id')
            ->get();

        $today = now(
            $tenant->timezone,
        )->toDateString();

        return [
            'branches' => $branches
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
                ->all(),

            'suppliers' => $suppliers
                ->map(
                    static fn (
                        Supplier $supplier,
                    ): array => [
                        'id' =>
                            (int) $supplier->getKey(),

                        'name' =>
                            $supplier->name,

                        'code' =>
                            $supplier->code,

                        'status' =>
                            $supplier->status,

                        'payment_terms_days' =>
                            (int) $supplier
                                ->payment_terms_days,
                    ],
                )
                ->values()
                ->all(),

            'paymentAccounts' =>
                $paymentAccounts
                    ->map(
                        static fn (
                            Account $account,
                        ): array => [
                            'id' =>
                                (int) $account
                                    ->getKey(),

                            'code' =>
                                $account->code,

                            'name' =>
                                $account->name,

                            'account_subtype' =>
                                $account
                                    ->account_subtype,

                            'control_type' =>
                                $account
                                    ->control_type,

                            'status' =>
                                $account->status,
                        ],
                    )
                    ->values()
                    ->all(),

            'openItems' => $openItems
                ->map(
                    fn (
                        SupplierOpenItem $openItem,
                    ): array => $this
                        ->openItemOption(
                            $openItem,
                            $selectedOpenItemIds,
                        ),
                )
                ->values()
                ->all(),

            'paymentMethods' =>
                $this->methodRegistry
                    ->options(),

            'defaults' => [
                'payment_date' =>
                    $today,

                'posting_date' =>
                    $today,

                'currency_code' =>
                    mb_strtoupper(
                        (string) $tenant
                            ->currency_code,
                    ),

                'exchange_rate' =>
                    '1.00000000',

                'branch_id' =>
                    $selectedBranchId,

                'supplier_id' =>
                    $selectedSupplierId,
            ],
        ];
    }

    /**
     * @param list<int> $selectedOpenItemIds
     * @return array<string, mixed>
     */
    private function openItemOption(
        SupplierOpenItem $openItem,
        array $selectedOpenItemIds,
    ): array {
        $invoice = $openItem->source;

        if (
            !$invoice
                instanceof SupplierInvoice
        ) {
            throw new LogicException(
                'A Supplier Invoice open item does not retain its source invoice.',
            );
        }

        return [
            'id' =>
                (int) $openItem->getKey(),

            'branch_id' =>
                (int) $openItem->branch_id,

            'supplier_id' =>
                (int) $openItem->supplier_id,

            'supplier_invoice_id' =>
                (int) $invoice->getKey(),

            'document_number' =>
                $openItem->document_number,

            'supplier_invoice_number' =>
                $invoice
                    ->supplier_invoice_number,

            'document_date' =>
                $openItem
                    ->document_date
                    ->toDateString(),

            'due_date' =>
                $openItem
                    ->due_date
                    ?->toDateString(),

            'currency_code' =>
                $openItem->currency_code,

            'exchange_rate' =>
                $openItem->exchange_rate,

            'original_amount' =>
                $openItem->original_amount,

            'allocated_amount' =>
                $openItem->allocated_amount,

            'outstanding_amount' =>
                $openItem->outstanding_amount,

            'base_outstanding_amount' =>
                $openItem
                    ->base_outstanding_amount,

            'status' =>
                $openItem->status,

            'available' => in_array(
                $openItem->status,
                [
                    'open',
                    'partially_settled',
                ],
                true,
            )
                && (float) $openItem
                    ->outstanding_amount > 0,

            'selected' => in_array(
                (int) $openItem->getKey(),
                $selectedOpenItemIds,
                true,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryData(
        SupplierPayment $supplierPayment,
        User $actor,
    ): array {
        return [
            'id' =>
                (int) $supplierPayment
                    ->getKey(),

            'payment_number' =>
                $supplierPayment
                    ->payment_number,

            'payment_date' =>
                $supplierPayment
                    ->payment_date
                    ->toDateString(),

            'posting_date' =>
                $supplierPayment
                    ->posting_date
                    ->toDateString(),

            'branch' => [
                'id' =>
                    (int) $supplierPayment
                        ->branch_id,

                'name' =>
                    $supplierPayment
                        ->branch
                        ?->name,

                'code' =>
                    $supplierPayment
                        ->branch
                        ?->code,
            ],

            'supplier' => [
                'id' =>
                    (int) $supplierPayment
                        ->supplier_id,

                'name' =>
                    $supplierPayment
                        ->supplier_name,

                'code' =>
                    $supplierPayment
                        ->supplier_code,
            ],

            'payment_account' => [
                'id' =>
                    (int) $supplierPayment
                        ->payment_account_id,

                'code' =>
                    $supplierPayment
                        ->payment_account_code,

                'name' =>
                    $supplierPayment
                        ->payment_account_name,
            ],

            'payment_method' =>
                $supplierPayment
                    ->payment_method,

            'payment_method_label' =>
                $this->methodRegistry
                    ->label(
                        $supplierPayment
                            ->payment_method,
                    ),

            'payment_reference' =>
                $supplierPayment
                    ->payment_reference,

            'currency_code' =>
                $supplierPayment
                    ->currency_code,

            'total_amount' =>
                $supplierPayment
                    ->total_amount,

            'allocated_amount' =>
                $supplierPayment
                    ->allocated_amount,

            'unallocated_amount' =>
                $supplierPayment
                    ->unallocated_amount,

            'status' =>
                $supplierPayment->status,

            'status_label' =>
                $this->statusRegistry
                    ->label(
                        $supplierPayment
                            ->status,
                    ),

            'created_at' =>
                $supplierPayment
                    ->created_at
                    ?->toIso8601String(),

            'can' =>
                $this->actionPermissions(
                    $supplierPayment,
                    $actor,
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(
        SupplierPayment $supplierPayment,
        User $actor,
    ): array {
        return [
            ...$this->summaryData(
                $supplierPayment,
                $actor,
            ),

            'exchange_rate' =>
                $supplierPayment
                    ->exchange_rate,

            'cheque_number' =>
                $supplierPayment
                    ->cheque_number,

            'cheque_date' =>
                $supplierPayment
                    ->cheque_date
                    ?->toDateString(),

            'base_total_amount' =>
                $supplierPayment
                    ->base_total_amount,

            'base_allocated_amount' =>
                $supplierPayment
                    ->base_allocated_amount,

            'base_unallocated_amount' =>
                $supplierPayment
                    ->base_unallocated_amount,

            'notes' =>
                $supplierPayment->notes,

            'revision' =>
                (int) $supplierPayment
                    ->revision,

            'accounting_posting_reference' =>
                $supplierPayment
                    ->accounting_posting_reference,

            'accounting_reversal_reference' =>
                $supplierPayment
                    ->accounting_reversal_reference,

            'reversal_posting_date' =>
                $supplierPayment
                    ->reversal_posting_date
                    ?->toDateString(),

            'reversal_reason' =>
                $supplierPayment
                    ->reversal_reason,

            'cancellation_reason' =>
                $supplierPayment
                    ->cancellation_reason,

            'submitted_at' =>
                $supplierPayment
                    ->submitted_at
                    ?->toIso8601String(),

            'approved_at' =>
                $supplierPayment
                    ->approved_at
                    ?->toIso8601String(),

            'posted_at' =>
                $supplierPayment
                    ->posted_at
                    ?->toIso8601String(),

            'reversed_at' =>
                $supplierPayment
                    ->reversed_at
                    ?->toIso8601String(),

            'cancelled_at' =>
                $supplierPayment
                    ->cancelled_at
                    ?->toIso8601String(),

            'created_by' =>
                $this->userData(
                    $supplierPayment
                        ->createdBy,
                ),

            'submitted_by' =>
                $this->userData(
                    $supplierPayment
                        ->submittedBy,
                ),

            'approved_by' =>
                $this->userData(
                    $supplierPayment
                        ->approvedBy,
                ),

            'posted_by' =>
                $this->userData(
                    $supplierPayment
                        ->postedBy,
                ),

            'reversed_by' =>
                $this->userData(
                    $supplierPayment
                        ->reversedBy,
                ),

            'cancelled_by' =>
                $this->userData(
                    $supplierPayment
                        ->cancelledBy,
                ),

            'allocations' =>
                $supplierPayment
                    ->allocations
                    ->map(
                        static fn (
                            SupplierPaymentAllocation $allocation,
                        ): array => [
                            'id' =>
                                (int) $allocation
                                    ->getKey(),

                            'line_number' =>
                                (int) $allocation
                                    ->line_number,

                            'supplier_open_item_id' =>
                                (int) $allocation
                                    ->supplier_open_item_id,

                            'supplier_invoice_id' =>
                                (int) $allocation
                                    ->supplier_invoice_id,

                            'invoice_document_number' =>
                                $allocation
                                    ->invoice_document_number,

                            'invoice_due_date' =>
                                $allocation
                                    ->invoice_due_date
                                    ?->toDateString(),

                            'currency_code' =>
                                $allocation
                                    ->currency_code,

                            'invoice_exchange_rate' =>
                                $allocation
                                    ->invoice_exchange_rate,

                            'payment_exchange_rate' =>
                                $allocation
                                    ->payment_exchange_rate,

                            'amount' =>
                                $allocation
                                    ->amount,

                            'payable_base_amount' =>
                                $allocation
                                    ->payable_base_amount,

                            'credit_base_amount' =>
                                $allocation
                                    ->credit_base_amount,

                            'exchange_difference_amount' =>
                                $allocation
                                    ->exchange_difference_amount,

                            'status' =>
                                $allocation
                                    ->status,

                            'applied_at' =>
                                $allocation
                                    ->applied_at
                                    ?->toIso8601String(),

                            'reversed_at' =>
                                $allocation
                                    ->reversed_at
                                    ?->toIso8601String(),
                        ],
                    )
                    ->values()
                    ->all(),

            'journal_entries' =>
                $supplierPayment
                    ->journalEntries
                    ->map(
                        static fn (
                            $journalEntry,
                        ): array => [
                            'id' =>
                                (int) $journalEntry
                                    ->getKey(),

                            'journal_number' =>
                                $journalEntry
                                    ->journal_number,

                            'journal_type' =>
                                $journalEntry
                                    ->journal_type,

                            'status' =>
                                $journalEntry
                                    ->status,

                            'posting_date' =>
                                $journalEntry
                                    ->posting_date
                                    ->toDateString(),

                            'total_debit' =>
                                $journalEntry
                                    ->total_debit,

                            'total_credit' =>
                                $journalEntry
                                    ->total_credit,

                            'base_total_debit' =>
                                $journalEntry
                                    ->base_total_debit,

                            'base_total_credit' =>
                                $journalEntry
                                    ->base_total_credit,
                        ],
                    )
                    ->values()
                    ->all(),

            'supplier_ledger_entries' =>
                $supplierPayment
                    ->supplierLedgerEntries
                    ->map(
                        static fn (
                            $entry,
                        ): array => [
                            'id' =>
                                (int) $entry
                                    ->getKey(),

                            'reference' =>
                                $entry
                                    ->reference,

                            'journal_reference' =>
                                $entry
                                    ->journal_reference,

                            'entry_type' =>
                                $entry
                                    ->entry_type,

                            'posting_date' =>
                                $entry
                                    ->posting_date
                                    ->toDateString(),

                            'debit_amount' =>
                                $entry
                                    ->debit_amount,

                            'credit_amount' =>
                                $entry
                                    ->credit_amount,

                            'base_debit_amount' =>
                                $entry
                                    ->base_debit_amount,

                            'base_credit_amount' =>
                                $entry
                                    ->base_credit_amount,
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
        SupplierPayment $supplierPayment,
    ): array {
        return [
            'id' =>
                (int) $supplierPayment
                    ->getKey(),

            'branch_id' =>
                (int) $supplierPayment
                    ->branch_id,

            'supplier_id' =>
                (int) $supplierPayment
                    ->supplier_id,

            'payment_account_id' =>
                (int) $supplierPayment
                    ->payment_account_id,

            'payment_number' =>
                $supplierPayment
                    ->payment_number,

            'payment_date' =>
                $supplierPayment
                    ->payment_date
                    ->toDateString(),

            'posting_date' =>
                $supplierPayment
                    ->posting_date
                    ->toDateString(),

            'currency_code' =>
                $supplierPayment
                    ->currency_code,

            'exchange_rate' =>
                $supplierPayment
                    ->exchange_rate,

            'payment_method' =>
                $supplierPayment
                    ->payment_method,

            'payment_reference' =>
                $supplierPayment
                    ->payment_reference,

            'cheque_number' =>
                $supplierPayment
                    ->cheque_number,

            'cheque_date' =>
                $supplierPayment
                    ->cheque_date
                    ?->toDateString(),

            'total_amount' =>
                $supplierPayment
                    ->total_amount,

            'notes' =>
                $supplierPayment
                    ->notes,

            'revision' =>
                (int) $supplierPayment
                    ->revision,

            'allocations' =>
                $supplierPayment
                    ->allocations
                    ->map(
                        static fn (
                            SupplierPaymentAllocation $allocation,
                        ): array => [
                            'supplier_open_item_id' =>
                                (int) $allocation
                                    ->supplier_open_item_id,

                            'amount' =>
                                $allocation
                                    ->amount,
                        ],
                    )
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function actionPermissions(
        SupplierPayment $supplierPayment,
        User $actor,
    ): array {
        return [
            'view' => $actor->can(
                'view',
                $supplierPayment,
            ),

            'update' => $actor->can(
                'update',
                $supplierPayment,
            ),

            'delete' => $actor->can(
                'delete',
                $supplierPayment,
            ),

            'submit' => $actor->can(
                'submit',
                $supplierPayment,
            ),

            'return_to_draft' => $actor->can(
                'returnToDraft',
                $supplierPayment,
            ),

            'approve' => $actor->can(
                'approve',
                $supplierPayment,
            ),

            'cancel' => $actor->can(
                'cancel',
                $supplierPayment,
            ),

            'post' => $actor->can(
                'post',
                $supplierPayment,
            ),

            'reverse' => $actor->can(
                'reverse',
                $supplierPayment,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(
        SupplierPayment $supplierPayment,
    ): array {
        return [
            'id' =>
                (int) $supplierPayment
                    ->getKey(),

            'payment_number' =>
                $supplierPayment
                    ->payment_number,

            'status' =>
                $supplierPayment
                    ->status,

            'total_amount' =>
                $supplierPayment
                    ->total_amount,

            'allocated_amount' =>
                $supplierPayment
                    ->allocated_amount,

            'unallocated_amount' =>
                $supplierPayment
                    ->unallocated_amount,

            'accounting_posting_reference' =>
                $supplierPayment
                    ->accounting_posting_reference,

            'accounting_reversal_reference' =>
                $supplierPayment
                    ->accounting_reversal_reference,
        ];
    }

    private function workflowResponse(
        SupplierPayment $supplierPayment,
        string $message,
    ): JsonResponse|RedirectResponse {
        return $this->responseService
            ->success(
                message: $message,

                data:
                    $this->responseData(
                        $supplierPayment,
                    ),

                redirectTo: route(
                    'supplier-payments.show',
                    $supplierPayment,
                ),
            );
    }

    /**
     * @return array{id: int, name: string}|null
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

    private function actor(
        Request $request,
    ): User {
        $actor = $request->user();

        if (!$actor instanceof User) {
            throw new LogicException(
                'An authenticated user is required.',
            );
        }

        return $actor;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function validatedId(
        array $validated,
        string $field,
    ): ?int {
        $value = $validated[$field] ?? null;

        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return (int) $value;
    }

    private function queryId(
        Request $request,
        string $field,
    ): ?int {
        $value = $request->query($field);

        if (
            $value === null
            || $value === ''
            || filter_var(
                $value,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ],
            ) === false
        ) {
            return null;
        }

        return (int) $value;
    }
}