<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\BankStatementImportRequest;
use App\Models\BankStatementImport;
use App\Models\User;
use App\Services\Accounting\BankStatementImportService;
use App\Services\Accounting\TreasuryPresentationService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Responses\CommonResponseService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

final class BankStatementController extends Controller
{
    public function __construct(
        private readonly BankStatementImportService $service,
        private readonly TreasuryPresentationService $presentation,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', BankStatementImport::class);
        $actor = $this->actor($request);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'bank_account_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'in:10,15,25,50,100'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $query = BankStatementImport::query()
            ->with(['branch:id,name,code', 'bankAccount:id,code,name,control_type', 'importedBy:id,name'])
            ->when($search !== '', static function (Builder $query) use ($search): void {
                $query->where(static fn (Builder $searchQuery): Builder => $searchQuery
                    ->where('statement_reference', 'like', "%{$search}%")
                    ->orWhere('source_filename', 'like', "%{$search}%"));
            })
            ->when(isset($filters['status']) && $filters['status'] !== '', static fn (Builder $query): Builder => $query->where('status', (string) $filters['status']))
            ->when(isset($filters['branch_id']), static fn (Builder $query): Builder => $query->where('branch_id', (int) $filters['branch_id']))
            ->when(isset($filters['bank_account_id']), static fn (Builder $query): Builder => $query->where('bank_account_id', (int) $filters['bank_account_id']))
            ->orderByDesc('period_end')
            ->orderByDesc('id');
        $this->branchAccessService->scopeQuery($query, $actor, 'bank_statement_imports.branch_id');
        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15))->withQueryString();

        return Inertia::render('BankStatements/Index', [
            'statements' => [
                'data' => $paginator->getCollection()->map(fn (BankStatementImport $statement): array => $this->presentation->statementSummary($statement, $actor))->values()->all(),
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
            'branches' => $this->presentation->branches($actor, false),
            'bankAccounts' => $this->presentation->treasuryAccounts('bank'),
            'can' => ['import' => $actor->can('bank_statements.import')],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('import', BankStatementImport::class);
        $actor = $this->actor($request);

        return Inertia::render('BankStatements/Create', [
            'branches' => $this->presentation->branches($actor),
            'bankAccounts' => $this->presentation->treasuryAccounts('bank'),
            'currencyCode' => $this->tenantContext->tenant()->currency_code,
        ]);
    }

    public function store(BankStatementImportRequest $request): JsonResponse|RedirectResponse
    {
        $file = $request->file('statement_file');

        if (!$file instanceof UploadedFile) {
            abort(422, 'A valid bank statement CSV file is required.');
        }

        $statement = $this->service->import(
            $request->validated(),
            $file,
            $this->actor($request),
        );

        return $this->responseService->success('Bank statement imported.', ['id' => $statement->getKey()], redirectTo: route('bank-statements.show', $statement));
    }

    public function show(Request $request, BankStatementImport $bankStatement): Response
    {
        Gate::authorize('view', $bankStatement);
        $bankStatement->load(['branch', 'bankAccount', 'importedBy', 'lines', 'reconciliations.branch', 'reconciliations.bankAccount', 'reconciliations.statementImport']);

        return Inertia::render('BankStatements/Show', [
            'statement' => $this->presentation->statementDetail($bankStatement, $this->actor($request)),
            'canCreateReconciliation' => $this->actor($request)->can('create', \App\Models\BankReconciliation::class)
                && $bankStatement->status === 'imported'
                && $bankStatement->reconciliations()->whereIn('status', ['draft', 'completed'])->doesntExist(),
        ]);
    }

    public function destroy(Request $request, BankStatementImport $bankStatement): JsonResponse|RedirectResponse
    {
        Gate::authorize('delete', $bankStatement);
        $this->service->delete($bankStatement, $this->actor($request));

        return $this->responseService->success('Bank statement deleted.', redirectTo: route('bank-statements.index'));
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(static function (): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['transaction_date', 'value_date', 'reference', 'description', 'debit', 'credit', 'balance']);
            fputcsv($handle, ['2026-08-01', '2026-08-01', 'BANK-REF-001', 'Example incoming deposit', '0.000000', '1000.000000', '1000.000000']);
            fputcsv($handle, ['2026-08-02', '2026-08-02', 'BANK-REF-002', 'Example outgoing payment', '250.000000', '0.000000', '750.000000']);
            fclose($handle);
        }, 'bank-statement-import-template.csv', ['Content-Type' => 'text/csv']);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
