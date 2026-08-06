<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\InventoryBalance;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

final class InventoryBalanceObserver
{
    public function saving(
        InventoryBalance $inventoryBalance,
    ): void {
        $quantityOnHand = BigDecimal::of(
            (string) (
                $inventoryBalance->quantity_on_hand
                ?? '0'
            ),
        );

        $quantityReserved = BigDecimal::of(
            (string) (
                $inventoryBalance->quantity_reserved
                ?? '0'
            ),
        );

        if (
            $quantityReserved->isLessThan(
                BigDecimal::zero(),
            )
        ) {
            throw ValidationException::withMessages([
                'inventory' => [
                    'Reserved inventory quantity cannot be negative.',
                ],
            ]);
        }

        /*
         * Negative stock remains possible where an existing warehouse
         * configuration permits it, but stock cannot be reduced below
         * a positive quantity already reserved for customer orders.
         */
        if (
            $quantityReserved->isGreaterThan(
                BigDecimal::zero(),
            )
            && $quantityOnHand->isLessThan(
                $quantityReserved,
            )
        ) {
            throw ValidationException::withMessages([
                'inventory' => [
                    'This stock movement would reduce inventory below the quantity reserved for customer orders. Release or reduce the reservations first.',
                ],
            ]);
        }
    }
}