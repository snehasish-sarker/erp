<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;

final class ManagementCustomerProfitabilityExportDefinition extends AbstractManagementReportExportDefinition
{
    public function key(): string
    {
        return 'management_customer_profitability';
    }

    public function label(): string
    {
        return 'Customer Profitability';
    }

    public function headings(): array
    {
        return [
            'Customer Code',
            'Customer',
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
                $row['customer_code'],
                $row['customer_name'],
                $row['revenue'],
                $row['cost'],
                $row['gross_profit'],
                $row['margin_percent'],
            ],
            $this->service->customerProfitability($filters, $requester),
        );
    }
}
