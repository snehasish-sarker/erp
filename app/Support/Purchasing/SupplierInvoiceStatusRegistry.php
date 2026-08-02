<?php

declare(strict_types=1);

namespace App\Support\Purchasing;

use LogicException;

final class SupplierInvoiceStatusRegistry
{
    /**
     * @var array<string, string>
     */
    private const STATUSES = [
        'draft' => 'Draft',
        'validated' => 'Validated',
        'approved' => 'Approved',
        'posted' => 'Posted',
        'disputed' => 'Disputed',
        'reversed' => 'Reversed',
        'cancelled' => 'Cancelled',
    ];

    /**
     * @var array<string, list<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        'draft' => [
            'validated',
            'cancelled',
        ],
        'validated' => [
            'draft',
            'approved',
            'disputed',
            'cancelled',
        ],
        'approved' => [
            'posted',
            'disputed',
            'cancelled',
        ],
        'posted' => [
            'reversed',
        ],
        'disputed' => [
            'draft',
            'validated',
            'cancelled',
        ],
        'reversed' => [],
        'cancelled' => [],
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
                "Unsupported supplier invoice status [{$status}].",
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

    /**
     * @return list<string>
     */
    public function allowedTransitionsFrom(
        string $currentStatus,
    ): array {
        $transitions = self::ALLOWED_TRANSITIONS[
            $currentStatus
        ] ?? null;

        if (!is_array($transitions)) {
            throw new LogicException(
                "Unsupported supplier invoice status [{$currentStatus}].",
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

    public function isEditable(string $status): bool
    {
        return $status === 'draft';
    }

    public function canValidate(string $status): bool
    {
        return in_array(
            $status,
            [
                'draft',
                'disputed',
            ],
            true,
        );
    }

    public function canReturnToDraft(string $status): bool
    {
        return in_array(
            $status,
            [
                'validated',
                'disputed',
            ],
            true,
        );
    }

    public function canApprove(string $status): bool
    {
        return $status === 'validated';
    }

    public function canDispute(string $status): bool
    {
        return in_array(
            $status,
            [
                'validated',
                'approved',
            ],
            true,
        );
    }

    public function canPost(string $status): bool
    {
        return $status === 'approved';
    }

    public function canReverse(string $status): bool
    {
        return $status === 'posted';
    }

    public function canCancel(string $status): bool
    {
        return in_array(
            $status,
            [
                'draft',
                'validated',
                'approved',
                'disputed',
            ],
            true,
        );
    }

    public function isFinal(string $status): bool
    {
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