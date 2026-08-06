<?php

declare(strict_types=1);

namespace App\Support\Sales;

use LogicException;

final class SalesInvoiceStatusRegistry
{
    /**
     * @var array<string, string>
     */
    private const STATUSES = [
        'draft' => 'Draft',
        'posted' => 'Posted',
        'reversed' => 'Reversed',
    ];

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::STATUSES);
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

    public function label(string $status): string
    {
        $label = self::STATUSES[$status]
            ?? null;

        if (!is_string($label)) {
            throw new LogicException(
                "Unsupported sales invoice status [{$status}].",
            );
        }

        return $label;
    }
}