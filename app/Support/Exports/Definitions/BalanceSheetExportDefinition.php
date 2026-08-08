<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;

final class BalanceSheetExportDefinition extends AbstractFinancialStatementExportDefinition
{
    public function key(): string
    {
        return 'balance_sheet';
    }

    public function label(): string
    {
        return 'Balance Sheet';
    }

    public function headings(): array
    {
        return [
            'Section',
            'Account Code',
            'Account Name',
            'Amount',
        ];
    }

    protected function exportRows(array $filters, User $requester): array
    {
        $report = $this->service->balanceSheet($filters, $requester);
        $rows = [];

        foreach ($report['sections'] as $section => $items) {
            foreach ($items as $item) {
                $rows[] = [
                    ucfirst((string) $section),
                    $item['code'],
                    $item['name'],
                    $item['amount'],
                ];
            }
        }

        foreach ($report['totals'] as $label => $amount) {
            $rows[] = [
                'Total',
                '',
                ucwords(str_replace('_', ' ', (string) $label)),
                $amount,
            ];
        }

        return $rows;
    }
}
