<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\CustomerDispatch;
use App\Models\CustomerDispatchLine;
use App\Models\InventoryBalance;
use App\Models\InventoryReservation;
use App\Models\SalesOrderLine;
use App\Models\StockLedgerEntry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;
use LogicException;

final class DispatchInventoryPostingService
{
    private const SCALE = 6;

    public function postLine(
        CustomerDispatch $dispatch,
        CustomerDispatchLine $dispatchLine,
        SalesOrderLine $salesOrderLine,
        InventoryBalance $balance,
        InventoryReservation $reservation,
        User $actor,
        CarbonInterface $occurredAt,
    ): StockLedgerEntry {
        if (!$dispatchLine->isStockItem()) {
            throw new LogicException(
                'Only stock dispatch lines can create stock-ledger issues.',
            );
        }

        $postingKey = sprintf(
            'customer-dispatch:%d:line:%d:post',
            (int) $dispatch->getKey(),
            (int) $dispatchLine->getKey(),
        );

        $existing = StockLedgerEntry::query()
            ->where(
                'posting_key',
                $postingKey,
            )
            ->lockForUpdate()
            ->first();

        if (
            $existing
            instanceof StockLedgerEntry
        ) {
            return $existing;
        }

        $this->ensureMatchingContext(
            dispatch: $dispatch,
            dispatchLine: $dispatchLine,
            salesOrderLine: $salesOrderLine,
            balance: $balance,
            reservation: $reservation,
            actor: $actor,
        );

        $quantity = BigDecimal::of(
            (string) $dispatchLine
                ->dispatched_quantity,
        )->toScale(
            self::SCALE,
            RoundingMode::UNNECESSARY,
        );

        $currentQuantity = BigDecimal::of(
            (string) $balance
                ->quantity_on_hand,
        )->toScale(
            self::SCALE,
            RoundingMode::UNNECESSARY,
        );

        $currentReserved = BigDecimal::of(
            (string) $balance
                ->quantity_reserved,
        )->toScale(
            self::SCALE,
            RoundingMode::UNNECESSARY,
        );

        $currentValue = BigDecimal::of(
            (string) $balance
                ->inventory_value,
        )->toScale(
            self::SCALE,
            RoundingMode::UNNECESSARY,
        );

        if (
            $currentQuantity->isLessThan(
                $quantity,
            )
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    "Insufficient on-hand stock for {$dispatchLine->product_name}.",
                ],
            ]);
        }

        if (
            $currentReserved->isLessThan(
                $quantity,
            )
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    "Reserved stock for {$dispatchLine->product_name} is lower than the dispatch quantity.",
                ],
            ]);
        }

        $outstandingReservation =
            BigDecimal::of(
                $reservation
                    ->outstandingQuantity(),
            );

        if (
            $outstandingReservation
                ->isLessThan($quantity)
        ) {
            throw ValidationException::withMessages([
                'lines' => [
                    "The active reservation for {$dispatchLine->product_name} is lower than the dispatch quantity.",
                ],
            ]);
        }

        if (
            $quantity->isEqualTo(
                $currentQuantity,
            )
        ) {
            $totalCost = $currentValue;

            $unitCost = $quantity->isZero()
                ? BigDecimal::zero()
                    ->toScale(self::SCALE)
                : $currentValue->dividedBy(
                    $quantity,
                    self::SCALE,
                    RoundingMode::HALF_UP,
                );
        } else {
            $unitCost = BigDecimal::of(
                (string) $balance
                    ->average_unit_cost,
            )->toScale(
                self::SCALE,
                RoundingMode::UNNECESSARY,
            );

            $totalCost = $quantity
                ->multipliedBy($unitCost)
                ->toScale(
                    self::SCALE,
                    RoundingMode::HALF_UP,
                );

            if (
                $totalCost->isGreaterThan(
                    $currentValue,
                )
            ) {
                throw ValidationException::withMessages([
                    'inventory' => [
                        "Inventory value is insufficient to issue {$dispatchLine->product_name}.",
                    ],
                ]);
            }
        }

        $newQuantity = $currentQuantity
            ->minus($quantity)
            ->toScale(
                self::SCALE,
                RoundingMode::HALF_UP,
            );

        $newReserved = $currentReserved
            ->minus($quantity)
            ->toScale(
                self::SCALE,
                RoundingMode::HALF_UP,
            );

        $newValue = $currentValue
            ->minus($totalCost)
            ->toScale(
                self::SCALE,
                RoundingMode::HALF_UP,
            );

        if ($newQuantity->isZero()) {
            $newValue = BigDecimal::zero()
                ->toScale(self::SCALE);

            $newAverageCost =
                BigDecimal::zero()
                    ->toScale(self::SCALE);
        } else {
            if (
                $newValue->isLessThan(
                    BigDecimal::zero(),
                )
            ) {
                throw ValidationException::withMessages([
                    'inventory' => [
                        'The stock issue would create a negative inventory value.',
                    ],
                ]);
            }

            $newAverageCost =
                $newValue->dividedBy(
                    $newQuantity,
                    self::SCALE,
                    RoundingMode::HALF_UP,
                );
        }

        $balance->quantity_on_hand =
            $newQuantity->__toString();

        $balance->quantity_reserved =
            $newReserved->__toString();

        $balance->inventory_value =
            $newValue->__toString();

        $balance->average_unit_cost =
            $newAverageCost->__toString();

        $balance->version =
            (int) $balance->version + 1;

        $balance->save();

        $newConsumed = BigDecimal::of(
            (string) $reservation
                ->consumed_quantity,
        )
            ->plus($quantity)
            ->toScale(
                self::SCALE,
                RoundingMode::HALF_UP,
            );

        $reservation->consumed_quantity =
            $newConsumed->__toString();

        $remaining = BigDecimal::of(
            (string) $reservation
                ->reserved_quantity,
        )
            ->minus($newConsumed)
            ->minus(
                BigDecimal::of(
                    (string) $reservation
                        ->released_quantity,
                ),
            );

        if ($remaining->isZero()) {
            $reservation->status =
                'consumed';

            $reservation->active_key =
                null;
        } else {
            $reservation->status =
                'partially_consumed';
        }

        $reservation->save();

        return StockLedgerEntry::query()
            ->create([
                'branch_id' =>
                    $dispatch->branch_id,

                'warehouse_id' =>
                    $dispatch->warehouse_id,

                'product_id' =>
                    $dispatchLine->product_id,

                'unit_id' =>
                    $dispatchLine->unit_id,

                'movement_type' =>
                    'dispatch',

                'posting_key' =>
                    $postingKey,

                'source_type' =>
                    CustomerDispatch::class,

                'source_id' =>
                    $dispatch->getKey(),

                'source_line_id' =>
                    $dispatchLine->getKey(),

                'document_number' =>
                    $dispatch->dispatch_number,

                'occurred_at' =>
                    $occurredAt,

                'quantity_in' =>
                    '0.000000',

                'quantity_out' =>
                    $quantity->__toString(),

                'unit_cost' =>
                    $unitCost->__toString(),

                'total_cost' =>
                    $totalCost->__toString(),

                'balance_quantity' =>
                    $newQuantity->__toString(),

                'balance_value' =>
                    $newValue->__toString(),

                'created_by_user_id' =>
                    $actor->getKey(),

                'reversal_of_id' =>
                    null,
            ]);
    }

    public function reverseLine(
        CustomerDispatch $dispatch,
        CustomerDispatchLine $dispatchLine,
        SalesOrderLine $salesOrderLine,
        InventoryBalance $balance,
        InventoryReservation $reservation,
        User $actor,
        CarbonInterface $occurredAt,
    ): StockLedgerEntry {
        if (!$dispatchLine->isStockItem()) {
            throw new LogicException(
                'Only stock dispatch lines can reverse stock-ledger issues.',
            );
        }

        $originalPostingKey = sprintf(
            'customer-dispatch:%d:line:%d:post',
            (int) $dispatch->getKey(),
            (int) $dispatchLine->getKey(),
        );

        $reversalPostingKey = sprintf(
            'customer-dispatch:%d:line:%d:reverse',
            (int) $dispatch->getKey(),
            (int) $dispatchLine->getKey(),
        );

        $original = StockLedgerEntry::query()
            ->where(
                'posting_key',
                $originalPostingKey,
            )
            ->lockForUpdate()
            ->first();

        if (
            !$original
            instanceof StockLedgerEntry
        ) {
            throw new LogicException(
                'The original dispatch stock-ledger entry was not found.',
            );
        }

        $existing = StockLedgerEntry::query()
            ->where(
                'posting_key',
                $reversalPostingKey,
            )
            ->lockForUpdate()
            ->first();

        if (
            $existing
            instanceof StockLedgerEntry
        ) {
            return $existing;
        }

        $this->ensureMatchingContext(
            dispatch: $dispatch,
            dispatchLine: $dispatchLine,
            salesOrderLine: $salesOrderLine,
            balance: $balance,
            reservation: $reservation,
            actor: $actor,
        );

        if (
            $original->source_type
                !== CustomerDispatch::class
            || (int) $original->source_id
                !== (int) $dispatch->getKey()
            || (int) $original->source_line_id
                !== (int) $dispatchLine->getKey()
            || $original->movement_type
                !== 'dispatch'
        ) {
            throw new LogicException(
                'The original stock-ledger entry does not match the dispatch line.',
            );
        }

        $blockingLaterEntry =
            StockLedgerEntry::query()
                ->where(
                    'warehouse_id',
                    $dispatch->warehouse_id,
                )
                ->where(
                    'product_id',
                    $dispatchLine->product_id,
                )
                ->where(
                    'id',
                    '>',
                    $original->getKey(),
                )
                ->where(
                    static function (
                        $query,
                    ) use (
                        $dispatch,
                    ): void {
                        $query
                            ->where(
                                'source_type',
                                '!=',
                                CustomerDispatch::class,
                            )
                            ->orWhere(
                                'source_id',
                                '!=',
                                $dispatch->getKey(),
                            );
                    },
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

        if (
            $blockingLaterEntry
            instanceof StockLedgerEntry
        ) {
            throw ValidationException::withMessages([
                'dispatch' => [
                    "Dispatch {$dispatch->dispatch_number} cannot be reversed because a later stock movement exists for {$dispatchLine->product_name}. Reverse the later movement first.",
                ],
            ]);
        }

        $quantity = BigDecimal::of(
            (string) $original->quantity_out,
        )->toScale(
            self::SCALE,
            RoundingMode::UNNECESSARY,
        );

        $unitCost = BigDecimal::of(
            (string) $original->unit_cost,
        )->toScale(
            self::SCALE,
            RoundingMode::UNNECESSARY,
        );

        $totalCost = BigDecimal::of(
            (string) $original->total_cost,
        )->toScale(
            self::SCALE,
            RoundingMode::UNNECESSARY,
        );

        $currentQuantity = BigDecimal::of(
            (string) $balance
                ->quantity_on_hand,
        )->toScale(
            self::SCALE,
            RoundingMode::UNNECESSARY,
        );

        $currentReserved = BigDecimal::of(
            (string) $balance
                ->quantity_reserved,
        )->toScale(
            self::SCALE,
            RoundingMode::UNNECESSARY,
        );

        $currentValue = BigDecimal::of(
            (string) $balance
                ->inventory_value,
        )->toScale(
            self::SCALE,
            RoundingMode::UNNECESSARY,
        );

        $postedBalanceQuantity =
            BigDecimal::of(
                (string) $original
                    ->balance_quantity,
            )->toScale(
                self::SCALE,
                RoundingMode::UNNECESSARY,
            );

        $postedBalanceValue =
            BigDecimal::of(
                (string) $original
                    ->balance_value,
            )->toScale(
                self::SCALE,
                RoundingMode::UNNECESSARY,
            );

        if (
            !$currentQuantity->isEqualTo(
                $postedBalanceQuantity,
            )
            || !$currentValue->isEqualTo(
                $postedBalanceValue,
            )
        ) {
            throw ValidationException::withMessages([
                'dispatch' => [
                    'The dispatch cannot be reversed because the inventory balance no longer matches its posted balance.',
                ],
            ]);
        }

        $consumed = BigDecimal::of(
            (string) $reservation
                ->consumed_quantity,
        );

        if (
            $consumed->isLessThan(
                $quantity,
            )
        ) {
            throw new LogicException(
                'The reservation consumed quantity is lower than the dispatch issue quantity.',
            );
        }

        $newQuantity = $currentQuantity
            ->plus($quantity)
            ->toScale(
                self::SCALE,
                RoundingMode::HALF_UP,
            );

        $newReserved = $currentReserved
            ->plus($quantity)
            ->toScale(
                self::SCALE,
                RoundingMode::HALF_UP,
            );

        $newValue = $currentValue
            ->plus($totalCost)
            ->toScale(
                self::SCALE,
                RoundingMode::HALF_UP,
            );

        $newAverageCost = $newQuantity
            ->isZero()
                ? BigDecimal::zero()
                    ->toScale(self::SCALE)
                : $newValue->dividedBy(
                    $newQuantity,
                    self::SCALE,
                    RoundingMode::HALF_UP,
                );

        $balance->quantity_on_hand =
            $newQuantity->__toString();

        $balance->quantity_reserved =
            $newReserved->__toString();

        $balance->inventory_value =
            $newValue->__toString();

        $balance->average_unit_cost =
            $newAverageCost->__toString();

        $balance->version =
            (int) $balance->version + 1;

        $balance->save();

        $newConsumed = $consumed
            ->minus($quantity)
            ->toScale(
                self::SCALE,
                RoundingMode::HALF_UP,
            );

        $reservation->consumed_quantity =
            $newConsumed->__toString();

        $reservation->active_key =
            $reservation->reservation_key;

        $reservation->status =
            $newConsumed->isZero()
                ? 'active'
                : 'partially_consumed';

        $reservation->save();

        return StockLedgerEntry::query()
            ->create([
                'branch_id' =>
                    $dispatch->branch_id,

                'warehouse_id' =>
                    $dispatch->warehouse_id,

                'product_id' =>
                    $dispatchLine->product_id,

                'unit_id' =>
                    $dispatchLine->unit_id,

                'movement_type' =>
                    'dispatch_reversal',

                'posting_key' =>
                    $reversalPostingKey,

                'source_type' =>
                    CustomerDispatch::class,

                'source_id' =>
                    $dispatch->getKey(),

                'source_line_id' =>
                    $dispatchLine->getKey(),

                'document_number' =>
                    $dispatch->dispatch_number,

                'occurred_at' =>
                    $occurredAt,

                'quantity_in' =>
                    $quantity->__toString(),

                'quantity_out' =>
                    '0.000000',

                'unit_cost' =>
                    $unitCost->__toString(),

                'total_cost' =>
                    $totalCost->__toString(),

                'balance_quantity' =>
                    $newQuantity->__toString(),

                'balance_value' =>
                    $newValue->__toString(),

                'created_by_user_id' =>
                    $actor->getKey(),

                'reversal_of_id' =>
                    $original->getKey(),
            ]);
    }

    private function ensureMatchingContext(
        CustomerDispatch $dispatch,
        CustomerDispatchLine $dispatchLine,
        SalesOrderLine $salesOrderLine,
        InventoryBalance $balance,
        InventoryReservation $reservation,
        User $actor,
    ): void {
        $tenantId = (int) $dispatch
            ->tenant_id;

        if (
            (int) $dispatchLine->tenant_id
                !== $tenantId
            || (int) $salesOrderLine->tenant_id
                !== $tenantId
            || (int) $balance->tenant_id
                !== $tenantId
            || (int) $reservation->tenant_id
                !== $tenantId
            || (int) $actor->tenant_id
                !== $tenantId
        ) {
            throw new LogicException(
                'Dispatch inventory posting crossed a tenant boundary.',
            );
        }

        if (
            (int) $dispatch->branch_id
                !== (int) $balance->branch_id
            || (int) $dispatch->warehouse_id
                !== (int) $balance->warehouse_id
            || (int) $dispatchLine->product_id
                !== (int) $balance->product_id
            || (int) $dispatchLine->unit_id
                !== (int) $balance->unit_id
        ) {
            throw new LogicException(
                'The dispatch line does not match the locked inventory balance.',
            );
        }

        if (
            (int) $reservation->warehouse_id
                !== (int) $dispatch->warehouse_id
            || (int) $reservation->product_id
                !== (int) $dispatchLine->product_id
            || (int) $reservation->unit_id
                !== (int) $dispatchLine->unit_id
            || (int) $reservation->source_line_id
                !== (int) $salesOrderLine->getKey()
        ) {
            throw new LogicException(
                'The dispatch line does not match its inventory reservation.',
            );
        }
    }
}