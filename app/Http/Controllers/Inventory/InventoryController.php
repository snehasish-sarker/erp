<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\IndexInventoryRequest;
use App\Models\InventoryBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organisation\BranchAccessService;
use Brick\Math\BigDecimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryController extends Controller
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function index(
        IndexInventoryRequest $request,
    ): Response {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new AuthorizationException(
                'An authenticated user is required.',
            );
        }

        $validated = $request->validated();

        $search = (string) (
            $validated['search'] ?? ''
        );

        $branchId = isset($validated['branch_id'])
            ? (int) $validated['branch_id']
            : null;

        $warehouseId = isset($validated['warehouse_id'])
            ? (int) $validated['warehouse_id']
            : null;

        $stockState = (string) (
            $validated['stock_state'] ?? ''
        );

        $sort = (string) (
            $validated['sort'] ?? 'updated_at'
        );

        $direction = (string) (
            $validated['direction'] ?? 'desc'
        );

        $perPage = (int) (
            $validated['per_page'] ?? 25
        );

        $canViewCost = $user->can(
            'inventory.view_cost',
        );

        if ($branchId !== null) {
            $branch = $this->branchAccessService
                ->findAccessibleBranch(
                    user: $user,
                    branchId: $branchId,
                    requireActive: false,
                );

            if ($branch === null) {
                throw new AuthorizationException(
                    'You are not authorized to access the selected branch.',
                );
            }
        }

        if ($warehouseId !== null) {
            $warehouse = $this->branchAccessService
                ->scopeQuery(
                    query: Warehouse::query(),
                    user: $user,
                )
                ->whereKey($warehouseId)
                ->first();

            if (!$warehouse instanceof Warehouse) {
                throw new AuthorizationException(
                    'You are not authorized to access the selected warehouse.',
                );
            }
        }

        $query = $this->inventoryQuery(
            user: $user,
            search: $search,
            branchId: $branchId,
            warehouseId: $warehouseId,
            stockState: $stockState,
        );

        $totalLocations = (clone $query)->count();

        $totalOnHand = (string) (
            (clone $query)->sum('quantity_on_hand')
        );

        $totalReserved = (string) (
            (clone $query)->sum('quantity_reserved')
        );

        $totalAvailable = BigDecimal::of(
            $totalOnHand,
        )
            ->minus(
                BigDecimal::of($totalReserved),
            )
            ->__toString();

        $totalInventoryValue = $canViewCost
            ? (string) (
                (clone $query)->sum('inventory_value')
            )
            : null;

        $balances = $query
            ->with([
                'branch:id,name,code',
                'warehouse:id,branch_id,name,code',
                'product:id,name,sku,product_type,status',
                'unit:id,name,code,symbol',
            ])
            ->orderBy(
                "inventory_balances.{$sort}",
                $direction,
            )
            ->orderByDesc('inventory_balances.id')
            ->paginate($perPage)
            ->withQueryString();

        $branchOptions = $this->branchAccessService
            ->accessibleBranches(
                user: $user,
                activeOnly: false,
            )
            ->map(
                static fn ($branch): array => [
                    'id' => (int) $branch->getKey(),
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'status' => $branch->status,
                ],
            )
            ->values()
            ->all();

        $warehouseOptions = $this->branchAccessService
            ->scopeQuery(
                query: Warehouse::query(),
                user: $user,
            )
            ->with('branch:id,name,code')
            ->orderBy('name')
            ->orderBy('id')
            ->get([
                'id',
                'branch_id',
                'name',
                'code',
                'status',
            ])
            ->map(
                static fn (Warehouse $warehouse): array => [
                    'id' => (int) $warehouse->getKey(),
                    'branch_id' => (int) $warehouse->branch_id,
                    'name' => $warehouse->name,
                    'code' => $warehouse->code,
                    'status' => $warehouse->status,
                    'branch_name' => $warehouse->branch?->name,
                ],
            )
            ->values()
            ->all();

        return Inertia::render(
            'Inventory/Index',
            [
                'inventory' => [
                    'data' => $balances
                        ->getCollection()
                        ->map(
                            static function (
                                InventoryBalance $balance,
                            ) use ($canViewCost): array {
                                return [
                                    'id' => (int) $balance->getKey(),
                                    'branch' => [
                                        'id' => (int) $balance->branch_id,
                                        'name' => $balance->branch?->name ?? '',
                                        'code' => $balance->branch?->code ?? '',
                                    ],
                                    'warehouse' => [
                                        'id' => (int) $balance->warehouse_id,
                                        'name' => $balance->warehouse?->name ?? '',
                                        'code' => $balance->warehouse?->code ?? '',
                                    ],
                                    'product' => [
                                        'id' => (int) $balance->product_id,
                                        'name' => $balance->product?->name ?? '',
                                        'sku' => $balance->product?->sku ?? '',
                                        'product_type' => $balance->product?->product_type ?? '',
                                        'status' => $balance->product?->status ?? '',
                                    ],
                                    'unit' => [
                                        'id' => (int) $balance->unit_id,
                                        'name' => $balance->unit?->name ?? '',
                                        'code' => $balance->unit?->code ?? '',
                                        'symbol' => $balance->unit?->symbol,
                                    ],
                                    'quantity_on_hand' => (string) $balance->quantity_on_hand,
                                    'quantity_reserved' => (string) $balance->quantity_reserved,
                                    'quantity_available' => $balance->availableQuantity(),
                                    'inventory_value' => $canViewCost
                                        ? (string) $balance->inventory_value
                                        : null,
                                    'average_unit_cost' => $canViewCost
                                        ? (string) $balance->average_unit_cost
                                        : null,
                                    'updated_at' => $balance->updated_at?->toISOString(),
                                ];
                            },
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' => $balances->currentPage(),
                        'last_page' => $balances->lastPage(),
                        'per_page' => $balances->perPage(),
                        'from' => $balances->firstItem(),
                        'to' => $balances->lastItem(),
                        'total' => $balances->total(),
                    ],
                ],

                'summary' => [
                    'location_count' => $totalLocations,
                    'quantity_on_hand' => $totalOnHand,
                    'quantity_reserved' => $totalReserved,
                    'quantity_available' => $totalAvailable,
                    'inventory_value' => $totalInventoryValue,
                ],

                'filters' => [
                    'search' => $search,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'stock_state' => $stockState,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'branchOptions' => $branchOptions,
                'warehouseOptions' => $warehouseOptions,
                'canViewCost' => $canViewCost,
                'currencyCode' => (string) (
                    $user->tenant?->currency_code
                    ?? config('app.currency', 'USD')
                ),
            ],
        );
    }

    /**
     * @return Builder<InventoryBalance>
     */
    private function inventoryQuery(
        User $user,
        string $search,
        ?int $branchId,
        ?int $warehouseId,
        string $stockState,
    ): Builder {
        $query = $this->branchAccessService
            ->scopeQuery(
                query: InventoryBalance::query(),
                user: $user,
                branchColumn: 'inventory_balances.branch_id',
            );

        return $query
            ->when(
                $search !== '',
                static function (
                    Builder $balanceQuery,
                ) use ($search): void {
                    $balanceQuery->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->whereHas(
                                    'product',
                                    static fn (
                                        Builder $productQuery,
                                    ): Builder => $productQuery
                                        ->where(
                                            'name',
                                            'like',
                                            "%{$search}%",
                                        )
                                        ->orWhere(
                                            'sku',
                                            'like',
                                            "%{$search}%",
                                        ),
                                )
                                ->orWhereHas(
                                    'warehouse',
                                    static fn (
                                        Builder $warehouseQuery,
                                    ): Builder => $warehouseQuery
                                        ->where(
                                            'name',
                                            'like',
                                            "%{$search}%",
                                        )
                                        ->orWhere(
                                            'code',
                                            'like',
                                            "%{$search}%",
                                        ),
                                );
                        },
                    );
                },
            )
            ->when(
                $branchId !== null,
                static fn (
                    Builder $balanceQuery,
                ): Builder => $balanceQuery->where(
                    'inventory_balances.branch_id',
                    $branchId,
                ),
            )
            ->when(
                $warehouseId !== null,
                static fn (
                    Builder $balanceQuery,
                ): Builder => $balanceQuery->where(
                    'inventory_balances.warehouse_id',
                    $warehouseId,
                ),
            )
            ->when(
                $stockState === 'available',
                static fn (
                    Builder $balanceQuery,
                ): Builder => $balanceQuery->whereColumn(
                    'inventory_balances.quantity_on_hand',
                    '>',
                    'inventory_balances.quantity_reserved',
                ),
            )
            ->when(
                $stockState === 'reserved',
                static fn (
                    Builder $balanceQuery,
                ): Builder => $balanceQuery->where(
                    'inventory_balances.quantity_reserved',
                    '>',
                    0,
                ),
            )
            ->when(
                $stockState === 'out_of_stock',
                static fn (
                    Builder $balanceQuery,
                ): Builder => $balanceQuery->where(
                    'inventory_balances.quantity_on_hand',
                    '<=',
                    0,
                ),
            );
    }
}