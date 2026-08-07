<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\CustomerRefund;
use App\Models\User;
use DateTimeInterface;

interface CustomerRefundAccountingGateway
{
    public function post(CustomerRefund $refund, AccountingPeriod $period, User $actor): string;
    public function reverse(CustomerRefund $refund, AccountingPeriod $period, DateTimeInterface $reversalPostingDate, string $reason, User $actor,): string;
}