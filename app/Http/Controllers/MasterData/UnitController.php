<?php

declare(strict_types=1);

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\IndexUnitRequest;
use App\Http\Requests\MasterData\StoreUnitRequest;
use App\Http\Requests\MasterData\UpdateUnitRequest;
use App\Models\Unit;
use App\Models\User;
use App\Services\MasterData\UnitService;
use App\Support\MasterData\UnitCategoryRegistry;
use App\Support\Responses\CommonResponseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class UnitController extends Controller
{
    public function __construct(
        private readonly UnitService $unitService,
        private readonly UnitCategoryRegistry $categoryRegistry,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexUnitRequest $request,
    ): Response {
        Gate::authorize(
            'viewAny',
            Unit::class,
        );

        $validated = $request->validated();

        $search = (string) (
            $validated['search'] ?? ''
        );

        $category = (string) (
            $validated['category'] ?? ''
        );

        $status = (string) (
            $validated['status'] ?? ''
        );

        $sort = (string) (
            $validated['sort'] ?? 'name'
        );

        $direction = (string) (
            $validated['direction'] ?? 'asc'
        );

        $perPage = (int) (
            $validated['per_page'] ?? 25
        );

        $units = Unit::query()
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
                                    'symbol',
                                    'like',
                                    "%{$search}%",
                                );
                        },
                    );
                },
            )
            ->when(
                $category !== '',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'category',
                    $category,
                ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $query,
                ): Builder => $query->where(
                    'status',
                    $status,
                ),
            )
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Units/Index',
            [
                'units' => [
                    'data' => $units
                        ->getCollection()
                        ->map(
                            fn (Unit $unit): array =>
                                $this->unitData($unit),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' =>
                            $units->currentPage(),

                        'last_page' =>
                            $units->lastPage(),

                        'per_page' =>
                            $units->perPage(),

                        'from' =>
                            $units->firstItem(),

                        'to' =>
                            $units->lastItem(),

                        'total' =>
                            $units->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'category' => $category,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'categoryOptions' =>
                    $this->categoryRegistry
                        ->options(),

                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function create(): Response
    {
        Gate::authorize(
            'create',
            Unit::class,
        );

        return Inertia::render(
            'Units/Create',
            [
                'categoryOptions' =>
                    $this->categoryRegistry
                        ->options(),

                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function store(
        StoreUnitRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'create',
            Unit::class,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $unit = $this->unitService->create(
            data: $request->validated(),
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Unit created successfully.',

            data: [
                'id' => (int) $unit->getKey(),
            ],

            redirectTo: route('units.index'),
        );
    }

    public function edit(Unit $unit): Response
    {
        Gate::authorize(
            'update',
            $unit,
        );

        return Inertia::render(
            'Units/Edit',
            [
                'unit' => $this->unitData($unit),

                'categoryOptions' =>
                    $this->categoryRegistry
                        ->options(),

                'statusOptions' =>
                    $this->statusOptions(),
            ],
        );
    }

    public function update(
        UpdateUnitRequest $request,
        Unit $unit,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'update',
            $unit,
        );

        $actor = $request->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $unit = $this->unitService->update(
            unit: $unit,
            data: $request->validated(),
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Unit updated successfully.',

            data: [
                'id' => (int) $unit->getKey(),
            ],

            redirectTo: route('units.index'),
        );
    }

    public function destroy(
        Unit $unit,
    ): JsonResponse|RedirectResponse {
        Gate::authorize(
            'delete',
            $unit,
        );

        $actor = request()->user();

        abort_unless(
            $actor instanceof User,
            401,
        );

        $this->unitService->delete(
            unit: $unit,
            actor: $actor,
        );

        return $this->responseService->success(
            message: 'Unit deleted successfully.',
            redirectTo: route('units.index'),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     symbol: string|null,
     *     category: string,
     *     category_label: string,
     *     allow_decimal: bool,
     *     decimal_places: int,
     *     status: string,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function unitData(Unit $unit): array
    {
        return [
            'id' => (int) $unit->getKey(),
            'name' => $unit->name,
            'code' => $unit->code,
            'symbol' => $unit->symbol,
            'category' => $unit->category,

            'category_label' =>
                $this->categoryRegistry->label(
                    $unit->category,
                ),

            'allow_decimal' =>
                $unit->allow_decimal,

            'decimal_places' =>
                (int) $unit->decimal_places,

            'status' => $unit->status,

            'created_at' =>
                $unit->created_at
                    ?->toIso8601String(),

            'updated_at' =>
                $unit->updated_at
                    ?->toIso8601String(),
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
        ];
    }
}