<?php

declare(strict_types=1);

namespace App\Support\Purchasing;

use LogicException;

final class PurchaseOrderStatusRegistry
{
    /**
     * @var array<string, string>
     */
    private const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'partially_received' => 'Partially Received',
        'received' => 'Received',
        'closed' => 'Closed',
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
            'partially_received',
            'received',
            'closed',
            'cancelled',
        ],

        'partially_received' => [
            'received',
            'closed',
            'cancelled',
        ],

        'received' => [
            'closed',
        ],

        'closed' => [],

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
                "Unsupported purchase order status [{$status}].",
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
        string $currentStatus,
    ): array {
        $transitions = self::ALLOWED_TRANSITIONS[
            $currentStatus
        ] ?? null;

        if (!is_array($transitions)) {
            throw new LogicException(
                "Unsupported purchase order status [{$currentStatus}].",
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

    public function isSubmitted(string $status): bool
    {
        return $status === 'submitted';
    }

    public function isApproved(string $status): bool
    {
        return in_array(
            $status,
            [
                'approved',
                'partially_received',
                'received',
                'closed',
            ],
            true,
        );
    }

    public function isReceivable(string $status): bool
    {
        return in_array(
            $status,
            [
                'approved',
                'partially_received',
            ],
            true,
        );
    }

    public function isFullyReceived(
        string $status,
    ): bool {
        return in_array(
            $status,
            [
                'received',
                'closed',
            ],
            true,
        );
    }

    public function isFinal(string $status): bool
    {
        return in_array(
            $status,
            [
                'closed',
                'cancelled',
            ],
            true,
        );
    }

    public function canSubmit(string $status): bool
    {
        return $status === 'draft';
    }

    public function canReturnToDraft(
        string $status,
    ): bool {
        return $status === 'submitted';
    }

    public function canApprove(string $status): bool
    {
        return $status === 'submitted';
    }

    public function canCancel(string $status): bool
    {
        return in_array(
            $status,
            [
                'draft',
                'submitted',
                'approved',
                'partially_received',
            ],
            true,
        );
    }

    public function canClose(string $status): bool
    {
        return in_array(
            $status,
            [
                'approved',
                'partially_received',
                'received',
            ],
            true,
        );
    }
}