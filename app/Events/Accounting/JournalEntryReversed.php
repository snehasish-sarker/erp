<?php

declare(strict_types=1);

namespace App\Events\Accounting;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class JournalEntryReversed implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $originalJournalEntryId,
        public readonly int $reversalJournalEntryId,
        public readonly int $branchId,
        public readonly int $actorId,
    ) {
    }
}