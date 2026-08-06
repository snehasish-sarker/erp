<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\InventoryBalance;
use App\Models\InventoryReservation;
use App\Models\SalesOrder;
use App\Models\SalesOrderAllocation;
use App\Models\SalesOrderLine;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

final class InventoryAvailabilityService
{
    private const SCALE = 6;

    /**
     * @return list<array{
     *     sales_order_line_id: int,
     *     product_id: int,
     *     product_type: string,
     *     quantity_on_hand: string,
     *     quantity_reserved_total: string,
     *     quantity_reserved_current_order: string,
     *     quantity_reserved_other: string,
     *     quantity_available_to_order: string,
     *     current_allocated_quantity: string,
     *     maximum_allocatable_quantity: string
     * }>
     */
    public function forSalesOrder(
        SalesOrder $salesOrder,
    ): array {
        $salesOrder->loadMissing('lines');

        $activeAllocation =
            SalesOrderAllocation::query()
                ->where(
                    'sales_order_id',
                    $salesOrder->getKey(),
                )
                ->whereNotNull('active_key')
                ->where('status', 'active')
                ->first();

        $currentReservations =
            $this->currentReservations(
                $activeAllocation,
            );

        $currentByLine = [];
        $currentByProduct = [];

        foreach (
            $currentReservations
            as $reservation
        ) {
            $lineId =
                (int) $reservation->source_line_id;

            $productId =
                (int) $reservation->product_id;

            $outstanding = BigDecimal::of(
                $reservation->outstandingQuantity(),
            );

            $currentByLine[$lineId] =
                $outstanding;

            $currentByProduct[$productId] = (
                $currentByProduct[$productId]
                ?? BigDecimal::zero()
            )->plus($outstanding);
        }

        $stockProductIds =
            $salesOrder->lines
                ->filter(
                    static fn (
                        SalesOrderLine $line,
                    ): bool => $line->isStockItem(),
                )
                ->pluck('product_id')
                ->map(
                    static fn (
                        mixed $id,
                    ): int => (int) $id,
                )
                ->unique()
                ->values()
                ->all();

        $balances = $this->balances(
            salesOrder: $salesOrder,
            productIds: $stockProductIds,
        );

        $result = [];

        foreach (
            $salesOrder->lines
            as $line
        ) {
            $ordered = BigDecimal::of(
                (string) $line->ordered_quantity,
            );

            if (!$line->isStockItem()) {
                $result[] = [
                    'sales_order_line_id' =>
                        (int) $line->getKey(),

                    'product_id' =>
                        (int) $line->product_id,

                    'product_type' =>
                        $line->product_type,

                    'quantity_on_hand' =>
                        '0.000000',

                    'quantity_reserved_total' =>
                        '0.000000',

                    'quantity_reserved_current_order' =>
                        '0.000000',

                    'quantity_reserved_other' =>
                        '0.000000',

                    'quantity_available_to_order' =>
                        $ordered
                            ->toScale(
                                self::SCALE,
                            )
                            ->__toString(),

                    'current_allocated_quantity' =>
                        (string) $line
                            ->allocated_quantity,

                    'maximum_allocatable_quantity' =>
                        $ordered
                            ->toScale(
                                self::SCALE,
                            )
                            ->__toString(),
                ];

                continue;
            }

            $balance = $balances->get(
                (int) $line->product_id,
            );

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
                $currentByProduct[
                    (int) $line->product_id
                ]
                ?? BigDecimal::zero();

            $reservedOther =
                $reservedTotal->minus(
                    $reservedCurrent,
                );

            if (
                $reservedOther->isLessThan(
                    BigDecimal::zero(),
                )
            ) {
                $reservedOther =
                    BigDecimal::zero();
            }

            $availableToOrder =
                $onHand->minus(
                    $reservedOther,
                );

            if (
                $availableToOrder->isLessThan(
                    BigDecimal::zero(),
                )
            ) {
                $availableToOrder =
                    BigDecimal::zero();
            }

            $maximum =
                $ordered->isLessThan(
                    $availableToOrder,
                )
                    ? $ordered
                    : $availableToOrder;

            $currentLine =
                $currentByLine[
                    (int) $line->getKey()
                ]
                ?? BigDecimal::zero();

            $result[] = [
                'sales_order_line_id' =>
                    (int) $line->getKey(),

                'product_id' =>
                    (int) $line->product_id,

                'product_type' =>
                    $line->product_type,

                'quantity_on_hand' =>
                    $this->decimal($onHand),

                'quantity_reserved_total' =>
                    $this->decimal(
                        $reservedTotal,
                    ),

                'quantity_reserved_current_order' =>
                    $this->decimal(
                        $reservedCurrent,
                    ),

                'quantity_reserved_other' =>
                    $this->decimal(
                        $reservedOther,
                    ),

                'quantity_available_to_order' =>
                    $this->decimal(
                        $availableToOrder,
                    ),

                'current_allocated_quantity' =>
                    $this->decimal(
                        $currentLine,
                    ),

                'maximum_allocatable_quantity' =>
                    $this->decimal($maximum),
            ];
        }

        return $result;
    }

    /**
     * @return Collection<int, InventoryReservation>
     */
    private function currentReservations(
        ?SalesOrderAllocation $activeAllocation,
    ): Collection {
        if (
            !$activeAllocation
                instanceof SalesOrderAllocation
        ) {
            return collect();
        }

        return InventoryReservation::query()
            ->whereHas(
                'salesOrderAllocationLine',
                static function (
                    $query,
                ) use (
                    $activeAllocation,
                ): void {
                    $query->where(
                        'sales_order_allocation_id',
                        $activeAllocation->getKey(),
                    );
                },
            )
            ->whereNotNull('active_key')
            ->whereIn(
                'status',
                [
                    'active',
                    'partially_consumed',
                ],
            )
            ->orderBy('id')
            ->get();
    }

    /**
     * @param list<int> $productIds
     *
     * @return Collection<int, InventoryBalance>
     */
    private function balances(
        SalesOrder $salesOrder,
        array $productIds,
    ): Collection {
        if (
            $salesOrder->warehouse_id === null
            || $productIds === []
        ) {
            return collect();
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
            ->get()
            ->keyBy(
                static fn (
                    InventoryBalance $balance,
                ): int => (int) $balance->product_id,
            );
    }

    private function decimal(
        BigDecimal $value,
    ): string {
        return $value->toScale(
            self::SCALE,
            RoundingMode::HALF_UP,
        )->__toString();
    }
}