<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\ProductionAcceptanceRun;
use App\Models\User;
use App\Services\Operations\ProductionAcceptanceService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ProductionAcceptanceController extends Controller
{
    public function __construct(
        private readonly ProductionAcceptanceService $service,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ProductionAcceptanceRun::class);
        $perPage = max(10, min(100, (int) $request->integer('per_page', 25)));
        $paginator = ProductionAcceptanceRun::query()
            ->with('startedBy:id,name')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Operations/Acceptance/Index', [
            'runs' => [
                'data' => $paginator->getCollection()->map(
                    static fn (ProductionAcceptanceRun $run): array => [
                        'id' => (int) $run->getKey(),
                        'uuid' => $run->uuid,
                        'status' => $run->status,
                        'environment' => $run->environment,
                        'source' => $run->source,
                        'total_checks' => (int) $run->total_checks,
                        'passed_checks' => (int) $run->passed_checks,
                        'warning_checks' => (int) $run->warning_checks,
                        'failed_checks' => (int) $run->failed_checks,
                        'blocking_failures' => (int) $run->blocking_failures,
                        'started_at' => $run->started_at?->toIso8601String(),
                        'completed_at' => $run->completed_at?->toIso8601String(),
                        'started_by' => $run->startedBy === null ? null : [
                            'id' => (int) $run->startedBy->getKey(),
                            'name' => $run->startedBy->name,
                        ],
                    ],
                )->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
            'can_run' => Gate::allows('run', ProductionAcceptanceRun::class),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('run', ProductionAcceptanceRun::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $report = $this->service->run($actor, 'web');
        $message = ($report['summary']['ready'] ?? false) === true
            ? 'Production acceptance passed.'
            : 'Production acceptance completed with blocking findings.';

        return $this->responseService->success(
            $message,
            data: $report,
            redirectTo: (int) $report['id'] > 0
                ? route('production-acceptance.show', ['productionAcceptanceRun' => $report['id']])
                : route('production-acceptance.index'),
        );
    }

    public function show(ProductionAcceptanceRun $productionAcceptanceRun): Response
    {
        Gate::authorize('view', $productionAcceptanceRun);

        return Inertia::render('Operations/Acceptance/Show', [
            'report' => $this->service->present($productionAcceptanceRun),
        ]);
    }
}
