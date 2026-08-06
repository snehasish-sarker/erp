<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\CustomerDispatch;
use App\Models\User;
use DateTimeInterface;

interface CustomerDispatchAccountingGateway
{
    public function post(
        CustomerDispatch $customerDispatch,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string;

    public function reverse(
        CustomerDispatch $customerDispatch,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string;
}