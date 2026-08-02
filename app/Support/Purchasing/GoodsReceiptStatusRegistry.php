<?php

declare(strict_types=1);

namespace App\Support\Purchasing;

use LogicException;

final class GoodsReceiptStatusRegistry
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
                "Unsupported goods receipt status [{$status}].",
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

    public function isEditable(string $status): bool
    {
        return $status === 'draft';
    }

    public function canPost(string $status): bool
    {
        return $status === 'draft';
    }

    public function canReverse(string $status): bool
    {
        return $status === 'posted';
    }

    public function isFinal(string $status): bool
    {
        return $status === 'reversed';
    }
}