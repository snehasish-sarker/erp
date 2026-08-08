<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\FinancialStatementRequest;
use App\Models\User;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Inertia\Inertia;
use Inertia\Response;

final class FinancialStatementController extends Controller
{
    public function __construct(
        private readonly FinancialStatementService $service,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function trialBalance(FinancialStatementRequest $request): Response
    {
        return $this->render('FinancialStatements/TrialBalance', $request, 'trialBalance');
    }

    public function profitAndLoss(FinancialStatementRequest $request): Response
    {
        return $this->render('FinancialStatements/ProfitAndLoss', $request, 'profitAndLoss');
    }

    public function balanceSheet(FinancialStatementRequest $request): Response
    {
        return $this->render('FinancialStatements/BalanceSheet', $request, 'balanceSheet');
    }

    public function cashFlow(FinancialStatementRequest $request): Response
    {
        return $this->render('FinancialStatements/CashFlow', $request, 'cashFlow');
    }

    public function printTrialBalance(FinancialStatementRequest $request): Response
    {
        return $this->print($request, 'trialBalance');
    }

    public function printProfitAndLoss(FinancialStatementRequest $request): Response
    {
        return $this->print($request, 'profitAndLoss');
    }

    public function printBalanceSheet(FinancialStatementRequest $request): Response
    {
        return $this->print($request, 'balanceSheet');
    }

    public function printCashFlow(FinancialStatementRequest $request): Response
    {
        return $this->print($request, 'cashFlow');
    }

    private function render(string $component, FinancialStatementRequest $request, string $method): Response
    {
        $actor = $this->actor($request);

        return Inertia::render($component, [
            'report' => $this->service->{$method}($request->validated(), $actor),
            'branches' => $this->branches($actor),
        ]);
    }

    private function print(FinancialStatementRequest $request, string $method): Response
    {
        $actor = $this->actor($request);

        return Inertia::render('FinancialStatements/Print/Statement', [
            'report' => $this->service->{$method}($request->validated(), $actor),
            'company' => $this->company(),
        ]);
    }

    private function actor(FinancialStatementRequest $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        return $actor;
    }

    /** @return list<array<string, mixed>> */
    private function branches(User $actor): array
    {
        return $this->branchAccessService->accessibleBranches($actor, false)
            ->map(static fn ($branch): array => [
                'id' => (int) $branch->getKey(),
                'code' => $branch->code,
                'name' => $branch->name,
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function company(): array
    {
        $tenant = $this->tenantContext->tenant();

        return [
            'name' => $tenant->name,
            'code' => $tenant->code,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'address' => $tenant->address,
            'currency_code' => $tenant->currency_code,
            'timezone' => $tenant->timezone,
        ];
    }
}
