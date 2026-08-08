<?php

declare(strict_types=1);

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\StoreManagementReportScheduleRequest;
use App\Models\ManagementBudget;
use App\Models\ManagementReportSchedule;
use App\Models\User;
use App\Services\Management\ManagementReportScheduleService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ManagementReportScheduleController extends Controller
{
    public function __construct(
        private readonly ManagementReportScheduleService $service,
        private readonly BranchAccessService $branchAccessService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(Request $request): Response
    {
        $actor = $this->actor($request);
        $query = ManagementReportSchedule::query()->with(['branch:id,code,name', 'createdBy:id,name,email,status'])->orderBy('name');
        $this->branchAccessService->scopeQuery($query, $actor, 'management_report_schedules.branch_id');
        $schedules = $query->get()->map(fn (ManagementReportSchedule $schedule): array => $this->map($schedule))->values()->all();

        $budgets = ManagementBudget::query()->with(['branch:id,code,name', 'fiscalYear:id,name'])->where('status', 'approved');
        $this->branchAccessService->scopeQuery($budgets, $actor, 'management_budgets.branch_id');

        return Inertia::render('Management/Schedules/Index', [
            'schedules' => $schedules,
            'branches' => $this->branchAccessService->accessibleBranches($actor, true)->map(static fn ($branch): array => ['id' => (int) $branch->getKey(), 'code' => $branch->code, 'name' => $branch->name])->values()->all(),
            'budgets' => $budgets->get()->map(static fn (ManagementBudget $budget): array => ['id' => (int) $budget->getKey(), 'name' => $budget->name, 'branch_name' => $budget->branch?->name, 'fiscal_year_name' => $budget->fiscalYear?->name])->values()->all(),
            'reportTypes' => [
                ['value' => 'management_branch_profitability', 'label' => 'Branch Profitability'],
                ['value' => 'management_budget_vs_actual', 'label' => 'Budget vs Actual'],
                ['value' => 'management_product_profitability', 'label' => 'Product Profitability'],
                ['value' => 'management_customer_profitability', 'label' => 'Customer Profitability'],
                ['value' => 'management_supplier_spend', 'label' => 'Supplier Spend'],
                ['value' => 'management_gross_margin', 'label' => 'Gross Margin'],
            ],
        ]);
    }

    public function store(StoreManagementReportScheduleRequest $request): JsonResponse|RedirectResponse
    {
        $schedule = $this->service->create($request->validated(), $this->actor($request));
        return $this->responseService->success('Management report schedule created.', ['id' => (int) $schedule->getKey()], redirectTo: route('management.schedules.index'));
    }

    public function toggle(Request $request, ManagementReportSchedule $managementReportSchedule): JsonResponse|RedirectResponse
    {
        abort_unless($this->actor($request)->can('management_report_schedules.update'), 403);
        $schedule = $this->service->toggle($managementReportSchedule, $this->actor($request));
        return $this->responseService->success('Management report schedule status updated.', ['id' => (int) $schedule->getKey(), 'status' => $schedule->status], redirectTo: route('management.schedules.index'));
    }

    public function destroy(Request $request, ManagementReportSchedule $managementReportSchedule): JsonResponse|RedirectResponse
    {
        abort_unless($this->actor($request)->can('management_report_schedules.delete'), 403);
        $this->service->delete($managementReportSchedule, $this->actor($request));
        return $this->responseService->success('Management report schedule deleted.', redirectTo: route('management.schedules.index'));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);
        return $actor;
    }

    /** @return array<string, mixed> */
    private function map(ManagementReportSchedule $schedule): array
    {
        return [
            'id' => (int) $schedule->getKey(), 'name' => $schedule->name, 'report_type' => $schedule->report_type,
            'format' => $schedule->format, 'frequency' => $schedule->frequency, 'run_day' => $schedule->run_day,
            'run_time' => substr((string) $schedule->run_time, 0, 5), 'status' => $schedule->status,
            'filters' => $schedule->filters, 'next_run_at' => $schedule->next_run_at?->toIso8601String(),
            'last_run_at' => $schedule->last_run_at?->toIso8601String(), 'last_status' => $schedule->last_status, 'last_error' => $schedule->last_error,
            'branch' => $schedule->branch === null ? null : ['id' => (int) $schedule->branch->getKey(), 'code' => $schedule->branch->code, 'name' => $schedule->branch->name],
            'created_by' => $schedule->createdBy === null ? null : ['id' => (int) $schedule->createdBy->getKey(), 'name' => $schedule->createdBy->name],
        ];
    }
}