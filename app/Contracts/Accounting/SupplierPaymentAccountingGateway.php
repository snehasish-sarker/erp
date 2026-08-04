<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\SupplierPayment;
use App\Models\User;
use DateTimeInterface;

/**
 * Integration boundary for Supplier Payment financial posting.
 *
 * A configured implementation must create the balanced General Ledger
 * journal, Supplier Ledger entry, payment open item, invoice allocations,
 * realized exchange differences, and source status atomically.
 *
 * Expected accounting treatment for allocated value:
 *
 * - Debit Accounts Payable Control.
 * - Credit the selected Cash or Bank account.
 * - Debit or credit realized exchange difference when required.
 *
 * Expected accounting treatment for unallocated value:
 *
 * - Debit Supplier Advances.
 * - Credit the selected Cash or Bank account.
 */
interface SupplierPaymentAccountingGateway
{
    public function post(
        SupplierPayment $supplierPayment,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string;

    public function reverse(
        SupplierPayment $supplierPayment,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string;
}