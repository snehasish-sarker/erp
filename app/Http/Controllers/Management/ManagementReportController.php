<?php

declare(strict_types=1);

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\ManagementReportRequest;
use App\Models\ManagementBudget;
use App\Models\User;
use App\Services\Management\ManagementReportingService;
use App\Services\Organisation\BranchAccessService;
use Inertia\Inertia;
use Inertia\Response;

final class ManagementReportController extends Controller
{
    public function __construct(
        private readonly ManagementReportingService $service,
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function branchProfitability(ManagementReportRequest $request): Response
    {
        return $this->render($request, 'branch_profitability', 'Branch Profitability', 'branchProfitability');
    }

    public function budgetVsActual(ManagementReportRequest $request): Response
    {
        $actor = $this->actor($request);
        $validated = $request->validated();
        $report = isset($validated['budget_id'])
            ? $this->service->budgetVsActual($validated, $actor)
            : null;

        return Inertia::render('Management/Reports/Analysis', [
            'reportType' => 'budget_vs_actual',
            'title' => 'Budget vs Actual',
            'report' => $report,
            'filters' => $this->service->context($validated, $actor),
            'branches' => $this->branches($actor),
            'budgets' => $this->budgets($actor),
            'exportType' => 'management_budget_vs_actual',
        ]);
    }

    public function productProfitability(ManagementReportRequest $request): Response
    {
        return $this->render($request, 'product_profitability', 'Product Profitability', 'productProfitability');
    }

    public function customerProfitability(ManagementReportRequest $request): Response
    {
        return $this->render($request, 'customer_profitability', 'Customer Profitability', 'customerProfitability');
    }

    public function supplierSpend(ManagementReportRequest $request): Response
    {
        return $this->render($request, 'supplier_spend', 'Supplier Spend Analysis', 'supplierSpend');
    }

    public function grossMargin(ManagementReportRequest $request): Response
    {
        return $this->render($request, 'gross_margin', 'Gross Margin Analysis', 'grossMargin');
    }

    private function render(
        ManagementReportRequest $request,
        string $type,
        string $title,
        string $method,
    ): Response {
        $actor = $this->actor($request);
        $validated = $request->validated();

        return Inertia::render('Management/Reports/Analysis', [
            'reportType' => $type,
            'title' => $title,
            'report' => $this->service->{$method}($validated, $actor),
            'filters' => $this->service->context($validated, $actor),
            'branches' => $this->branches($actor),
            'budgets' => [],
            'exportType' => 'management_'.$type,
        ]);
    }

    private function actor(ManagementReportRequest $request): User
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
            ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function budgets(User $actor): array
    {
        $query = ManagementBudget::query()
            ->with(['branch:id,code,name', 'fiscalYear:id,name,code,start_date,end_date'])
            ->where('status', 'approved')
            ->orderByDesc('fiscal_year_id')
            ->orderBy('name');
        $this->branchAccessService->scopeQuery($query, $actor, 'management_budgets.branch_id');

        return $query->get()->map(static fn (ManagementBudget $budget): array => [
            'id' => (int) $budget->getKey(),
            'name' => $budget->name,
            'branch_name' => $budget->branch?->name,
            'fiscal_year_name' => $budget->fiscalYear?->name,
        ])->values()->all();
    }
}