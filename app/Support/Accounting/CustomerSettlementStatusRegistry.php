<?php

declare(strict_types=1);

namespace App\Support\Accounting;

use LogicException;

final class CustomerSettlementStatusRegistry
{
    /** @var array<string, string> */
    private const STATUSES = ['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved', 'posted' => 'Posted', 'reversed' => 'Reversed', 'cancelled' => 'Cancelled',];
    /** @var array<string, list<string>> */
    private const TRANSITIONS = ['draft' => ['submitted', 'cancelled'], 'submitted' => ['draft', 'approved', 'cancelled'], 'approved' => ['posted', 'cancelled'], 'posted' => ['reversed'], 'reversed' => [], 'cancelled' => [],];
    /** @return list<string> */
    public function keys(): array
    {
        return array_keys(self::STATUSES);
    }

    public function exists(string $status): bool
    {
        return array_key_exists($status, self::STATUSES);
    }

    public function label(string $status): string
    {
        return self::STATUSES[$status] ?? throw new LogicException("Unsupported customer settlement status [{$status}].");
    }
    /** @return list<array{value: string, label: string}> */
    public function options(): array
    {
        return array_map(static fn(string $value, string $label): array => ['value' => $value, 'label' => $label], array_keys(self::STATUSES), array_values(self::STATUSES),);
    }

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function isEditable(string $status): bool
    {
        return $status === 'draft';
    }

    public function canSubmit(string $status): bool
    {
        return $status === 'draft';
    }

    public function canReturnToDraft(string $status): bool
    {
        return $status === 'submitted';
    }

    public function canApprove(string $status): bool
    {
        return $status === 'submitted';
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
        return in_array($status, ['draft', 'submitted', 'approved'], true);
    }
}