<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Customer;
use App\Models\CustomerOpenItem;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

final class CustomerBalanceService
{
    private const SCALE = 6;

    public function baseOutstanding(
        Customer $customer,
    ): string {
        $value = CustomerOpenItem::query()
            ->where(
                'customer_id',
                $customer->getKey(),
            )
            ->whereIn(
                'status',
                [
                    'open',
                    'partially_settled',
                ],
            )
            ->sum(
                'base_outstanding_amount',
            );

        return BigDecimal::of(
            (string) $value,
        )
            ->toScale(
                self::SCALE,
                RoundingMode::HALF_UP,
            )
            ->__toString();
    }
}