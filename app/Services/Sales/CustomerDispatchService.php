<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Branch;
use App\Models\CustomerDispatch;
use App\Models\CustomerDispatchLine;
use App\Models\InventoryBalance;
use App\Models\InventoryReservation;
use App\Models\SalesOrder;
use App\Models\SalesOrderAllocation;
use App\Models\SalesOrderAllocationLine;
use App\Models\SalesOrderLine;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\DispatchInventoryPostingService;
use App\Services\Organisation\BranchAccessService;
use App\Services\Settings\DocumentNumberService;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerDispatchService
{
    private const SCALE = 6;

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchAccessService $branchAccessService,
        private readonly DocumentNumberService $documentNumberService,
        private readonly DispatchInventoryPostingService $inventoryPostingService,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        User $actor,
    ): CustomerDispatch {
        $tenant = $this->tenantContext
            ->tenant();

        $tenantId =
            (int) $tenant->getKey();

        $this->ensureActorTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $normalized = $this->normalizeInput(
            data: $data,
            tenant: $tenant,
        );

        return DB::transaction(
            function () use (
                $normalized,
                $actor,
            ): CustomerDispatch {
                $salesOrder =
                    $this->lockSalesOrder(
                        salesOrderId:
                            $normalized[
                                'sales_order_id'
                            ],

                        actor: $actor,

                        requireActiveBranch:
                            true,
                    );

                $allocation =
                    $this->lockActiveAllocation(
                        $salesOrder,
                    );

                $existingDraft =
                    CustomerDispatch::query()
                        ->where(
                            'sales_order_id',
                            $salesOrder->getKey(),
                        )
                        ->whereNotNull(
                            'draft_key',
                        )
                        ->lockForUpdate()
                        ->first();

                if (
                    $existingDraft
                    instanceof CustomerDispatch
                ) {
                    throw ValidationException::withMessages([
                        'sales_order_id' => [
                            'This Sales Order already has an editable dispatch draft.',
                        ],
                    ]);
                }

                $lines = $this->buildLines(
                    salesOrder:
                        $salesOrder,

                    allocation:
                        $allocation,

                    inputLines:
                        $normalized['lines'],

                    excludingDispatchId:
                        null,
                );

                $dispatch =
                    CustomerDispatch::query()
                        ->create([
                            'sales_order_id' =>
                                $salesOrder
                                    ->getKey(),

                            'sales_order_allocation_id' =>
                                $allocation
                                    ->getKey(),

                            'branch_id' =>
                                $salesOrder
                                    ->branch_id,

                            'warehouse_id' =>
                                $salesOrder
                                    ->warehouse_id,

                            'customer_id' =>
                                $salesOrder
                                    ->customer_id,

                            'draft_key' =>
                                $this->draftKey(
                                    $salesOrder,
                                ),

                            'dispatch_date' =>
                                $normalized[
                                    'dispatch_date'
                                ],

                            'sales_order_number' =>
                                (string) $salesOrder
                                    ->document_number,

                            'customer_name' =>
                                $salesOrder
                                    ->customer_name,

                            'customer_code' =>
                                $salesOrder
                                    ->customer_code,

                            'customer_contact_person' =>
                                $salesOrder
                                    ->customer_contact_person,

                            'customer_phone' =>
                                $salesOrder
                                    ->customer_phone,

                            'shipping_address' =>
                                $normalized[
                                    'shipping_address'
                                ]
                                ?? $salesOrder
                                    ->shipping_address,

                            'delivery_instructions' =>
                                $normalized[
                                    'delivery_instructions'
                                ]
                                ?? $salesOrder
                                    ->delivery_instructions,

                            'carrier_name' =>
                                $normalized[
                                    'carrier_name'
                                ],

                            'vehicle_number' =>
                                $normalized[
                                    'vehicle_number'
                                ],

                            'tracking_number' =>
                                $normalized[
                                    'tracking_number'
                                ],

                            'notes' =>
                                $normalized[
                                    'notes'
                                ],

                            'status' =>
                                'draft',

                            'created_by_user_id' =>
                                $actor->getKey(),
                        ]);

                $this->replaceLines(
                    dispatch: $dispatch,
                    lines: $lines,
                );

                return $this->loadDispatch(
                    $dispatch,
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        CustomerDispatch $dispatch,
        array $data,
        User $actor,
    ): CustomerDispatch {
        $tenant = $this->tenantContext
            ->tenant();

        $tenantId =
            (int) $tenant->getKey();

        $this->ensureActorTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureDispatchTenant(
            dispatch: $dispatch,
            tenantId: $tenantId,
        );

        $normalized = $this->normalizeInput(
            data: $data,
            tenant: $tenant,
        );

        return DB::transaction(
            function () use (
                $dispatch,
                $normalized,
                $actor,
            ): CustomerDispatch {
                $lockedDispatch =
                    CustomerDispatch::query()
                        ->whereKey(
                            $dispatch->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->ensureEditable(
                    $lockedDispatch,
                );

                if (
                    (int) $lockedDispatch
                        ->sales_order_id
                    !== $normalized[
                        'sales_order_id'
                    ]
                ) {
                    throw ValidationException::withMessages([
                        'sales_order_id' => [
                            'The Sales Order cannot be changed after the dispatch draft is created.',
                        ],
                    ]);
                }

                $salesOrder =
                    $this->lockSalesOrder(
                        salesOrderId:
                            (int) $lockedDispatch
                                ->sales_order_id,

                        actor: $actor,

                        requireActiveBranch:
                            true,
                    );

                $allocation =
                    $this->lockActiveAllocation(
                        $salesOrder,
                    );

                if (
                    (int) $allocation
                        ->getKey()
                    !== (int) $lockedDispatch
                        ->sales_order_allocation_id
                ) {
                    throw ValidationException::withMessages([
                        'sales_order_id' => [
                            'The Sales Order allocation changed after this draft was created. Delete this draft and create a new dispatch.',
                        ],
                    ]);
                }

                $lines = $this->buildLines(
                    salesOrder:
                        $salesOrder,

                    allocation:
                        $allocation,

                    inputLines:
                        $normalized['lines'],

                    excludingDispatchId:
                        (int) $lockedDispatch
                            ->getKey(),
                );

                $lockedDispatch->fill([
                    'dispatch_date' =>
                        $normalized[
                            'dispatch_date'
                        ],

                    'shipping_address' =>
                        $normalized[
                            'shipping_address'
                        ]
                        ?? $salesOrder
                            ->shipping_address,

                    'delivery_instructions' =>
                        $normalized[
                            'delivery_instructions'
                        ]
                        ?? $salesOrder
                            ->delivery_instructions,

                    'carrier_name' =>
                        $normalized[
                            'carrier_name'
                        ],

                    'vehicle_number' =>
                        $normalized[
                            'vehicle_number'
                        ],

                    'tracking_number' =>
                        $normalized[
                            'tracking_number'
                        ],

                    'notes' =>
                        $normalized[
                            'notes'
                        ],
                ]);

                $lockedDispatch->save();

                $this->replaceLines(
                    dispatch:
                        $lockedDispatch,

                    lines:
                        $lines,
                );

                return $this->loadDispatch(
                    $lockedDispatch
                        ->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function delete(
        CustomerDispatch $dispatch,
        User $actor,
    ): void {
        $tenantId =
            $this->activeTenantId();

        $this->ensureActorTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureDispatchTenant(
            dispatch: $dispatch,
            tenantId: $tenantId,
        );

        DB::transaction(
            function () use (
                $dispatch,
                $actor,
            ): void {
                $lockedDispatch =
                    CustomerDispatch::query()
                        ->whereKey(
                            $dispatch->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->authorizeBranch(
                    branchId:
                        (int) $lockedDispatch
                            ->branch_id,

                    actor:
                        $actor,

                    requireActive:
                        false,
                );

                $this->ensureEditable(
                    $lockedDispatch,
                );

                $lockedDispatch->delete();
            },
            attempts: 5,
        );
    }

    public function post(
        CustomerDispatch $dispatch,
        User $actor,
    ): CustomerDispatch {
        $tenant = $this->tenantContext
            ->tenant();

        $tenantId =
            (int) $tenant->getKey();

        $this->ensureActorTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureDispatchTenant(
            dispatch: $dispatch,
            tenantId: $tenantId,
        );

        return DB::transaction(
            function () use (
                $dispatch,
                $actor,
                $tenant,
            ): CustomerDispatch {
                $lockedDispatch =
                    CustomerDispatch::query()
                        ->whereKey(
                            $dispatch->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    !$lockedDispatch
                        ->canBePosted()
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only draft dispatches can be posted.',
                        ],
                    ]);
                }

                $salesOrder =
                    $this->lockSalesOrder(
                        salesOrderId:
                            (int) $lockedDispatch
                                ->sales_order_id,

                        actor: $actor,

                        requireActiveBranch:
                            true,
                    );

                $allocation =
                    $this->lockActiveAllocation(
                        $salesOrder,
                    );

                if (
                    (int) $allocation
                        ->getKey()
                    !== (int) $lockedDispatch
                        ->sales_order_allocation_id
                ) {
                    throw ValidationException::withMessages([
                        'dispatch' => [
                            'The active Sales Order allocation changed after this draft was created.',
                        ],
                    ]);
                }

                $dispatchLines =
                    CustomerDispatchLine::query()
                        ->where(
                            'customer_dispatch_id',
                            $lockedDispatch
                                ->getKey(),
                        )
                        ->orderBy(
                            'product_id',
                        )
                        ->orderBy(
                            'line_number',
                        )
                        ->lockForUpdate()
                        ->get();

                if (
                    $dispatchLines->isEmpty()
                ) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            'The dispatch does not contain any lines.',
                        ],
                    ]);
                }

                $orderLineIds =
                    $dispatchLines
                        ->pluck(
                            'sales_order_line_id',
                        )
                        ->map(
                            static fn (
                                mixed $id,
                            ): int => (int) $id,
                        )
                        ->all();

                $orderLines =
                    SalesOrderLine::query()
                        ->where(
                            'sales_order_id',
                            $salesOrder->getKey(),
                        )
                        ->whereIn(
                            'id',
                            $orderLineIds,
                        )
                        ->orderBy(
                            'product_id',
                        )
                        ->orderBy(
                            'line_number',
                        )
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                $stockProductIds =
                    $dispatchLines
                        ->filter(
                            static fn (
                                CustomerDispatchLine $line,
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
                        salesOrder:
                            $salesOrder,

                        productIds:
                            $stockProductIds,
                    );

                $reservationIds =
                    $dispatchLines
                        ->filter(
                            static fn (
                                CustomerDispatchLine $line,
                            ): bool =>
                                $line
                                    ->inventory_reservation_id
                                !== null,
                        )
                        ->pluck(
                            'inventory_reservation_id',
                        )
                        ->map(
                            static fn (
                                mixed $id,
                            ): int => (int) $id,
                        )
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();

                $reservations =
                    $this->lockReservations(
                        $reservationIds,
                    );

                $this->validatePostingQuantities(
                    dispatchLines:
                        $dispatchLines,

                    orderLines:
                        $orderLines,

                    reservations:
                        $reservations,
                );

                if (
                    $lockedDispatch
                        ->dispatch_number
                    === null
                ) {
                    $numberAllocation =
                        $this
                            ->documentNumberService
                            ->allocate(
                                documentType:
                                    'delivery_note',

                                branchId:
                                    (int) $lockedDispatch
                                        ->branch_id,

                                idempotencyKey:
                                    $this
                                        ->numberAllocationKey(
                                            $lockedDispatch,
                                        ),

                                allocatableType:
                                    CustomerDispatch::class,

                                allocatableId:
                                    (int) $lockedDispatch
                                        ->getKey(),

                                allocatedAt:
                                    CarbonImmutable::parse(
                                        $lockedDispatch
                                            ->dispatch_date,

                                        $tenant
                                            ->timezone,
                                    ),
                            );

                    $lockedDispatch
                        ->document_number_allocation_id =
                            $numberAllocation
                                ->getKey();

                    $lockedDispatch
                        ->dispatch_number =
                            $numberAllocation
                                ->number;

                    $lockedDispatch->save();
                }

                $occurredAt =
                    CarbonImmutable::parse(
                        $lockedDispatch
                            ->dispatch_date,

                        $tenant->timezone,
                    )->startOfDay();

                foreach (
                    $dispatchLines
                    as $dispatchLine
                ) {
                    $orderLine =
                        $orderLines->get(
                            (int) $dispatchLine
                                ->sales_order_line_id,
                        );

                    if (
                        !$orderLine
                        instanceof SalesOrderLine
                    ) {
                        throw new LogicException(
                            'A dispatch Sales Order line was not found.',
                        );
                    }

                    $quantity =
                        BigDecimal::of(
                            (string) $dispatchLine
                                ->dispatched_quantity,
                        );

                    if (
                        $dispatchLine
                            ->isStockItem()
                    ) {
                        $balance =
                            $balances->get(
                                (int) $dispatchLine
                                    ->product_id,
                            );

                        $reservation =
                            $reservations->get(
                                (int) $dispatchLine
                                    ->inventory_reservation_id,
                            );

                        if (
                            !$balance
                                instanceof InventoryBalance
                            || !$reservation
                                instanceof InventoryReservation
                        ) {
                            throw new LogicException(
                                'The stock balance or reservation required for dispatch was not found.',
                            );
                        }

                        $ledgerEntry =
                            $this
                                ->inventoryPostingService
                                ->postLine(
                                    dispatch:
                                        $lockedDispatch,

                                    dispatchLine:
                                        $dispatchLine,

                                    salesOrderLine:
                                        $orderLine,

                                    balance:
                                        $balance,

                                    reservation:
                                        $reservation,

                                    actor:
                                        $actor,

                                    occurredAt:
                                        $occurredAt,
                                );

                        $dispatchLine
                            ->unit_cost =
                                $ledgerEntry
                                    ->unit_cost;

                        $dispatchLine
                            ->total_cost =
                                $ledgerEntry
                                    ->total_cost;

                        $dispatchLine
                            ->stock_ledger_entry_id =
                                $ledgerEntry
                                    ->getKey();

                        $dispatchLine->save();
                    }

                    $orderLine
                        ->dispatched_quantity =
                            BigDecimal::of(
                                (string) $orderLine
                                    ->dispatched_quantity,
                            )
                                ->plus($quantity)
                                ->toScale(
                                    self::SCALE,
                                    RoundingMode::HalfUp,
                                )
                                ->__toString();

                    $orderLine->save();
                }

                $lockedDispatch->status =
                    'posted';

                $lockedDispatch->draft_key =
                    null;

                $lockedDispatch
                    ->posted_by_user_id =
                        $actor->getKey();

                $lockedDispatch->posted_at =
                    now();

                $lockedDispatch->save();

                $this->synchronizeSalesOrderStatus(
                    $salesOrder,
                );

                return $this->loadDispatch(
                    $lockedDispatch
                        ->refresh(),
                );
            },
            attempts: 5,
        );
    }

    public function reverse(
        CustomerDispatch $dispatch,
        string $reason,
        User $actor,
    ): CustomerDispatch {
        $tenant = $this->tenantContext
            ->tenant();

        $tenantId =
            (int) $tenant->getKey();

        $this->ensureActorTenant(
            actor: $actor,
            tenantId: $tenantId,
        );

        $this->ensureDispatchTenant(
            dispatch: $dispatch,
            tenantId: $tenantId,
        );

        $reason = trim($reason);

        if (
            $reason === ''
            || mb_strlen($reason) > 500
        ) {
            throw ValidationException::withMessages([
                'reversal_reason' => [
                    'A reversal reason is required and may not exceed 500 characters.',
                ],
            ]);
        }

        return DB::transaction(
            function () use (
                $dispatch,
                $reason,
                $actor,
                $tenant,
            ): CustomerDispatch {
                $lockedDispatch =
                    CustomerDispatch::query()
                        ->whereKey(
                            $dispatch->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    !$lockedDispatch
                        ->canBeReversed()
                ) {
                    throw ValidationException::withMessages([
                        'status' => [
                            'Only posted dispatches can be reversed.',
                        ],
                    ]);
                }

                $salesOrder =
                    $this->lockSalesOrder(
                        salesOrderId:
                            (int) $lockedDispatch
                                ->sales_order_id,

                        actor:
                            $actor,

                        requireActiveBranch:
                            true,
                    );

                $allocation =
                    $this->lockActiveAllocation(
                        $salesOrder,
                    );

                if (
                    (int) $allocation
                        ->getKey()
                    !== (int) $lockedDispatch
                        ->sales_order_allocation_id
                ) {
                    throw ValidationException::withMessages([
                        'dispatch' => [
                            'The dispatch cannot be reversed because its allocation is no longer active.',
                        ],
                    ]);
                }

                $dispatchLines =
                    CustomerDispatchLine::query()
                        ->where(
                            'customer_dispatch_id',
                            $lockedDispatch
                                ->getKey(),
                        )
                        ->orderByDesc(
                            'stock_ledger_entry_id',
                        )
                        ->orderByDesc(
                            'line_number',
                        )
                        ->lockForUpdate()
                        ->get();

                $orderLineIds =
                    $dispatchLines
                        ->pluck(
                            'sales_order_line_id',
                        )
                        ->map(
                            static fn (
                                mixed $id,
                            ): int => (int) $id,
                        )
                        ->all();

                $orderLines =
                    SalesOrderLine::query()
                        ->where(
                            'sales_order_id',
                            $salesOrder->getKey(),
                        )
                        ->whereIn(
                            'id',
                            $orderLineIds,
                        )
                        ->orderBy(
                            'product_id',
                        )
                        ->orderBy(
                            'line_number',
                        )
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('id');

                foreach (
                    $orderLines
                    as $orderLine
                ) {
                    if (
                        BigDecimal::of(
                            (string) $orderLine
                                ->invoiced_quantity,
                        )->isGreaterThan(
                            BigDecimal::zero(),
                        )
                    ) {
                        throw ValidationException::withMessages([
                            'dispatch' => [
                                'The dispatch cannot be reversed after invoice activity exists for one of its Sales Order lines.',
                            ],
                        ]);
                    }
                }

                $stockProductIds =
                    $dispatchLines
                        ->filter(
                            static fn (
                                CustomerDispatchLine $line,
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
                        salesOrder:
                            $salesOrder,

                        productIds:
                            $stockProductIds,
                    );

                $reservationIds =
                    $dispatchLines
                        ->filter(
                            static fn (
                                CustomerDispatchLine $line,
                            ): bool =>
                                $line
                                    ->inventory_reservation_id
                                !== null,
                        )
                        ->pluck(
                            'inventory_reservation_id',
                        )
                        ->map(
                            static fn (
                                mixed $id,
                            ): int => (int) $id,
                        )
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();

                $reservations =
                    $this->lockReservations(
                        $reservationIds,
                    );

                $occurredAt =
                    CarbonImmutable::now(
                        $tenant->timezone,
                    );

                foreach (
                    $dispatchLines
                    as $dispatchLine
                ) {
                    $orderLine =
                        $orderLines->get(
                            (int) $dispatchLine
                                ->sales_order_line_id,
                        );

                    if (
                        !$orderLine
                        instanceof SalesOrderLine
                    ) {
                        throw new LogicException(
                            'A dispatch Sales Order line was not found during reversal.',
                        );
                    }

                    $quantity =
                        BigDecimal::of(
                            (string) $dispatchLine
                                ->dispatched_quantity,
                        );

                    if (
                        $dispatchLine
                            ->isStockItem()
                    ) {
                        $balance =
                            $balances->get(
                                (int) $dispatchLine
                                    ->product_id,
                            );

                        $reservation =
                            $reservations->get(
                                (int) $dispatchLine
                                    ->inventory_reservation_id,
                            );

                        if (
                            !$balance
                                instanceof InventoryBalance
                            || !$reservation
                                instanceof InventoryReservation
                        ) {
                            throw new LogicException(
                                'The balance or reservation required for dispatch reversal was not found.',
                            );
                        }

                        $reversal =
                            $this
                                ->inventoryPostingService
                                ->reverseLine(
                                    dispatch:
                                        $lockedDispatch,

                                    dispatchLine:
                                        $dispatchLine,

                                    salesOrderLine:
                                        $orderLine,

                                    balance:
                                        $balance,

                                    reservation:
                                        $reservation,

                                    actor:
                                        $actor,

                                    occurredAt:
                                        $occurredAt,
                                );

                        $dispatchLine
                            ->reversal_stock_ledger_entry_id =
                                $reversal
                                    ->getKey();

                        $dispatchLine->save();
                    }

                    $currentDispatched =
                        BigDecimal::of(
                            (string) $orderLine
                                ->dispatched_quantity,
                        );

                    if (
                        $currentDispatched
                            ->isLessThan($quantity)
                    ) {
                        throw new LogicException(
                            'Sales Order dispatched quantity is lower than the dispatch reversal quantity.',
                        );
                    }

                    $orderLine
                        ->dispatched_quantity =
                            $currentDispatched
                                ->minus($quantity)
                                ->toScale(
                                    self::SCALE,
                                    RoundingMode::HalfUp,
                                )
                                ->__toString();

                    $orderLine->save();
                }

                $lockedDispatch->status =
                    'reversed';

                $lockedDispatch
                    ->reversed_by_user_id =
                        $actor->getKey();

                $lockedDispatch->reversed_at =
                    now();

                $lockedDispatch->reversal_reason =
                    $reason;

                $lockedDispatch->save();

                $this->synchronizeSalesOrderStatus(
                    $salesOrder,
                );

                return $this->loadDispatch(
                    $lockedDispatch
                        ->refresh(),
                );
            },
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{
     *     sales_order_id: int,
     *     dispatch_date: string,
     *     shipping_address: string|null,
     *     delivery_instructions: string|null,
     *     carrier_name: string|null,
     *     vehicle_number: string|null,
     *     tracking_number: string|null,
     *     notes: string|null,
     *     lines: list<array<string, mixed>>
     * }
     */
    private function normalizeInput(
        array $data,
        Tenant $tenant,
    ): array {
        $salesOrderId = filter_var(
            $data['sales_order_id']
                ?? null,

            FILTER_VALIDATE_INT,

            [
                'options' => [
                    'min_range' => 1,
                ],
            ],
        );

        if ($salesOrderId === false) {
            throw ValidationException::withMessages([
                'sales_order_id' => [
                    'The selected Sales Order is invalid.',
                ],
            ]);
        }

        $dispatchDate = $this->date(
            value:
                $data['dispatch_date']
                    ?? null,

            field:
                'dispatch_date',

            timezone:
                $tenant->timezone,
        );

        $lines = $data['lines']
            ?? null;

        if (
            !is_array($lines)
            || $lines === []
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    'A dispatch must contain at least one line.',
                ],
            ]);
        }

        return [
            'sales_order_id' =>
                $salesOrderId,

            'dispatch_date' =>
                $dispatchDate,

            'shipping_address' =>
                $this->text(
                    value:
                        $data['shipping_address']
                            ?? null,

                    maximum:
                        4000,
                ),

            'delivery_instructions' =>
                $this->text(
                    value:
                        $data['delivery_instructions']
                            ?? null,

                    maximum:
                        4000,
                ),

            'carrier_name' =>
                $this->text(
                    value:
                        $data['carrier_name']
                            ?? null,

                    maximum:
                        160,
                ),

            'vehicle_number' =>
                $this->text(
                    value:
                        $data['vehicle_number']
                            ?? null,

                    maximum:
                        80,
                ),

            'tracking_number' =>
                $this->text(
                    value:
                        $data['tracking_number']
                            ?? null,

                    maximum:
                        120,
                ),

            'notes' =>
                $this->text(
                    value:
                        $data['notes']
                            ?? null,

                    maximum:
                        4000,
                ),

            'lines' =>
                array_values($lines),
        ];
    }

    /**
     * @param list<array<string, mixed>> $inputLines
     *
     * @return list<array<string, mixed>>
     */
    private function buildLines(
        SalesOrder $salesOrder,
        SalesOrderAllocation $allocation,
        array $inputLines,
        ?int $excludingDispatchId,
    ): array {
        $orderLines =
            SalesOrderLine::query()
                ->where(
                    'sales_order_id',
                    $salesOrder->getKey(),
                )
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

        $allocationLines =
            SalesOrderAllocationLine::query()
                ->where(
                    'sales_order_allocation_id',
                    $allocation->getKey(),
                )
                ->with('reservation')
                ->orderBy('line_number')
                ->lockForUpdate()
                ->get()
                ->keyBy(
                    'sales_order_line_id',
                );

        $draftQuantities =
            CustomerDispatchLine::query()
                ->whereHas(
                    'dispatch',
                    static function (
                        $query,
                    ) use (
                        $salesOrder,
                        $excludingDispatchId,
                    ): void {
                        $query
                            ->where(
                                'sales_order_id',
                                $salesOrder->getKey(),
                            )
                            ->where(
                                'status',
                                'draft',
                            )
                            ->when(
                                $excludingDispatchId
                                    !== null,

                                static fn (
                                    $draftQuery,
                                ) => $draftQuery
                                    ->where(
                                        'id',
                                        '!=',
                                        $excludingDispatchId,
                                    ),
                            );
                    },
                )
                ->selectRaw(
                    'sales_order_line_id, SUM(dispatched_quantity) AS draft_quantity',
                )
                ->groupBy(
                    'sales_order_line_id',
                )
                ->pluck(
                    'draft_quantity',
                    'sales_order_line_id',
                );

        $built = [];
        $seen = [];

        foreach (
            array_values($inputLines)
            as $index => $inputLine
        ) {
            if (!is_array($inputLine)) {
                throw ValidationException::withMessages([
                    "lines.{$index}" => [
                        'Each dispatch line must be an object.',
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

            if (
                $lineId === false
                || isset($seen[$lineId])
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.sales_order_line_id" => [
                        'The selected Sales Order line is invalid or duplicated.',
                    ],
                ]);
            }

            $seen[$lineId] = true;

            $orderLine =
                $orderLines->get($lineId);

            $allocationLine =
                $allocationLines->get(
                    $lineId,
                );

            if (
                !$orderLine
                    instanceof SalesOrderLine
                || !$allocationLine
                    instanceof SalesOrderAllocationLine
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.sales_order_line_id" => [
                        'The selected line is not part of the active allocation.',
                    ],
                ]);
            }

            $quantity = $this->quantity(
                value:
                    $inputLine[
                        'dispatched_quantity'
                    ] ?? null,

                field:
                    "lines.{$index}.dispatched_quantity",

                allowZero:
                    true,
            );

            if ($quantity->isZero()) {
                continue;
            }

            $draftQuantity =
                BigDecimal::of(
                    (string) (
                        $draftQuantities[
                            $lineId
                        ] ?? '0'
                    ),
                );

            $remainingAllocated =
                BigDecimal::of(
                    (string) $orderLine
                        ->allocated_quantity,
                )
                    ->minus(
                        BigDecimal::of(
                            (string) $orderLine
                                ->dispatched_quantity,
                        ),
                    )
                    ->minus(
                        $draftQuantity,
                    );

            if (
                $remainingAllocated
                    ->isLessThan($quantity)
            ) {
                throw ValidationException::withMessages([
                    "lines.{$index}.dispatched_quantity" => [
                        "Only {$this->decimal($remainingAllocated)} remains dispatchable for {$orderLine->product_name}.",
                    ],
                ]);
            }

            $reservationId = null;

            if (
                $orderLine->isStockItem()
            ) {
                $reservation =
                    $allocationLine
                        ->reservation;

                if (
                    !$reservation
                    instanceof InventoryReservation
                ) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.sales_order_line_id" => [
                            "The active inventory reservation for {$orderLine->product_name} was not found.",
                        ],
                    ]);
                }

                if (
                    BigDecimal::of(
                        $reservation
                            ->outstandingQuantity(),
                    )->isLessThan(
                        $quantity,
                    )
                ) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.dispatched_quantity" => [
                            "The open reservation for {$orderLine->product_name} is lower than the dispatch quantity.",
                        ],
                    ]);
                }

                $reservationId =
                    $reservation->getKey();
            }

            $built[] = [
                'sales_order_line_id' =>
                    $orderLine->getKey(),

                'sales_order_allocation_line_id' =>
                    $allocationLine->getKey(),

                'inventory_reservation_id' =>
                    $reservationId,

                'product_id' =>
                    $orderLine->product_id,

                'unit_id' =>
                    $orderLine->unit_id,

                'line_number' =>
                    count($built) + 1,

                'product_name' =>
                    $orderLine->product_name,

                'product_sku' =>
                    $orderLine->product_sku,

                'product_type' =>
                    $orderLine->product_type,

                'unit_name' =>
                    $orderLine->unit_name,

                'unit_code' =>
                    $orderLine->unit_code,

                'description' =>
                    $this->text(
                        value:
                            $inputLine[
                                'description'
                            ]
                            ?? $orderLine
                                ->description,

                        maximum:
                            4000,
                    ),

                'dispatched_quantity' =>
                    $this->decimal(
                        $quantity,
                    ),

                'unit_cost' =>
                    '0.000000',

                'total_cost' =>
                    '0.000000',
            ];
        }

        if ($built === []) {
            throw ValidationException::withMessages([
                'lines' => [
                    'Enter a dispatch quantity greater than zero for at least one line.',
                ],
            ]);
        }

        return $built;
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function replaceLines(
        CustomerDispatch $dispatch,
        array $lines,
    ): void {
        CustomerDispatchLine::query()
            ->where(
                'customer_dispatch_id',
                $dispatch->getKey(),
            )
            ->lockForUpdate()
            ->get()
            ->each(
                static function (
                    CustomerDispatchLine $line,
                ): void {
                    $line->delete();
                },
            );

        foreach ($lines as $line) {
            $dispatch->lines()
                ->create($line);
        }
    }

    private function lockSalesOrder(
        int $salesOrderId,
        User $actor,
        bool $requireActiveBranch,
    ): SalesOrder {
        $salesOrder =
            SalesOrder::query()
                ->whereKey($salesOrderId)
                ->lockForUpdate()
                ->first();

        if (
            !$salesOrder
            instanceof SalesOrder
        ) {
            throw ValidationException::withMessages([
                'sales_order_id' => [
                    'The selected Sales Order is unavailable.',
                ],
            ]);
        }

        $this->authorizeBranch(
            branchId:
                (int) $salesOrder
                    ->branch_id,

            actor:
                $actor,

            requireActive:
                $requireActiveBranch,
        );

        if (
            !$salesOrder->isDispatchable()
        ) {
            throw ValidationException::withMessages([
                'sales_order_id' => [
                    'The selected Sales Order is not currently dispatchable.',
                ],
            ]);
        }

        if (
            $salesOrder->warehouse_id
            === null
        ) {
            $hasStockLine =
                $salesOrder->lines()
                    ->where(
                        'product_type',
                        'stock',
                    )
                    ->exists();

            if ($hasStockLine) {
                throw ValidationException::withMessages([
                    'sales_order_id' => [
                        'The Sales Order requires a fulfillment warehouse before stock can be dispatched.',
                    ],
                ]);
            }
        }

        return $salesOrder;
    }

    private function lockActiveAllocation(
        SalesOrder $salesOrder,
    ): SalesOrderAllocation {
        $allocation =
            SalesOrderAllocation::query()
                ->where(
                    'sales_order_id',
                    $salesOrder->getKey(),
                )
                ->whereNotNull(
                    'active_key',
                )
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
                'sales_order_id' => [
                    'The Sales Order must have an active allocation before dispatch.',
                ],
            ]);
        }

        return $allocation;
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
            $salesOrder->warehouse_id
            === null
        ) {
            throw new LogicException(
                'Stock dispatch requires a fulfillment warehouse.',
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
            ->keyBy('product_id');
    }

    /**
     * @param list<int> $reservationIds
     *
     * @return EloquentCollection<int, InventoryReservation>
     */
    private function lockReservations(
        array $reservationIds,
    ): EloquentCollection {
        if ($reservationIds === []) {
            return new EloquentCollection();
        }

        return InventoryReservation::query()
            ->whereIn(
                'id',
                $reservationIds,
            )
            ->orderBy('product_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
    }

    /**
     * @param EloquentCollection<int, CustomerDispatchLine> $dispatchLines
     * @param EloquentCollection<int, SalesOrderLine> $orderLines
     * @param EloquentCollection<int, InventoryReservation> $reservations
     */
    private function validatePostingQuantities(
        EloquentCollection $dispatchLines,
        EloquentCollection $orderLines,
        EloquentCollection $reservations,
    ): void {
        foreach (
            $dispatchLines
            as $dispatchLine
        ) {
            $orderLine =
                $orderLines->get(
                    (int) $dispatchLine
                        ->sales_order_line_id,
                );

            if (
                !$orderLine
                instanceof SalesOrderLine
            ) {
                throw new LogicException(
                    'A Sales Order line required for dispatch posting was not found.',
                );
            }

            $quantity =
                BigDecimal::of(
                    (string) $dispatchLine
                        ->dispatched_quantity,
                );

            $remaining =
                BigDecimal::of(
                    (string) $orderLine
                        ->allocated_quantity,
                )->minus(
                    BigDecimal::of(
                        (string) $orderLine
                            ->dispatched_quantity,
                    ),
                );

            if (
                $remaining->isLessThan(
                    $quantity,
                )
            ) {
                throw ValidationException::withMessages([
                    'lines' => [
                        "Only {$this->decimal($remaining)} remains dispatchable for {$orderLine->product_name}.",
                    ],
                ]);
            }

            if (
                $dispatchLine
                    ->isStockItem()
            ) {
                $reservation =
                    $reservations->get(
                        (int) $dispatchLine
                            ->inventory_reservation_id,
                    );

                if (
                    !$reservation
                        instanceof InventoryReservation
                    || BigDecimal::of(
                        $reservation
                            ->outstandingQuantity(),
                    )->isLessThan(
                        $quantity,
                    )
                ) {
                    throw ValidationException::withMessages([
                        'lines' => [
                            "The active reservation for {$orderLine->product_name} is insufficient.",
                        ],
                    ]);
                }
            }
        }
    }

    private function synchronizeSalesOrderStatus(
        SalesOrder $salesOrder,
    ): void {
        $lines = SalesOrderLine::query()
            ->where(
                'sales_order_id',
                $salesOrder->getKey(),
            )
            ->orderBy('line_number')
            ->lockForUpdate()
            ->get();

        $anyDispatched = false;
        $allDispatched =
            !$lines->isEmpty();

        $anyAllocated = false;
        $allAllocated =
            !$lines->isEmpty();

        foreach ($lines as $line) {
            $ordered = BigDecimal::of(
                (string) $line
                    ->ordered_quantity,
            );

            $allocated = BigDecimal::of(
                (string) $line
                    ->allocated_quantity,
            );

            $dispatched = BigDecimal::of(
                (string) $line
                    ->dispatched_quantity,
            );

            $anyDispatched =
                $anyDispatched
                || $dispatched
                    ->isGreaterThan(
                        BigDecimal::zero(),
                    );

            $allDispatched =
                $allDispatched
                && !$dispatched
                    ->isLessThan(
                        $ordered,
                    );

            $anyAllocated =
                $anyAllocated
                || $allocated
                    ->isGreaterThan(
                        BigDecimal::zero(),
                    );

            $allAllocated =
                $allAllocated
                && !$allocated
                    ->isLessThan(
                        $ordered,
                    );
        }

        if ($allDispatched) {
            $salesOrder->status =
                'dispatched';
        } elseif ($anyDispatched) {
            $salesOrder->status =
                'partially_dispatched';
        } elseif ($allAllocated) {
            $salesOrder->status =
                'allocated';
        } elseif ($anyAllocated) {
            $salesOrder->status =
                'partially_allocated';
        } else {
            $salesOrder->status =
                'approved';
        }

        $salesOrder->save();
    }

    private function authorizeBranch(
        int $branchId,
        User $actor,
        bool $requireActive,
    ): void {
        $branch = Branch::query()
            ->whereKey($branchId)
            ->lockForUpdate()
            ->firstOrFail();

        $this->branchAccessService
            ->authorizeBranch(
                user: $actor,
                branch: $branch,
                requireActive: $requireActive,
            );
    }

    private function ensureEditable(
        CustomerDispatch $dispatch,
    ): void {
        if ($dispatch->canBeEdited()) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => [
                'Only draft dispatches can be modified or deleted.',
            ],
        ]);
    }

    private function loadDispatch(
        CustomerDispatch $dispatch,
    ): CustomerDispatch {
        return $dispatch->load([
            'salesOrder:id,document_number,status',

            'salesOrderAllocation:id,revision,status',

            'branch:id,name,code,status',

            'warehouse:id,branch_id,name,code,status',

            'customer:id,name,code,status',

            'lines.stockLedgerEntry',

            'lines.reversalStockLedgerEntry',

            'createdBy:id,name',

            'postedBy:id,name',

            'reversedBy:id,name',

            'documentNumberAllocation',
        ]);
    }

    private function activeTenantId(): int
    {
        return (int) $this
            ->tenantContext
            ->tenant()
            ->getKey();
    }

    private function ensureActorTenant(
        User $actor,
        int $tenantId,
    ): void {
        if (
            (int) $actor->tenant_id
            !== $tenantId
        ) {
            throw new LogicException(
                'The selected user does not belong to the active tenant.',
            );
        }
    }

    private function ensureDispatchTenant(
        CustomerDispatch $dispatch,
        int $tenantId,
    ): void {
        if (
            (int) $dispatch->tenant_id
            !== $tenantId
        ) {
            throw new LogicException(
                'The selected dispatch belongs to another tenant.',
            );
        }
    }

    private function draftKey(
        SalesOrder $salesOrder,
    ): string {
        return sprintf(
            'sales-order:%d:dispatch-draft',
            (int) $salesOrder->getKey(),
        );
    }

    private function numberAllocationKey(
        CustomerDispatch $dispatch,
    ): string {
        return sprintf(
            'customer-dispatch:%d:%d',
            (int) $dispatch->tenant_id,
            (int) $dispatch->getKey(),
        );
    }

    private function date(
        mixed $value,
        string $field,
        string $timezone,
    ): string {
        if (!is_string($value)) {
            throw ValidationException::withMessages([
                $field => [
                    'The date must use the YYYY-MM-DD format.',
                ],
            ]);
        }

        $value = trim($value);

        $date = CarbonImmutable::createFromFormat(
            '!Y-m-d',
            $value,
            $timezone,
        );

        if (
            !$date instanceof CarbonImmutable
            || $date->format('Y-m-d')
                !== $value
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The date must use the YYYY-MM-DD format.',
                ],
            ]);
        }

        return $value;
    }

    private function text(
        mixed $value,
        int $maximum,
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (!is_string($value)) {
            throw ValidationException::withMessages([
                'dispatch' => [
                    'Text fields must contain valid text.',
                ],
            ]);
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (
            mb_strlen($value)
            > $maximum
        ) {
            throw ValidationException::withMessages([
                'dispatch' => [
                    "A dispatch text field exceeds {$maximum} characters.",
                ],
            ]);
        }

        return $value;
    }

    private function quantity(
        mixed $value,
        string $field,
        bool $allowZero = false,
    ): BigDecimal {
        if (
            !is_int($value)
            && !is_float($value)
            && !is_string($value)
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The dispatch quantity must be a valid number.',
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
                    'The dispatch quantity must be a positive number.',
                ],
            ]);
        }

        try {
            $quantity = BigDecimal::of(
                $value,
            )->toScale(
                self::SCALE,
                RoundingMode::Unnecessary,
            );
        } catch (\ArithmeticException) {
            throw ValidationException::withMessages([
                $field => [
                    'The dispatch quantity may not exceed 6 decimal places.',
                ],
            ]);
        }

        if (
            !$allowZero
            && !$quantity->isGreaterThan(
                BigDecimal::zero(),
            )
        ) {
            throw ValidationException::withMessages([
                $field => [
                    'The dispatch quantity must be greater than zero.',
                ],
            ]);
        }

        return $quantity;
    }

    private function decimal(
        BigDecimal $value,
    ): string {
        return $value
            ->toScale(
                self::SCALE,
                RoundingMode::HalfUp,
            )
            ->__toString();
    }
}