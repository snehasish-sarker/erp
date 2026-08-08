<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;

final class ProfitAndLossExportDefinition extends AbstractFinancialStatementExportDefinition
{
    public function key(): string
    {
        return 'profit_and_loss';
    }

    public function label(): string
    {
        return 'Profit and Loss Statement';
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
        $report = $this->service->profitAndLoss($filters, $requester);
        $rows = [];

        foreach ($report['sections'] as $section => $items) {
            foreach ($items as $item) {
                $rows[] = [
                    ucwords(str_replace('_', ' ', (string) $section)),
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
