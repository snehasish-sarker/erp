<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\CancelInventoryStockCountRequest;
use App\Http\Requests\Inventory\IndexInventoryStockCountRequest;
use App\Http\Requests\Inventory\StoreInventoryStockCountRequest;
use App\Models\InventoryBalance;
use App\Models\InventoryStockCount;
use App\Models\InventoryStockCountLine;
use App\Models\ProductWarehouseSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryStockCountService;
use App\Services\Organisation\BranchAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryStockCountController extends Controller
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
        private readonly InventoryStockCountService $stockCountService,
    ) {
    }

    public function index(
        IndexInventoryStockCountRequest $request,
    ): Response {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $search = (string) ($validated['search'] ?? '');
        $branchId = isset($validated['branch_id'])
            ? (int) $validated['branch_id']
            : null;
        $warehouseId = isset($validated['warehouse_id'])
            ? (int) $validated['warehouse_id']
            : null;
        $status = (string) ($validated['status'] ?? '');
        $sort = (string) ($validated['sort'] ?? 'count_date');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $perPage = (int) ($validated['per_page'] ?? 25);

        if ($branchId !== null) {
            $branch = $this->branchAccessService->findAccessibleBranch(
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

        $query = $this->branchAccessService->scopeQuery(
            query: InventoryStockCount::query(),
            user: $user,
        );

        $query
            ->when(
                $search !== '',
                static function (Builder $countQuery) use ($search): void {
                    $countQuery->where(
                        static function (Builder $searchQuery) use ($search): void {
                            $searchQuery
                                ->where('count_number', 'like', "%{$search}%")
                                ->orWhereHas(
                                    'warehouse',
                                    static fn (Builder $warehouseQuery): Builder => $warehouseQuery
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('code', 'like', "%{$search}%"),
                                );
                        },
                    );
                },
            )
            ->when(
                $branchId !== null,
                static fn (Builder $countQuery): Builder => $countQuery
                    ->where('branch_id', $branchId),
            )
            ->when(
                $warehouseId !== null,
                static fn (Builder $countQuery): Builder => $countQuery
                    ->where('warehouse_id', $warehouseId),
            )
            ->when(
                $status !== '',
                static fn (Builder $countQuery): Builder => $countQuery
                    ->where('status', $status),
            );

        $counts = $query
            ->with([
                'branch:id,name,code',
                'warehouse:id,branch_id,name,code',
                'createdBy:id,name,email',
            ])
            ->orderBy("inventory_stock_counts.{$sort}", $direction)
            ->orderByDesc('inventory_stock_counts.id')
            ->paginate($perPage)
            ->withQueryString();

        $canViewCost = $user->can('inventory.view_cost');

        return Inertia::render(
            'Inventory/Counts/Index',
            [
                'counts' => [
                    'data' => $counts
                        ->getCollection()
                        ->map(
                            static fn (InventoryStockCount $count): array => [
                                'id' => (int) $count->getKey(),
                                'count_number' => $count->count_number,
                                'count_date' => $count->count_date?->format('Y-m-d'),
                                'status' => $count->status,
                                'total_lines' => (int) $count->total_lines,
                                'variance_line_count' => (int) $count->variance_line_count,
                                'total_positive_variance' => (string) $count->total_positive_variance,
                                'total_negative_variance' => (string) $count->total_negative_variance,
                                'total_value_gain' => $canViewCost
                                    ? (string) $count->total_value_gain
                                    : null,
                                'total_value_loss' => $canViewCost
                                    ? (string) $count->total_value_loss
                                    : null,
                                'branch' => [
                                    'id' => (int) $count->branch_id,
                                    'name' => $count->branch?->name ?? '',
                                    'code' => $count->branch?->code ?? '',
                                ],
                                'warehouse' => [
                                    'id' => (int) $count->warehouse_id,
                                    'name' => $count->warehouse?->name ?? '',
                                    'code' => $count->warehouse?->code ?? '',
                                ],
                                'created_by' => [
                                    'id' => (int) $count->created_by_user_id,
                                    'name' => $count->createdBy?->name ?? '',
                                ],
                                'created_at' => $count->created_at?->toISOString(),
                            ],
                        )
                        ->values()
                        ->all(),
                    'meta' => [
                        'current_page' => $counts->currentPage(),
                        'last_page' => $counts->lastPage(),
                        'per_page' => $counts->perPage(),
                        'from' => $counts->firstItem(),
                        'to' => $counts->lastItem(),
                        'total' => $counts->total(),
                    ],
                ],
                'filters' => [
                    'search' => $search,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],
                'branchOptions' => $this->branchOptions($user),
                'warehouseOptions' => $this->warehouseOptions($user),
                'canViewCost' => $canViewCost,
                'currencyCode' => $this->currencyCode($user),
            ],
        );
    }

    public function create(Request $request): Response
    {
        $user = $this->authenticatedUser($request);

        return Inertia::render(
            'Inventory/Counts/Create',
            [
                'warehouseOptions' => $this->warehouseOptions($user),
                'stockOptions' => $this->stockOptions($user),
                'defaultCountDate' => CarbonImmutable::now(
                    $user->tenant?->timezone ?? config('app.timezone', 'UTC'),
                )->toDateString(),
            ],
        );
    }

    public function store(
        StoreInventoryStockCountRequest $request,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $stockCount = $this->stockCountService->create(
            data: $request->validated(),
            actor: $user,
        );

        return redirect()
            ->route('inventory.counts.show', $stockCount)
            ->with('success', 'Stock count draft created.');
    }

    public function show(
        Request $request,
        InventoryStockCount $inventoryStockCount,
    ): Response {
        $user = $this->authenticatedUser($request);

        $this->stockCountService->authorizeStockCount(
            stockCount: $inventoryStockCount,
            actor: $user,
        );

        $inventoryStockCount->load([
            'branch:id,name,code',
            'warehouse:id,branch_id,name,code',
            'createdBy:id,name,email',
            'postedBy:id,name,email',
            'cancelledBy:id,name,email',
            'lines',
        ]);

        return Inertia::render(
            'Inventory/Counts/Show',
            [
                'stockCount' => $this->presentStockCount(
                    count: $inventoryStockCount,
                    canViewCost: $user->can('inventory.view_cost'),
                ),
                'canViewCost' => $user->can('inventory.view_cost'),
                'currencyCode' => $this->currencyCode($user),
            ],
        );
    }

    public function post(
        Request $request,
        InventoryStockCount $inventoryStockCount,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->stockCountService->post(
            stockCount: $inventoryStockCount,
            actor: $user,
        );

        return redirect()
            ->route('inventory.counts.show', $inventoryStockCount)
            ->with('success', 'Stock count posted.');
    }

    public function cancel(
        CancelInventoryStockCountRequest $request,
        InventoryStockCount $inventoryStockCount,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->stockCountService->cancel(
            stockCount: $inventoryStockCount,
            actor: $user,
            reason: (string) $request->validated('reason'),
        );

        return redirect()
            ->route('inventory.counts.show', $inventoryStockCount)
            ->with('success', 'Stock count cancelled.');
    }

    /** @return list<array{id: int, name: string, code: string, status: string}> */
    private function branchOptions(User $user): array
    {
        return $this->branchAccessService
            ->accessibleBranches(user: $user, activeOnly: false)
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
    }

    /** @return list<array{id: int, branch_id: int, name: string, code: string, branch_name: string}> */
    private function warehouseOptions(User $user): array
    {
        return $this->branchAccessService
            ->scopeQuery(query: Warehouse::query(), user: $user)
            ->with('branch:id,name,code')
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'code'])
            ->map(
                static fn (Warehouse $warehouse): array => [
                    'id' => (int) $warehouse->getKey(),
                    'branch_id' => (int) $warehouse->branch_id,
                    'name' => $warehouse->name,
                    'code' => $warehouse->code,
                    'branch_name' => $warehouse->branch?->name ?? '',
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return list<array{
     *     warehouse_id: int,
     *     product_id: int,
     *     product_name: string,
     *     product_sku: string,
     *     unit_id: int,
     *     unit_name: string,
     *     unit_code: string,
     *     quantity_on_hand: string,
     *     quantity_reserved: string
     * }>
     */
    private function stockOptions(User $user): array
    {
        $settings = $this->branchAccessService
            ->scopeQuery(
                query: ProductWarehouseSetting::query(),
                user: $user,
            )
            ->with([
                'product:id,name,sku,product_type,status,base_unit_id',
                'product.baseUnit:id,name,code,symbol',
                'warehouse:id,branch_id,name,code,status',
            ])
            ->where('status', 'active')
            ->whereHas(
                'product',
                static fn (Builder $query): Builder => $query
                    ->where('product_type', 'stock')
                    ->where('status', 'active'),
            )
            ->whereHas(
                'warehouse',
                static fn (Builder $query): Builder => $query
                    ->where('status', 'active'),
            )
            ->orderBy('warehouse_id')
            ->orderBy('product_id')
            ->get();

        $balances = $this->branchAccessService
            ->scopeQuery(query: InventoryBalance::query(), user: $user)
            ->whereIn(
                'warehouse_id',
                $settings->pluck('warehouse_id')->unique()->all(),
            )
            ->whereIn(
                'product_id',
                $settings->pluck('product_id')->unique()->all(),
            )
            ->get()
            ->keyBy(
                static fn (InventoryBalance $balance): string => sprintf(
                    '%d:%d',
                    (int) $balance->warehouse_id,
                    (int) $balance->product_id,
                ),
            );

        return $settings
            ->map(
                static function (
                    ProductWarehouseSetting $setting,
                ) use ($balances): ?array {
                    $product = $setting->product;
                    $warehouse = $setting->warehouse;
                    $unit = $product?->baseUnit;

                    if ($product === null || $warehouse === null || $unit === null) {
                        return null;
                    }

                    $key = sprintf(
                        '%d:%d',
                        (int) $warehouse->getKey(),
                        (int) $product->getKey(),
                    );

                    /** @var InventoryBalance|null $balance */
                    $balance = $balances->get($key);

                    return [
                        'warehouse_id' => (int) $warehouse->getKey(),
                        'product_id' => (int) $product->getKey(),
                        'product_name' => $product->name,
                        'product_sku' => $product->sku,
                        'unit_id' => (int) $unit->getKey(),
                        'unit_name' => $unit->name,
                        'unit_code' => $unit->code,
                        'quantity_on_hand' => $balance instanceof InventoryBalance
                            ? (string) $balance->quantity_on_hand
                            : '0.000000',
                        'quantity_reserved' => $balance instanceof InventoryBalance
                            ? (string) $balance->quantity_reserved
                            : '0.000000',
                    ];
                },
            )
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function presentStockCount(
        InventoryStockCount $count,
        bool $canViewCost,
    ): array {
        return [
            'id' => (int) $count->getKey(),
            'count_number' => $count->count_number,
            'count_date' => $count->count_date?->format('Y-m-d'),
            'status' => $count->status,
            'notes' => $count->notes,
            'total_lines' => (int) $count->total_lines,
            'variance_line_count' => (int) $count->variance_line_count,
            'total_positive_variance' => (string) $count->total_positive_variance,
            'total_negative_variance' => (string) $count->total_negative_variance,
            'total_value_gain' => $canViewCost
                ? (string) $count->total_value_gain
                : null,
            'total_value_loss' => $canViewCost
                ? (string) $count->total_value_loss
                : null,
            'branch' => [
                'id' => (int) $count->branch_id,
                'name' => $count->branch?->name ?? '',
                'code' => $count->branch?->code ?? '',
            ],
            'warehouse' => [
                'id' => (int) $count->warehouse_id,
                'name' => $count->warehouse?->name ?? '',
                'code' => $count->warehouse?->code ?? '',
            ],
            'created_by' => [
                'id' => (int) $count->created_by_user_id,
                'name' => $count->createdBy?->name ?? '',
            ],
            'posted_by' => $count->posted_by_user_id === null
                ? null
                : [
                    'id' => (int) $count->posted_by_user_id,
                    'name' => $count->postedBy?->name ?? '',
                ],
            'posted_at' => $count->posted_at?->toISOString(),
            'cancelled_by' => $count->cancelled_by_user_id === null
                ? null
                : [
                    'id' => (int) $count->cancelled_by_user_id,
                    'name' => $count->cancelledBy?->name ?? '',
                ],
            'cancelled_at' => $count->cancelled_at?->toISOString(),
            'cancellation_reason' => $count->cancellation_reason,
            'lines' => $count->lines
                ->map(
                    static fn (InventoryStockCountLine $line): array => [
                        'id' => (int) $line->getKey(),
                        'line_number' => (int) $line->line_number,
                        'product_id' => (int) $line->product_id,
                        'product_name' => $line->product_name,
                        'product_sku' => $line->product_sku,
                        'unit_id' => (int) $line->unit_id,
                        'unit_name' => $line->unit_name,
                        'unit_code' => $line->unit_code,
                        'system_quantity' => (string) $line->system_quantity,
                        'reserved_quantity' => (string) $line->reserved_quantity,
                        'counted_quantity' => (string) $line->counted_quantity,
                        'variance_quantity' => (string) $line->variance_quantity,
                        'unit_cost' => $canViewCost
                            ? (string) $line->unit_cost
                            : null,
                        'variance_value' => $canViewCost
                            ? (string) $line->variance_value
                            : null,
                    ],
                )
                ->values()
                ->all(),
        ];
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (!$user instanceof User) {
            throw new AuthorizationException(
                'An authenticated user is required.',
            );
        }

        return $user;
    }

    private function currencyCode(User $user): string
    {
        return (string) (
            $user->tenant?->currency_code
            ?? config('app.currency', 'USD')
        );
    }
}
