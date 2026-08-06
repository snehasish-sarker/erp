<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use LogicException;

final class CustomerReceiptStatusRegistry
{
    /**
     * @var array<string, string>
     */
    private const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'posted' => 'Posted',
        'reversed' => 'Reversed',
        'cancelled' => 'Cancelled',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'draft' => [
            'submitted',
            'cancelled',
        ],

        'submitted' => [
            'draft',
            'approved',
            'cancelled',
        ],

        'approved' => [
            'posted',
            'cancelled',
        ],

        'posted' => [
            'reversed',
        ],

        'reversed' => [],
        'cancelled' => [],
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
                "Unsupported Customer Receipt status [{$status}].",
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

    /**
     * @return list<string>
     */
    public function allowedTransitionsFrom(
        string $status,
    ): array {
        $transitions = self::ALLOWED_TRANSITIONS[$status]
            ?? null;

        if (!is_array($transitions)) {
            throw new LogicException(
                "Unsupported Customer Receipt status [{$status}].",
            );
        }

        return $transitions;
    }

    public function canTransition(
        string $currentStatus,
        string $nextStatus,
    ): bool {
        if (
            !$this->exists($currentStatus)
            || !$this->exists($nextStatus)
        ) {
            return false;
        }

        return in_array(
            $nextStatus,
            $this->allowedTransitionsFrom(
                $currentStatus,
            ),
            true,
        );
    }

    public function isEditable(
        string $status,
    ): bool {
        return $status === 'draft';
    }

    public function canSubmit(
        string $status,
    ): bool {
        return $status === 'draft';
    }

    public function canReturnToDraft(
        string $status,
    ): bool {
        return $status === 'submitted';
    }

    public function canApprove(
        string $status,
    ): bool {
        return $status === 'submitted';
    }

    public function canPost(
        string $status,
    ): bool {
        return $status === 'approved';
    }

    public function canReverse(
        string $status,
    ): bool {
        return $status === 'posted';
    }

    public function canCancel(
        string $status,
    ): bool {
        return in_array(
            $status,
            [
                'draft',
                'submitted',
                'approved',
            ],
            true,
        );
    }

    public function isFinal(
        string $status,
    ): bool {
        return in_array(
            $status,
            [
                'reversed',
                'cancelled',
            ],
            true,
        );
    }
}