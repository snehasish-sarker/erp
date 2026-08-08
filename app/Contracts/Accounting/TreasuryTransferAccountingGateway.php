<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\TreasuryTransfer;
use App\Models\User;
use DateTimeInterface;

interface TreasuryTransferAccountingGateway
{
    public function post(
        TreasuryTransfer $transfer,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string;

    public function reverse(
        TreasuryTransfer $transfer,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string;
}