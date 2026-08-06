<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\CustomerReceipt;
use App\Models\User;
use DateTimeInterface;

/**
 * Integration boundary for Customer Receipt financial posting.
 *
 * A configured implementation must create the balanced General Ledger
 * journal, Customer Ledger entry, receipt open item, invoice allocations,
 * and realized exchange differences atomically inside the source workflow
 * transaction.
 *
 * Expected accounting treatment for allocated value:
 *
 * - Debit the selected Cash or Bank account.
 * - Credit Accounts Receivable Control.
 * - Debit or credit realized exchange difference when required.
 *
 * Expected accounting treatment for unallocated value:
 *
 * - Debit the selected Cash or Bank account.
 * - Credit Customer Advances.
 */
interface CustomerReceiptAccountingGateway
{
    public function post(
        CustomerReceipt $customerReceipt,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string;

    public function reverse(
        CustomerReceipt $customerReceipt,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string;
}