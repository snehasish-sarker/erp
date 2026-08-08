<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;

final class ManagementGrossMarginExportDefinition extends AbstractManagementReportExportDefinition
{
    public function key(): string
    {
        return 'management_gross_margin';
    }

    public function label(): string
    {
        return 'Gross Margin Analysis';
    }

    public function headings(): array
    {
        return [
            'Period',
            'Revenue',
            'Cost',
            'Gross Profit',
            'Margin %',
        ];
    }

    protected function exportRows(array $filters, User $requester): array
    {
        return array_map(
            static fn (array $row): array => [
                $row['period'],
                $row['revenue'],
                $row['cost'],
                $row['gross_profit'],
                $row['margin_percent'],
            ],
            $this->service->grossMargin($filters, $requester),
        );
    }
}
