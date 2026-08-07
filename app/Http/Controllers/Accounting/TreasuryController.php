<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\BankStatementImport;
use App\Models\TreasuryAdjustment;
use App\Models\TreasuryTransfer;
use App\Models\User;
use App\Services\Accounting\TreasuryPresentationService;
use App\Services\Accounting\TreasuryRegisterService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TreasuryController extends Controller
{
    public function __construct(
        private readonly TreasuryRegisterService $registerService,
        private readonly TreasuryPresentationService $presentation,
    ) {
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('treasury.view') === true, 403);
        $actor = $this->actor($request);

        return Inertia::render('Treasury/Index', [
            'accounts' => $this->registerService->accountCards($actor),
            'metrics' => [
                'draft_transfers' => TreasuryTransfer::query()->whereIn('status', ['draft', 'submitted', 'approved'])->count(),
                'draft_adjustments' => TreasuryAdjustment::query()->whereIn('status', ['draft', 'submitted', 'approved'])->count(),
                'unreconciled_statements' => BankStatementImport::query()->where('status', 'imported')->count(),
                'draft_reconciliations' => BankReconciliation::query()->where('status', 'draft')->count(),
            ],
            'can' => [
                'view_register' => $actor->can('treasury.view'),
                'create_transfer' => $actor->can('create', TreasuryTransfer::class),
                'create_adjustment' => $actor->can('create', TreasuryAdjustment::class),
                'import_statement' => $actor->can('bank_statements.import'),
                'create_reconciliation' => $actor->can('create', BankReconciliation::class),
            ],
        ]);
    }

    public function register(Request $request): Response
    {
        abort_unless($request->user()?->can('treasury.view') === true, 403);
        $actor = $this->actor($request);
        $filters = $request->validate([
            'account_id' => ['nullable', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:160'],
        ]);

        return Inertia::render('Treasury/Register', [
            'register' => $this->registerService->register($filters, $actor),
            'filters' => [
                'account_id' => isset($filters['account_id']) ? (int) $filters['account_id'] : null,
                'branch_id' => isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
                'date_from' => (string) ($filters['date_from'] ?? ''),
                'date_to' => (string) ($filters['date_to'] ?? ''),
                'search' => trim((string) ($filters['search'] ?? '')),
            ],
            'branches' => $this->presentation->branches($actor, false),
            'accounts' => $this->presentation->treasuryAccounts(),
        ]);
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }
}
