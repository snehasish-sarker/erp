<?php

declare(strict_types=1);

namespace App\Support\Purchasing;

use LogicException;

final class GoodsReceiptInspectionStatusRegistry
{
    /**
     * @var array<string, string>
     */
    private const STATUSES = [
        'not_required' => 'Not Required',
        'pending' => 'Pending',
        'passed' => 'Passed',
        'partial' => 'Partially Accepted',
        'failed' => 'Failed',
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
                "Unsupported goods receipt inspection status [{$status}].",
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

    public function allowsPosting(
        string $status,
    ): bool {
        return in_array(
            $status,
            [
                'not_required',
                'passed',
                'partial',
            ],
            true,
        );
    }
}