<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;

final class ManagementSupplierSpendExportDefinition extends AbstractManagementReportExportDefinition
{
    public function key(): string
    {
        return 'management_supplier_spend';
    }

    public function label(): string
    {
        return 'Supplier Spend Analysis';
    }

    public function headings(): array
    {
        return [
            'Supplier Code',
            'Supplier',
            'Gross Spend',
            'Debit Notes',
            'Net Spend',
        ];
    }

    protected function exportRows(array $filters, User $requester): array
    {
        return array_map(
            static fn (array $row): array => [
                $row['supplier_code'],
                $row['supplier_name'],
                $row['gross_spend'],
                $row['debit_notes'],
                $row['net_spend'],
            ],
            $this->service->supplierSpend($filters, $requester),
        );
    }
}
