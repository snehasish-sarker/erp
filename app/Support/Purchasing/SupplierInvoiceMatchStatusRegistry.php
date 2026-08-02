<?php

declare(strict_types=1);

namespace App\Support\Purchasing;

use LogicException;

final class SupplierInvoiceMatchStatusRegistry
{
    /**
     * @var array<string, string>
     */
    private const STATUSES = [
        'unmatched' => 'Unmatched',
        'matched' => 'Matched',
        'variance' => 'Variance',
        'blocked' => 'Blocked',
    ];

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::STATUSES);
    }

    public function exists(string $status): bool
    {
        return array_key_exists(
            $status,
            self::STATUSES,
        );
    }

    public function label(string $status): string
    {
        $label = self::STATUSES[$status] ?? null;

        if (!is_string($label)) {
            throw new LogicException(
                "Unsupported supplier invoice match status [{$status}].",
            );
        }

        return $label;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function options(): array
    {
        $options = [];

        foreach (self::STATUSES as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $options;
    }

    public function allowsValidation(string $status): bool
    {
        return in_array(
            $status,
            [
                'matched',
                'variance',
            ],
            true,
        );
    }

    public function isBlocked(string $status): bool
    {
        return $status === 'blocked';
    }

    /**
     * @param list<string> $lineStatuses
     */
    public function summarize(array $lineStatuses): string
    {
        if ($lineStatuses === []) {
            return 'unmatched';
        }

        if (in_array('blocked', $lineStatuses, true)) {
            return 'blocked';
        }

        if (in_array('unmatched', $lineStatuses, true)) {
            return 'unmatched';
        }

        if (in_array('variance', $lineStatuses, true)) {
            return 'variance';
        }

        return 'matched';
    }
}