<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\SupplierDebitNoteAccountingGateway;
use App\Models\SupplierDebitNote;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

final class UnconfiguredSupplierDebitNoteAccountingGateway implements SupplierDebitNoteAccountingGateway
{
    public function post(
        SupplierDebitNote $supplierDebitNote,
        User $actor,
    ): string {
        throw ValidationException::withMessages([
            'accounting' => [
                'Supplier Debit Note posting is unavailable until the Accounts Payable and journal-entry module is configured.',
            ],
        ]);
    }

    public function reverse(
        SupplierDebitNote $supplierDebitNote,
        DateTimeInterface|string $reversalPostingDate,
        string $reason,
        User $actor,
    ): string {
        throw ValidationException::withMessages([
            'accounting' => [
                'Supplier Debit Note reversal is unavailable until the Accounts Payable and journal-entry module is configured.',
            ],
        ]);
    }
}