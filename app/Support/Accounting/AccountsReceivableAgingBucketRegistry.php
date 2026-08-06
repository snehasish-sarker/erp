<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use LogicException;

final class AccountsReceivableAgingBucketRegistry
{
    /**
     * @var array<string, array{
     *     label: string,
     *     minimum_days: int|null,
     *     maximum_days: int|null
     * }>
     */
    private const BUCKETS = [
        'current' => [
            'label' => 'Current',
            'minimum_days' => null,
            'maximum_days' => 0,
        ],
        'days_1_30' => [
            'label' => '1–30 Days',
            'minimum_days' => 1,
            'maximum_days' => 30,
        ],
        'days_31_60' => [
            'label' => '31–60 Days',
            'minimum_days' => 31,
            'maximum_days' => 60,
        ],
        'days_61_90' => [
            'label' => '61–90 Days',
            'minimum_days' => 61,
            'maximum_days' => 90,
        ],
        'days_91_120' => [
            'label' => '91–120 Days',
            'minimum_days' => 91,
            'maximum_days' => 120,
        ],
        'days_over_120' => [
            'label' => 'Over 120 Days',
            'minimum_days' => 121,
            'maximum_days' => null,
        ],
    ];

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys(self::BUCKETS);
    }

    public function exists(string $bucket): bool
    {
        return array_key_exists($bucket, self::BUCKETS);
    }

    public function label(string $bucket): string
    {
        $configuration = self::BUCKETS[$bucket]
            ?? null;

        if (!is_array($configuration)) {
            throw new LogicException(
                "Unsupported Accounts Receivable aging bucket [{$bucket}].",
            );
        }

        return $configuration['label'];
    }

    public function bucketForDaysOverdue(int $daysOverdue): string
    {
        if ($daysOverdue <= 0) {
            return 'current';
        }

        if ($daysOverdue <= 30) {
            return 'days_1_30';
        }

        if ($daysOverdue <= 60) {
            return 'days_31_60';
        }

        if ($daysOverdue <= 90) {
            return 'days_61_90';
        }

        if ($daysOverdue <= 120) {
            return 'days_91_120';
        }

        return 'days_over_120';
    }

    /**
     * @return list<array{
     *     value: string,
     *     label: string,
     *     minimum_days: int|null,
     *     maximum_days: int|null
     * }>
     */
    public function options(): array
    {
        $options = [];

        foreach (self::BUCKETS as $value => $configuration) {
            $options[] = [
                'value' => $value,
                'label' => $configuration['label'],
                'minimum_days' => $configuration['minimum_days'],
                'maximum_days' => $configuration['maximum_days'],
            ];
        }

        return $options;
    }
}