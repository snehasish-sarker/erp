<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use LogicException;

final class SupplierPaymentAllocationStatusRegistry
{
    /**
     * @var array<string, string>
     */
    private const STATUSES = [
        'draft' => 'Draft',
        'applied' => 'Applied',
        'reversed' => 'Reversed',
        'cancelled' => 'Cancelled',
    ];

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(
            self::STATUSES,
        );
    }

    public function exists(
        string $status,
    ): bool {
        return array_key_exists(
            $status,
            self::STATUSES,
        );
    }

    public function label(
        string $status,
    ): string {
        $label = self::STATUSES[$status]
            ?? null;

        if (!is_string($label)) {
            throw new LogicException(
                "Unsupported Supplier Payment allocation status [{$status}].",
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
            self::STATUSES
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