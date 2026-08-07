<?php

declare(strict_types=1);

namespace App\Support\Accounting;

final class TreasuryTransferTypeRegistry
{
    /** @var array<string, string> */
    private const TYPES = [
        'cash_to_cash' => 'Cash to Cash',
        'cash_to_bank' => 'Cash to Bank',
        'bank_to_cash' => 'Bank to Cash',
        'bank_to_bank' => 'Bank to Bank',
    ];

    /** @return list<array{value: string, label: string}> */
    public function options(): array
    {
        $options = [];

        foreach (self::TYPES as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }

        return $options;
    }

    public function label(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    public function expectedType(string $sourceControl, string $destinationControl): string
    {
        return sprintf('%s_to_%s', $sourceControl, $destinationControl);
    }
}
