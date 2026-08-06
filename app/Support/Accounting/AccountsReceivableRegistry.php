<?php

declare(strict_types=1);

namespace App\Support\Accounting;

final class AccountsReceivableRegistry
{
    /**
     * @var array<string, string>
     */
    private const LEDGER_ENTRY_TYPES = [
        'invoice' => 'Sales Invoice',
        'invoice_reversal' => 'Sales Invoice Reversal',
        'credit_note' => 'Customer Credit Note',
        'credit_note_reversal' => 'Customer Credit Note Reversal',
        'receipt' => 'Customer Receipt',
        'receipt_reversal' => 'Customer Receipt Reversal',
        'adjustment' => 'Customer Adjustment',
        'adjustment_reversal' => 'Customer Adjustment Reversal',
    ];

    /**
     * @var array<string, string>
     */
    private const OPEN_ITEM_TYPES = [
        'invoice' => 'Invoice Receivable',
        'credit' => 'Customer Credit',
        'receipt' => 'Unallocated Receipt',
        'adjustment' => 'Adjustment',
    ];

    /**
     * @var array<string, string>
     */
    private const OPEN_ITEM_STATUSES = [
        'open' => 'Open',
        'partially_settled' => 'Partially Settled',
        'settled' => 'Settled',
        'reversed' => 'Reversed',
    ];

    /**
     * @var array<string, string>
     */
    private const ALLOCATION_TYPES = [
        'credit_note' => 'Customer Credit Note',
        'receipt' => 'Customer Receipt',
        'manual' => 'Manual Allocation',
        'adjustment' => 'Adjustment',
    ];

    /**
     * @var array<string, string>
     */
    private const ALLOCATION_STATUSES = [
        'applied' => 'Applied',
        'reversed' => 'Reversed',
    ];

    /** @return list<string> */
    public function ledgerEntryTypes(): array
    {
        return array_keys(self::LEDGER_ENTRY_TYPES);
    }

    /** @return list<string> */
    public function openItemTypes(): array
    {
        return array_keys(self::OPEN_ITEM_TYPES);
    }

    /** @return list<string> */
    public function openItemStatuses(): array
    {
        return array_keys(self::OPEN_ITEM_STATUSES);
    }

    /** @return list<string> */
    public function allocationTypes(): array
    {
        return array_keys(self::ALLOCATION_TYPES);
    }

    /** @return list<string> */
    public function allocationStatuses(): array
    {
        return array_keys(self::ALLOCATION_STATUSES);
    }

    public function isLedgerEntryType(string $type): bool
    {
        return array_key_exists($type, self::LEDGER_ENTRY_TYPES);
    }

    public function isOpenItemType(string $type): bool
    {
        return array_key_exists($type, self::OPEN_ITEM_TYPES);
    }

    public function isOpenItemStatus(string $status): bool
    {
        return array_key_exists($status, self::OPEN_ITEM_STATUSES);
    }

    public function isAllocationType(string $type): bool
    {
        return array_key_exists($type, self::ALLOCATION_TYPES);
    }

    public function isAllocationStatus(string $status): bool
    {
        return array_key_exists($status, self::ALLOCATION_STATUSES);
    }

    public function ledgerEntryTypeLabel(string $type): string
    {
        return self::LEDGER_ENTRY_TYPES[$type] ?? $type;
    }

    public function openItemTypeLabel(string $type): string
    {
        return self::OPEN_ITEM_TYPES[$type] ?? $type;
    }

    public function openItemStatusLabel(string $status): string
    {
        return self::OPEN_ITEM_STATUSES[$status] ?? $status;
    }

    public function allocationTypeLabel(string $type): string
    {
        return self::ALLOCATION_TYPES[$type] ?? $type;
    }

    public function allocationStatusLabel(string $status): string
    {
        return self::ALLOCATION_STATUSES[$status] ?? $status;
    }
}