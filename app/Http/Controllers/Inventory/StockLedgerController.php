<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\IndexStockLedgerRequest;
use App\Models\InventoryStockCount;
use App\Models\StockLedgerEntry;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organisation\BranchAccessService;
use Brick\Math\BigDecimal;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final class StockLedgerController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const MOVEMENT_LABELS = [
        'goods_receipt' => 'Goods Receipt',
        'goods_receipt_reversal' => 'Goods Receipt Reversal',
        'purchase_return' => 'Purchase Return',
        'purchase_return_reversal' => 'Purchase Return Reversal',
        'dispatch' => 'Customer Dispatch',
        'dispatch_reversal' => 'Dispatch Reversal',
        'sales_return' => 'Sales Return',
        'sales_return_reversal' => 'Sales Return Reversal',
        'transfer_in' => 'Inventory Transfer In',
        'transfer_out' => 'Inventory Transfer Out',
        'adjustment_in' => 'Inventory Adjustment In',
        'adjustment_out' => 'Inventory Adjustment Out',
    ];

    public function __construct(
        private readonly BranchAccessService $branchAccessService,
    ) {
    }

    public function index(
        IndexStockLedgerRequest $request,
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

        $movementType = (string) (
            $validated['movement_type'] ?? ''
        );

        $dateFrom = isset($validated['date_from'])
            ? (string) $validated['date_from']
            : null;

        $dateTo = isset($validated['date_to'])
            ? (string) $validated['date_to']
            : null;

        $sort = (string) (
            $validated['sort'] ?? 'occurred_at'
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

            if (
                $branchId !== null
                && (int) $warehouse->branch_id !== $branchId
            ) {
                throw new AuthorizationException(
                    'The selected warehouse does not belong to the selected branch.',
                );
            }
        }

        $query = $this->ledgerQuery(
            user: $user,
            search: $search,
            branchId: $branchId,
            warehouseId: $warehouseId,
            movementType: $movementType,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
        );

        $entryCount = (clone $query)->count();

        $quantityIn = (string) (
            (clone $query)->sum('quantity_in')
        );

        $quantityOut = (string) (
            (clone $query)->sum('quantity_out')
        );

        $netMovement = BigDecimal::of($quantityIn)
            ->minus(
                BigDecimal::of($quantityOut),
            )
            ->__toString();

        $movementValue = $canViewCost
            ? (string) (
                (clone $query)->sum('total_cost')
            )
            : null;

        $entries = $query
            ->with([
                'branch:id,name,code',
                'warehouse:id,branch_id,name,code',
                'product:id,name,sku,product_type,status',
                'unit:id,name,code,symbol',
                'createdBy:id,name,email',
            ])
            ->orderBy(
                "stock_ledger_entries.{$sort}",
                $direction,
            )
            ->orderByDesc('stock_ledger_entries.id')
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
            'Inventory/Ledger',
            [
                'ledger' => [
                    'data' => $entries
                        ->getCollection()
                        ->map(
                            static function (
                                StockLedgerEntry $entry,
                            ) use ($canViewCost): array {
                                return [
                                    'id' => (int) $entry->getKey(),
                                    'movement_type' => $entry->movement_type,
                                    'movement_label' => self::movementLabel($entry),
                                    'posting_key' => $entry->posting_key,
                                    'document_number' => $entry->document_number,
                                    'source_type' => class_basename(
                                        $entry->source_type,
                                    ),
                                    'source_id' => (int) $entry->source_id,
                                    'source_line_id' => $entry->source_line_id === null
                                        ? null
                                        : (int) $entry->source_line_id,
                                    'occurred_at' => $entry->occurred_at?->toISOString(),
                                    'quantity_in' => (string) $entry->quantity_in,
                                    'quantity_out' => (string) $entry->quantity_out,
                                    'balance_quantity' => (string) $entry->balance_quantity,
                                    'unit_cost' => $canViewCost
                                        ? (string) $entry->unit_cost
                                        : null,
                                    'total_cost' => $canViewCost
                                        ? (string) $entry->total_cost
                                        : null,
                                    'balance_value' => $canViewCost
                                        ? (string) $entry->balance_value
                                        : null,
                                    'reversal_of_id' => $entry->reversal_of_id === null
                                        ? null
                                        : (int) $entry->reversal_of_id,
                                    'branch' => [
                                        'id' => (int) $entry->branch_id,
                                        'name' => $entry->branch?->name ?? '',
                                        'code' => $entry->branch?->code ?? '',
                                    ],
                                    'warehouse' => [
                                        'id' => (int) $entry->warehouse_id,
                                        'name' => $entry->warehouse?->name ?? '',
                                        'code' => $entry->warehouse?->code ?? '',
                                    ],
                                    'product' => [
                                        'id' => (int) $entry->product_id,
                                        'name' => $entry->product?->name ?? '',
                                        'sku' => $entry->product?->sku ?? '',
                                    ],
                                    'unit' => [
                                        'id' => (int) $entry->unit_id,
                                        'name' => $entry->unit?->name ?? '',
                                        'code' => $entry->unit?->code ?? '',
                                        'symbol' => $entry->unit?->symbol,
                                    ],
                                    'created_by' => [
                                        'id' => (int) $entry->created_by_user_id,
                                        'name' => $entry->createdBy?->name ?? '',
                                        'email' => $entry->createdBy?->email ?? '',
                                    ],
                                ];
                            },
                        )
                        ->values()
                        ->all(),

                    'meta' => [
                        'current_page' => $entries->currentPage(),
                        'last_page' => $entries->lastPage(),
                        'per_page' => $entries->perPage(),
                        'from' => $entries->firstItem(),
                        'to' => $entries->lastItem(),
                        'total' => $entries->total(),
                    ],
                ],

                'summary' => [
                    'entry_count' => $entryCount,
                    'quantity_in' => $quantityIn,
                    'quantity_out' => $quantityOut,
                    'net_movement' => $netMovement,
                    'movement_value' => $movementValue,
                ],

                'filters' => [
                    'search' => $search,
                    'branch_id' => $branchId,
                    'warehouse_id' => $warehouseId,
                    'movement_type' => $movementType,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'sort' => $sort,
                    'direction' => $direction,
                    'per_page' => $perPage,
                ],

                'movementOptions' => collect(
                    self::MOVEMENT_LABELS,
                )
                    ->map(
                        static fn (
                            string $label,
                            string $value,
                        ): array => [
                            'value' => $value,
                            'label' => $label,
                        ],
                    )
                    ->values()
                    ->all(),

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
     * @return Builder<StockLedgerEntry>
     */
    private function ledgerQuery(
        User $user,
        string $search,
        ?int $branchId,
        ?int $warehouseId,
        string $movementType,
        ?string $dateFrom,
        ?string $dateTo,
    ): Builder {
        $query = $this->branchAccessService
            ->scopeQuery(
                query: StockLedgerEntry::query(),
                user: $user,
                branchColumn: 'stock_ledger_entries.branch_id',
            );

        return $query
            ->when(
                $search !== '',
                static function (
                    Builder $ledgerQuery,
                ) use ($search): void {
                    $ledgerQuery->where(
                        static function (
                            Builder $searchQuery,
                        ) use ($search): void {
                            $searchQuery
                                ->where(
                                    'stock_ledger_entries.document_number',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhere(
                                    'stock_ledger_entries.posting_key',
                                    'like',
                                    "%{$search}%",
                                )
                                ->orWhereHas(
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
                                );
                        },
                    );
                },
            )
            ->when(
                $branchId !== null,
                static fn (
                    Builder $ledgerQuery,
                ): Builder => $ledgerQuery->where(
                    'stock_ledger_entries.branch_id',
                    $branchId,
                ),
            )
            ->when(
                $warehouseId !== null,
                static fn (
                    Builder $ledgerQuery,
                ): Builder => $ledgerQuery->where(
                    'stock_ledger_entries.warehouse_id',
                    $warehouseId,
                ),
            )
            ->when(
                $movementType !== '',
                static fn (
                    Builder $ledgerQuery,
                ): Builder => $ledgerQuery->where(
                    'stock_ledger_entries.movement_type',
                    $movementType,
                ),
            )
            ->when(
                $dateFrom !== null,
                static fn (
                    Builder $ledgerQuery,
                ): Builder => $ledgerQuery->where(
                    'stock_ledger_entries.occurred_at',
                    '>=',
                    "{$dateFrom} 00:00:00",
                ),
            )
            ->when(
                $dateTo !== null,
                static fn (
                    Builder $ledgerQuery,
                ): Builder => $ledgerQuery->where(
                    'stock_ledger_entries.occurred_at',
                    '<=',
                    "{$dateTo} 23:59:59",
                ),
            );
    }
    private static function movementLabel(
        StockLedgerEntry $entry,
    ): string {
        if ($entry->source_type === InventoryStockCount::class) {
            return $entry->movement_type === 'adjustment_in'
                ? 'Stock Count Gain'
                : 'Stock Count Loss';
        }

        return self::MOVEMENT_LABELS[$entry->movement_type]
            ?? str($entry->movement_type)
                ->replace('_', ' ')
                ->title()
                ->toString();
    }

}