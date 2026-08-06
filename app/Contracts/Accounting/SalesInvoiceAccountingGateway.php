<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\SalesInvoice;
use App\Models\User;
use DateTimeInterface;

interface SalesInvoiceAccountingGateway
{
    public function post(
        SalesInvoice $salesInvoice,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string;

    public function reverse(
        SalesInvoice $salesInvoice,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string;
}