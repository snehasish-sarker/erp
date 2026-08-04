<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\SupplierDebitNote;
use App\Models\User;
use DateTimeInterface;

/**
 * Integration boundary for Supplier Debit Note financial posting.
 *
 * A configured implementation must create the balanced general-ledger
 * journal and the Accounts Payable supplier-subledger records atomically.
 *
 * Expected accounting treatment:
 *
 * - Debit Accounts Payable.
 * - Credit Purchase Returns, inventory recovery, tax, or the configured
 *   return-clearing accounts according to the final chart-of-accounts rules.
 */
interface SupplierDebitNoteAccountingGateway
{
    public function post(
        SupplierDebitNote $supplierDebitNote,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string;

    public function reverse(
        SupplierDebitNote $supplierDebitNote,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string;
}