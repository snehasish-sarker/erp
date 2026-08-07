<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CustomerSettlementReasonRequest;
use App\Http\Requests\Accounting\StoreCustomerCreditApplicationRequest;
use App\Http\Requests\Accounting\UpdateCustomerCreditApplicationRequest;
use App\Models\CustomerCreditApplication;
use App\Models\CustomerCreditApplicationLine;
use App\Models\User;
use App\Services\Accounting\CustomerCreditApplicationService;
use App\Services\Accounting\CustomerSettlementPresentationService;
use App\Services\Organisation\BranchAccessService;
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

final class CustomerCreditApplicationController extends Controller
{
    public function __construct(private readonly CustomerCreditApplicationService $service, private readonly CustomerSettlementPresentationService $presentation, private readonly BranchAccessService $branchAccessService, private readonly CustomerSettlementStatusRegistry $statusRegistry, private readonly TenantContext $tenantContext, private readonly CommonResponseService $responseService,)
    {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CustomerCreditApplication::class);
        $actor = $this->actor($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:160'], 'branch_id' => ['nullable', 'integer'], 'customer_id' => ['nullable', 'integer'], 'status' => ['nullable', 'string'], 'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100']]);
        $search = trim((string)($filters['search'] ?? ''));
        $query = CustomerCreditApplication::query()->with(['branch:id,name,code', 'customer:id,name,code'])->when($search !== '', static fn(Builder $q): Builder => $q->where(static fn(Builder $s): Builder => $s->where('application_number', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%")->orWhere('customer_code', 'like', "%{$search}%")))->when(isset ($filters['branch_id']), static fn(Builder $q): Builder => $q->where('branch_id', (int) $filters['branch_id']))->when(isset ($filters['customer_id']), static fn(Builder $q): Builder => $q->where('customer_id', (int) $filters['customer_id']))->when(isset ($filters['status']) && $filters['status'] !== '', static fn(Builder $q): Builder => $q->where('status', (string) $filters['status']))->orderByDesc('created_at')->orderByDesc('id');
        $this->branchAccessService->scopeQuery($query, $actor, 'customer_credit_applications.branch_id');
        $paginator = $query->paginate((int)($filters['per_page'] ?? 15))->withQueryString();
        return Inertia::render('CustomerCreditApplications/Index', ['documents' => ['data' => $paginator->getCollection()->map(fn(CustomerCreditApplication $d): array => $this->summary($d, $actor))->values()->all(), 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total(), 'per_page' => $paginator->perPage()]], 'filters' => ['search' => $search, 'branch_id' => isset ($filters['branch_id']) ? (int) $filters['branch_id']: null, 'customer_id' => isset ($filters['customer_id']) ? (int) $filters['customer_id']: null, 'status' => (string)($filters['status'] ?? ''), 'per_page' => (int)($filters['per_page'] ?? 15)], 'branches' => $this->presentation->branches($actor, false), 'customers' => $this->presentation->customers(), 'statuses' => $this->statusRegistry->options(), 'can' => ['create' => $actor->can('create', CustomerCreditApplication::class)],]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', CustomerCreditApplication::class);
        return Inertia::render('CustomerCreditApplications/Create', $this->formOptions($request));
    }

    public function store(StoreCustomerCreditApplicationRequest $request): JsonResponse | RedirectResponse
    {
        $document = $this->service->create($request->validated(), $this->actor($request));
        return $this->responseService->success('Customer Credit Application draft created.', $this->responseData($document), redirectTo: route('customer-credit-applications.show', $document));
    }

    public function show(Request $request, CustomerCreditApplication $customerCreditApplication): Response
    {
        Gate::authorize('view', $customerCreditApplication);
        $actor = $this->actor($request);
        return Inertia::render('CustomerCreditApplications/Show', ['document' => $this->detail($customerCreditApplication, $actor)]);
    }

    public function edit(Request $request, CustomerCreditApplication $customerCreditApplication): Response
    {
        Gate::authorize('update', $customerCreditApplication);
        return Inertia::render('CustomerCreditApplications/Edit', [... $this->formOptions($request, $customerCreditApplication), 'document' => $this->formData($customerCreditApplication)]);
    }

    public function update(UpdateCustomerCreditApplicationRequest $request, CustomerCreditApplication $customerCreditApplication): JsonResponse | RedirectResponse
    {
        $document = $this->service->update($customerCreditApplication, $request->validated(), $this->actor($request));
        return $this->responseService->success('Customer Credit Application updated.', $this->responseData($document), redirectTo: route('customer-credit-applications.show', $document));
    }

    public function destroy(Request $request, CustomerCreditApplication $customerCreditApplication): JsonResponse | RedirectResponse
    {
        Gate::authorize('delete', $customerCreditApplication);
        $this->service->delete($customerCreditApplication, $this->actor($request));
        return $this->responseService->success('Customer Credit Application deleted.', redirectTo: route('customer-credit-applications.index'));
    }

    public function submit(Request $request, CustomerCreditApplication $customerCreditApplication): JsonResponse | RedirectResponse
    {
        Gate::authorize('submit', $customerCreditApplication);
        return $this->actionResponse($this->service->submit($customerCreditApplication, $this->actor($request)), 'Customer Credit Application submitted.');
    }

    public function returnToDraft(Request $request, CustomerCreditApplication $customerCreditApplication): JsonResponse | RedirectResponse
    {
        Gate::authorize('returnToDraft', $customerCreditApplication);
        return $this->actionResponse($this->service->returnToDraft($customerCreditApplication, $this->actor($request)), 'Customer Credit Application returned to draft.');
    }

    public function approve(Request $request, CustomerCreditApplication $customerCreditApplication): JsonResponse | RedirectResponse
    {
        Gate::authorize('approve', $customerCreditApplication);
        return $this->actionResponse($this->service->approve($customerCreditApplication, $this->actor($request)), 'Customer Credit Application approved.');
    }

    public function post(Request $request, CustomerCreditApplication $customerCreditApplication): JsonResponse | RedirectResponse
    {
        Gate::authorize('post', $customerCreditApplication);
        return $this->actionResponse($this->service->post($customerCreditApplication, $this->actor($request)), 'Customer Credit Application posted.');
    }

    public function cancel(CustomerSettlementReasonRequest $request, CustomerCreditApplication $customerCreditApplication): JsonResponse | RedirectResponse
    {
        Gate::authorize('cancel', $customerCreditApplication);
        return $this->actionResponse($this->service->cancel($customerCreditApplication, (string) $request->validated('reason'), $this->actor($request)), 'Customer Credit Application cancelled.');
    }

    public function reverse(CustomerSettlementReasonRequest $request, CustomerCreditApplication $customerCreditApplication): JsonResponse | RedirectResponse
    {
        Gate::authorize('reverse', $customerCreditApplication);
        $postingDate = (string)($request->validated('posting_date') ?? CarbonImmutable::now($this->tenantContext->tenant()->timezone)->format('Y-m-d'));
        return $this->actionResponse($this->service->reverse($customerCreditApplication, $postingDate, (string) $request->validated('reason'), $this->actor($request)), 'Customer Credit Application reversed.');
    }
    /** @return array<string, mixed> */
    private function formOptions(Request $request, ? CustomerCreditApplication $document = null): array
    {
        $actor = $this->actor($request);
        $branchId = $document?->branch_id ?? ($request->filled('branch_id') ? $request->integer('branch_id'): null);
        $customerId = $document?->customer_id ?? ($request->filled('customer_id') ? $request->integer('customer_id'): null);
        $currency = $document?->currency_code ?? ($request->filled('currency_code') ? strtoupper((string) $request->input('currency_code')): null);
        $today = CarbonImmutable::now($this->tenantContext->tenant()->timezone)->format('Y-m-d');
        return['branches' => $this->presentation->branches($actor), 'customers' => $this->presentation->customers(), 'receivables' => $this->presentation->openReceivables($actor, $branchId, $customerId, $currency), 'credits' => $this->presentation->openCredits($actor, $branchId, $customerId, $currency), 'selection' => ['branch_id' => $branchId, 'customer_id' => $customerId, 'currency_code' => $currency ?? $this->tenantContext->tenant()->currency_code], 'defaults' => ['application_date' => $today, 'posting_date' => $today]];
    }
    /** @return array<string, mixed> */
    private function summary(CustomerCreditApplication $d, User $actor): array
    {
        return['id' => (int) $d->getKey(), 'application_number' => $d->application_number, 'application_date' => $d->application_date?->format('Y-m-d'), 'posting_date' => $d->posting_date?->format('Y-m-d'), 'customer_name' => $d->customer_name, 'customer_code' => $d->customer_code, 'currency_code' => $d->currency_code, 'total_amount' => (string) $d->total_amount, 'status' => $d->status, 'status_label' => $this->statusRegistry->label($d->status), 'branch' => $d->branch ? ['id' => (int) $d->branch->getKey(), 'name' => $d->branch->name, 'code' => $d->branch->code]: null, 'can' => $this->permissions($d, $actor)];
    }
    /** @return array<string, mixed> */
    private function detail(CustomerCreditApplication $d, User $actor): array
    {
        $d->load(['branch:id,name,code', 'customer:id,name,code', 'lines.receivableOpenItem', 'lines.creditOpenItem', 'createdBy:id,name', 'submittedBy:id,name', 'approvedBy:id,name', 'postedBy:id,name', 'reversedBy:id,name', 'cancelledBy:id,name']);
        return[... $this->summary($d, $actor), 'reason' => $d->reason, 'notes' => $d->notes, 'revision' => (int) $d->revision, 'receivable_base_amount' => (string) $d->receivable_base_amount, 'credit_base_amount' => (string) $d->credit_base_amount, 'exchange_difference_amount' => (string) $d->exchange_difference_amount, 'accounting_posting_reference' => $d->accounting_posting_reference, 'accounting_reversal_reference' => $d->accounting_reversal_reference, 'reversal_posting_date' => $d->reversal_posting_date?->format('Y-m-d'), 'reversal_reason' => $d->reversal_reason, 'cancellation_reason' => $d->cancellation_reason, 'created_by' => $this->user($d->createdBy), 'submitted_by' => $this->user($d->submittedBy), 'approved_by' => $this->user($d->approvedBy), 'posted_by' => $this->user($d->postedBy), 'reversed_by' => $this->user($d->reversedBy), 'cancelled_by' => $this->user($d->cancelledBy), 'lines' => $d->lines->map(static fn(CustomerCreditApplicationLine $line): array => ['id' => (int) $line->getKey(), 'line_number' => (int) $line->line_number, 'receivable_open_item_id' => (int) $line->receivable_open_item_id, 'credit_open_item_id' => (int) $line->credit_open_item_id, 'receivable_document_number' => $line->receivable_document_number, 'credit_document_number' => $line->credit_document_number, 'credit_item_type' => $line->credit_item_type, 'amount' => (string) $line->amount, 'receivable_base_amount' => (string) $line->receivable_base_amount, 'credit_base_amount' => (string) $line->credit_base_amount, 'exchange_difference_amount' => (string) $line->exchange_difference_amount, 'status' => $line->status])->values()->all()];
    }
    /** @return array<string, mixed> */
    private function formData(CustomerCreditApplication $d): array
    {
        $d->load('lines');
        return['id' => (int) $d->getKey(), 'branch_id' => (int) $d->branch_id, 'customer_id' => (int) $d->customer_id, 'application_date' => $d->application_date?->format('Y-m-d'), 'posting_date' => $d->posting_date?->format('Y-m-d'), 'currency_code' => $d->currency_code, 'reason' => $d->reason, 'notes' => $d->notes, 'revision' => (int) $d->revision, 'lines' => $d->lines->map(static fn(CustomerCreditApplicationLine $line): array => ['receivable_open_item_id' => (int) $line->receivable_open_item_id, 'credit_open_item_id' => (int) $line->credit_open_item_id, 'amount' => (string) $line->amount])->values()->all()];
    }
    /** @return array<string, bool> */
    private function permissions(CustomerCreditApplication $d, User $actor): array
    {
        return['view' => $actor->can('view', $d), 'update' => $actor->can('update', $d), 'delete' => $actor->can('delete', $d), 'submit' => $actor->can('submit', $d), 'return_to_draft' => $actor->can('returnToDraft', $d), 'approve' => $actor->can('approve', $d), 'post' => $actor->can('post', $d), 'cancel' => $actor->can('cancel', $d), 'reverse' => $actor->can('reverse', $d)];
    }
    /** @return array{id: int, name: string}|null */
    private function user( ? User $user): ? array
    {
        return $user ? ['id' => (int) $user->getKey(), 'name' => $user->name]: null;
    }
    /** @return array<string, mixed> */
    private function responseData(CustomerCreditApplication $d): array
    {
        return['id' => (int) $d->getKey(), 'application_number' => $d->application_number, 'status' => $d->status];
    }

    private function actionResponse(CustomerCreditApplication $d, string $message): JsonResponse | RedirectResponse
    {
        return $this->responseService->success($message, $this->responseData($d), redirectTo: route('customer-credit-applications.show', $d));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        return $actor;
    }
}
