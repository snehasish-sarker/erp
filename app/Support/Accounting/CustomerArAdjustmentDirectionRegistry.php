<?php

declare(strict_types=1);

namespace App\Support\Accounting;

final class CustomerArAdjustmentDirectionRegistry
{
    /** @var array<string, string> */
    private const DIRECTIONS = ['debit' => 'Debit Adjustment', 'credit' => 'Credit Adjustment',];
    /** @return list<string> */
    public function keys(): array
    {
        return array_keys(self::DIRECTIONS);
    }

    public function exists(string $direction): bool
    {
        return array_key_exists($direction, self::DIRECTIONS);
    }

    public function label(string $direction): string
    {
        return self::DIRECTIONS[$direction] ?? $direction;
    }
    /** @return list<array{value: string, label: string}> */
    public function options(): array
    {
        return array_map(static fn(string $value, string $label): array => ['value' => $value, 'label' => $label], array_keys(self::DIRECTIONS), array_values(self::DIRECTIONS),);
    }
}