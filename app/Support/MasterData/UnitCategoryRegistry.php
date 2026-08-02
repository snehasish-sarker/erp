<?php

declare(strict_types=1);

namespace App\Support\MasterData;

use LogicException;

final class UnitCategoryRegistry
{
    /**
     * @var array<string, string>
     */
    private const CATEGORIES = [
        'count' => 'Count',
        'weight' => 'Weight',
        'length' => 'Length',
        'volume' => 'Volume',
        'area' => 'Area',
        'time' => 'Time',
        'other' => 'Other',
    ];

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(
            self::CATEGORIES,
        );
    }

    public function exists(string $category): bool
    {
        return array_key_exists(
            $category,
            self::CATEGORIES,
        );
    }

    public function label(string $category): string
    {
        $label = self::CATEGORIES[$category] ?? null;

        if (!is_string($label)) {
            throw new LogicException(
                "Unsupported unit category [{$category}].",
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
            self::CATEGORIES
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