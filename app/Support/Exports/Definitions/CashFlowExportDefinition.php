<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;

final class CashFlowExportDefinition extends AbstractFinancialStatementExportDefinition
{
    public function key(): string
    {
        return 'cash_flow';
    }

    public function label(): string
    {
        return 'Cash Flow Statement';
    }

    public function headings(): array
    {
        return [
            'Method',
            'Section',
            'Description',
            'Amount',
        ];
    }

    protected function exportRows(array $filters, User $requester): array
    {
        $report = $this->service->cashFlow($filters, $requester);
        $method = (string) ($filters['method'] ?? 'direct');
        $rows = [];

        if ($method === 'indirect') {
            foreach ($report['indirect']['rows'] as $row) {
                $rows[] = [
                    'Indirect',
                    'Operating',
                    $row['label'],
                    $row['amount'],
                ];
            }

            foreach ($report['indirect']['totals'] as $label => $amount) {
                $rows[] = [
                    'Indirect',
                    'Total',
                    ucwords(str_replace('_', ' ', (string) $label)),
                    $amount,
                ];
            }

            return $rows;
        }

        foreach ($report['direct']['sections'] as $section => $items) {
            foreach ($items as $row) {
                $rows[] = [
                    'Direct',
                    ucfirst((string) $section),
                    $row['label'],
                    $row['amount'],
                ];
            }
        }

        foreach ($report['direct']['totals'] as $label => $amount) {
            $rows[] = [
                'Direct',
                'Total',
                ucwords(str_replace('_', ' ', (string) $label)),
                $amount,
            ];
        }

        return $rows;
    }
}
