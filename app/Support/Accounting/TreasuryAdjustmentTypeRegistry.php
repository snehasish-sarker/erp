<?php

declare(strict_types=1);

namespace App\Support\Accounting;

final class TreasuryAdjustmentTypeRegistry
{
    /** @var array<string, array{label: string, bank_direction: string}> */
    private const TYPES = [
        'bank_charge' => [
            'label' => 'Bank Charge',
            'bank_direction' => 'credit',
        ],
        'bank_interest' => [
            'label' => 'Bank Interest Income',
            'bank_direction' => 'debit',
        ],
        'other_debit' => [
            'label' => 'Other Bank Debit',
            'bank_direction' => 'credit',
        ],
        'other_credit' => [
            'label' => 'Other Bank Credit',
            'bank_direction' => 'debit',
        ],
    ];

    /** @return list<array{value: string, label: string, bank_direction: string}> */
    public function options(): array
    {
        $options = [];

        foreach (self::TYPES as $value => $configuration) {
            $options[] = [
                'value' => $value,
                'label' => $configuration['label'],
                'bank_direction' => $configuration['bank_direction'],
            ];
        }

        return $options;
    }

    public function label(string $type): string
    {
        return self::TYPES[$type]['label'] ?? $type;
    }

    public function bankDirection(string $type): string
    {
        return self::TYPES[$type]['bank_direction'] ?? '';
    }

    public function exists(string $type): bool
    {
        return array_key_exists($type, self::TYPES);
    }
}
