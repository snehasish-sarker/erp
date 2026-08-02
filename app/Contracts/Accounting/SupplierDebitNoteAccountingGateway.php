<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\SupplierDebitNote;
use App\Models\User;
use DateTimeInterface;

interface SupplierDebitNoteAccountingGateway
{
    public function post(
        SupplierDebitNote $supplierDebitNote,
        User $actor,
    ): string;

    public function reverse(
        SupplierDebitNote $supplierDebitNote,
        DateTimeInterface|string $reversalPostingDate,
        string $reason,
        User $actor,
    ): string;
}