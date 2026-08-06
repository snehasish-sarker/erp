<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\InventoryReservation;
use Brick\Math\BigDecimal;
use Illuminate\Validation\ValidationException;

final class InventoryReservationObserver
{
    public function saving(
        InventoryReservation $reservation,
    ): void {
        $reserved = BigDecimal::of(
            (string) (
                $reservation->reserved_quantity
                ?? '0'
            ),
        );

        $consumed = BigDecimal::of(
            (string) (
                $reservation->consumed_quantity
                ?? '0'
            ),
        );

        $released = BigDecimal::of(
            (string) (
                $reservation->released_quantity
                ?? '0'
            ),
        );

        if (
            $reserved->isLessThan(
                BigDecimal::zero(),
            )
            || $consumed->isLessThan(
                BigDecimal::zero(),
            )
            || $released->isLessThan(
                BigDecimal::zero(),
            )
        ) {
            throw ValidationException::withMessages([
                'reservation' => [
                    'Inventory reservation quantities cannot be negative.',
                ],
            ]);
        }

        if (
            $consumed
                ->plus($released)
                ->isGreaterThan($reserved)
        ) {
            throw ValidationException::withMessages([
                'reservation' => [
                    'Consumed and released quantities cannot exceed the reserved quantity.',
                ],
            ]);
        }

        $isOpenStatus = in_array(
            $reservation->status,
            [
                'active',
                'partially_consumed',
            ],
            true,
        );

        if (
            $isOpenStatus
            && $reservation->active_key === null
        ) {
            throw ValidationException::withMessages([
                'reservation' => [
                    'An open inventory reservation must have an active key.',
                ],
            ]);
        }

        if (
            !$isOpenStatus
            && $reservation->active_key !== null
        ) {
            throw ValidationException::withMessages([
                'reservation' => [
                    'A closed inventory reservation cannot retain an active key.',
                ],
            ]);
        }
    }
}