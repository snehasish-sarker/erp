<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\CustomerCreditNote;
use App\Models\User;
use DateTimeInterface;

interface CustomerCreditNoteAccountingGateway
{
    /**
     * @return array{
     *     accounting_reference: string,
     *     inventory_reference: string|null
     * }
     */
    public function post(
        CustomerCreditNote $creditNote,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): array;

    /**
     * @return array{
     *     accounting_reference: string,
     *     inventory_reference: string|null
     * }
     */
    public function reverse(
        CustomerCreditNote $creditNote,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): array;
}