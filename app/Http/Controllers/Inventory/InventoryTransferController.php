<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\CancelInventoryTransferRequest;
use App\Http\Requests\Inventory\IndexInventoryTransferRequest;
use App\Http\Requests\Inventory\StoreInventoryTransferRequest;
use App\Models\InventoryBalance;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\InventoryTransferService;
use App\Services\Organisation\BranchAccessService;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class InventoryTransferController extends Controller
{
    public function __construct(
        private readonly BranchAccessService $branchAccessService,
        private readonly InventoryTransferService $transferService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function index(
        IndexInventoryTransferRequest $request,
    ): Response {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $search = (string) ($validated['search'] ?? '');
        $branchId = isset($validated['branch_id'])
            ? (int) $validated['branch_id']
            : null;
        $status = (string) ($validated['status'] ?? '');
        $sort = (string) ($validated['sort'] ?? 'transfer_date');
        $direction = (string) ($validated['direction'] ?? 'desc');
        $perPage = (int) ($validated['per_page'] ?? 25);

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

        $query = $this->branchAccessService->scopeQuery(
            query: InventoryTransfer::query(),
            user: $user,
            branchColumn: 'source_branch_id',
        );

        $query
            ->when(
                $search !== '',
                static function (
                    Builder $transferQuery,
                ) use ($search): void {
                    $transferQuery->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'transfer_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhereHas(
                                    'sourceWarehouse',
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
                                )
                                ->orWhereHas(
                                    'destinationWarehouse',
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
                    Builder $transferQuery,
                ): Builder => $transferQuery->where(
                    static fn (
                        Builder $branchQuery,
                    ): Builder => $branchQuery
                        ->where('source_branch_id', $branchId)
                        ->orWhere('destination_branch_id', $branchId),
                ),
            )
            ->when(
                $status !== '',
                static fn (
                    Builder $transferQuery,
                ): Builder => $transferQuery->where(
                    'status',
                    $status,
                ),
            );

        $transfers = $query
            ->with([
                'sourceWarehouse:id,branch_id,name,code',
                'destinationWarehouse:id,branch_id,name,code',
                'sourceBranch:id,name,code',
                'destinationBranch:id,name,code',
                'createdBy:id,name,email',
            ])
            ->withCount('lines')
            ->orderBy("inventory_transfers.{$sort}", $direction)
            ->orderByDesc('inventory_transfers.id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render(
            'Inventory/Transfers/Index',
            [
                'transfers' => [
                    'data' => $transfers
                        ->getCollection()
                        ->map(
                            static fn (
                                InventoryTransfer $transfer,
                            ): array => [
                                'id' => (int) $transfer->getKey(),
                                'transfer_number' => $transfer->transfer_number,
                                'transfer_date' => $transfer->transfer_date?->format('Y-m-d'),
                                'status' => $transfer->status,
                                'line_count' => (int) $transfer->lines_count,
                                'source_branch' => [
                                    'id' => (int) $transfer->source_branch_id,
                                    'name' => $transfer->sourceBranch?->name ?? '',
                                    'code' => $transfer->sourceBranch?->code ?? '',
                                ],
                                'destination_branch' => [
                                    'id' => (int) $transfer->destination_branch_id,
                                    'name' => $transfer->destinationBranch?->name ?? '',
                                    'code' => $transfer->destinationBranch?->code ?? '',
                                ],
                                'source_warehouse' => [
                                    'id' => (int) $transfer->source_warehouse_id,
                                    'name' => $transfer->sourceWarehouse?->name ?? '',
                                    'code' => $transfer->sourceWarehouse?->code ?? '',
                                ],
                                'destination_warehouse' => [
                                    'id' => (int) $transfer->destination_warehouse_id,
                                    'name' => $transfer->destinationWarehouse?->name ?? '',
                                    'code' => $transfer->destinationWarehouse?->code ?? '',
                                ],
                                'created_by' => [
                                    'id' => (int) $transfer->created_by_user_id,
                                    'name' => $transfer->createdBy?->name ?? '',
                                ],
                                'created_at' => $transfer->created_at?->toISOString(),
                            ],
                        )
                        ->values()
                        ->all(),
                    'meta' => [
                        'current_page' => $transfers->currentPage(),
                        'last_page' => $transfers->lastPage(),
                        'per_page' => $transfers->perPage(),
                        'from' => $transfers->firstItem(),
                        'to' => $transfers->lastItem(),
                        'total' => $transfers->total(),
                    ],
                ],
                'filters' => [
                    'search' => $search,
                    'branch_id' => $branchId,
                    'status' => $status,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],
                'branchOptions' => $this->branchOptions($user),
            ],
        );
    }

    public function create(Request $request): Response
    {
        $user = $this->authenticatedUser($request);

        return Inertia::render(
            'Inventory/Transfers/Create',
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
        StoreInventoryTransferRequest $request,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $transfer = $this->transferService->create(
            data: $request->validated(),
            actor: $user,
        );

        return redirect()
            ->route('inventory.transfers.show', $transfer)
            ->with('success', 'Inventory Transfer draft created.');
    }

    public function show(
        Request $request,
        InventoryTransfer $inventoryTransfer,
    ): Response {
        $user = $this->authenticatedUser($request);
        $this->transferService->authorizeTransfer(
            transfer: $inventoryTransfer,
            actor: $user,
        );

        $inventoryTransfer->load([
            'sourceBranch:id,name,code',
            'destinationBranch:id,name,code',
            'sourceWarehouse:id,branch_id,name,code',
            'destinationWarehouse:id,branch_id,name,code',
            'createdBy:id,name,email',
            'postedBy:id,name,email',
            'cancelledBy:id,name,email',
            'lines',
        ]);

        $canViewCost = $user->can('inventory.view_cost');

        return Inertia::render(
            'Inventory/Transfers/Show',
            [
                'transfer' => $this->presentTransfer(
                    transfer: $inventoryTransfer,
                    canViewCost: $canViewCost,
                ),
                'canViewCost' => $canViewCost,
                'currencyCode' => (string) (
                    $user->tenant?->currency_code
                    ?? config('app.currency', 'USD')
                ),
            ],
        );
    }

    public function post(
        Request $request,
        InventoryTransfer $inventoryTransfer,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->transferService->post(
            transfer: $inventoryTransfer,
            actor: $user,
        );

        return redirect()
            ->route('inventory.transfers.show', $inventoryTransfer)
            ->with('success', 'Inventory Transfer posted successfully.');
    }

    public function cancel(
        CancelInventoryTransferRequest $request,
        InventoryTransfer $inventoryTransfer,
    ): RedirectResponse {
        $user = $this->authenticatedUser($request);

        $this->transferService->cancel(
            transfer: $inventoryTransfer,
            actor: $user,
            reason: (string) $request->validated('reason'),
        );

        return redirect()
            ->route('inventory.transfers.show', $inventoryTransfer)
            ->with('success', 'Inventory Transfer cancelled.');
    }

    /**
     * @return list<array{id: int, name: string, code: string, status: string}>
     */
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

    /**
     * @return list<array{id: int, branch_id: int, name: string, code: string, branch_name: string}>
     */
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
     *     quantity_available: string
     * }>
     */
    private function stockOptions(User $user): array
    {
        return $this->branchAccessService
            ->scopeQuery(
                query: InventoryBalance::query(),
                user: $user,
            )
            ->with([
                'product:id,name,sku,product_type,status',
                'unit:id,name,code,symbol',
                'warehouse:id,branch_id,name,code,status',
            ])
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
                ): Builder => $query->where(
                    'status',
                    'active',
                ),
            )
            ->whereColumn(
                'inventory_balances.quantity_on_hand',
                '>',
                'inventory_balances.quantity_reserved',
            )
            ->orderBy('warehouse_id')
            ->orderBy('product_id')
            ->get()
            ->map(
                static fn (
                    InventoryBalance $balance,
                ): array => [
                    'warehouse_id' => (int) $balance->warehouse_id,
                    'product_id' => (int) $balance->product_id,
                    'product_name' => $balance->product?->name ?? '',
                    'product_sku' => $balance->product?->sku ?? '',
                    'unit_id' => (int) $balance->unit_id,
                    'unit_name' => $balance->unit?->name ?? '',
                    'unit_code' => $balance->unit?->code ?? '',
                    'quantity_available' => $balance->availableQuantity(),
                ],
            )
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentTransfer(
        InventoryTransfer $transfer,
        bool $canViewCost,
    ): array {
        return [
            'id' => (int) $transfer->getKey(),
            'transfer_number' => $transfer->transfer_number,
            'transfer_date' => $transfer->transfer_date?->format('Y-m-d'),
            'status' => $transfer->status,
            'notes' => $transfer->notes,
            'source_branch' => [
                'id' => (int) $transfer->source_branch_id,
                'name' => $transfer->sourceBranch?->name ?? '',
                'code' => $transfer->sourceBranch?->code ?? '',
            ],
            'destination_branch' => [
                'id' => (int) $transfer->destination_branch_id,
                'name' => $transfer->destinationBranch?->name ?? '',
                'code' => $transfer->destinationBranch?->code ?? '',
            ],
            'source_warehouse' => [
                'id' => (int) $transfer->source_warehouse_id,
                'name' => $transfer->sourceWarehouse?->name ?? '',
                'code' => $transfer->sourceWarehouse?->code ?? '',
            ],
            'destination_warehouse' => [
                'id' => (int) $transfer->destination_warehouse_id,
                'name' => $transfer->destinationWarehouse?->name ?? '',
                'code' => $transfer->destinationWarehouse?->code ?? '',
            ],
            'created_by' => [
                'id' => (int) $transfer->created_by_user_id,
                'name' => $transfer->createdBy?->name ?? '',
            ],
            'posted_by' => $transfer->posted_by_user_id === null
                ? null
                : [
                    'id' => (int) $transfer->posted_by_user_id,
                    'name' => $transfer->postedBy?->name ?? '',
                ],
            'posted_at' => $transfer->posted_at?->toISOString(),
            'cancelled_by' => $transfer->cancelled_by_user_id === null
                ? null
                : [
                    'id' => (int) $transfer->cancelled_by_user_id,
                    'name' => $transfer->cancelledBy?->name ?? '',
                ],
            'cancelled_at' => $transfer->cancelled_at?->toISOString(),
            'cancellation_reason' => $transfer->cancellation_reason,
            'lines' => $transfer->lines
                ->map(
                    static fn (
                        InventoryTransferLine $line,
                    ): array => [
                        'id' => (int) $line->getKey(),
                        'line_number' => (int) $line->line_number,
                        'product_id' => (int) $line->product_id,
                        'product_name' => $line->product_name,
                        'product_sku' => $line->product_sku,
                        'unit_id' => (int) $line->unit_id,
                        'unit_name' => $line->unit_name,
                        'unit_code' => $line->unit_code,
                        'quantity' => (string) $line->quantity,
                        'unit_cost' => $canViewCost
                            ? (string) $line->unit_cost
                            : null,
                        'transfer_value' => $canViewCost
                            ? (string) $line->transfer_value
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
}
