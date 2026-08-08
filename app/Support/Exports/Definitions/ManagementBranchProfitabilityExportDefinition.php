<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;

final class ManagementBranchProfitabilityExportDefinition extends AbstractManagementReportExportDefinition
{
    public function key(): string
    {
        return 'management_branch_profitability';
    }

    public function label(): string
    {
        return 'Branch Profitability';
    }

    public function headings(): array
    {
        return [
            'Branch Code',
            'Branch Name',
            'Revenue',
            'Expenses',
            'Profit',
            'Margin %',
        ];
    }

    protected function exportRows(array $filters, User $requester): array
    {
        return array_map(
            static fn (array $row): array => [
                $row['branch_code'],
                $row['branch_name'],
                $row['revenue'],
                $row['expenses'],
                $row['profit'],
                $row['margin_percent'],
            ],
            $this->service->branchProfitability($filters, $requester),
        );
    }
}
