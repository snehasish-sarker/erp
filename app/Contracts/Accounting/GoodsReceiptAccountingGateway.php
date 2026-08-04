<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\GoodsReceipt;
use App\Models\User;
use DateTimeInterface;

/**
 * Integration boundary for Goods Receipt General Ledger posting.
 *
 * A configured implementation must post or reverse the balanced General
 * Ledger journal inside the same database transaction that updates inventory
 * and the Goods Receipt workflow status.
 */
interface GoodsReceiptAccountingGateway
{
    public function post(
        GoodsReceipt $goodsReceipt,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): ?string;

    public function reverse(
        GoodsReceipt $goodsReceipt,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): ?string;
}