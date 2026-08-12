<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Branch;
use App\Models\InventoryBalance;
use App\Models\InventoryReservation;
use App\Models\SalesOrder;
use App\Models\SalesOrderAllocation;
use App\Models\SalesOrderAllocationLine;
use App\Models\SalesOrderLine;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Organisation\BranchAccessService;
use App\Support\Sales\SalesOrderStatusRegistry;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SalesOrderAllocationService
{
    private const SCALE = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly SalesOrderStatusRegistry $statusRegistry,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function allocate(
        SalesOrder $salesOrder,
        array $data,
        User $actor,
    ): SalesOrderAllocation {
        $tenantId = $this->activeTenantId();

        $this->ensureTenant(
            salesOrder: $salesOrder,
            actor: $actor,
            tenantId: $tenantId,
        );

        $normalized =
            $this->normalizeAllocationInput(
                $data,
            );

        return DB::transaction(
            function () use (
                $salesOrder,
                $normalized,
                $actor,
            ): SalesOrderAllocation {
                $lockedOrder =
                    SalesOrder::query()
                        ->whereKey(
                            $salesOrder->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeOrderBranch(
                    salesOrder: $lockedOrder,
                    actor: $actor,
                    requireActive: true,
                );

                $this->ensureAllocatable(
                    $lockedOrder,
                );

                $lines =
                    SalesOrderLine::query()
                        ->where(
                            'sales_order_id',
                            $lockedOrder->getKey(),
                        )
                        ->orderBy('product_id')
                        ->orderBy('line_number')
                        ->lockForUpdate()
                        ->get();

                if ($lines->isEmpty()) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'The Sales Order does not contain any lines to allocate.',
                        ],
                    ]);
                }

                $warehouse =
                    $this->resolveWarehouse(
                        salesOrder: $lockedOrder,
                        lines: $lines,
                    );

                $currentAllocation =
                    SalesOrderAllocation::query()
                        ->where(
                            'sales_order_id',
                            $lockedOrder->getKey(),
                        )
                        ->whereNotNull('active_key')
                        ->where(
                            'status',
                            'active',
                        )
                        ->lockForUpdate()
                        ->first();

                $desiredByLine =
                    $this->desiredQuantities(
                        lines: $lines,

                        inputByLine:
                            $normalized['lines'],
                    );

                $stockProductIds =
                    $lines
                        ->filter(
                            static fn (
                                SalesOrderLine $line,
                            ): bool =>
                                $line->isStockItem(),
                        )
                        ->pluck('product_id')
                        ->map(
                            static fn (
                                mixed $id,
                            ): int => (int) $id,
                        )
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();

                /*
                 * Every allocation transaction follows the same
                 * deterministic lock order:
                 *
                 * 1. Sales Order
                 * 2. Inventory balances by Product
                 * 3. Open reservations by Product and ID
                 *
                 * This prevents cross-order allocation deadlocks.
                 */
                $balances =
                    $this->lockBalances(
                        salesOrder: $lockedOrder,

                        productIds:
                            $stockProductIds,
                    );

                $openReservations =
                    $this->lockOpenReservations(
                        salesOrder: $lockedOrder,

                        productIds:
                            $stockProductIds,
                    );

                $currentReservations =
                    $this->currentOrderReservations(
                        reservations:
                            $openReservations,

                        salesOrder:
                            $lockedOrder,
                    );

                $this
                    ->ensureReservationsAreReallocatable(
                        $currentReservations,
                    );

                $currentByProduct =
                    $this->currentReservedByProduct(
                        $currentReservations,
                    );

                $desiredByProduct =
                    $this->desiredReservedByProduct(
                        lines: $lines,

                        desiredByLine:
                            $desiredByLine,
                    );

                $availabilityByProduct =
                    $this
                        ->validateAndCalculateAvailability(
                            lines: $lines,

                            balances:
                                $balances,

                            currentByProduct:
                                $currentByProduct,

                            desiredByProduct:
                                $desiredByProduct,
                        );

                $hasAnyAllocation = false;

                foreach (
                    $desiredByLine
                    as $quantity
                ) {
                    if (
                        $quantity->isGreaterThan(
                            BigDecimal::zero(),
                        )
                    ) {
                        $hasAnyAllocation = true;

                        break;
                    }
                }

                if (!$hasAnyAllocation) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'Allocate at least one line quantity. Use Release Allocation to remove an existing allocation.',
                        ],
                    ]);
                }

                $nextRevision =
                    (int) SalesOrderAllocation::query()
                        ->where(
                            'sales_order_id',
                            $lockedOrder->getKey(),
                        )
                        ->max('revision') + 1;

                if (
                    $currentAllocation
                    instanceof SalesOrderAllocation
                ) {
                    $this->closeAllocation(
                        allocation:
                            $currentAllocation,

                        reservations:
                            $currentReservations,

                        status:
                            'superseded',

                        reason:
                            "Superseded by allocation revision {$nextRevision}.",

                        actor:
                            $actor,
                    );
                }

                $this
                    ->applyBalanceReservationTotals(
                        balances:
                            $balances,

                        currentByProduct:
                            $currentByProduct,

                        desiredByProduct:
                            $desiredByProduct,
                    );

                $allocation =
                    SalesOrderAllocation::query()
                        ->create([
                            'sales_order_id' =>
                                $lockedOrder->getKey(),

                            'branch_id' =>
                                $lockedOrder->branch_id,

                            'warehouse_id' =>
                                $warehouse?->getKey(),

                            'active_key' =>
                                $this->allocationActiveKey(
                                    $lockedOrder,
                                ),

                            'status' => 'active',

                            'revision' =>
                                $nextRevision,

                            'notes' =>
                                $normalized['notes'],

                            'created_by_user_id' =>
                                $actor->getKey(),
                        ]);

                foreach (
                    $lines->sortBy('line_number')
                    as $line
                ) {
                    $desired =
                        $desiredByLine[
                            (int) $line->getKey()
                        ];

                    $availability =
                        $line->isStockItem()
                            ? $availabilityByProduct[
                                (int) $line->product_id
                            ]
                            : [
                                'quantity_on_hand' =>
                                    BigDecimal::zero(),

                                'quantity_reserved_other' =>
                                    BigDecimal::zero(),

                                'quantity_available' =>
                                    BigDecimal::of(
                                        (string) $line
                                            ->ordered_quantity,
                                    ),
                            ];

                    $allocationLine =
                        $allocation->lines()
                            ->create([
                                'sales_order_line_id' =>
                                    $line->getKey(),

                                'product_id' =>
                                    $line->product_id,

                                'unit_id' =>
                                    $line->unit_id,

                                'line_number' =>
                                    $line->line_number,

                                'product_name' =>
                                    $line->product_name,

                                'product_sku' =>
                                    $line->product_sku,

                                'product_type' =>
                                    $line->product_type,

                                'unit_name' =>
                                    $line->unit_name,

                                'unit_code' =>
                                    $line->unit_code,

                                'requested_quantity' =>
                                    (string) $line
                                        ->ordered_quantity,

                                'allocated_quantity' =>
                                    $this->decimal(
                                        $desired,
                                    ),

                                'quantity_on_hand_snapshot' =>
                                    $this->decimal(
                                        $availability[
                                            'quantity_on_hand'
                                        ],
                                    ),

                                'quantity_reserved_other_snapshot' =>
                                    $this->decimal(
                                        $availability[
                                            'quantity_reserved_other'
                                        ],
                                    ),

                                'quantity_available_snapshot' =>
                                    $this->decimal(
                                        $availability[
                                            'quantity_available'
                                        ],
                                    ),
                            ]);

                    if (
                        $line->isStockItem()
                        && $desired->isGreaterThan(
                            BigDecimal::zero(),
                        )
                    ) {
                        if (
                            !$warehouse
                                instanceof Warehouse
                        ) {
                            throw new LogicException(
                                'A stock reservation requires a warehouse.',
                            );
                        }

                        $reservationKey =
                            $this->reservationKey(
                                allocation:
                                    $allocation,

                                allocationLine:
                                    $allocationLine,
                            );

                        InventoryReservation::query()
                            ->create([
                                'branch_id' =>
                                    $lockedOrder
                                        ->branch_id,

                                'warehouse_id' =>
                                    $warehouse
                                        ->getKey(),

                                'product_id' =>
                                    $line->product_id,

                                'unit_id' =>
                                    $line->unit_id,

                                'sales_order_allocation_line_id' =>
                                    $allocationLine
                                        ->getKey(),

                                'reservation_key' =>
                                    $reservationKey,

                                'active_key' =>
                                    $reservationKey,

                                'source_type' =>
                                    SalesOrder::class,

                                'source_id' =>
                                    $lockedOrder
                                        ->getKey(),

                                'source_line_id' =>
                                    $line->getKey(),

                                'reserved_quantity' =>
                                    $this->decimal(
                                        $desired,
                                    ),

                                'consumed_quantity' =>
                                    '0.000000',

                                'released_quantity' =>
                                    '0.000000',

                                'status' => 'active',
                                'reserved_at' => now(),
                                'expires_at' => null,

                                'created_by_user_id' =>
                                    $actor->getKey(),
                            ]);
                    }

                    $line->allocated_quantity =
                        $this->decimal(
                            $desired,
                        );

                    $line->save();
                }

                $this->synchronizeOrderStatus(
                    salesOrder: $lockedOrder,
                    lines: $lines,
                );

                return $allocation->load([
                    'lines.reservation',
                    'createdBy:id,name',
                    'warehouse:id,name,code',
                ]);
            },
            attempts: 5,
        );
    }

    public function release(
        SalesOrder $salesOrder,
        string $reason,
        User $actor,
    ): SalesOrder {
        $tenantId = $this->activeTenantId();

        $this->ensureTenant(
            salesOrder: $salesOrder,
            actor: $actor,
            tenantId: $tenantId,
        );

        $reason = trim($reason);

        if (
            $reason === ''
            || mb_strlen($reason) > 500
        ) {
            throw ValidationException::withMessages([
                'release_reason' => [
                    'A release reason is required and may not exceed 500 characters.',
                ],
            ]);
        }

        return DB::transaction(
            function () use (
                $salesOrder,
                $reason,
                $actor,
            ): SalesOrder {
                $lockedOrder =
                    SalesOrder::query()
                        ->whereKey(
                            $salesOrder->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeOrderBranch(
                    salesOrder: $lockedOrder,
                    actor: $actor,
                    requireActive: true,
                );

                $this->ensureAllocatable(
                    $lockedOrder,
                );

                $allocation =
                    SalesOrderAllocation::query()
                        ->where(
                            'sales_order_id',
                            $lockedOrder->getKey(),
                        )
                        ->whereNotNull('active_key')
                        ->where(
                            'status',
                            'active',
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    !$allocation
                        instanceof SalesOrderAllocation
                ) {
                    throw ValidationException::withMessages([
                        'allocation' => [
                            'This Sales Order does not have an active allocation to release.',
                        ],
                    ]);
                }

                $lines =
                    SalesOrderLine::query()
                        ->where(
                            'sales_order_id',
                            $lockedOrder->getKey(),
                        )
                        ->orderBy('product_id')
                        ->orderBy('line_number')
                        ->lockForUpdate()
                        ->get();

                $productIds =
                    $lines
                        ->filter(
                            static fn (
                                SalesOrderLine $line,
                            ): bool =>
                                $line->isStockItem(),
                        )
                        ->pluck('product_id')
                        ->map(
                            static fn (
                                mixed $id,
                            ): int => (int) $id,
                        )
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();

                $balances =
                    $this->lockBalances(
                        salesOrder: $lockedOrder,
                        productIds: $productIds,
                    );

                $openReservations =
                    $this->lockOpenReservations(
                        salesOrder: $lockedOrder,
                        productIds: $productIds,
                    );

                $reservations =
                    $this->currentOrderReservations(
                        reservations:
                            $openReservations,

                        salesOrder:
                            $lockedOrder,
                    );

                $this
                    ->ensureReservationsAreReallocatable(
                        $reservations,
                    );

                $currentByProduct =
                    $this->currentReservedByProduct(
                        $reservations,
                    );

                foreach (
                    $currentByProduct
                    as $productId => $quantity
                ) {
                    $balance =
                        $balances->get($productId);

                    if (
                        !$balance
                            instanceof InventoryBalance
                    ) {
                        throw new LogicException(
                            'The inventory balance for an active reservation was not found.',
                        );
                    }

                    $currentReserved =
                        BigDecimal::of(
                            (string) $balance
                                ->quantity_reserved,
                        );

                    if (
                        $currentReserved
                            ->isLessThan($quantity)
                    ) {
                        throw new LogicException(
                            'Inventory reserved quantity is lower than its active reservation records.',
                        );
                    }

                    $balance->quantity_reserved =
                        $this->decimal(
                            $currentReserved
                                ->minus($quantity),
                        );

                    $balance->version =
                        (int) $balance->version + 1;

                    $balance->save();
                }

                $this->closeAllocation(
                    allocation: $allocation,
                    reservations: $reservations,
                    status: 'released',
                    reason: $reason,
                    actor: $actor,
                );

                foreach ($lines as $line) {
                    $line->allocated_quantity =
                        '0.000000';

                    $line->save();
                }

                $lockedOrder->status =
                    'approved';

                $lockedOrder->save();

                return $lockedOrder
                    ->refresh()
                    ->load([
                        'lines',
                        'activeAllocation.lines.reservation',
                    ]);
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     notes: string|null,
     *     lines: array<int, BigDecimal>
     * }
     */
    private function normalizeAllocationInput(
        array $data,
    ): array {
        $inputLines = $data['lines'] ?? null;

        if (
            !is_array($inputLines)
            || $inputLines === []
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    'At least one allocation line is required.',
                ],
            ]);
        }

        $normalizedLines = [];

        foreach (
            array_values($inputLines)
            as $index => $inputLine
        ) {
            if (!is_array($inputLine)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => [
                        'Each allocation line must be an object.',
                    ],
                ]);
            }

            $lineId = filter_var(
                $inputLine[
                    'sales_order_line_id'
                ] ?? null,
                FILTER_VALIDATE_INT,
                [
                    'options' => [
                        'min_range' => 1,
                    ],
                ],
            );

            if ($lineId === false) {
                throw ValidationException::withMessages([
                    "lines.{$index}.sales_order_line_id" => [
                        'The selected Sales Order line is invalid.',
                    ],
                ]);
            }

            if (
                array_key_exists(
                    $lineId,
                    $normalizedLines,
                )
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.sales_order_line_id" => [
                        'The same Sales Order line cannot be allocated twice.',
                    ],
                ]);
            }

            $normalizedLines[$lineId] =
                $this->quantity(
                    value:
                        $inputLine[
                            'allocated_quantity'
                        ] ?? null,

                    field:
                        "lines.{$index}.allocated_quantity",
                );
        }

        $notes = $data['notes'] ?? null;

        if (
            $notes !== null
            && !is_string($notes)
        ) {
            throw ValidationException::withMessages([
                'notes' => [
                    'Allocation notes must be text.',
                ],
            ]);
        }

        $notes = is_string($notes)
            ? trim($notes)
            : null;

        if ($notes === '') {
            $notes = null;
        }

        if (
            $notes !== null
            && mb_strlen($notes) > 4000
        ) {
            throw ValidationException::withMessages([
                'notes' => [
                    'Allocation notes may not exceed 4,000 characters.',
                ],
            ]);
        }

        return [
            'notes' => $notes,
            'lines' => $normalizedLines,
        ];
    }

    /**
     * @param EloquentCollection<int, SalesOrderLine> $lines
     * @param array<int, BigDecimal> $inputByLine
     *
     * @return array<int, BigDecimal>
     */
    private function desiredQuantities(
        EloquentCollection $lines,
        array $inputByLine,
    ): array {
        $expectedLineIds =
            $lines
                ->pluck('id')
                ->map(
                    static fn (
                        mixed $id,
                    ): int => (int) $id,
                )
                ->sort()
                ->values()
                ->all();

        $submittedLineIds =
            array_keys($inputByLine);

        sort($submittedLineIds);

        if (
            $expectedLineIds
            !== $submittedLineIds
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    'The allocation lines are stale or incomplete. Refresh the page and try again.',
                ],
            ]);
        }

        $desired = [];

        foreach ($lines as $line) {
            $lineId =
                (int) $line->getKey();

            $ordered = BigDecimal::of(
                (string) $line->ordered_quantity,
            );

            /*
             * Non-stock and service lines do not reserve inventory.
             * They become operationally allocated in full whenever
             * an allocation revision is saved.
             */
            $quantity = $line->isStockItem()
                ? $inputByLine[$lineId]
                : $ordered;

            if (
                $quantity->isGreaterThan(
                    $ordered,
                )
            ) {
                throw ValidationException::withMessages([
                    'lines' => [
                        "Allocated quantity for {$line->product_name} cannot exceed the ordered quantity.",
                    ],
                ]);
            }

            $desired[$lineId] = $quantity;
        }

        return $desired;
    }

    /**
     * @param EloquentCollection<int, SalesOrderLine> $lines
     */
    private function resolveWarehouse(
        SalesOrder $salesOrder,
        EloquentCollection $lines,
    ): ?Warehouse {
        $hasStockLines =
            $lines->contains(
                static fn (
                    SalesOrderLine $line,
                ): bool => $line->isStockItem(),
            );

        if (!$hasStockLines) {
            return null;
        }

        if (
            $salesOrder->warehouse_id === null
        ) {
            throw ValidationException::withMessages([
                'warehouse_id' => [
                    'A fulfillment warehouse is required to allocate stock products.',
                ],
            ]);
        }

        $warehouse = Warehouse::query()
            ->whereKey(
                $salesOrder->warehouse_id,
            )
            ->lockForUpdate()
            ->first();

        if (
            !$warehouse instanceof Warehouse
        ) {
            throw ValidationException::withMessages([
                'warehouse_id' => [
                    'The fulfillment warehouse is unavailable.',
                ],
            ]);
        }

        if (
            (int) $warehouse->branch_id
                !== (int) $salesOrder->branch_id
            || $warehouse->status !== 'active'
        ) {
            throw ValidationException::withMessages([
                'warehouse_id' => [
                    'The fulfillment warehouse is inactive or does not belong to the Sales Order branch.',
                ],
            ]);
        }

        return $warehouse;
    }

    /**
     * @param EloquentCollection<int, InventoryReservation> $reservations
     *
     * @return EloquentCollection<int, InventoryReservation>
     */
    private function currentOrderReservations(
        EloquentCollection $reservations,
        SalesOrder $salesOrder,
    ): EloquentCollection {
        return $reservations
            ->filter(
                static fn (
                    InventoryReservation $reservation,
                ): bool =>
                    $reservation->source_type
                        === SalesOrder::class
                    && (int) $reservation->source_id
                        === (int) $salesOrder->getKey(),
            )
            ->values();
    }

    /**
     * @param EloquentCollection<int, InventoryReservation> $reservations
     */
    private function ensureReservationsAreReallocatable(
        EloquentCollection $reservations,
    ): void {
        foreach (
            $reservations
            as $reservation
        ) {
            if (
                $reservation
                    ->hasConsumedQuantity()
            ) {
                throw ValidationException::withMessages([
                    'allocation' => [
                        'The allocation cannot be changed because reserved inventory has already been consumed by a dispatch.',
                    ],
                ]);
            }
        }
    }

    /**
     * @param list<int> $productIds
     *
     * @return EloquentCollection<int, InventoryBalance>
     */
    private function lockBalances(
        SalesOrder $salesOrder,
        array $productIds,
    ): EloquentCollection {
        if ($productIds === []) {
            return new EloquentCollection();
        }

        if (
            $salesOrder->warehouse_id === null
        ) {
            throw new LogicException(
                'Stock products require a warehouse before balances can be locked.',
            );
        }

        return InventoryBalance::query()
            ->where(
                'warehouse_id',
                $salesOrder->warehouse_id,
            )
            ->whereIn(
                'product_id',
                $productIds,
            )
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get()
            ->keyBy(
                static fn (
                    InventoryBalance $balance,
                ): int => (int) $balance->product_id,
            );
    }

    /**
     * @param list<int> $productIds
     *
     * @return EloquentCollection<int, InventoryReservation>
     */
    private function lockOpenReservations(
        SalesOrder $salesOrder,
        array $productIds,
    ): EloquentCollection {
        if (
            $productIds === []
            || $salesOrder->warehouse_id === null
        ) {
            return new EloquentCollection();
        }

        return InventoryReservation::query()
            ->where(
                'warehouse_id',
                $salesOrder->warehouse_id,
            )
            ->whereIn(
                'product_id',
                $productIds,
            )
            ->whereNotNull('active_key')
            ->whereIn(
                'status',
                [
                    'active',
                    'partially_consumed',
                ],
            )
            ->orderBy('product_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param EloquentCollection<int, InventoryReservation> $reservations
     *
     * @return array<int, BigDecimal>
     */
    private function currentReservedByProduct(
        EloquentCollection $reservations,
    ): array {
        $result = [];

        foreach (
            $reservations
            as $reservation
        ) {
            $productId =
                (int) $reservation->product_id;

            $quantity = BigDecimal::of(
                $reservation
                    ->outstandingQuantity(),
            );

            $result[$productId] = (
                $result[$productId]
                ?? BigDecimal::zero()
            )->plus($quantity);
        }

        return $result;
    }

    /**
     * @param EloquentCollection<int, SalesOrderLine> $lines
     * @param array<int, BigDecimal> $desiredByLine
     *
     * @return array<int, BigDecimal>
     */
    private function desiredReservedByProduct(
        EloquentCollection $lines,
        array $desiredByLine,
    ): array {
        $result = [];

        foreach ($lines as $line) {
            if (!$line->isStockItem()) {
                continue;
            }

            $productId =
                (int) $line->product_id;

            $result[$productId] = (
                $result[$productId]
                ?? BigDecimal::zero()
            )->plus(
                $desiredByLine[
                    (int) $line->getKey()
                ],
            );
        }

        return $result;
    }

    /**
     * @param EloquentCollection<int, SalesOrderLine> $lines
     * @param EloquentCollection<int, InventoryBalance> $balances
     * @param array<int, BigDecimal> $currentByProduct
     * @param array<int, BigDecimal> $desiredByProduct
     *
     * @return array<int, array{
     *     quantity_on_hand: BigDecimal,
     *     quantity_reserved_other: BigDecimal,
     *     quantity_available: BigDecimal
     * }>
     */
    private function validateAndCalculateAvailability(
        EloquentCollection $lines,
        EloquentCollection $balances,
        array $currentByProduct,
        array $desiredByProduct,
    ): array {
        $productNames = [];

        foreach ($lines as $line) {
            $productNames[
                (int) $line->product_id
            ] = $line->product_name;
        }

        $result = [];

        foreach (
            $desiredByProduct
            as $productId => $desired
        ) {
            $balance =
                $balances->get($productId);

            $onHand = BigDecimal::of(
                (string) (
                    $balance?->quantity_on_hand
                    ?? '0'
                ),
            );

            $reservedTotal = BigDecimal::of(
                (string) (
                    $balance?->quantity_reserved
                    ?? '0'
                ),
            );

            $reservedCurrent =
                $currentByProduct[$productId]
                ?? BigDecimal::zero();

            if (
                $reservedTotal->isLessThan(
                    $reservedCurrent,
                )
            ) {
                throw new LogicException(
                    'Inventory reserved quantity is lower than the active reservation records for this Sales Order.',
                );
            }

            $reservedOther =
                $reservedTotal->minus(
                    $reservedCurrent,
                );

            $available =
                $onHand->minus(
                    $reservedOther,
                );

            if (
                $available->isLessThan(
                    BigDecimal::zero(),
                )
            ) {
                throw new LogicException(
                    'Inventory availability is negative after excluding the current Sales Order reservation.',
                );
            }

            if (
                $desired->isGreaterThan(
                    $available,
                )
            ) {
                $name =
                    $productNames[$productId]
                    ?? "Product {$productId}";

                throw ValidationException::withMessages([
                    'lines' => [
                        sprintf(
                            '%s has only %s available after other reservations, but %s was requested.',
                            $name,
                            $this->decimal(
                                $available,
                            ),
                            $this->decimal(
                                $desired,
                            ),
                        ),
                    ],
                ]);
            }

            $result[$productId] = [
                'quantity_on_hand' =>
                    $onHand,

                'quantity_reserved_other' =>
                    $reservedOther,

                'quantity_available' =>
                    $available,
            ];
        }

        return $result;
    }

    /**
     * @param EloquentCollection<int, InventoryBalance> $balances
     * @param array<int, BigDecimal> $currentByProduct
     * @param array<int, BigDecimal> $desiredByProduct
     */
    private function applyBalanceReservationTotals(
        EloquentCollection $balances,
        array $currentByProduct,
        array $desiredByProduct,
    ): void {
        $productIds = array_values(
            array_unique([
                ...array_keys(
                    $currentByProduct,
                ),
                ...array_keys(
                    $desiredByProduct,
                ),
            ]),
        );

        sort($productIds);

        foreach (
            $productIds
            as $productId
        ) {
            $balance =
                $balances->get($productId);

            $current =
                $currentByProduct[$productId]
                ?? BigDecimal::zero();

            $desired =
                $desiredByProduct[$productId]
                ?? BigDecimal::zero();

            if (
                !$balance
                    instanceof InventoryBalance
            ) {
                if (
                    $desired->isZero()
                    && $current->isZero()
                ) {
                    continue;
                }

                throw ValidationException::withMessages([
                    'lines' => [
                        'An inventory balance is required before stock can be reserved.',
                    ],
                ]);
            }

            $reservedTotal =
                BigDecimal::of(
                    (string) $balance
                        ->quantity_reserved,
                );

            if (
                $reservedTotal->isLessThan(
                    $current,
                )
            ) {
                throw new LogicException(
                    'Inventory reserved quantity is lower than the current Sales Order reservation.',
                );
            }

            $newReserved =
                $reservedTotal
                    ->minus($current)
                    ->plus($desired);

            $balance->quantity_reserved =
                $this->decimal(
                    $newReserved,
                );

            $balance->version =
                (int) $balance->version + 1;

            $balance->save();
        }
    }

    /**
     * @param EloquentCollection<int, InventoryReservation> $reservations
     */
    private function closeAllocation(
        SalesOrderAllocation $allocation,
        EloquentCollection $reservations,
        string $status,
        string $reason,
        User $actor,
    ): void {
        foreach (
            $reservations
            as $reservation
        ) {
            $outstanding =
                BigDecimal::of(
                    $reservation
                        ->outstandingQuantity(),
                );

            $reservation->released_quantity =
                $this->decimal(
                    BigDecimal::of(
                        (string) $reservation
                            ->released_quantity,
                    )->plus($outstanding),
                );

            $reservation->status =
                'released';

            $reservation->active_key =
                null;

            $reservation
                ->released_by_user_id =
                    $actor->getKey();

            $reservation->released_at =
                now();

            $reservation->release_reason =
                $reason;

            $reservation->save();
        }

        $allocation->status = $status;
        $allocation->active_key = null;

        $allocation->released_by_user_id =
            $actor->getKey();

        $allocation->released_at = now();
        $allocation->release_reason = $reason;

        $allocation->save();
    }

    /**
     * @param EloquentCollection<int, SalesOrderLine> $lines
     */
    private function synchronizeOrderStatus(
        SalesOrder $salesOrder,
        EloquentCollection $lines,
    ): void {
        $allAllocated = true;
        $anyAllocated = false;

        foreach ($lines as $line) {
            $ordered = BigDecimal::of(
                (string) $line->ordered_quantity,
            );

            $allocated = BigDecimal::of(
                (string) $line
                    ->allocated_quantity,
            );

            if (
                $allocated->isGreaterThan(
                    BigDecimal::zero(),
                )
            ) {
                $anyAllocated = true;
            }

            if (
                $allocated->isLessThan(
                    $ordered,
                )
            ) {
                $allAllocated = false;
            }
        }

        $nextStatus = $allAllocated
            ? 'allocated'
            : (
                $anyAllocated
                    ? 'partially_allocated'
                    : 'approved'
            );

        if (
            $salesOrder->status
                !== $nextStatus
            && !$this->statusRegistry
                ->canTransition(
                    currentStatus:
                        $salesOrder->status,

                    nextStatus:
                        $nextStatus,
                )
        ) {
            throw new LogicException(
                "Sales Order status cannot transition from {$salesOrder->status} to {$nextStatus} during allocation.",
            );
        }

        $salesOrder->status =
            $nextStatus;

        $salesOrder->save();
    }

    private function ensureAllocatable(
        SalesOrder $salesOrder,
    ): void {
        if (!$salesOrder->isAllocatable()) {
            throw ValidationException::withMessages([
                'status' => [
                    'Only approved or currently allocated Sales Orders can be allocated.',
                ],
            ]);
        }

        if (
            $salesOrder->hasDispatches()
            || $salesOrder->hasInvoices()
        ) {
            throw ValidationException::withMessages([
                'allocation' => [
                    'The allocation cannot be changed after dispatch or invoice activity begins.',
                ],
            ]);
        }
    }

    private function authorizeOrderBranch(
        SalesOrder $salesOrder,
        User $actor,
        bool $requireActive,
    ): void {
        $branch = Branch::query()
            ->whereKey(
                $salesOrder->branch_id,
            )
            ->lockForUpdate()
            ->firstOrFail();

        $this->branchAccessService
            ->authorizeBranch(
                user: $actor,
                branch: $branch,
                requireActive: $requireActive,
            );
    }

    private function ensureTenant(
        SalesOrder $salesOrder,
        User $actor,
        int $tenantId,
    ): void {
        if (
            (int) $salesOrder->tenant_id
                !== $tenantId
            || (int) $actor->tenant_id
                !== $tenantId
        ) {
            throw new LogicException(
                'Sales Order allocation crossed a tenant boundary.',
            );
        }
    }

    private function activeTenantId(): int
    {
        return (int) $this->tenantContext
            ->tenant()
            ->getKey();
    }

    private function allocationActiveKey(
        SalesOrder $salesOrder,
    ): string {
        return sprintf(
            'sales-order:%d:allocation',
            (int) $salesOrder->getKey(),
        );
    }

    private function reservationKey(
        SalesOrderAllocation $allocation,
        SalesOrderAllocationLine $allocationLine,
    ): string {
        return sprintf(
            'sales-order-allocation:%d:line:%d',
            (int) $allocation->getKey(),
            (int) $allocationLine->getKey(),
        );
    }

    private function quantity(
        mixed $value,
        string $field,
    ): BigDecimal {
        if (
            !is_int($value)
            && !is_float($value)
            && !is_string($value)
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The allocated quantity must be a valid number.',
                ],
            ]);
        }

        $value = trim(
            (string) $value,
        );

        if (
            preg_match(
                '/^\d+(?:\.\d+)?$/',
                $value,
            ) !== 1
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The allocated quantity must be a non-negative number.',
                ],
            ]);
        }

        try {
            return BigDecimal::of(
                $value,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );
        } catch (\ArithmeticException) {
            throw ValidationException::withMessages([
                $field => [
                    'The allocated quantity may not contain more than 6 decimal places.',
                ],
            ]);
        }
    }

    private function decimal(
        BigDecimal $value,
    ): string {
        return $value->toScale(
            self::SCALE,
            RoundingMode::HalfUp,
        )->__toString();
    }
}