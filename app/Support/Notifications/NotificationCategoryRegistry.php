<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use LogicException;

final class NotificationCategoryRegistry
{
    /**
     * @var array<string, string>
     */
    private const CATEGORIES = [
        'system' => 'System',
        'security' => 'Security',
        'approval' => 'Approval',
        'procurement' => 'Procurement',
        'inventory' => 'Inventory',
        'sales' => 'Sales',
        'accounting' => 'Accounting',
        'export' => 'Export',
    ];

    /**
     * @var list<string>
     */
    private const SEVERITIES = [
        'info',
        'success',
        'warning',
        'error',
    ];

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::CATEGORIES);
    }

    /**
     * @return list<string>
     */
    public function severityKeys(): array
    {
        return self::SEVERITIES;
    }

    public function categoryExists(
        string $category,
    ): bool {
        return array_key_exists(
            $category,
            self::CATEGORIES,
        );
    }

    public function severityExists(
        string $severity,
    ): bool {
        return in_array(
            $severity,
            self::SEVERITIES,
            true,
        );
    }

    public function label(string $category): string
    {
        $label = self::CATEGORIES[$category] ?? null;

        if (!is_string($label)) {
            throw new LogicException(
                "Unsupported notification category [{$category}].",
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