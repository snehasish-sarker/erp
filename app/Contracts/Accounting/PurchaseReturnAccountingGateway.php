<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\PurchaseReturn;
use App\Models\User;
use DateTimeInterface;

/**
 * Integration boundary for Purchase Return General Ledger posting.
 *
 * A configured implementation must post or reverse the balanced General
 * Ledger journal inside the same database transaction that updates inventory
 * and the Purchase Return workflow status.
 */
interface PurchaseReturnAccountingGateway
{
    public function post(
        PurchaseReturn $purchaseReturn,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): ?string;

    public function reverse(
        PurchaseReturn $purchaseReturn,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): ?string;
}