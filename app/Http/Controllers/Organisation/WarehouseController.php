<?php

declare(strict_types=1);

namespace App\Http\Controllers\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Warehouses\IndexWarehouseRequest;
use App\Http\Requests\Warehouses\StoreWarehouseRequest;
use App\Http\Requests\Warehouses\UpdateWarehouseRequest;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Services\Organisation\WarehouseService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class WarehouseController extends Controller
{
    public function __construct(
        private readonly WarehouseService $warehouseService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(
        IndexWarehouseRequest $request,
    ): Response {
        Gate::authorize('viewAny', Warehouse::class);

        $validated = $request->validated();

        $search = (string) ($validated['search'] ?? '');

        $branchId = isset($validated['branch_id'])
            ? (int) $validated['branch_id']
            : null;

        $type = (string) ($validated['type'] ?? '');
        $status = (string) ($validated['status'] ?? '');
        $sort = (string) ($validated['sort'] ?? 'name');
        $direction = (string) ($validated['direction'] ?? 'asc');
        $perPage = (int) ($validated['per_page'] ?? 25);

        $warehouses = Warehouse::query()
            ->with([
                'branch:id,name,code,status',
            ])
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
                                    'address',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhereHas(
                                    'branch',
                                    static function (
                                        Builder $branchQuery,
                                    ) use ($search): void {
                                        $branchQuery
                                            ->where(
                                                'name',
                                                'like',
                                                "%{$search}%",
                                            )
                                            ->orWhere(
                                                'code',
                                                'like',
                                                "%{$search}%",
                                            );
                                    },
                                );
                        },
                    );
                },
            )
            ->when(
                $branchId !== null,
                static fn (Builder $query): Builder =>
                    $query->where('branch_id', $branchId),
            )
            ->when(
                $type !== '',
                static fn (Builder $query): Builder =>
                    $query->where('type', $type),
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
            'Warehouses/Index',
            [
                'warehouses' => [
                    'data' => $warehouses
                        ->getCollection()
                        ->map(
                            fn (Warehouse $warehouse): array =>
                                $this->warehouseData($warehouse),
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' => $warehouses->currentPage(),
                        'last_page' => $warehouses->lastPage(),
                        'per_page' => $warehouses->perPage(),
                        'from' => $warehouses->firstItem(),
                        'to' => $warehouses->lastItem(),
                        'total' => $warehouses->total(),
                    ],
                ],

                'filters' => [
                    'search' => $search,
                    'branch_id' => $branchId,
                    'type' => $type,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'branchOptions' => $this->branchOptions(),
                'typeOptions' => $this->typeOptions(),
                'statusOptions' => $this->statusOptions(),
            ],
        );
    }

    public function create(): Response
    {
        Gate::authorize('create', Warehouse::class);

        return Inertia::render(
            'Warehouses/Create',
            [
                'branchOptions' => $this->branchOptions(),
                'typeOptions' => $this->typeOptions(),
                'statusOptions' => $this->statusOptions(),
            ],
        );
    }

    public function store(
        StoreWarehouseRequest $request,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('create', Warehouse::class);

        $warehouse = $this->warehouseService->create(
            $request->validated(),
        );

        $warehouse->load(
            'branch:id,name,code,status',
        );

        return $this->responseService->success(
            message: 'Warehouse created successfully.',
            data: $this->warehouseData($warehouse),
            redirectTo: route('warehouses.index'),
        );
    }

    public function edit(Warehouse $warehouse): Response
    {
        Gate::authorize('update', $warehouse);

        $warehouse->load(
            'branch:id,name,code,status',
        );

        return Inertia::render(
            'Warehouses/Edit',
            [
                'warehouse' => $this->warehouseData($warehouse),

                'branchOptions' => $this->branchOptions(
                    currentBranchId: (int) $warehouse->branch_id,
                ),

                'typeOptions' => $this->typeOptions(),
                'statusOptions' => $this->statusOptions(),
            ],
        );
    }

    public function update(
        UpdateWarehouseRequest $request,
        Warehouse $warehouse,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('update', $warehouse);

        $warehouse = $this->warehouseService->update(
            warehouse: $warehouse,
            attributes: $request->validated(),
        );

        $warehouse->load(
            'branch:id,name,code,status',
        );

        return $this->responseService->success(
            message: 'Warehouse updated successfully.',
            data: $this->warehouseData($warehouse),
            redirectTo: route('warehouses.index'),
        );
    }

    public function destroy(
        Warehouse $warehouse,
    ): JsonResponse|RedirectResponse {
        Gate::authorize('delete', $warehouse);

        $this->warehouseService->delete($warehouse);

        return $this->responseService->success(
            message: 'Warehouse deleted successfully.',
            redirectTo: route('warehouses.index'),
        );
    }

    /**
     * @return array{
     *     id: int,
     *     branch_id: int,
     *     branch: array{
     *         id: int,
     *         name: string,
     *         code: string,
     *         status: string
     *     },
     *     name: string,
     *     code: string,
     *     type: string,
     *     status: string,
     *     is_default: bool,
     *     address: string|null,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function warehouseData(
        Warehouse $warehouse,
    ): array {
        $branch = $warehouse->branch;

        return [
            'id' => (int) $warehouse->getKey(),
            'branch_id' => (int) $warehouse->branch_id,

            'branch' => [
                'id' => (int) $branch->getKey(),
                'name' => $branch->name,
                'code' => $branch->code,
                'status' => $branch->status,
            ],

            'name' => $warehouse->name,
            'code' => $warehouse->code,
            'type' => $warehouse->type,
            'status' => $warehouse->status,
            'is_default' => (bool) $warehouse->is_default,
            'address' => $warehouse->address,

            'created_at' =>
                $warehouse->created_at?->toIso8601String(),

            'updated_at' =>
                $warehouse->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     name: string,
     *     code: string,
     *     status: string
     * }>
     */
    private function branchOptions(
        ?int $currentBranchId = null,
    ): array {
        return Branch::query()
            ->where(
                static function (
                    Builder $query,
                ) use ($currentBranchId): void {
                    $query->where(
                        'status',
                        '!=',
                        'archived',
                    );

                    if ($currentBranchId !== null) {
                        $query->orWhere(
                            'id',
                            $currentBranchId,
                        );
                    }
                },
            )
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'code',
                'status',
            ])
            ->map(
                static fn (Branch $branch): array => [
                    'id' => (int) $branch->getKey(),
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'status' => $branch->status,
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string
     * }>
     */
    private function typeOptions(): array
    {
        return [
            [
                'value' => 'general',
                'label' => 'General',
            ],
            [
                'value' => 'transit',
                'label' => 'Transit',
            ],
            [
                'value' => 'returns',
                'label' => 'Returns',
            ],
            [
                'value' => 'damaged',
                'label' => 'Damaged Goods',
            ],
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