<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\BankReconciliationMatchRequest;
use App\Http\Requests\Accounting\StoreBankReconciliationRequest;
use App\Http\Requests\Accounting\TreasuryReasonRequest;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatementLine;
use App\Models\User;
use App\Services\Accounting\BankReconciliationService;
use App\Services\Accounting\TreasuryPresentationService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Accounting\BankReconciliationStatusRegistry;
use App\Support\Responses\CommonResponseService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class BankReconciliationController extends Controller
{
    public function __construct(
        private readonly BankReconciliationService $service,
        private readonly TreasuryPresentationService $presentation,
        private readonly BranchAccessService $branchAccessService,
        private readonly BankReconciliationStatusRegistry $statusRegistry,
        private readonly TenantContext $tenantContext,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', BankReconciliation::class);
        $actor = $this->actor($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'bank_account_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $query = BankReconciliation::query()
            ->with(['branch:id,name,code', 'bankAccount:id,code,name,control_type', 'statementImport:id,statement_reference,source_filename'])
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $query->where(static function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('reconciliation_number', 'like', "%{$search}%")
                        ->orWhereHas('statementImport', static fn (Builder $statementQuery): Builder => $statementQuery
                            ->where('statement_reference', 'like', "%{$search}%")
                            ->orWhere('source_filename', 'like', "%{$search}%"));
                });
            })
            ->when(isset($filters['status']) && $filters['status'] !== '', static fn (Builder $query): Builder => $query->where('status', (string) $filters['status']))
            ->when(isset($filters['branch_id']), static fn (Builder $query): Builder => $query->where('branch_id', (int) $filters['branch_id']))
            ->when(isset($filters['bank_account_id']), static fn (Builder $query): Builder => $query->where('bank_account_id', (int) $filters['bank_account_id']))
            ->orderByDesc('statement_end_date')
            ->orderByDesc('id');
        $this->branchAccessService->scopeQuery($query, $actor, 'bank_reconciliations.branch_id');
        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();

        return Inertia::render('BankReconciliations/Index', [
            'reconciliations' => [
                'data' => $paginator->getCollection()->map(fn (BankReconciliation $reconciliation): array => $this->presentation->reconciliationSummary($reconciliation, $actor))->values()->all(),
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
                'bank_account_id' => isset($filters['bank_account_id']) ? (int) $filters['bank_account_id'] : null,
                'per_page' => (int) ($filters['per_page'] ?? 15),
            ],
            'statuses' => $this->statusRegistry->options(),
            'branches' => $this->presentation->branches($actor, false),
            'bankAccounts' => $this->presentation->treasuryAccounts('bank'),
            'can' => ['create' => $actor->can('create', BankReconciliation::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', BankReconciliation::class);

        return Inertia::render('BankReconciliations/Create', [
            'statements' => $this->presentation->availableStatements($this->actor($request)),
            'selectedStatementId' => $request->filled('bank_statement_import_id')
                ? $request->integer('bank_statement_import_id')
                : null,
        ]);
    }

    public function store(StoreBankReconciliationRequest $request): JsonResponse|RedirectResponse
    {
        $reconciliation = $this->service->create($request->validated(), $this->actor($request));

        return $this->responseService->success('Bank reconciliation session created.', ['id' => $reconciliation->getKey()], redirectTo: route('bank-reconciliations.show', $reconciliation));
    }

    public function show(Request $request, BankReconciliation $bankReconciliation): Response
    {
        Gate::authorize('view', $bankReconciliation);
        $actor = $this->actor($request);
        $bankReconciliation = $bankReconciliation->isDraft()
            ? $this->service->refresh($bankReconciliation, $actor)
            : $bankReconciliation->load([
                'branch:id,name,code',
                'bankAccount:id,code,name,control_type',
                'statementImport.lines.matches',
                'matches.statementLine',
                'matches.journalEntryLine.journalEntry',
                'createdBy:id,name',
                'completedBy:id,name',
                'reversedBy:id,name',
            ]);
        $available = $bankReconciliation->isDraft()
            ? $this->service->availableJournalLines($bankReconciliation)
            : collect();

        return Inertia::render('BankReconciliations/Show', [
            'reconciliation' => $this->presentation->reconciliationDetail($bankReconciliation, $actor, $available),
        ]);
    }

    public function destroy(Request $request, BankReconciliation $bankReconciliation): JsonResponse|RedirectResponse
    {
        Gate::authorize('match', $bankReconciliation);
        $this->service->delete($bankReconciliation, $this->actor($request));

        return $this->responseService->success('Draft bank reconciliation deleted.', redirectTo: route('bank-reconciliations.index'));
    }

    public function automaticMatch(Request $request, BankReconciliation $bankReconciliation): JsonResponse|RedirectResponse
    {
        Gate::authorize('match', $bankReconciliation);

        return $this->action($this->service->automaticMatch($bankReconciliation, $this->actor($request)), 'Automatic matching completed.');
    }

    public function manualMatch(BankReconciliationMatchRequest $request, BankReconciliation $bankReconciliation): JsonResponse|RedirectResponse
    {
        $reconciliation = $this->service->manualMatch(
            $bankReconciliation,
            (int) $request->validated('bank_statement_line_id'),
            (int) $request->validated('journal_entry_line_id'),
            (string) $request->validated('matched_amount'),
            $this->actor($request),
        );

        return $this->action($reconciliation, 'Bank transaction matched.');
    }

    public function unmatch(Request $request, BankReconciliation $bankReconciliation, BankReconciliationMatch $bankReconciliationMatch): JsonResponse|RedirectResponse
    {
        Gate::authorize('match', $bankReconciliation);

        return $this->action($this->service->unmatch($bankReconciliation, $bankReconciliationMatch, $this->actor($request)), 'Bank transaction unmatched.');
    }

    public function ignore(TreasuryReasonRequest $request, BankReconciliation $bankReconciliation, BankStatementLine $bankStatementLine): JsonResponse|RedirectResponse
    {
        Gate::authorize('match', $bankReconciliation);

        return $this->action($this->service->ignoreLine($bankReconciliation, $bankStatementLine, (string) $request->validated('reason'), $this->actor($request)), 'Statement line ignored.');
    }

    public function unignore(Request $request, BankReconciliation $bankReconciliation, BankStatementLine $bankStatementLine): JsonResponse|RedirectResponse
    {
        Gate::authorize('match', $bankReconciliation);

        return $this->action($this->service->unignoreLine($bankReconciliation, $bankStatementLine, $this->actor($request)), 'Statement line restored.');
    }

    public function refresh(Request $request, BankReconciliation $bankReconciliation): JsonResponse|RedirectResponse
    {
        Gate::authorize('view', $bankReconciliation);

        return $this->action($this->service->refresh($bankReconciliation, $this->actor($request)), 'Reconciliation balances refreshed.');
    }

    public function complete(Request $request, BankReconciliation $bankReconciliation): JsonResponse|RedirectResponse
    {
        Gate::authorize('complete', $bankReconciliation);

        return $this->action($this->service->complete($bankReconciliation, $this->actor($request)), 'Bank reconciliation completed.');
    }

    public function reverse(TreasuryReasonRequest $request, BankReconciliation $bankReconciliation): JsonResponse|RedirectResponse
    {
        Gate::authorize('reverse', $bankReconciliation);

        return $this->action($this->service->reverse($bankReconciliation, (string) $request->validated('reason'), $this->actor($request)), 'Bank reconciliation reversed.');
    }

    public function print(Request $request, BankReconciliation $bankReconciliation): Response
    {
        Gate::authorize('view', $bankReconciliation);
        $actor = $this->actor($request);
        $bankReconciliation = $bankReconciliation->isDraft()
            ? $this->service->refresh($bankReconciliation, $actor)
            : $bankReconciliation->load([
                'branch:id,name,code',
                'bankAccount:id,code,name,control_type',
                'statementImport.lines.matches',
                'matches.statementLine',
                'matches.journalEntryLine.journalEntry',
                'createdBy:id,name',
                'completedBy:id,name',
                'reversedBy:id,name',
            ]);
        $tenant = $this->tenantContext->tenant();

        return Inertia::render('BankReconciliations/Print/Statement', [
            'reconciliation' => $this->presentation->reconciliationDetail($bankReconciliation, $actor, collect()),
            'company' => [
                'name' => $tenant->name,
                'code' => $tenant->code,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'address' => $tenant->address,
            ],
        ]);
    }

    private function action(BankReconciliation $reconciliation, string $message): JsonResponse|RedirectResponse
    {
        return $this->responseService->success($message, ['id' => $reconciliation->getKey(), 'status' => $reconciliation->status], redirectTo: route('bank-reconciliations.show', $reconciliation));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
