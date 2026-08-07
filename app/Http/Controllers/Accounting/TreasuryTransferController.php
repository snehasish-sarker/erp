<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreTreasuryTransferRequest;
use App\Http\Requests\Accounting\TreasuryReasonRequest;
use App\Http\Requests\Accounting\UpdateTreasuryTransferRequest;
use App\Models\TreasuryTransfer;
use App\Models\User;
use App\Services\Accounting\TreasuryPresentationService;
use App\Services\Accounting\TreasuryTransferService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\TreasuryStatusRegistry;
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

final class TreasuryTransferController extends Controller
{
    public function __construct(
        private readonly TreasuryTransferService $service,
        private readonly TreasuryPresentationService $presentation,
        private readonly BranchAccessService $branchAccessService,
        private readonly TreasuryStatusRegistry $statusRegistry,
        private readonly TenantContext $tenantContext,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TreasuryTransfer::class);
        $actor = $this->actor($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $query = TreasuryTransfer::query()
            ->with(['sourceBranch:id,name,code', 'destinationBranch:id,name,code', 'sourceAccount:id,code,name,control_type', 'destinationAccount:id,code,name,control_type'])
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $query->where(static function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('transfer_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('source_account_name', 'like', "%{$search}%")
                        ->orWhere('destination_account_name', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['status']) && $filters['status'] !== '', static fn (Builder $query): Builder => $query->where('status', (string) $filters['status']))
            ->when(isset($filters['branch_id']), static function (Builder $query) use ($filters): void {
                $branchId = (int) $filters['branch_id'];
                $query->where(static fn (Builder $branchQuery): Builder => $branchQuery
                    ->where('source_branch_id', $branchId)
                    ->orWhere('destination_branch_id', $branchId));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
        $accessibleIds = $this->branchAccessService->accessibleBranches($actor, false)->pluck('id');
        $query->whereIn('source_branch_id', $accessibleIds)->whereIn('destination_branch_id', $accessibleIds);
        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();

        return Inertia::render('TreasuryTransfers/Index', [
            'documents' => [
                'data' => $paginator->getCollection()->map(fn (TreasuryTransfer $transfer): array => $this->presentation->transferSummary($transfer, $actor))->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
            'filters' => [
                'search' => $search,
                'status' => (string) ($filters['status'] ?? ''),
                'branch_id' => isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
                'per_page' => (int) ($filters['per_page'] ?? 15),
            ],
            'statuses' => $this->statusRegistry->options(),
            'branches' => $this->presentation->branches($actor, false),
            'can' => ['create' => $actor->can('create', TreasuryTransfer::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', TreasuryTransfer::class);

        return Inertia::render('TreasuryTransfers/Create', $this->formOptions($request));
    }

    public function store(StoreTreasuryTransferRequest $request): JsonResponse|RedirectResponse
    {
        $transfer = $this->service->create($request->validated(), $this->actor($request));

        return $this->responseService->success(
            'Treasury Transfer draft created.',
            ['id' => $transfer->getKey()],
            redirectTo: route('treasury-transfers.show', $transfer),
        );
    }

    public function show(Request $request, TreasuryTransfer $treasuryTransfer): Response
    {
        Gate::authorize('view', $treasuryTransfer);
        $treasuryTransfer->load(['sourceBranch', 'destinationBranch', 'sourceAccount', 'destinationAccount', 'createdBy', 'submittedBy', 'approvedBy', 'postedBy', 'reversedBy', 'cancelledBy']);

        return Inertia::render('TreasuryTransfers/Show', [
            'document' => $this->presentation->transferDetail($treasuryTransfer, $this->actor($request)),
        ]);
    }

    public function edit(Request $request, TreasuryTransfer $treasuryTransfer): Response
    {
        Gate::authorize('update', $treasuryTransfer);

        return Inertia::render('TreasuryTransfers/Edit', [
            ...$this->formOptions($request),
            'document' => $this->presentation->transferDetail($treasuryTransfer->load(['sourceBranch', 'destinationBranch', 'sourceAccount', 'destinationAccount']), $this->actor($request)),
        ]);
    }

    public function update(UpdateTreasuryTransferRequest $request, TreasuryTransfer $treasuryTransfer): JsonResponse|RedirectResponse
    {
        $transfer = $this->service->update($treasuryTransfer, $request->validated(), $this->actor($request));

        return $this->responseService->success('Treasury Transfer updated.', ['id' => $transfer->getKey()], redirectTo: route('treasury-transfers.show', $transfer));
    }

    public function destroy(Request $request, TreasuryTransfer $treasuryTransfer): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $treasuryTransfer);
        $this->service->delete($treasuryTransfer, $this->actor($request));

        return $this->responseService->success('Treasury Transfer deleted.', redirectTo: route('treasury-transfers.index'));
    }

    public function submit(Request $request, TreasuryTransfer $treasuryTransfer): JsonResponse|RedirectResponse
    {
        Gate::authorize('submit', $treasuryTransfer);

        return $this->action($this->service->submit($treasuryTransfer, $this->actor($request)), 'Treasury Transfer submitted.');
    }

    public function returnToDraft(Request $request, TreasuryTransfer $treasuryTransfer): JsonResponse|RedirectResponse
    {
        Gate::authorize('returnToDraft', $treasuryTransfer);

        return $this->action($this->service->returnToDraft($treasuryTransfer, $this->actor($request)), 'Treasury Transfer returned to draft.');
    }

    public function approve(Request $request, TreasuryTransfer $treasuryTransfer): JsonResponse|RedirectResponse
    {
        Gate::authorize('approve', $treasuryTransfer);

        return $this->action($this->service->approve($treasuryTransfer, $this->actor($request)), 'Treasury Transfer approved.');
    }

    public function post(Request $request, TreasuryTransfer $treasuryTransfer): JsonResponse|RedirectResponse
    {
        Gate::authorize('post', $treasuryTransfer);

        return $this->action($this->service->post($treasuryTransfer, $this->actor($request)), 'Treasury Transfer posted.');
    }

    public function cancel(TreasuryReasonRequest $request, TreasuryTransfer $treasuryTransfer): JsonResponse|RedirectResponse
    {
        Gate::authorize('cancel', $treasuryTransfer);

        return $this->action($this->service->cancel($treasuryTransfer, (string) $request->validated('reason'), $this->actor($request)), 'Treasury Transfer cancelled.');
    }

    public function reverse(TreasuryReasonRequest $request, TreasuryTransfer $treasuryTransfer): JsonResponse|RedirectResponse
    {
        Gate::authorize('reverse', $treasuryTransfer);
        $date = (string) ($request->validated('posting_date') ?? CarbonImmutable::now($this->tenantContext->tenant()->timezone)->format('Y-m-d'));

        return $this->action($this->service->reverse($treasuryTransfer, $date, (string) $request->validated('reason'), $this->actor($request)), 'Treasury Transfer reversed.');
    }

    /** @return array<string, mixed> */
    private function formOptions(Request $request): array
    {
        $actor = $this->actor($request);
        $today = CarbonImmutable::now($this->tenantContext->tenant()->timezone)->format('Y-m-d');

        return [
            'branches' => $this->presentation->branches($actor),
            'accounts' => $this->presentation->treasuryAccounts(),
            'defaults' => [
                'transfer_date' => $today,
                'posting_date' => $today,
                'currency_code' => $this->tenantContext->tenant()->currency_code,
                'exchange_rate' => '1.00000000',
            ],
        ];
    }

    private function action(TreasuryTransfer $transfer, string $message): JsonResponse|RedirectResponse
    {
        return $this->responseService->success($message, ['id' => $transfer->getKey(), 'status' => $transfer->status], redirectTo: route('treasury-transfers.show', $transfer));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
