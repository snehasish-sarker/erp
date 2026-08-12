<?php

declare(strict_types=1);

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\IndexManagementBudgetRequest;
use App\Http\Requests\Management\StoreManagementBudgetRequest;
use App\Http\Requests\Management\UpdateManagementBudgetRequest;
use App\Models\ManagementBudget;
use App\Models\ManagementBudgetLine;
use App\Models\User;
use App\Services\Management\ManagementBudgetService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Management\ManagementBudgetStatusRegistry;
use App\Support\Responses\CommonResponseService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ManagementBudgetController extends Controller
{
    public function __construct(
        private readonly ManagementBudgetService $service,
        private readonly BranchAccessService $branchAccessService,
        private readonly ManagementBudgetStatusRegistry $statusRegistry,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(IndexManagementBudgetRequest $request): Response
    {
        Gate::authorize('viewAny', ManagementBudget::class);
        $actor = $this->actor($request);
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));
        $query = ManagementBudget::query()
            ->with(['branch:id,code,name', 'fiscalYear:id,name,code,start_date,end_date'])
            ->when($search !== '', static fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%"))
            ->when(isset($filters['branch_id']), static fn (Builder $query): Builder => $query->where('branch_id', (int) $filters['branch_id']))
            ->when(isset($filters['fiscal_year_id']), static fn (Builder $query): Builder => $query->where('fiscal_year_id', (int) $filters['fiscal_year_id']))
            ->when(isset($filters['status']) && $filters['status'] !== '', static fn (Builder $query): Builder => $query->where('status', (string) $filters['status']))
            ->orderByDesc('fiscal_year_id')->orderBy('branch_id');
        $this->branchAccessService->scopeQuery($query, $actor, 'management_budgets.branch_id');
        $paginator = $query->paginate((int) ($filters['per_page'] ?? 25))->withQueryString();
        $options = $this->service->formOptions($actor);

        return Inertia::render('Management/Budgets/Index', [
            'budgets' => [
                'data' => $paginator->getCollection()->map(fn (ManagementBudget $budget): array => $this->summary($budget, $actor))->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total(), 'per_page' => $paginator->perPage(),
                ],
            ],
            'filters' => [
                'search' => $search,
                'branch_id' => isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
                'fiscal_year_id' => isset($filters['fiscal_year_id']) ? (int) $filters['fiscal_year_id'] : null,
                'status' => (string) ($filters['status'] ?? ''),
                'per_page' => (int) ($filters['per_page'] ?? 25),
            ],
            'branches' => $options['branches'],
            'fiscalYears' => $options['fiscal_years'],
            'statuses' => $this->statusRegistry->options(),
            'can' => ['create' => Gate::allows('create', ManagementBudget::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', ManagementBudget::class);
        return Inertia::render('Management/Budgets/Create', $this->service->formOptions($this->actor($request)));
    }

    public function store(StoreManagementBudgetRequest $request): JsonResponse|RedirectResponse
    {
        $budget = $this->service->create($request->validated(), $this->actor($request));
        return $this->responseService->success('Management budget created.', $this->responseData($budget), redirectTo: route('management.budgets.show', $budget));
    }

    public function show(Request $request, ManagementBudget $managementBudget): Response
    {
        Gate::authorize('view', $managementBudget);
        $actor = $this->actor($request);
        $this->ensureBranch($managementBudget, $actor);
        return Inertia::render('Management/Budgets/Show', ['budget' => $this->detail($this->service->load($managementBudget), $actor)]);
    }

    public function edit(Request $request, ManagementBudget $managementBudget): Response
    {
        Gate::authorize('update', $managementBudget);
        $actor = $this->actor($request);
        $this->ensureBranch($managementBudget, $actor);
        return Inertia::render('Management/Budgets/Edit', [
            ...$this->service->formOptions($actor),
            'budget' => $this->formData($this->service->load($managementBudget)),
        ]);
    }

    public function update(UpdateManagementBudgetRequest $request, ManagementBudget $managementBudget): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $managementBudget);
        $budget = $this->service->update($managementBudget, $request->validated(), $this->actor($request));
        return $this->responseService->success('Management budget updated.', $this->responseData($budget), redirectTo: route('management.budgets.show', $budget));
    }

    public function destroy(Request $request, ManagementBudget $managementBudget): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $managementBudget);
        $this->service->delete($managementBudget, $this->actor($request));
        return $this->responseService->success('Management budget deleted.', redirectTo: route('management.budgets.index'));
    }

    public function approve(Request $request, ManagementBudget $managementBudget): JsonResponse|RedirectResponse
    {
        Gate::authorize('approve', $managementBudget);
        $budget = $this->service->approve($managementBudget, $this->actor($request));
        return $this->responseService->success('Management budget approved.', $this->responseData($budget), redirectTo: route('management.budgets.show', $budget));
    }

    public function reopen(Request $request, ManagementBudget $managementBudget): JsonResponse|RedirectResponse
    {
        Gate::authorize('reopen', $managementBudget);
        $budget = $this->service->reopen($managementBudget, $this->actor($request));
        return $this->responseService->success('Management budget reopened.', $this->responseData($budget), redirectTo: route('management.budgets.show', $budget));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        return $actor;
    }

    private function ensureBranch(ManagementBudget $budget, User $actor): void
    {
        abort_if($this->branchAccessService->findAccessibleBranch($actor, (int) $budget->branch_id, false) === null, 403);
    }

    /** @return array<string, mixed> */
    private function summary(ManagementBudget $budget, User $actor): array
    {
        return [
            'id' => (int) $budget->getKey(), 'name' => $budget->name, 'currency_code' => $budget->currency_code,
            'status' => $budget->status, 'status_label' => $this->statusRegistry->label($budget->status),
            'branch' => $budget->branch === null ? null : ['id' => (int) $budget->branch->getKey(), 'code' => $budget->branch->code, 'name' => $budget->branch->name],
            'fiscal_year' => $budget->fiscalYear === null ? null : ['id' => (int) $budget->fiscalYear->getKey(), 'name' => $budget->fiscalYear->name, 'code' => $budget->fiscalYear->code],
            'can' => ['view' => $actor->can('view', $budget), 'update' => $actor->can('update', $budget), 'delete' => $actor->can('delete', $budget), 'approve' => $actor->can('approve', $budget), 'reopen' => $actor->can('reopen', $budget)],
        ];
    }

    /** @return array<string, mixed> */
    private function detail(ManagementBudget $budget, User $actor): array
    {
        $total = BigDecimal::of('0');
        foreach ($budget->lines as $line) {
            $total = $total->plus((string) $line->amount);
        }
        $totalAmount = $total->toScale(6, RoundingMode::HalfUp)->__toString();
        return [
            ...$this->summary($budget, $actor),
            'notes' => $budget->notes, 'total_amount' => $totalAmount, 'approved_at' => $budget->approved_at?->toIso8601String(),
            'approved_by' => $budget->approvedBy === null ? null : ['id' => (int) $budget->approvedBy->getKey(), 'name' => $budget->approvedBy->name],
            'lines' => $budget->lines->map(static fn (ManagementBudgetLine $line): array => [
                'id' => (int) $line->getKey(), 'account_id' => (int) $line->account_id,
                'account_code' => $line->account?->code ?? '', 'account_name' => $line->account?->name ?? '', 'account_type' => $line->account?->account_type ?? '',
                'month_number' => (int) $line->month_number, 'amount' => (string) $line->amount, 'notes' => $line->notes,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function formData(ManagementBudget $budget): array
    {
        return [
            'id' => (int) $budget->getKey(), 'branch_id' => (int) $budget->branch_id, 'fiscal_year_id' => (int) $budget->fiscal_year_id,
            'name' => $budget->name, 'notes' => $budget->notes,
            'lines' => $budget->lines->map(static fn (ManagementBudgetLine $line): array => [
                'account_id' => (int) $line->account_id, 'month_number' => (int) $line->month_number, 'amount' => (string) $line->amount, 'notes' => $line->notes,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function responseData(ManagementBudget $budget): array
    {
        return ['id' => (int) $budget->getKey(), 'status' => $budget->status];
    }
}