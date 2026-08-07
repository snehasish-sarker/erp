<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\CustomerArAdjustment;
use App\Models\User;
use DateTimeInterface;

interface CustomerArAdjustmentAccountingGateway
{
    public function post(CustomerArAdjustment $adjustment, AccountingPeriod $period, User $actor): string;
    public function reverse(CustomerArAdjustment $adjustment, AccountingPeriod $period, DateTimeInterface $reversalPostingDate, string $reason, User $actor,): string;
}