<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\TreasuryAdjustment;
use App\Models\User;
use DateTimeInterface;

interface TreasuryAdjustmentAccountingGateway
{
    public function post(
        TreasuryAdjustment $adjustment,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string;

    public function reverse(
        TreasuryAdjustment $adjustment,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string;
}
