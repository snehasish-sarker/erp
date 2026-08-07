<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\CustomerSettlementReasonRequest;
use App\Http\Requests\Accounting\StoreCustomerArAdjustmentRequest;
use App\Http\Requests\Accounting\UpdateCustomerArAdjustmentRequest;
use App\Models\CustomerArAdjustment;
use App\Models\User;
use App\Services\Accounting\CustomerArAdjustmentService;
use App\Services\Accounting\CustomerSettlementPresentationService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\CustomerArAdjustmentDirectionRegistry;
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

final class CustomerArAdjustmentController extends Controller
{
    public function __construct(private readonly CustomerArAdjustmentService $service, private readonly CustomerSettlementPresentationService $presentation, private readonly BranchAccessService $branchAccessService, private readonly CustomerSettlementStatusRegistry $statusRegistry, private readonly CustomerArAdjustmentDirectionRegistry $directionRegistry, private readonly TenantContext $tenantContext, private readonly CommonResponseService $responseService,)
    {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CustomerArAdjustment::class);
        $actor = $this->actor($request);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:160'], 'branch_id' => ['nullable', 'integer'], 'customer_id' => ['nullable', 'integer'], 'status' => ['nullable', 'string'], 'direction' => ['nullable', 'in:debit,credit'], 'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100']]);
        $search = trim((string)($filters['search'] ?? ''));
        $query = CustomerArAdjustment::query()->with(['branch:id,name,code', 'customer:id,name,code', 'offsetAccount:id,code,name'])->when($search !== '', static fn(Builder $q): Builder => $q->where(static fn(Builder $s): Builder => $s->where('adjustment_number', 'like', "%{$search}%")->orWhere('customer_name', 'like', "%{$search}%")->orWhere('customer_code', 'like', "%{$search}%")->orWhere('reason', 'like', "%{$search}%")))->when(isset ($filters['branch_id']), static fn(Builder $q): Builder => $q->where('branch_id', (int) $filters['branch_id']))->when(isset ($filters['customer_id']), static fn(Builder $q): Builder => $q->where('customer_id', (int) $filters['customer_id']))->when(isset ($filters['status']) && $filters['status'] !== '', static fn(Builder $q): Builder => $q->where('status', (string) $filters['status']))->when(isset ($filters['direction']) && $filters['direction'] !== '', static fn(Builder $q): Builder => $q->where('direction', (string) $filters['direction']))->orderByDesc('created_at')->orderByDesc('id');
        $this->branchAccessService->scopeQuery($query, $actor, 'customer_ar_adjustments.branch_id');
        $paginator = $query->paginate((int)($filters['per_page'] ?? 15))->withQueryString();
        return Inertia::render('CustomerArAdjustments/Index', ['documents' => ['data' => $paginator->getCollection()->map(fn(CustomerArAdjustment $d): array => $this->summary($d, $actor))->values()->all(), 'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total(), 'per_page' => $paginator->perPage()]], 'filters' => ['search' => $search, 'branch_id' => isset ($filters['branch_id']) ? (int) $filters['branch_id']: null, 'customer_id' => isset ($filters['customer_id']) ? (int) $filters['customer_id']: null, 'status' => (string)($filters['status'] ?? ''), 'direction' => (string)($filters['direction'] ?? ''), 'per_page' => (int)($filters['per_page'] ?? 15)], 'branches' => $this->presentation->branches($actor, false), 'customers' => $this->presentation->customers(), 'statuses' => $this->statusRegistry->options(), 'directions' => $this->directionRegistry->options(), 'can' => ['create' => $actor->can('create', CustomerArAdjustment::class)],]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', CustomerArAdjustment::class);
        return Inertia::render('CustomerArAdjustments/Create', $this->formOptions($request));
    }

    public function store(StoreCustomerArAdjustmentRequest $request): JsonResponse | RedirectResponse
    {
        $d = $this->service->create($request->validated(), $this->actor($request));
        return $this->responseService->success('Customer AR Adjustment draft created.', $this->responseData($d), redirectTo: route('customer-ar-adjustments.show', $d));
    }

    public function show(Request $request, CustomerArAdjustment $customerArAdjustment): Response
    {
        Gate::authorize('view', $customerArAdjustment);
        return Inertia::render('CustomerArAdjustments/Show', ['document' => $this->detail($customerArAdjustment, $this->actor($request))]);
    }

    public function edit(Request $request, CustomerArAdjustment $customerArAdjustment): Response
    {
        Gate::authorize('update', $customerArAdjustment);
        return Inertia::render('CustomerArAdjustments/Edit', [... $this->formOptions($request), 'document' => $this->formData($customerArAdjustment)]);
    }

    public function update(UpdateCustomerArAdjustmentRequest $request, CustomerArAdjustment $customerArAdjustment): JsonResponse | RedirectResponse
    {
        $d = $this->service->update($customerArAdjustment, $request->validated(), $this->actor($request));
        return $this->responseService->success('Customer AR Adjustment updated.', $this->responseData($d), redirectTo: route('customer-ar-adjustments.show', $d));
    }

    public function destroy(Request $request, CustomerArAdjustment $customerArAdjustment): JsonResponse | RedirectResponse
    {
        Gate::authorize('delete', $customerArAdjustment);
        $this->service->delete($customerArAdjustment, $this->actor($request));
        return $this->responseService->success('Customer AR Adjustment deleted.', redirectTo: route('customer-ar-adjustments.index'));
    }

    public function submit(Request $request, CustomerArAdjustment $customerArAdjustment): JsonResponse | RedirectResponse
    {
        Gate::authorize('submit', $customerArAdjustment);
        return $this->actionResponse($this->service->submit($customerArAdjustment, $this->actor($request)), 'Customer AR Adjustment submitted.');
    }

    public function returnToDraft(Request $request, CustomerArAdjustment $customerArAdjustment): JsonResponse | RedirectResponse
    {
        Gate::authorize('returnToDraft', $customerArAdjustment);
        return $this->actionResponse($this->service->returnToDraft($customerArAdjustment, $this->actor($request)), 'Customer AR Adjustment returned to draft.');
    }

    public function approve(Request $request, CustomerArAdjustment $customerArAdjustment): JsonResponse | RedirectResponse
    {
        Gate::authorize('approve', $customerArAdjustment);
        return $this->actionResponse($this->service->approve($customerArAdjustment, $this->actor($request)), 'Customer AR Adjustment approved.');
    }

    public function post(Request $request, CustomerArAdjustment $customerArAdjustment): JsonResponse | RedirectResponse
    {
        Gate::authorize('post', $customerArAdjustment);
        return $this->actionResponse($this->service->post($customerArAdjustment, $this->actor($request)), 'Customer AR Adjustment posted.');
    }

    public function cancel(CustomerSettlementReasonRequest $request, CustomerArAdjustment $customerArAdjustment): JsonResponse | RedirectResponse
    {
        Gate::authorize('cancel', $customerArAdjustment);
        return $this->actionResponse($this->service->cancel($customerArAdjustment, (string) $request->validated('reason'), $this->actor($request)), 'Customer AR Adjustment cancelled.');
    }

    public function reverse(CustomerSettlementReasonRequest $request, CustomerArAdjustment $customerArAdjustment): JsonResponse | RedirectResponse
    {
        Gate::authorize('reverse', $customerArAdjustment);
        $date = (string)($request->validated('posting_date') ?? CarbonImmutable::now($this->tenantContext->tenant()->timezone)->format('Y-m-d'));
        return $this->actionResponse($this->service->reverse($customerArAdjustment, $date, (string) $request->validated('reason'), $this->actor($request)), 'Customer AR Adjustment reversed.');
    }
    /** @return array<string, mixed> */
    private function formOptions(Request $request): array
    {
        $today = CarbonImmutable::now($this->tenantContext->tenant()->timezone)->format('Y-m-d');
        return['branches' => $this->presentation->branches($this->actor($request)), 'customers' => $this->presentation->customers(), 'accounts' => $this->presentation->adjustmentAccounts(), 'directions' => $this->directionRegistry->options(), 'defaults' => ['adjustment_date' => $today, 'posting_date' => $today, 'currency_code' => $this->tenantContext->tenant()->currency_code, 'exchange_rate' => '1.00000000']];
    }
    /** @return array<string, mixed> */
    private function summary(CustomerArAdjustment $d, User $actor): array
    {
        return['id' => (int) $d->getKey(), 'adjustment_number' => $d->adjustment_number, 'adjustment_date' => $d->adjustment_date?->format('Y-m-d'), 'posting_date' => $d->posting_date?->format('Y-m-d'), 'customer_name' => $d->customer_name, 'customer_code' => $d->customer_code, 'currency_code' => $d->currency_code, 'amount' => (string) $d->amount, 'direction' => $d->direction, 'direction_label' => $this->directionRegistry->label($d->direction), 'status' => $d->status, 'status_label' => $this->statusRegistry->label($d->status), 'branch' => $d->branch ? ['id' => (int) $d->branch->getKey(), 'name' => $d->branch->name, 'code' => $d->branch->code]: null, 'offset_account' => $d->offsetAccount ? ['id' => (int) $d->offsetAccount->getKey(), 'code' => $d->offsetAccount->code, 'name' => $d->offsetAccount->name]: null, 'can' => $this->permissions($d, $actor)];
    }
    /** @return array<string, mixed> */
    private function detail(CustomerArAdjustment $d, User $actor): array
    {
        $d->load(['branch:id,name,code', 'customer:id,name,code', 'offsetAccount:id,code,name', 'customerOpenItem', 'createdBy:id,name', 'submittedBy:id,name', 'approvedBy:id,name', 'postedBy:id,name', 'reversedBy:id,name', 'cancelledBy:id,name']);
        return[... $this->summary($d, $actor), 'exchange_rate' => (string) $d->exchange_rate, 'base_amount' => (string) $d->base_amount, 'reason' => $d->reason, 'notes' => $d->notes, 'revision' => (int) $d->revision, 'accounting_posting_reference' => $d->accounting_posting_reference, 'accounting_reversal_reference' => $d->accounting_reversal_reference, 'reversal_posting_date' => $d->reversal_posting_date?->format('Y-m-d'), 'reversal_reason' => $d->reversal_reason, 'cancellation_reason' => $d->cancellation_reason, 'open_item' => $d->customerOpenItem ? ['id' => (int) $d->customerOpenItem->getKey(), 'item_type' => $d->customerOpenItem->item_type, 'status' => $d->customerOpenItem->status, 'outstanding_amount' => (string) $d->customerOpenItem->outstanding_amount]: null, 'created_by' => $this->user($d->createdBy), 'submitted_by' => $this->user($d->submittedBy), 'approved_by' => $this->user($d->approvedBy), 'posted_by' => $this->user($d->postedBy), 'reversed_by' => $this->user($d->reversedBy), 'cancelled_by' => $this->user($d->cancelledBy)];
    }
    /** @return array<string, mixed> */
    private function formData(CustomerArAdjustment $d): array
    {
        return['id' => (int) $d->getKey(), 'branch_id' => (int) $d->branch_id, 'customer_id' => (int) $d->customer_id, 'offset_account_id' => (int) $d->offset_account_id, 'adjustment_date' => $d->adjustment_date?->format('Y-m-d'), 'posting_date' => $d->posting_date?->format('Y-m-d'), 'currency_code' => $d->currency_code, 'exchange_rate' => (string) $d->exchange_rate, 'direction' => $d->direction, 'amount' => (string) $d->amount, 'reason' => $d->reason, 'notes' => $d->notes, 'revision' => (int) $d->revision];
    }
    /** @return array<string, bool> */
    private function permissions(CustomerArAdjustment $d, User $actor): array
    {
        return['view' => $actor->can('view', $d), 'update' => $actor->can('update', $d), 'delete' => $actor->can('delete', $d), 'submit' => $actor->can('submit', $d), 'return_to_draft' => $actor->can('returnToDraft', $d), 'approve' => $actor->can('approve', $d), 'post' => $actor->can('post', $d), 'cancel' => $actor->can('cancel', $d), 'reverse' => $actor->can('reverse', $d)];
    }
    /** @return array{id: int, name: string}|null */
    private function user( ? User $user): ? array
    {
        return $user ? ['id' => (int) $user->getKey(), 'name' => $user->name]: null;
    }
    /** @return array<string, mixed> */
    private function responseData(CustomerArAdjustment $d): array
    {
        return['id' => (int) $d->getKey(), 'adjustment_number' => $d->adjustment_number, 'status' => $d->status];
    }

    private function actionResponse(CustomerArAdjustment $d, string $message): JsonResponse | RedirectResponse
    {
        return $this->responseService->success($message, $this->responseData($d), redirectTo: route('customer-ar-adjustments.show', $d));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        return $actor;
    }
}
