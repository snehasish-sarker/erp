<?php

declare(strict_types=1);

namespace App\Support\Sales;

use LogicException;

final class SalesOrderStatusRegistry
{
    /**
     * @var array<string, string>
     */
    private const STATUSES = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'partially_allocated' =>
            'Partially Allocated',
        'allocated' => 'Allocated',
        'partially_dispatched' =>
            'Partially Dispatched',
        'dispatched' => 'Dispatched',
        'partially_invoiced' =>
            'Partially Invoiced',
        'invoiced' => 'Invoiced',
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
            'partially_allocated',
            'allocated',
            'partially_dispatched',
            'dispatched',
            'cancelled',
            'closed',
        ],

        'partially_allocated' => [
            'approved',
            'allocated',
            'partially_dispatched',
            'dispatched',
            'cancelled',
            'closed',
        ],

        'allocated' => [
            'approved',
            'partially_allocated',
            'partially_dispatched',
            'dispatched',
            'cancelled',
            'closed',
        ],

        'partially_dispatched' => [
            'dispatched',
            'partially_invoiced',
            'invoiced',
            'closed',
        ],

        'dispatched' => [
            'partially_invoiced',
            'invoiced',
            'closed',
        ],

        'partially_invoiced' => [
            'invoiced',
            'closed',
        ],

        'invoiced' => [
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
        $label =
            self::STATUSES[$status]
            ?? null;

        if (!is_string($label)) {
            throw new LogicException(
                "Unsupported sales order status [{$status}].",
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
        $transitions =
            self::ALLOWED_TRANSITIONS[
                $currentStatus
            ] ?? null;

        if (!is_array($transitions)) {
            throw new LogicException(
                "Unsupported sales order status [{$currentStatus}].",
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

    public function isSubmitted(
        string $status,
    ): bool {
        return $status === 'submitted';
    }

    public function isApproved(
        string $status,
    ): bool {
        return in_array(
            $status,
            [
                'approved',
                'partially_allocated',
                'allocated',
                'partially_dispatched',
                'dispatched',
                'partially_invoiced',
                'invoiced',
                'closed',
            ],
            true,
        );
    }

    public function isAllocatable(
        string $status,
    ): bool {
        return in_array(
            $status,
            [
                'approved',
                'partially_allocated',
                'allocated',
            ],
            true,
        );
    }

    public function isDispatchable(
        string $status,
    ): bool {
        return in_array(
            $status,
            [
                'approved',
                'partially_allocated',
                'allocated',
                'partially_dispatched',
            ],
            true,
        );
    }

    public function isInvoiceable(
        string $status,
    ): bool {
        return in_array(
            $status,
            [
                'partially_dispatched',
                'dispatched',
                'partially_invoiced',
            ],
            true,
        );
    }

    public function isCancellable(
        string $status,
    ): bool {
        return in_array(
            $status,
            [
                'draft',
                'submitted',
                'approved',
                'partially_allocated',
                'allocated',
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
                'closed',
                'cancelled',
            ],
            true,
        );
    }
}