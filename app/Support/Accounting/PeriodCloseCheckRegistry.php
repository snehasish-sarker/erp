<?php

declare(strict_types=1);

namespace App\Support\Accounting;

final class PeriodCloseCheckRegistry
{
    /** @return array<string, string> */
    public function statuses(): array
    {
        return [
            'passed' => 'Passed',
            'warning' => 'Warning',
            'failed' => 'Failed',
        ];
    }

    /** @return array<string, string> */
    public function categories(): array
    {
        return [
            'documents' => 'Documents',
            'general_ledger' => 'General Ledger',
            'subledgers' => 'Subledger Reconciliation',
            'inventory' => 'Inventory Control',
            'treasury' => 'Treasury and Bank',
            'closing' => 'Year-end Closing',
        ];
    }
}
