<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;

final class TrialBalanceExportDefinition extends AbstractFinancialStatementExportDefinition
{
    public function key(): string
    {
        return 'trial_balance';
    }

    public function label(): string
    {
        return 'Trial Balance';
    }

    public function headings(): array
    {
        return [
            'Account Code',
            'Account Name',
            'Account Type',
            'Opening Debit',
            'Opening Credit',
            'Period Debit',
            'Period Credit',
            'Closing Debit',
            'Closing Credit',
        ];
    }

    protected function exportRows(array $filters, User $requester): array
    {
        $report = $this->service->trialBalance($filters, $requester);

        return array_map(
            static fn (array $row): array => [
                $row['code'],
                $row['name'],
                $row['account_type'],
                $row['opening_debit'],
                $row['opening_credit'],
                $row['period_debit'],
                $row['period_credit'],
                $row['closing_debit'],
                $row['closing_credit'],
            ],
            $report['rows'],
        );
    }
}
