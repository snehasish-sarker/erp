<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\SupplierInvoiceAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\SupplierInvoice;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

final class UnconfiguredSupplierInvoiceAccountingGateway implements SupplierInvoiceAccountingGateway
{
    public function post(
        SupplierInvoice $supplierInvoice,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string {
        throw ValidationException::withMessages([
            'accounting' => [
                'Supplier Invoice posting is blocked until the Accounts Payable and GRNI journal integration is configured.',
            ],
        ]);
    }

    public function reverse(
        SupplierInvoice $supplierInvoice,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string {
        throw ValidationException::withMessages([
            'accounting' => [
                'Supplier Invoice reversal is blocked until the Accounts Payable and GRNI journal integration is configured.',
            ],
        ]);
    }
}