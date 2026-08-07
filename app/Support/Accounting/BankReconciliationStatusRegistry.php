<?php

declare(strict_types=1);

namespace App\Support\Accounting;

final class BankReconciliationStatusRegistry
{
    /** @return list<array{value: string, label: string}> */
    public function options(): array
    {
        return [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'completed', 'label' => 'Completed'],
            ['value' => 'reversed', 'label' => 'Reversed'],
        ];
    }

    public function label(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'completed' => 'Completed',
            'reversed' => 'Reversed',
            default => $status,
        };
    }
}
