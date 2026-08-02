<?php

declare(strict_types=1);

namespace App\Support\MasterData;

use LogicException;

final class SupplierTypeRegistry
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

    public function exists(string $supplierType): bool
    {
        return array_key_exists(
            $supplierType,
            self::TYPES,
        );
    }

    public function label(string $supplierType): string
    {
        $label = self::TYPES[$supplierType] ?? null;

        if (!is_string($label)) {
            throw new LogicException(
                "Unsupported supplier type [{$supplierType}].",
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