<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CancelCustomerReceiptRequest;
use App\Http\Requests\Accounting\IndexCustomerReceiptRequest;
use App\Http\Requests\Accounting\ReverseCustomerReceiptRequest;
use App\Http\Requests\Accounting\StoreCustomerReceiptRequest;
use App\Http\Requests\Accounting\UpdateCustomerReceiptRequest;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\SalesInvoice;
use App\Models\CustomerOpenItem;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptAllocation;
use App\Models\User;
use App\Services\Accounting\CustomerReceiptService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\CustomerReceiptMethodRegistry;
use App\Support\Accounting\CustomerReceiptStatusRegistry;
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

final class CustomerReceiptController extends Controller
{
    public function __construct(
        private readonly CustomerReceiptService $customerReceiptService,
        private readonly CustomerReceiptStatusRegistry $statusRegistry,
        private readonly CustomerReceiptMethodRegistry $methodRegistry,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexCustomerReceiptRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            CustomerReceipt::class,
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

        $customerId = $this->validatedId(
            $validated,
            'customer_id',
        );

        $receiptAccountId = $this->validatedId(
            $validated,
            'receipt_account_id',
        );

        $status = (string) (
            $validated['status'] ?? ''
        );

        $receiptMethod = (string) (
            $validated['receipt_method'] ?? ''
        );

        $receiptDateFrom = (string) (
            $validated['receipt_date_from'] ?? ''
        );

        $receiptDateTo = (string) (
            $validated['receipt_date_to'] ?? ''
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

        $query = CustomerReceipt::query()
            ->with([
                'branch:id,name,code,status',
                'customer:id,name,code,status',
                'receiptAccount:id,code,name,status,control_type',
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
                'customer_receipts.branch_id',
        );

        $customerReceipts = $query
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
                                    'receipt_reference',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'cheque_number',
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
                                    'receipt_account_code',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'receipt_account_name',
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
                ): Builder => $receiptQuery->where(
                    'branch_id',
                    $branchId,
                ),
            )
            ->when(
                $customerId !== null,
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery->where(
                    'customer_id',
                    $customerId,
                ),
            )
            ->when(
                $receiptAccountId !== null,
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery->where(
                    'receipt_account_id',
                    $receiptAccountId,
                ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery->where(
                    'status',
                    $status,
                ),
            )
            ->when(
                $receiptMethod !== '',
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery->where(
                    'receipt_method',
                    $receiptMethod,
                ),
            )
            ->when(
                $receiptDateFrom !== '',
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery->whereDate(
                    'receipt_date',
                    '>=',
                    $receiptDateFrom,
                ),
            )
            ->when(
                $receiptDateTo !== '',
                static fn (
                    Builder $receiptQuery,
                ): Builder => $receiptQuery->whereDate(
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
            'CustomerReceipts/Index',
            [
                'customerReceipts' => [
                    'data' => $customerReceipts
                        ->getCollection()
                        ->map(
                            fn (
                                CustomerReceipt $receipt,
                            ): array => $this->summaryData(
                                $receipt,
                                $actor,
                            ),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $customerReceipts
                                ->currentPage(),

                        'last_page' =>
                            $customerReceipts
                                ->lastPage(),

                        'per_page' =>
                            $customerReceipts
                                ->perPage(),

                        'from' =>
                            $customerReceipts
                                ->firstItem(),

                        'to' =>
                            $customerReceipts
                                ->lastItem(),

                        'total' =>
                            $customerReceipts
                                ->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'branch_id' => $branchId,
                    'customer_id' => $customerId,
                    'receipt_account_id' =>
                        $receiptAccountId,
                    'status' => $status,
                    'receipt_method' =>
                        $receiptMethod,
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
                        CustomerReceipt::class,
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
            CustomerReceipt::class,
        );

        return Inertia::render(
            'CustomerReceipts/Create',
            $this->formOptions(
                actor: $this->actor($request),

                selectedBranchId:
                    $this->queryId(
                        $request,
                        'branch_id',
                    ),

                selectedCustomerId:
                    $this->queryId(
                        $request,
                        'customer_id',
                    ),

                selectedReceiptAccountId:
                    null,

                selectedOpenItemIds:
                    [],
            ),
        );
    }

    public function store(
        StoreCustomerReceiptRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            CustomerReceipt::class,
        );

        $customerReceipt =
            $this->customerReceiptService
                ->create(
                    data:
                        $request->validated(),

                    actor:
                        $this->actor($request),
                );

        return $this->responseService
            ->success(
                message:
                    'Customer Receipt created successfully.',

                data:
                    $this->responseData(
                        $customerReceipt,
                    ),

                redirectTo: route(
                    'customer-receipts.show',
                    $customerReceipt,
                ),
            );
    }

    public function show(
        Request $request,
        CustomerReceipt $customerReceipt,
    ): Response {
        Gate::authorize(
            'view',
            $customerReceipt,
        );

        $actor = $this->actor($request);

        $customerReceipt->load([
            'branch:id,name,code,status,address',
            'customer:id,name,code,status,email,phone',
            'receiptAccount:id,code,name,status,account_type,account_subtype,control_type',
            'documentNumberAllocation',
            'allocations.salesInvoice:id,invoice_number,invoice_date,due_date,status,total_amount,currency_code',
            'allocations.customerOpenItem',
            'allocations.customerOpenItemAllocation',
            'createdBy:id,name',
            'submittedBy:id,name',
            'approvedBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'cancelledBy:id,name',
            'journalEntries.lines.account:id,code,name,system_key',
            'customerLedgerEntries.openItem',
            'customerOpenItems',
        ]);

        return Inertia::render(
            'CustomerReceipts/Show',
            [
                'customerReceipt' =>
                    $this->detailData(
                        $customerReceipt,
                        $actor,
                    ),
            ],
        );
    }

    public function print(
        Request $request,
        CustomerReceipt $customerReceipt,
    ): Response {
        Gate::authorize(
            'print',
            $customerReceipt,
        );

        $actor = $this->actor($request);

        $customerReceipt->load([
            'branch:id,name,code,status,address,phone,email',
            'customer:id,name,code,status,email,phone',
            'receiptAccount:id,code,name,status,account_type,account_subtype,control_type',
            'allocations.salesInvoice:id,invoice_number,invoice_date,due_date,status,total_amount,currency_code',
            'createdBy:id,name',
            'submittedBy:id,name',
            'approvedBy:id,name',
            'postedBy:id,name',
            'reversedBy:id,name',
            'cancelledBy:id,name',
            'journalEntries.lines.account:id,code,name,system_key',
            'customerLedgerEntries.openItem',
        ]);

        $tenant = $this->tenantContext->tenant();

        return Inertia::render(
            'CustomerReceipts/Print/CustomerReceipt',
            [
                'customerReceipt' =>
                    $this->detailData(
                        $customerReceipt,
                        $actor,
                    ),

                'company' => [
                    'name' => $tenant->name,
                    'code' => $tenant->code,
                    'email' => $tenant->email,
                    'phone' => $tenant->phone,
                    'address' => $tenant->address,
                ],
            ],
        );
    }

    public function edit(
        Request $request,
        CustomerReceipt $customerReceipt,
    ): Response {
        Gate::authorize(
            'update',
            $customerReceipt,
        );

        $actor = $this->actor($request);

        $customerReceipt->load(
            'allocations',
        );

        $selectedOpenItemIds =
            $customerReceipt
                ->allocations
                ->pluck(
                    'customer_open_item_id',
                )
                ->map(
                    static fn (
                        mixed $id,
                    ): int => (int) $id,
                )
                ->all();

        return Inertia::render(
            'CustomerReceipts/Edit',
            [
                ...$this->formOptions(
                    actor: $actor,

                    selectedBranchId:
                        (int) $customerReceipt
                            ->branch_id,

                    selectedCustomerId:
                        (int) $customerReceipt
                            ->customer_id,

                    selectedReceiptAccountId:
                        (int) $customerReceipt
                            ->receipt_account_id,

                    selectedOpenItemIds:
                        $selectedOpenItemIds,
                ),

                'customerReceipt' =>
                    $this->formData(
                        $customerReceipt,
                    ),
            ],
        );
    }

    public function update(
        UpdateCustomerReceiptRequest $request,
        CustomerReceipt $customerReceipt,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $customerReceipt,
        );

        $customerReceipt =
            $this->customerReceiptService
                ->update(
                    customerReceipt:
                        $customerReceipt,

                    data:
                        $request->validated(),

                    actor:
                        $this->actor($request),
                );

        return $this->responseService
            ->success(
                message:
                    'Customer Receipt updated successfully.',

                data:
                    $this->responseData(
                        $customerReceipt,
                    ),

                redirectTo: route(
                    'customer-receipts.show',
                    $customerReceipt,
                ),
            );
    }

    public function destroy(
        Request $request,
        CustomerReceipt $customerReceipt,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $customerReceipt,
        );

        $this->customerReceiptService
            ->delete(
                customerReceipt:
                    $customerReceipt,

                actor:
                    $this->actor($request),
            );

        return $this->responseService
            ->success(
                message:
                    'Customer Receipt deleted permanently.',

                redirectTo: route(
                    'customer-receipts.index',
                ),
            );
    }

    public function submit(
        Request $request,
        CustomerReceipt $customerReceipt,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'submit',
            $customerReceipt,
        );

        return $this->workflowResponse(
            customerReceipt:
                $this->customerReceiptService
                    ->submit(
                        customerReceipt:
                            $customerReceipt,

                        actor:
                            $this->actor($request),
                    ),

            message:
                'Customer Receipt submitted successfully.',
        );
    }

    public function returnToDraft(
        Request $request,
        CustomerReceipt $customerReceipt,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'returnToDraft',
            $customerReceipt,
        );

        return $this->workflowResponse(
            customerReceipt:
                $this->customerReceiptService
                    ->returnToDraft(
                        customerReceipt:
                            $customerReceipt,

                        actor:
                            $this->actor($request),
                    ),

            message:
                'Customer Receipt returned to draft successfully.',
        );
    }

    public function approve(
        Request $request,
        CustomerReceipt $customerReceipt,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'approve',
            $customerReceipt,
        );

        return $this->workflowResponse(
            customerReceipt:
                $this->customerReceiptService
                    ->approve(
                        customerReceipt:
                            $customerReceipt,

                        actor:
                            $this->actor($request),
                    ),

            message:
                'Customer Receipt approved successfully.',
        );
    }

    public function cancel(
        CancelCustomerReceiptRequest $request,
        CustomerReceipt $customerReceipt,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'cancel',
            $customerReceipt,
        );

        return $this->workflowResponse(
            customerReceipt:
                $this->customerReceiptService
                    ->cancel(
                        customerReceipt:
                            $customerReceipt,

                        reason:
                            (string) $request
                                ->validated(
                                    'cancellation_reason',
                                ),

                        actor:
                            $this->actor($request),
                    ),

            message:
                'Customer Receipt cancelled successfully.',
        );
    }

    public function post(
        Request $request,
        CustomerReceipt $customerReceipt,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'post',
            $customerReceipt,
        );

        return $this->workflowResponse(
            customerReceipt:
                $this->customerReceiptService
                    ->post(
                        customerReceipt:
                            $customerReceipt,

                        actor:
                            $this->actor($request),
                    ),

            message:
                'Customer Receipt posted successfully.',
        );
    }

    public function reverse(
        ReverseCustomerReceiptRequest $request,
        CustomerReceipt $customerReceipt,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'reverse',
            $customerReceipt,
        );

        return $this->workflowResponse(
            customerReceipt:
                $this->customerReceiptService
                    ->reverse(
                        customerReceipt:
                            $customerReceipt,

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
                'Customer Receipt reversed successfully.',
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

        $customers = Customer::query()
            ->whereIn(
                'id',
                CustomerReceipt::query()
                    ->whereIn(
                        'branch_id',
                        $branchIds,
                    )
                    ->select(
                        'customer_id',
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

        $receiptAccounts = Account::query()
            ->whereIn(
                'id',
                CustomerReceipt::query()
                    ->whereIn(
                        'branch_id',
                        $branchIds,
                    )
                    ->select(
                        'receipt_account_id',
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

            'customers' => $customers
                ->map(
                    static fn (
                        Customer $customer,
                    ): array => [
                        'id' =>
                            (int) $customer->getKey(),

                        'name' =>
                            $customer->name,

                        'code' =>
                            $customer->code,

                        'status' =>
                            $customer->status,
                    ],
                )
                ->values()
                ->all(),

            'receiptAccounts' =>
                $receiptAccounts
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

            'receiptMethods' =>
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
        ?int $selectedCustomerId,
        ?int $selectedReceiptAccountId,
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

        $customers = Customer::query()
            ->where(
                static function (
                    Builder $query,
                ) use (
                    $selectedCustomerId,
                ): void {
                    $query->where(
                        'status',
                        'active',
                    );

                    if (
                        $selectedCustomerId
                        !== null
                    ) {
                        $query->orWhereKey(
                            $selectedCustomerId,
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

        $receiptAccounts = Account::query()
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
                    $selectedReceiptAccountId,
                ): void {
                    $query->where(
                        'status',
                        'active',
                    );

                    if (
                        $selectedReceiptAccountId
                        !== null
                    ) {
                        $query->orWhereKey(
                            $selectedReceiptAccountId,
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
            (new SalesInvoice())
                ->getMorphClass();

        $openItemQuery =
            CustomerOpenItem::query()
                ->with([
                    'customer:id,name,code,status',
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
                    'customer_open_items.branch_id',
            );

        /**
         * @var Collection<int, CustomerOpenItem>
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

            'customers' => $customers
                ->map(
                    static fn (
                        Customer $customer,
                    ): array => [
                        'id' =>
                            (int) $customer->getKey(),

                        'name' =>
                            $customer->name,

                        'code' =>
                            $customer->code,

                        'status' =>
                            $customer->status,

                        'payment_terms_days' =>
                            (int) $customer
                                ->payment_terms_days,
                    ],
                )
                ->values()
                ->all(),

            'receiptAccounts' =>
                $receiptAccounts
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
                        CustomerOpenItem $openItem,
                    ): array => $this
                        ->openItemOption(
                            $openItem,
                            $selectedOpenItemIds,
                        ),
                )
                ->values()
                ->all(),

            'receiptMethods' =>
                $this->methodRegistry
                    ->options(),

            'defaults' => [
                'receipt_date' =>
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

                'customer_id' =>
                    $selectedCustomerId,
            ],
        ];
    }

    /**
     * @param list<int> $selectedOpenItemIds
     * @return array<string, mixed>
     */
    private function openItemOption(
        CustomerOpenItem $openItem,
        array $selectedOpenItemIds,
    ): array {
        $invoice = $openItem->source;

        if (
            !$invoice
                instanceof SalesInvoice
        ) {
            throw new LogicException(
                'A Sales Invoice open item does not retain its source invoice.',
            );
        }

        return [
            'id' =>
                (int) $openItem->getKey(),

            'branch_id' =>
                (int) $openItem->branch_id,

            'customer_id' =>
                (int) $openItem->customer_id,

            'sales_invoice_id' =>
                (int) $invoice->getKey(),

            'document_number' =>
                $openItem->document_number,

            'sales_invoice_number' =>
                $invoice
                    ->invoice_number,

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
        CustomerReceipt $customerReceipt,
        User $actor,
    ): array {
        return [
            'id' =>
                (int) $customerReceipt
                    ->getKey(),

            'receipt_number' =>
                $customerReceipt
                    ->receipt_number,

            'receipt_date' =>
                $customerReceipt
                    ->receipt_date
                    ->toDateString(),

            'posting_date' =>
                $customerReceipt
                    ->posting_date
                    ->toDateString(),

            'branch' => [
                'id' =>
                    (int) $customerReceipt
                        ->branch_id,

                'name' =>
                    $customerReceipt
                        ->branch
                        ?->name,

                'code' =>
                    $customerReceipt
                        ->branch
                        ?->code,
            ],

            'customer' => [
                'id' =>
                    (int) $customerReceipt
                        ->customer_id,

                'name' =>
                    $customerReceipt
                        ->customer_name,

                'code' =>
                    $customerReceipt
                        ->customer_code,
            ],

            'receipt_account' => [
                'id' =>
                    (int) $customerReceipt
                        ->receipt_account_id,

                'code' =>
                    $customerReceipt
                        ->receipt_account_code,

                'name' =>
                    $customerReceipt
                        ->receipt_account_name,
            ],

            'receipt_method' =>
                $customerReceipt
                    ->receipt_method,

            'receipt_method_label' =>
                $this->methodRegistry
                    ->label(
                        $customerReceipt
                            ->receipt_method,
                    ),

            'receipt_reference' =>
                $customerReceipt
                    ->receipt_reference,

            'currency_code' =>
                $customerReceipt
                    ->currency_code,

            'total_amount' =>
                $customerReceipt
                    ->total_amount,

            'allocated_amount' =>
                $customerReceipt
                    ->allocated_amount,

            'unallocated_amount' =>
                $customerReceipt
                    ->unallocated_amount,

            'status' =>
                $customerReceipt->status,

            'status_label' =>
                $this->statusRegistry
                    ->label(
                        $customerReceipt
                            ->status,
                    ),

            'created_at' =>
                $customerReceipt
                    ->created_at
                    ?->toIso8601String(),

            'can' =>
                $this->actionPermissions(
                    $customerReceipt,
                    $actor,
                ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailData(
        CustomerReceipt $customerReceipt,
        User $actor,
    ): array {
        return [
            ...$this->summaryData(
                $customerReceipt,
                $actor,
            ),

            'exchange_rate' =>
                $customerReceipt
                    ->exchange_rate,

            'cheque_number' =>
                $customerReceipt
                    ->cheque_number,

            'cheque_date' =>
                $customerReceipt
                    ->cheque_date
                    ?->toDateString(),

            'base_total_amount' =>
                $customerReceipt
                    ->base_total_amount,

            'base_allocated_amount' =>
                $customerReceipt
                    ->base_allocated_amount,

            'base_unallocated_amount' =>
                $customerReceipt
                    ->base_unallocated_amount,

            'notes' =>
                $customerReceipt->notes,

            'revision' =>
                (int) $customerReceipt
                    ->revision,

            'accounting_posting_reference' =>
                $customerReceipt
                    ->accounting_posting_reference,

            'accounting_reversal_reference' =>
                $customerReceipt
                    ->accounting_reversal_reference,

            'reversal_posting_date' =>
                $customerReceipt
                    ->reversal_posting_date
                    ?->toDateString(),

            'reversal_reason' =>
                $customerReceipt
                    ->reversal_reason,

            'cancellation_reason' =>
                $customerReceipt
                    ->cancellation_reason,

            'submitted_at' =>
                $customerReceipt
                    ->submitted_at
                    ?->toIso8601String(),

            'approved_at' =>
                $customerReceipt
                    ->approved_at
                    ?->toIso8601String(),

            'posted_at' =>
                $customerReceipt
                    ->posted_at
                    ?->toIso8601String(),

            'reversed_at' =>
                $customerReceipt
                    ->reversed_at
                    ?->toIso8601String(),

            'cancelled_at' =>
                $customerReceipt
                    ->cancelled_at
                    ?->toIso8601String(),

            'created_by' =>
                $this->userData(
                    $customerReceipt
                        ->createdBy,
                ),

            'submitted_by' =>
                $this->userData(
                    $customerReceipt
                        ->submittedBy,
                ),

            'approved_by' =>
                $this->userData(
                    $customerReceipt
                        ->approvedBy,
                ),

            'posted_by' =>
                $this->userData(
                    $customerReceipt
                        ->postedBy,
                ),

            'reversed_by' =>
                $this->userData(
                    $customerReceipt
                        ->reversedBy,
                ),

            'cancelled_by' =>
                $this->userData(
                    $customerReceipt
                        ->cancelledBy,
                ),

            'allocations' =>
                $customerReceipt
                    ->allocations
                    ->map(
                        static fn (
                            CustomerReceiptAllocation $allocation,
                        ): array => [
                            'id' =>
                                (int) $allocation
                                    ->getKey(),

                            'line_number' =>
                                (int) $allocation
                                    ->line_number,

                            'customer_open_item_id' =>
                                (int) $allocation
                                    ->customer_open_item_id,

                            'sales_invoice_id' =>
                                (int) $allocation
                                    ->sales_invoice_id,

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

                            'receipt_exchange_rate' =>
                                $allocation
                                    ->receipt_exchange_rate,

                            'amount' =>
                                $allocation
                                    ->amount,

                            'receivable_base_amount' =>
                                $allocation
                                    ->receivable_base_amount,

                            'receipt_base_amount' =>
                                $allocation
                                    ->receipt_base_amount,

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
                $customerReceipt
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

            'customer_ledger_entries' =>
                $customerReceipt
                    ->customerLedgerEntries
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
        CustomerReceipt $customerReceipt,
    ): array {
        return [
            'id' =>
                (int) $customerReceipt
                    ->getKey(),

            'branch_id' =>
                (int) $customerReceipt
                    ->branch_id,

            'customer_id' =>
                (int) $customerReceipt
                    ->customer_id,

            'receipt_account_id' =>
                (int) $customerReceipt
                    ->receipt_account_id,

            'receipt_number' =>
                $customerReceipt
                    ->receipt_number,

            'receipt_date' =>
                $customerReceipt
                    ->receipt_date
                    ->toDateString(),

            'posting_date' =>
                $customerReceipt
                    ->posting_date
                    ->toDateString(),

            'currency_code' =>
                $customerReceipt
                    ->currency_code,

            'exchange_rate' =>
                $customerReceipt
                    ->exchange_rate,

            'receipt_method' =>
                $customerReceipt
                    ->receipt_method,

            'receipt_reference' =>
                $customerReceipt
                    ->receipt_reference,

            'cheque_number' =>
                $customerReceipt
                    ->cheque_number,

            'cheque_date' =>
                $customerReceipt
                    ->cheque_date
                    ?->toDateString(),

            'total_amount' =>
                $customerReceipt
                    ->total_amount,

            'notes' =>
                $customerReceipt
                    ->notes,

            'revision' =>
                (int) $customerReceipt
                    ->revision,

            'allocations' =>
                $customerReceipt
                    ->allocations
                    ->map(
                        static fn (
                            CustomerReceiptAllocation $allocation,
                        ): array => [
                            'customer_open_item_id' =>
                                (int) $allocation
                                    ->customer_open_item_id,

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
        CustomerReceipt $customerReceipt,
        User $actor,
    ): array {
        return [
            'view' => $actor->can(
                'view',
                $customerReceipt,
            ),

            'update' => $actor->can(
                'update',
                $customerReceipt,
            ),

            'delete' => $actor->can(
                'delete',
                $customerReceipt,
            ),

            'submit' => $actor->can(
                'submit',
                $customerReceipt,
            ),

            'return_to_draft' => $actor->can(
                'returnToDraft',
                $customerReceipt,
            ),

            'approve' => $actor->can(
                'approve',
                $customerReceipt,
            ),

            'cancel' => $actor->can(
                'cancel',
                $customerReceipt,
            ),

            'post' => $actor->can(
                'post',
                $customerReceipt,
            ),

            'reverse' => $actor->can(
                'reverse',
                $customerReceipt,
            ),

            'print' => $actor->can(
                'print',
                $customerReceipt,
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(
        CustomerReceipt $customerReceipt,
    ): array {
        return [
            'id' =>
                (int) $customerReceipt
                    ->getKey(),

            'receipt_number' =>
                $customerReceipt
                    ->receipt_number,

            'status' =>
                $customerReceipt
                    ->status,

            'total_amount' =>
                $customerReceipt
                    ->total_amount,

            'allocated_amount' =>
                $customerReceipt
                    ->allocated_amount,

            'unallocated_amount' =>
                $customerReceipt
                    ->unallocated_amount,

            'accounting_posting_reference' =>
                $customerReceipt
                    ->accounting_posting_reference,

            'accounting_reversal_reference' =>
                $customerReceipt
                    ->accounting_reversal_reference,
        ];
    }

    private function workflowResponse(
        CustomerReceipt $customerReceipt,
        string $message,
    ): JsonResponse|RedirectResponse {
        return $this->responseService
            ->success(
                message: $message,

                data:
                    $this->responseData(
                        $customerReceipt,
                    ),

                redirectTo: route(
                    'customer-receipts.show',
                    $customerReceipt,
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