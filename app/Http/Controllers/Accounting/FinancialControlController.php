<?php

declare(strict_types=1);

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\FinancialStatementRequest;
use App\Models\User;
use App\Services\Accounting\FinancialControlDashboardService;
use App\Services\Accounting\FinancialReconciliationService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

final class FinancialControlController extends Controller
{
    public function __construct(
        private readonly FinancialControlDashboardService $dashboardService,
        private readonly FinancialReconciliationService $reconciliationService,
        private readonly BranchAccessService $branchAccessService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function index(FinancialStatementRequest $request): Response
    {
        $actor = $this->actor($request);
        $branchId = $request->validated('branch_id');

        return Inertia::render('FinancialControl/Index', [
            'dashboard' => $this->dashboardService->build(
                actor: $actor,
                branchId: $branchId === null ? null : (int) $branchId,
            ),
            'branches' => $this->branches($actor),
            'filters' => ['branch_id' => $branchId],
        ]);
    }

    public function reconciliations(FinancialStatementRequest $request): Response
    {
        $actor = $this->actor($request);
        $validated = $request->validated();
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;
        $asOf = (string) ($validated['as_of_date']
            ?? CarbonImmutable::now(
                $this->tenantContext->tenant()->timezone,
            )->toDateString());

        return Inertia::render('FinancialReconciliations/Index', [
            'report' => $this->reconciliationService->build($asOf, $actor, $branchId),
            'branches' => $this->branches($actor),
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
}
