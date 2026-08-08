<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreReleaseCandidateRequest;
use App\Models\ReleaseCandidate;
use App\Models\User;
use App\Services\Operations\ReleaseCandidateService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ReleaseCandidateController extends Controller
{
    public function __construct(
        private readonly ReleaseCandidateService $service,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ReleaseCandidate::class);
        $perPage = max(10, min(100, (int) $request->integer('per_page', 25)));
        $paginator = ReleaseCandidate::query()
            ->with(['frozenBy:id,name', 'acceptanceRun:id,uuid,status,blocking_failures,completed_at'])
            ->latest('frozen_at')
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Operations/ReleaseCandidates/Index', [
            'candidates' => [
                'data' => $paginator->getCollection()->map(static fn (ReleaseCandidate $candidate): array => [
                    'id' => (int) $candidate->getKey(),
                    'version' => $candidate->version,
                    'status' => $candidate->status,
                    'environment' => $candidate->environment,
                    'source' => $candidate->source,
                    'project_fingerprint' => $candidate->project_fingerprint,
                    'git_commit' => $candidate->git_commit,
                    'verification_status' => $candidate->verification_status,
                    'frozen_at' => $candidate->frozen_at?->toIso8601String(),
                    'verified_at' => $candidate->verified_at?->toIso8601String(),
                    'frozen_by' => $candidate->frozenBy === null ? null : [
                        'id' => (int) $candidate->frozenBy->getKey(),
                        'name' => $candidate->frozenBy->name,
                    ],
                    'acceptance' => $candidate->acceptanceRun === null ? null : [
                        'id' => (int) $candidate->acceptanceRun->getKey(),
                        'uuid' => $candidate->acceptanceRun->uuid,
                        'status' => $candidate->acceptanceRun->status,
                        'blocking_failures' => (int) $candidate->acceptanceRun->blocking_failures,
                        'completed_at' => $candidate->acceptanceRun->completed_at?->toIso8601String(),
                    ],
                ])->values()->all(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
            'can_create' => Gate::allows('create', ReleaseCandidate::class),
        ]);
    }

    public function store(StoreReleaseCandidateRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', ReleaseCandidate::class);
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $candidate = $this->service->freeze(
            actor: $actor,
            version: (string) $request->validated('version'),
            notes: $request->validated('notes'),
            source: 'web',
        );

        return $this->responseService->success(
            'Release candidate frozen successfully.',
            data: $candidate,
            redirectTo: route('release-candidates.show', ['releaseCandidate' => $candidate['id']]),
        );
    }

    public function show(ReleaseCandidate $releaseCandidate): Response
    {
        Gate::authorize('view', $releaseCandidate);

        return Inertia::render('Operations/ReleaseCandidates/Show', [
            'candidate' => $this->service->present($releaseCandidate),
            'can_verify' => Gate::allows('verify', $releaseCandidate),
        ]);
    }

    public function verify(ReleaseCandidate $releaseCandidate): JsonResponse|RedirectResponse
    {
        Gate::authorize('verify', $releaseCandidate);
        $candidate = $this->service->verify($releaseCandidate);
        $matched = $candidate['verification_status'] === 'matched';

        return $this->responseService->success(
            $matched
                ? 'Release candidate fingerprint still matches the frozen build.'
                : 'Release candidate drift detected. Rerun production acceptance before freezing another candidate.',
            data: $candidate,
            redirectTo: route('release-candidates.show', ['releaseCandidate' => $candidate['id']]),
        );
    }
}
