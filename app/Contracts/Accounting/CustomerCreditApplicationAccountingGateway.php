<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\CustomerCreditApplication;
use App\Models\User;
use DateTimeInterface;

interface CustomerCreditApplicationAccountingGateway
{
    public function post(CustomerCreditApplication $application, AccountingPeriod $period, User $actor): string;
    public function reverse(CustomerCreditApplication $application, AccountingPeriod $period, DateTimeInterface $reversalPostingDate, string $reason, User $actor,): string;
}