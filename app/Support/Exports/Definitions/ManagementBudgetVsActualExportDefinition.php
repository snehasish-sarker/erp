<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ManagementBudgetVsActualExportDefinition extends AbstractManagementReportExportDefinition
{
    public function key(): string
    {
        return 'management_budget_vs_actual';
    }

    public function label(): string
    {
        return 'Budget vs Actual';
    }

    public function headings(): array
    {
        return [
            'Account Code',
            'Account Name',
            'Type',
            'Month',
            'Budget',
            'Actual',
            'Favourable Variance',
            'Variance %',
        ];
    }

    public function validateFilters(array $filters, User $requester): array
    {
        $validated = parent::validateFilters($filters, $requester);

        if (!isset($validated['budget_id'])) {
            throw ValidationException::withMessages([
                'budget_id' => [
                    'A management budget is required for this export.',
                ],
            ]);
        }

        return $validated;
    }

    protected function exportRows(array $filters, User $requester): array
    {
        $report = $this->service->budgetVsActual($filters, $requester);

        return array_map(
            static fn (array $row): array => [
                $row['account_code'],
                $row['account_name'],
                $row['account_type'],
                $row['month_number'],
                $row['budget_amount'],
                $row['actual_amount'],
                $row['variance_amount'],
                $row['variance_percent'],
            ],
            $report['rows'],
        );
    }
}
