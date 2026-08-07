<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CustomerSettlementReasonRequest;
use App\Http\Requests\Accounting\StoreCustomerRefundRequest;
use App\Http\Requests\Accounting\UpdateCustomerRefundRequest;
use App\Models\CustomerRefund;
use App\Models\CustomerRefundAllocation;
use App\Models\User;
use App\Services\Accounting\CustomerRefundService;
use App\Services\Accounting\CustomerSettlementPresentationService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\CustomerReceiptMethodRegistry;
use App\Support\Accounting\CustomerSettlementStatusRegistry;
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

final class CustomerRefundController extends Controller
{
    public function __construct(private readonly CustomerRefundService $service, private readonly CustomerSettlementPresentationService $presentation, private readonly BranchAccessService $branchAccessService, private readonly CustomerSettlementStatusRegistry $statusRegistry, private readonly CustomerReceiptMethodRegistry $methodRegistry, private readonly TenantContext $tenantContext, private readonly CommonResponseService $responseService,)
    {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CustomerRefund::class);
        $actor = $this->actor($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:160'], 'branch_id' => ['nullable', 'integer'], 'customer_id' => ['nullable', 'integer'], 'status' => ['nullable', 'string'], 'refund_method' => ['nullable', 'string'], 'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],]);
        $search = trim((string)($filters['search'] ?? ''));
        $query = CustomerRefund::query()->with(['branch:id,name,code', 'customer:id,name,code', 'refundAccount:id,code,name'])->when($search !== '', static fn(Builder $q): Builder => $q->where(static fn(Builder $s): Builder => $s->where('refund_number', 'like', "%{$search}%")->orWhere('refund_reference', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%")->orWhere('customer_code', 'like', "%{$search}%")))->when(isset ($filters['branch_id']), static fn(Builder $q): Builder => $q->where('branch_id', (int) $filters['branch_id']))->when(isset ($filters['customer_id']), static fn(Builder $q): Builder => $q->where('customer_id', (int) $filters['customer_id']))->when(isset ($filters['status']) && $filters['status'] !== '', static fn(Builder $q): Builder => $q->where('status', (string) $filters['status']))->when(isset ($filters['refund_method']) && $filters['refund_method'] !== '', static fn(Builder $q): Builder => $q->where('refund_method', (string) $filters['refund_method']))->orderByDesc('created_at')->orderByDesc('id');
        $this->branchAccessService->scopeQuery($query, $actor, 'customer_refunds.branch_id');
        $paginator = $query->paginate((int)($filters['per_page'] ?? 15))->withQueryString();
        return Inertia::render('CustomerRefunds/Index', ['documents' => ['data' => $paginator->getCollection()->map(fn(CustomerRefund $d): array => $this->summary($d, $actor))->values()->all(), 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total(), 'per_page' => $paginator->perPage()]], 'filters' => ['search' => $search, 'branch_id' => isset ($filters['branch_id']) ? (int) $filters['branch_id']: null, 'customer_id' => isset ($filters['customer_id']) ? (int) $filters['customer_id']: null, 'status' => (string)($filters['status'] ?? ''), 'refund_method' => (string)($filters['refund_method'] ?? ''), 'per_page' => (int)($filters['per_page'] ?? 15)], 'branches' => $this->presentation->branches($actor, false), 'customers' => $this->presentation->customers(), 'statuses' => $this->statusRegistry->options(), 'methods' => $this->methodRegistry->options(), 'can' => ['create' => $actor->can('create', CustomerRefund::class)],]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', CustomerRefund::class);
        return Inertia::render('CustomerRefunds/Create', $this->formOptions($request));
    }

    public function store(StoreCustomerRefundRequest $request): JsonResponse | RedirectResponse
    {
        $document = $this->service->create($request->validated(), $this->actor($request));
        return $this->responseService->success('Customer Refund draft created.', $this->responseData($document), redirectTo: route('customer-refunds.show', $document));
    }

    public function show(Request $request, CustomerRefund $customerRefund): Response
    {
        Gate::authorize('view', $customerRefund);
        return Inertia::render('CustomerRefunds/Show', ['document' => $this->detail($customerRefund, $this->actor($request))]);
    }

    public function edit(Request $request, CustomerRefund $customerRefund): Response
    {
        Gate::authorize('update', $customerRefund);
        return Inertia::render('CustomerRefunds/Edit', [... $this->formOptions($request, $customerRefund), 'document' => $this->formData($customerRefund)]);
    }

    public function update(UpdateCustomerRefundRequest $request, CustomerRefund $customerRefund): JsonResponse | RedirectResponse
    {
        $document = $this->service->update($customerRefund, $request->validated(), $this->actor($request));
        return $this->responseService->success('Customer Refund updated.', $this->responseData($document), redirectTo: route('customer-refunds.show', $document));
    }

    public function destroy(Request $request, CustomerRefund $customerRefund): JsonResponse | RedirectResponse
    {
        Gate::authorize('delete', $customerRefund);
        $this->service->delete($customerRefund, $this->actor($request));
        return $this->responseService->success('Customer Refund deleted.', redirectTo: route('customer-refunds.index'));
    }

    public function submit(Request $request, CustomerRefund $customerRefund): JsonResponse | RedirectResponse
    {
        Gate::authorize('submit', $customerRefund);
        return $this->actionResponse($this->service->submit($customerRefund, $this->actor($request)), 'Customer Refund submitted.');
    }

    public function returnToDraft(Request $request, CustomerRefund $customerRefund): JsonResponse | RedirectResponse
    {
        Gate::authorize('returnToDraft', $customerRefund);
        return $this->actionResponse($this->service->returnToDraft($customerRefund, $this->actor($request)), 'Customer Refund returned to draft.');
    }

    public function approve(Request $request, CustomerRefund $customerRefund): JsonResponse | RedirectResponse
    {
        Gate::authorize('approve', $customerRefund);
        return $this->actionResponse($this->service->approve($customerRefund, $this->actor($request)), 'Customer Refund approved.');
    }

    public function post(Request $request, CustomerRefund $customerRefund): JsonResponse | RedirectResponse
    {
        Gate::authorize('post', $customerRefund);
        return $this->actionResponse($this->service->post($customerRefund, $this->actor($request)), 'Customer Refund posted.');
    }

    public function cancel(CustomerSettlementReasonRequest $request, CustomerRefund $customerRefund): JsonResponse | RedirectResponse
    {
        Gate::authorize('cancel', $customerRefund);
        return $this->actionResponse($this->service->cancel($customerRefund, (string) $request->validated('reason'), $this->actor($request)), 'Customer Refund cancelled.');
    }

    public function reverse(CustomerSettlementReasonRequest $request, CustomerRefund $customerRefund): JsonResponse | RedirectResponse
    {
        Gate::authorize('reverse', $customerRefund);
        $postingDate = (string)($request->validated('posting_date') ?? CarbonImmutable::now($this->tenantContext->tenant()->timezone)->format('Y-m-d'));
        return $this->actionResponse($this->service->reverse($customerRefund, $postingDate, (string) $request->validated('reason'), $this->actor($request)), 'Customer Refund reversed.');
    }

    public function print(Request $request, CustomerRefund $customerRefund): Response
    {
        Gate::authorize('print', $customerRefund);
        $customerRefund->load(['branch:id,name,code,address,phone,email', 'refundAccount:id,code,name', 'allocations.creditOpenItem', 'createdBy:id,name', 'approvedBy:id,name', 'postedBy:id,name']);
        $tenant = $this->tenantContext->tenant();
        return Inertia::render('CustomerRefunds/Print/CustomerRefund', ['document' => $this->detail($customerRefund, $this->actor($request)), 'company' => ['name' => $tenant->name, 'code' => $tenant->code, 'email' => $tenant->email, 'phone' => $tenant->phone, 'address' => $tenant->address],]);
    }
    /** @return array<string, mixed> */
    private function formOptions(Request $request, ? CustomerRefund $document = null): array
    {
        $actor = $this->actor($request);
        $branchId = $document?->branch_id ?? ($request->filled('branch_id') ? $request->integer('branch_id'): null);
        $customerId = $document?->customer_id ?? ($request->filled('customer_id') ? $request->integer('customer_id'): null);
        $currency = $document?->currency_code ?? ($request->filled('currency_code') ? strtoupper((string) $request->input('currency_code')): $this->tenantContext->tenant()->currency_code);
        $today = CarbonImmutable::now($this->tenantContext->tenant()->timezone)->format('Y-m-d');
        return['branches' => $this->presentation->branches($actor), 'customers' => $this->presentation->customers(), 'accounts' => $this->presentation->cashAndBankAccounts(), 'credits' => $this->presentation->openCredits($actor, $branchId, $customerId, $currency), 'methods' => $this->methodRegistry->options(), 'selection' => ['branch_id' => $branchId, 'customer_id' => $customerId, 'currency_code' => $currency], 'defaults' => ['refund_date' => $today, 'posting_date' => $today, 'exchange_rate' => '1.00000000'],];
    }
    /** @return array<string, mixed> */
    private function summary(CustomerRefund $d, User $actor): array
    {
        return['id' => (int) $d->getKey(), 'refund_number' => $d->refund_number, 'refund_date' => $d->refund_date?->format('Y-m-d'), 'posting_date' => $d->posting_date?->format('Y-m-d'), 'customer_name' => $d->customer_name, 'customer_code' => $d->customer_code, 'currency_code' => $d->currency_code, 'total_amount' => (string) $d->total_amount, 'refund_method' => $d->refund_method, 'refund_method_label' => $this->methodRegistry->label($d->refund_method), 'status' => $d->status, 'status_label' => $this->statusRegistry->label($d->status), 'branch' => $d->branch ? ['id' => (int) $d->branch->getKey(), 'name' => $d->branch->name, 'code' => $d->branch->code]: null, 'refund_account' => $d->refundAccount ? ['id' => (int) $d->refundAccount->getKey(), 'code' => $d->refundAccount->code, 'name' => $d->refundAccount->name]: null, 'can' => $this->permissions($d, $actor)];
    }
    /** @return array<string, mixed> */
    private function detail(CustomerRefund $d, User $actor): array
    {
        $d->load(['branch:id,name,code,address,phone,email', 'customer:id,name,code', 'refundAccount:id,code,name', 'allocations.creditOpenItem', 'createdBy:id,name', 'submittedBy:id,name', 'approvedBy:id,name', 'postedBy:id,name', 'reversedBy:id,name', 'cancelledBy:id,name']);
        return[... $this->summary($d, $actor), 'exchange_rate' => (string) $d->exchange_rate, 'refund_reference' => $d->refund_reference, 'cheque_number' => $d->cheque_number, 'cheque_date' => $d->cheque_date?->format('Y-m-d'), 'base_cash_amount' => (string) $d->base_cash_amount, 'base_credit_amount' => (string) $d->base_credit_amount, 'exchange_difference_amount' => (string) $d->exchange_difference_amount, 'reason' => $d->reason, 'notes' => $d->notes, 'revision' => (int) $d->revision, 'accounting_posting_reference' => $d->accounting_posting_reference, 'accounting_reversal_reference' => $d->accounting_reversal_reference, 'reversal_posting_date' => $d->reversal_posting_date?->format('Y-m-d'), 'reversal_reason' => $d->reversal_reason, 'cancellation_reason' => $d->cancellation_reason, 'created_by' => $this->user($d->createdBy), 'submitted_by' => $this->user($d->submittedBy), 'approved_by' => $this->user($d->approvedBy), 'posted_by' => $this->user($d->postedBy), 'reversed_by' => $this->user($d->reversedBy), 'cancelled_by' => $this->user($d->cancelledBy), 'allocations' => $d->allocations->map(static fn(CustomerRefundAllocation $line): array => ['id' => (int) $line->getKey(), 'line_number' => (int) $line->line_number, 'credit_open_item_id' => (int) $line->credit_open_item_id, 'credit_document_number' => $line->credit_document_number, 'credit_item_type' => $line->credit_item_type, 'amount' => (string) $line->amount, 'credit_exchange_rate' => (string) $line->credit_exchange_rate, 'credit_base_amount' => (string) $line->credit_base_amount, 'cash_base_amount' => (string) $line->cash_base_amount, 'exchange_difference_amount' => (string) $line->exchange_difference_amount, 'status' => $line->status])->values()->all()];
    }
    /** @return array<string, mixed> */
    private function formData(CustomerRefund $d): array
    {
        $d->load('allocations');
        return['id' => (int) $d->getKey(), 'branch_id' => (int) $d->branch_id, 'customer_id' => (int) $d->customer_id, 'refund_account_id' => (int) $d->refund_account_id, 'refund_date' => $d->refund_date?->format('Y-m-d'), 'posting_date' => $d->posting_date?->format('Y-m-d'), 'currency_code' => $d->currency_code, 'exchange_rate' => (string) $d->exchange_rate, 'refund_method' => $d->refund_method, 'refund_reference' => $d->refund_reference, 'cheque_number' => $d->cheque_number, 'cheque_date' => $d->cheque_date?->format('Y-m-d'), 'reason' => $d->reason, 'notes' => $d->notes, 'revision' => (int) $d->revision, 'allocations' => $d->allocations->map(static fn(CustomerRefundAllocation $line): array => ['credit_open_item_id' => (int) $line->credit_open_item_id, 'amount' => (string) $line->amount])->values()->all()];
    }
    /** @return array<string, bool> */
    private function permissions(CustomerRefund $d, User $actor): array
    {
        return['view' => $actor->can('view', $d), 'update' => $actor->can('update', $d), 'delete' => $actor->can('delete', $d), 'submit' => $actor->can('submit', $d), 'return_to_draft' => $actor->can('returnToDraft', $d), 'approve' => $actor->can('approve', $d), 'post' => $actor->can('post', $d), 'cancel' => $actor->can('cancel', $d), 'reverse' => $actor->can('reverse', $d), 'print' => $actor->can('print', $d)];
    }
    /** @return array{id: int, name: string}|null */
    private function user( ? User $user): ? array
    {
        return $user ? ['id' => (int) $user->getKey(), 'name' => $user->name]: null;
    }
    /** @return array<string, mixed> */
    private function responseData(CustomerRefund $d): array
    {
        return['id' => (int) $d->getKey(), 'refund_number' => $d->refund_number, 'status' => $d->status];
    }

    private function actionResponse(CustomerRefund $d, string $message): JsonResponse | RedirectResponse
    {
        return $this->responseService->success($message, $this->responseData($d), redirectTo: route('customer-refunds.show', $d));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        return $actor;
    }
}
