<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;

final class ManagementProductProfitabilityExportDefinition extends AbstractManagementReportExportDefinition
{
    public function key(): string
    {
        return 'management_product_profitability';
    }

    public function label(): string
    {
        return 'Product Profitability';
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Product',
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
                $row['product_sku'],
                $row['product_name'],
                $row['revenue'],
                $row['cost'],
                $row['gross_profit'],
                $row['margin_percent'],
            ],
            $this->service->productProfitability($filters, $requester),
        );
    }
}
