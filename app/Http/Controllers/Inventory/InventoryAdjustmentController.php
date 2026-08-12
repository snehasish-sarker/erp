<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\CancelInventoryAdjustmentRequest;
use App\Http\Requests\Inventory\IndexInventoryAdjustmentRequest;
use App\Http\Requests\Inventory\StoreInventoryAdjustmentRequest;
use App\Models\InventoryAdjustment;
use App\Models\InventoryAdjustmentLine;
use App\Models\InventoryBalance;
use App\Models\ProductWarehouseSetting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryAdjustmentService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryAdjustmentController extends Controller
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
        private readonly InventoryAdjustmentService $adjustmentService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function index(
        IndexInventoryAdjustmentRequest $request,
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
        $sort = (string) ($validated['sort'] ?? 'adjustment_date');
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
            query: InventoryAdjustment::query(),
            user: $user,
        );

        $query
            ->when(
                $search !== '',
                static function (
                    Builder $adjustmentQuery,
                ) use ($search): void {
                    $adjustmentQuery->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'adjustment_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'reason',
                                    'like',
                                    "%{$search}%",
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
                    Builder $adjustmentQuery,
                ): Builder => $adjustmentQuery->where(
                    'branch_id',
                    $branchId,
                ),
            )
            ->when(
                $warehouseId !== null,
                static fn (
                    Builder $adjustmentQuery,
                ): Builder => $adjustmentQuery->where(
                    'warehouse_id',
                    $warehouseId,
                ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $adjustmentQuery,
                ): Builder => $adjustmentQuery->where(
                    'status',
                    $status,
                ),
            );

        $adjustments = $query
            ->with([
                'branch:id,name,code',
                'warehouse:id,branch_id,name,code',
                'createdBy:id,name,email',
            ])
            ->withCount('lines')
            ->orderBy("inventory_adjustments.{$sort}", $direction)
            ->orderByDesc('inventory_adjustments.id')
            ->paginate($perPage)
            ->withQueryString();

        $canViewCost = $user->can('inventory.view_cost');

        return Inertia::render(
            'Inventory/Adjustments/Index',
            [
                'adjustments' => [
                    'data' => $adjustments
                        ->getCollection()
                        ->map(
                            static fn (
                                InventoryAdjustment $adjustment,
                            ): array => [
                                'id' => (int) $adjustment->getKey(),
                                'adjustment_number' => $adjustment->adjustment_number,
                                'adjustment_date' => $adjustment->adjustment_date?->format('Y-m-d'),
                                'status' => $adjustment->status,
                                'reason' => $adjustment->reason,
                                'line_count' => (int) $adjustment->lines_count,
                                'total_quantity_in' => (string) $adjustment->total_quantity_in,
                                'total_quantity_out' => (string) $adjustment->total_quantity_out,
                                'total_value_in' => $canViewCost
                                    ? (string) $adjustment->total_value_in
                                    : null,
                                'total_value_out' => $canViewCost
                                    ? (string) $adjustment->total_value_out
                                    : null,
                                'branch' => [
                                    'id' => (int) $adjustment->branch_id,
                                    'name' => $adjustment->branch?->name ?? '',
                                    'code' => $adjustment->branch?->code ?? '',
                                ],
                                'warehouse' => [
                                    'id' => (int) $adjustment->warehouse_id,
                                    'name' => $adjustment->warehouse?->name ?? '',
                                    'code' => $adjustment->warehouse?->code ?? '',
                                ],
                                'created_by' => [
                                    'id' => (int) $adjustment->created_by_user_id,
                                    'name' => $adjustment->createdBy?->name ?? '',
                                ],
                                'created_at' => $adjustment->created_at?->toISOString(),
                            ],
                        )
                        ->values()
                        ->all(),
                    'meta' => [
                        'current_page' => $adjustments->currentPage(),
                        'last_page' => $adjustments->lastPage(),
                        'per_page' => $adjustments->perPage(),
                        'from' => $adjustments->firstItem(),
                        'to' => $adjustments->lastItem(),
                        'total' => $adjustments->total(),
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
            'Inventory/Adjustments/Create',
            [
                'warehouseOptions' => $this->warehouseOptions($user),
                'stockOptions' => $this->stockOptions($user),
                'today' => CarbonImmutable::now(
                    $this->tenantContext->tenant()->timezone,
                )->format('Y-m-d'),
            ],
        );
    }

    public function store(
        StoreInventoryAdjustmentRequest $request,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $adjustment = $this->adjustmentService->create(
            data: $request->validated(),
            actor: $user,
        );

        return redirect()
            ->route('inventory.adjustments.show', $adjustment)
            ->with('success', 'Inventory Adjustment draft created.');
    }

    public function show(
        Request $request,
        InventoryAdjustment $inventoryAdjustment,
    ): Response {
        $user = $this->authenticatedUser($request);

        $this->adjustmentService->authorizeAdjustment(
            adjustment: $inventoryAdjustment,
            actor: $user,
        );

        $inventoryAdjustment->load([
            'branch:id,name,code',
            'warehouse:id,branch_id,name,code',
            'createdBy:id,name,email',
            'postedBy:id,name,email',
            'cancelledBy:id,name,email',
            'lines',
        ]);

        $canViewCost = $user->can('inventory.view_cost');

        return Inertia::render(
            'Inventory/Adjustments/Show',
            [
                'adjustment' => $this->presentAdjustment(
                    adjustment: $inventoryAdjustment,
                    canViewCost: $canViewCost,
                ),
                'canViewCost' => $canViewCost,
                'currencyCode' => $this->currencyCode($user),
            ],
        );
    }

    public function post(
        Request $request,
        InventoryAdjustment $inventoryAdjustment,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->adjustmentService->post(
            adjustment: $inventoryAdjustment,
            actor: $user,
        );

        return redirect()
            ->route('inventory.adjustments.show', $inventoryAdjustment)
            ->with('success', 'Inventory Adjustment posted successfully.');
    }

    public function cancel(
        CancelInventoryAdjustmentRequest $request,
        InventoryAdjustment $inventoryAdjustment,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->adjustmentService->cancel(
            adjustment: $inventoryAdjustment,
            actor: $user,
            reason: (string) $request->validated('reason'),
        );

        return redirect()
            ->route('inventory.adjustments.show', $inventoryAdjustment)
            ->with('success', 'Inventory Adjustment cancelled.');
    }

    /** @return list<array{id: int, name: string, code: string, status: string}> */
    private function branchOptions(User $user): array
    {
        return $this->branchAccessService
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
    }

    /** @return list<array{id: int, branch_id: int, name: string, code: string, branch_name: string}> */
    private function warehouseOptions(User $user): array
    {
        return $this->branchAccessService
            ->scopeQuery(
                query: Warehouse::query(),
                user: $user,
            )
            ->with('branch:id,name,code')
            ->where('status', 'active')
            ->orderBy('name')
            ->get([
                'id',
                'branch_id',
                'name',
                'code',
            ])
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
     *     quantity_reserved: string,
     *     quantity_available: string
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
                static fn (
                    Builder $query,
                ): Builder => $query
                    ->where('product_type', 'stock')
                    ->where('status', 'active'),
            )
            ->whereHas(
                'warehouse',
                static fn (
                    Builder $query,
                ): Builder => $query->where('status', 'active'),
            )
            ->orderBy('warehouse_id')
            ->orderBy('product_id')
            ->get();

        $balances = $this->branchAccessService
            ->scopeQuery(
                query: InventoryBalance::query(),
                user: $user,
            )
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

                    if (
                        $product === null
                        || $warehouse === null
                        || $unit === null
                    ) {
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
                        'quantity_available' => $balance instanceof InventoryBalance
                            ? $balance->availableQuantity()
                            : '0.000000',
                    ];
                },
            )
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function presentAdjustment(
        InventoryAdjustment $adjustment,
        bool $canViewCost,
    ): array {
        return [
            'id' => (int) $adjustment->getKey(),
            'adjustment_number' => $adjustment->adjustment_number,
            'adjustment_date' => $adjustment->adjustment_date?->format('Y-m-d'),
            'status' => $adjustment->status,
            'reason' => $adjustment->reason,
            'notes' => $adjustment->notes,
            'total_quantity_in' => (string) $adjustment->total_quantity_in,
            'total_quantity_out' => (string) $adjustment->total_quantity_out,
            'total_value_in' => $canViewCost
                ? (string) $adjustment->total_value_in
                : null,
            'total_value_out' => $canViewCost
                ? (string) $adjustment->total_value_out
                : null,
            'branch' => [
                'id' => (int) $adjustment->branch_id,
                'name' => $adjustment->branch?->name ?? '',
                'code' => $adjustment->branch?->code ?? '',
            ],
            'warehouse' => [
                'id' => (int) $adjustment->warehouse_id,
                'name' => $adjustment->warehouse?->name ?? '',
                'code' => $adjustment->warehouse?->code ?? '',
            ],
            'created_by' => [
                'id' => (int) $adjustment->created_by_user_id,
                'name' => $adjustment->createdBy?->name ?? '',
            ],
            'posted_by' => $adjustment->posted_by_user_id === null
                ? null
                : [
                    'id' => (int) $adjustment->posted_by_user_id,
                    'name' => $adjustment->postedBy?->name ?? '',
                ],
            'posted_at' => $adjustment->posted_at?->toISOString(),
            'cancelled_by' => $adjustment->cancelled_by_user_id === null
                ? null
                : [
                    'id' => (int) $adjustment->cancelled_by_user_id,
                    'name' => $adjustment->cancelledBy?->name ?? '',
                ],
            'cancelled_at' => $adjustment->cancelled_at?->toISOString(),
            'cancellation_reason' => $adjustment->cancellation_reason,
            'lines' => $adjustment->lines
                ->map(
                    static fn (
                        InventoryAdjustmentLine $line,
                    ): array => [
                        'id' => (int) $line->getKey(),
                        'line_number' => (int) $line->line_number,
                        'product_id' => (int) $line->product_id,
                        'product_name' => $line->product_name,
                        'product_sku' => $line->product_sku,
                        'unit_id' => (int) $line->unit_id,
                        'unit_name' => $line->unit_name,
                        'unit_code' => $line->unit_code,
                        'adjustment_type' => $line->adjustment_type,
                        'quantity' => (string) $line->quantity,
                        'unit_cost' => $canViewCost
                            ? (string) $line->unit_cost
                            : null,
                        'adjustment_value' => $canViewCost
                            ? (string) $line->adjustment_value
                            : null,
                        'quantity_before' => (string) $line->quantity_before,
                        'quantity_after' => (string) $line->quantity_after,
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
