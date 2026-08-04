<?php

declare(strict_types=1);

namespace App\Events\Accounting;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class JournalEntryPosted implements ShouldDispatchAfterCommit
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $journalEntryId,
        public readonly int $branchId,
        public readonly string $journalType,
        public readonly int $actorId,
    ) {
    }
}