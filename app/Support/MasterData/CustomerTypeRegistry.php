<?php

declare(strict_types=1);

namespace App\Support\MasterData;

use LogicException;

final class CustomerTypeRegistry
{
    /**
     * @var array<string, string>
     */
    private const TYPES = [
        'company' => 'Company',
        'individual' => 'Individual',
        'government' => 'Government',
        'other' => 'Other',
    ];

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::TYPES);
    }

    public function exists(string $customerType): bool
    {
        return array_key_exists(
            $customerType,
            self::TYPES,
        );
    }

    public function label(string $customerType): string
    {
        $label = self::TYPES[$customerType] ?? null;

        if (!is_string($label)) {
            throw new LogicException(
                "Unsupported customer type [{$customerType}].",
            );
        }

        return $label;
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string
     * }>
     */
    public function options(): array
    {
        $options = [];

        foreach (
            self::TYPES
            as $value => $label
        ) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }
}