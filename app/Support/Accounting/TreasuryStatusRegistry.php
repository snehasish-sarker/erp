<?php

declare(strict_types=1);

namespace App\Support\Accounting;

final class TreasuryStatusRegistry
{
    /** @var array<string, string> */
    private const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'posted' => 'Posted',
        'reversed' => 'Reversed',
        'cancelled' => 'Cancelled',
    ];

    /** @return list<array{value: string, label: string}> */
    public function options(): array
    {
        return array_map(
            static fn (string $value, string $label): array => [
                'value' => $value,
                'label' => $label,
            ],
            array_keys(self::STATUSES),
            array_values(self::STATUSES),
        );
    }

    public function label(string $status): string
    {
        return self::STATUSES[$status] ?? $status;
    }
}
