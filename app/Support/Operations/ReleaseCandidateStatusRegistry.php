<?php

declare(strict_types=1);

namespace App\Support\Operations;

final class ReleaseCandidateStatusRegistry
{
    public const FROZEN = 'frozen';
    public const SUPERSEDED = 'superseded';

    public const VERIFICATION_MATCHED = 'matched';
    public const VERIFICATION_DRIFTED = 'drifted';

    /** @return list<string> */
    public function statuses(): array
    {
        return [self::FROZEN, self::SUPERSEDED];
    }

    /** @return list<string> */
    public function verificationStatuses(): array
    {
        return [self::VERIFICATION_MATCHED, self::VERIFICATION_DRIFTED];
    }
}
