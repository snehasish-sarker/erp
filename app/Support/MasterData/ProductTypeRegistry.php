<?php

declare(strict_types=1);

namespace App\Support\MasterData;

use LogicException;

final class ProductTypeRegistry
{
    /**
     * @var array<string, string>
     */
    private const TYPES = [
        'stock' => 'Stock Item',
        'non_stock' => 'Non-stock Item',
        'service' => 'Service',
    ];

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::TYPES);
    }

    public function exists(string $productType): bool
    {
        return array_key_exists(
            $productType,
            self::TYPES,
        );
    }

    public function label(string $productType): string
    {
        $label = self::TYPES[$productType] ?? null;

        if (!is_string($label)) {
            throw new LogicException(
                "Unsupported product type [{$productType}].",
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