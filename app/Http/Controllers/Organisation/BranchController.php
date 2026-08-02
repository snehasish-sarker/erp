<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Branches\IndexBranchRequest;
use App\Http\Requests\Branches\StoreBranchRequest;
use App\Http\Requests\Branches\UpdateBranchRequest;
use App\Models\Branch;
use App\Services\Organisation\BranchService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class BranchController extends Controller
{
    public function __construct(
        private readonly BranchService $branchService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexBranchRequest $request,
    ): Response {
        Gate::authorize('viewAny', Branch::class);

        $validated = $request->validated();

        $search = (string) ($validated['search'] ?? '');
        $status = (string) ($validated['status'] ?? '');
        $sort = (string) ($validated['sort'] ?? 'name');
        $direction = (string) ($validated['direction'] ?? 'asc');
        $perPage = (int) ($validated['per_page'] ?? 25);

        $branches = Branch::query()
            ->when(
                $search !== '',
                static function (
                    Builder $query,
                ) use ($search): void {
                    $query->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'name',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'code',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'email',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'phone',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            )
            ->when(
                $status !== '',
                static fn (Builder $query): Builder =>
                    $query->where('status', $status),
            )
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Branches/Index',
            [
                'branches' => [
                    'data' => $branches
                        ->getCollection()
                        ->map(
                            fn (Branch $branch): array =>
                                $this->branchData($branch),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' => $branches->currentPage(),
                        'last_page' => $branches->lastPage(),
                        'per_page' => $branches->perPage(),
                        'from' => $branches->firstItem(),
                        'to' => $branches->lastItem(),
                        'total' => $branches->total(),
                        'previous_page_url' => $branches->previousPageUrl(),
                        'next_page_url' => $branches->nextPageUrl(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'statusOptions' => $this->statusOptions(),
            ],
        );
    }

    public function create(): Response
    {
        Gate::authorize('create', Branch::class);

        return Inertia::render(
            'Branches/Create',
            [
                'statusOptions' => $this->statusOptions(),
            ],
        );
    }

    public function store(
        StoreBranchRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('create', Branch::class);

        $branch = $this->branchService->create(
            $request->validated(),
        );

        return $this->responseService->success(
            message: 'Branch created successfully.',
            data: $this->branchData($branch),
            redirectTo: route('branches.index'),
        );
    }

    public function edit(Branch $branch): Response
    {
        Gate::authorize('update', $branch);

        return Inertia::render(
            'Branches/Edit',
            [
                'branch' => $this->branchData($branch),
                'statusOptions' => $this->statusOptions(),
            ],
        );
    }

    public function update(
        UpdateBranchRequest $request,
        Branch $branch,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $branch);

        $branch = $this->branchService->update(
            branch: $branch,
            attributes: $request->validated(),
        );

        return $this->responseService->success(
            message: 'Branch updated successfully.',
            data: $this->branchData($branch),
            redirectTo: route('branches.index'),
        );
    }

    public function destroy(
        Branch $branch,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('delete', $branch);

        $this->branchService->delete($branch);

        return $this->responseService->success(
            message: 'Branch deleted successfully.',
            redirectTo: route('branches.index'),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     status: string,
     *     email: string|null,
     *     phone: string|null,
     *     address: string|null,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function branchData(Branch $branch): array
    {
        return [
            'id' => (int) $branch->getKey(),
            'name' => $branch->name,
            'code' => $branch->code,
            'status' => $branch->status,
            'email' => $branch->email,
            'phone' => $branch->phone,
            'address' => $branch->address,
            'created_at' => $branch->created_at?->toIso8601String(),
            'updated_at' => $branch->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string
     * }>
     */
    private function statusOptions(): array
    {
        return [
            [
                'value' => 'active',
                'label' => 'Active',
            ],
            [
                'value' => 'inactive',
                'label' => 'Inactive',
            ],
            [
                'value' => 'archived',
                'label' => 'Archived',
            ],
        ];
    }
}