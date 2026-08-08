<?php

declare(strict_types=1);

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Http\Requests\Management\ManagementReportRequest;
use App\Models\User;
use App\Services\Management\ManagementReportingService;
use App\Services\Organisation\BranchAccessService;
use Inertia\Inertia;
use Inertia\Response;

final class ManagementDashboardController extends Controller
{
    public function __construct(
        private readonly ManagementReportingService $service,
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function index(ManagementReportRequest $request): Response
    {
        $actor = $this->actor($request);

        return Inertia::render('Management/Index', [
            'dashboard' => $this->service->dashboard($request->validated(), $actor),
            'branches' => $this->branches($actor),
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
}