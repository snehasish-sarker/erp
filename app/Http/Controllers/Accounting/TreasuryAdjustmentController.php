<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreTreasuryAdjustmentRequest;
use App\Http\Requests\Accounting\TreasuryReasonRequest;
use App\Http\Requests\Accounting\UpdateTreasuryAdjustmentRequest;
use App\Models\BankStatementLine;
use App\Models\TreasuryAdjustment;
use App\Models\User;
use App\Services\Accounting\TreasuryAdjustmentService;
use App\Services\Accounting\TreasuryPresentationService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\TreasuryAdjustmentTypeRegistry;
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

final class TreasuryAdjustmentController extends Controller
{
    public function __construct(
        private readonly TreasuryAdjustmentService $service,
        private readonly TreasuryPresentationService $presentation,
        private readonly BranchAccessService $branchAccessService,
        private readonly TreasuryStatusRegistry $statusRegistry,
        private readonly TreasuryAdjustmentTypeRegistry $typeRegistry,
        private readonly TenantContext $tenantContext,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TreasuryAdjustment::class);
        $actor = $this->actor($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string'],
            'adjustment_type' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $query = TreasuryAdjustment::query()
            ->with(['branch:id,name,code', 'bankAccount:id,code,name,control_type', 'offsetAccount:id,code,name'])
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $query->where(static function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('adjustment_number', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('bank_account_name', 'like', "%{$search}%");
                });
            })
            ->when(isset($filters['status']) && $filters['status'] !== '', static fn (Builder $query): Builder => $query->where('status', (string) $filters['status']))
            ->when(isset($filters['adjustment_type']) && $filters['adjustment_type'] !== '', static fn (Builder $query): Builder => $query->where('adjustment_type', (string) $filters['adjustment_type']))
            ->when(isset($filters['branch_id']), static fn (Builder $query): Builder => $query->where('branch_id', (int) $filters['branch_id']))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
        $this->branchAccessService->scopeQuery($query, $actor, 'treasury_adjustments.branch_id');
        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();

        return Inertia::render('TreasuryAdjustments/Index', [
            'documents' => [
                'data' => $paginator->getCollection()->map(fn (TreasuryAdjustment $adjustment): array => $this->presentation->adjustmentSummary($adjustment, $actor))->values()->all(),
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
                'adjustment_type' => (string) ($filters['adjustment_type'] ?? ''),
                'branch_id' => isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
                'per_page' => (int) ($filters['per_page'] ?? 15),
            ],
            'statuses' => $this->statusRegistry->options(),
            'types' => $this->typeRegistry->options(),
            'branches' => $this->presentation->branches($actor, false),
            'can' => ['create' => $actor->can('create', TreasuryAdjustment::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', TreasuryAdjustment::class);

        return Inertia::render('TreasuryAdjustments/Create', $this->formOptions($request));
    }

    public function store(StoreTreasuryAdjustmentRequest $request): JsonResponse|RedirectResponse
    {
        $adjustment = $this->service->create($request->validated(), $this->actor($request));

        return $this->responseService->success('Treasury Adjustment draft created.', ['id' => $adjustment->getKey()], redirectTo: route('treasury-adjustments.show', $adjustment));
    }

    public function show(Request $request, TreasuryAdjustment $treasuryAdjustment): Response
    {
        Gate::authorize('view', $treasuryAdjustment);
        $treasuryAdjustment->load(['branch', 'bankAccount', 'offsetAccount', 'bankStatementLine', 'createdBy', 'submittedBy', 'approvedBy', 'postedBy', 'reversedBy', 'cancelledBy']);

        return Inertia::render('TreasuryAdjustments/Show', [
            'document' => $this->presentation->adjustmentDetail($treasuryAdjustment, $this->actor($request)),
        ]);
    }

    public function edit(Request $request, TreasuryAdjustment $treasuryAdjustment): Response
    {
        Gate::authorize('update', $treasuryAdjustment);

        return Inertia::render('TreasuryAdjustments/Edit', [
            ...$this->formOptions($request),
            'document' => $this->presentation->adjustmentDetail($treasuryAdjustment->load(['branch', 'bankAccount', 'offsetAccount', 'bankStatementLine']), $this->actor($request)),
        ]);
    }

    public function update(UpdateTreasuryAdjustmentRequest $request, TreasuryAdjustment $treasuryAdjustment): JsonResponse|RedirectResponse
    {
        $adjustment = $this->service->update($treasuryAdjustment, $request->validated(), $this->actor($request));

        return $this->responseService->success('Treasury Adjustment updated.', ['id' => $adjustment->getKey()], redirectTo: route('treasury-adjustments.show', $adjustment));
    }

    public function destroy(Request $request, TreasuryAdjustment $treasuryAdjustment): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $treasuryAdjustment);
        $this->service->delete($treasuryAdjustment, $this->actor($request));

        return $this->responseService->success('Treasury Adjustment deleted.', redirectTo: route('treasury-adjustments.index'));
    }

    public function submit(Request $request, TreasuryAdjustment $treasuryAdjustment): JsonResponse|RedirectResponse
    {
        Gate::authorize('submit', $treasuryAdjustment);

        return $this->action($this->service->submit($treasuryAdjustment, $this->actor($request)), 'Treasury Adjustment submitted.');
    }

    public function returnToDraft(Request $request, TreasuryAdjustment $treasuryAdjustment): JsonResponse|RedirectResponse
    {
        Gate::authorize('returnToDraft', $treasuryAdjustment);

        return $this->action($this->service->returnToDraft($treasuryAdjustment, $this->actor($request)), 'Treasury Adjustment returned to draft.');
    }

    public function approve(Request $request, TreasuryAdjustment $treasuryAdjustment): JsonResponse|RedirectResponse
    {
        Gate::authorize('approve', $treasuryAdjustment);

        return $this->action($this->service->approve($treasuryAdjustment, $this->actor($request)), 'Treasury Adjustment approved.');
    }

    public function post(Request $request, TreasuryAdjustment $treasuryAdjustment): JsonResponse|RedirectResponse
    {
        Gate::authorize('post', $treasuryAdjustment);

        return $this->action($this->service->post($treasuryAdjustment, $this->actor($request)), 'Treasury Adjustment posted.');
    }

    public function cancel(TreasuryReasonRequest $request, TreasuryAdjustment $treasuryAdjustment): JsonResponse|RedirectResponse
    {
        Gate::authorize('cancel', $treasuryAdjustment);

        return $this->action($this->service->cancel($treasuryAdjustment, (string) $request->validated('reason'), $this->actor($request)), 'Treasury Adjustment cancelled.');
    }

    public function reverse(TreasuryReasonRequest $request, TreasuryAdjustment $treasuryAdjustment): JsonResponse|RedirectResponse
    {
        Gate::authorize('reverse', $treasuryAdjustment);
        $date = (string) ($request->validated('posting_date') ?? CarbonImmutable::now($this->tenantContext->tenant()->timezone)->format('Y-m-d'));

        return $this->action($this->service->reverse($treasuryAdjustment, $date, (string) $request->validated('reason'), $this->actor($request)), 'Treasury Adjustment reversed.');
    }

    /** @return array<string, mixed> */
    private function formOptions(Request $request): array
    {
        $actor = $this->actor($request);
        $today = CarbonImmutable::now($this->tenantContext->tenant()->timezone)->format('Y-m-d');
        $statementLineId = $request->filled('bank_statement_line_id') ? $request->integer('bank_statement_line_id') : null;
        $accessibleBranchIds = $this->branchAccessService
            ->accessibleBranches($actor, true)
            ->pluck('id');
        $statementLine = $statementLineId === null
            ? null
            : BankStatementLine::query()
                ->with('statementImport')
                ->whereKey($statementLineId)
                ->where('status', 'unmatched')
                ->whereHas(
                    'statementImport',
                    static fn (Builder $query): Builder => $query
                        ->where('status', 'imported')
                        ->whereIn('branch_id', $accessibleBranchIds),
                )
                ->first();

        return [
            'branches' => $this->presentation->branches($actor),
            'bankAccounts' => $this->presentation->treasuryAccounts('bank'),
            'offsetAccounts' => $this->presentation->offsetAccounts(),
            'types' => $this->typeRegistry->options(),
            'selectedStatementLine' => $statementLine instanceof BankStatementLine
                ? [
                    'id' => (int) $statementLine->getKey(),
                    'bank_account_id' => (int) $statementLine->bank_account_id,
                    'branch_id' => (int) $statementLine->statementImport->branch_id,
                    'currency_code' => (string) $statementLine->statementImport->currency_code,
                    'transaction_date' => $statementLine->transaction_date?->format('Y-m-d'),
                    'bank_reference' => $statementLine->bank_reference,
                    'description' => $statementLine->description,
                    'signed_amount' => (string) $statementLine->signed_amount,
                ]
                : null,
            'defaults' => [
                'adjustment_date' => $statementLine?->transaction_date?->format('Y-m-d') ?? $today,
                'posting_date' => $statementLine?->transaction_date?->format('Y-m-d') ?? $today,
                'currency_code' => $this->tenantContext->tenant()->currency_code,
                'exchange_rate' => '1.00000000',
            ],
        ];
    }

    private function action(TreasuryAdjustment $adjustment, string $message): JsonResponse|RedirectResponse
    {
        return $this->responseService->success($message, ['id' => $adjustment->getKey(), 'status' => $adjustment->status], redirectTo: route('treasury-adjustments.show', $adjustment));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
