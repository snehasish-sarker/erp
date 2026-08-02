<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\AccountingPeriod;
use App\Models\SupplierInvoice;
use App\Models\User;
use DateTimeInterface;

/**
 * Integration boundary for the future Accounts Payable journal module.
 *
 * A configured implementation must post the supplier invoice and its
 * accounting reference atomically with the surrounding database transaction.
 *
 * Expected accounting treatment:
 *
 * - Debit Goods Received Not Invoiced.
 * - Debit Input Tax when applicable.
 * - Credit Accounts Payable.
 */
interface SupplierInvoiceAccountingGateway
{
    public function post(
        SupplierInvoice $supplierInvoice,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string;

    public function reverse(
        SupplierInvoice $supplierInvoice,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string;
}