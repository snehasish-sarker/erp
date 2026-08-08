<?php

declare(strict_types=1);

namespace App\Support\Management;

final class ManagementBudgetStatusRegistry
{
    /** @return list<array{value: string, label: string}> */
    public function options(): array
    {
        return [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'approved', 'label' => 'Approved'],
        ];
    }

    public function label(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'approved' => 'Approved',
            default => $status,
        };
    }
}