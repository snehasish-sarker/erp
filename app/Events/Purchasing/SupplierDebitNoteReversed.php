<?php

declare(strict_types=1);

namespace App\Events\Purchasing;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SupplierDebitNoteReversed implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $supplierDebitNoteId,
        public readonly int $purchaseReturnId,
        public readonly ?int $supplierInvoiceId,
        public readonly int $actorId,
    ) {
    }
}