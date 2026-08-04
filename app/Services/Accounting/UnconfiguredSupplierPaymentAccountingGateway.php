<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\SupplierPaymentAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\SupplierPayment;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

final class UnconfiguredSupplierPaymentAccountingGateway implements SupplierPaymentAccountingGateway
{
    public function post(
        SupplierPayment $supplierPayment,
        AccountingPeriod $accountingPeriod,
        User $actor,
    ): string {
        throw ValidationException::withMessages([
            'accounting' => [
                'Supplier Payment posting is blocked until the General Ledger, Accounts Payable allocation, and cash or bank posting integration is configured.',
            ],
        ]);
    }

    public function reverse(
        SupplierPayment $supplierPayment,
        AccountingPeriod $accountingPeriod,
        DateTimeInterface $reversalPostingDate,
        string $reason,
        User $actor,
    ): string {
        throw ValidationException::withMessages([
            'accounting' => [
                'Supplier Payment reversal is blocked until the General Ledger, Accounts Payable allocation, and cash or bank reversal integration is configured.',
            ],
        ]);
    }
}