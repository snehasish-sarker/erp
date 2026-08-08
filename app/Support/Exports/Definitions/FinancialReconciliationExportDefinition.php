<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;
use App\Services\Accounting\FinancialReconciliationService;
use App\Support\Exports\ExportDefinition;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

final class FinancialReconciliationExportDefinition implements ExportDefinition
{
    public function __construct(
        private readonly FinancialReconciliationService $service,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function key(): string
    {
        return 'financial_reconciliations';
    }

    public function label(): string
    {
        return 'Financial Reconciliations';
    }

    public function requiredPermission(): string
    {
        return 'financial_control.view';
    }

    public function isSelectableFromExportCenter(): bool
    {
        return false;
    }

    public function headings(): array
    {
        return [
            'Control',
            'General Ledger',
            'Subledger/Reference',
            'Difference',
            'Status',
        ];
    }

    public function validateFilters(array $filters, User $requester): array
    {
        $validator = Validator::make($filters, [
            'as_of_date' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function totalRows(array $filters, User $requester): int
    {
        return count($this->data($filters, $requester));
    }

    public function rows(array $filters, User $requester): LazyCollection
    {
        $rows = $this->data($filters, $requester);

        return LazyCollection::make(
            static function () use ($rows): \Generator {
                foreach ($rows as $row) {
                    yield $row;
                }
            },
        );
    }

    public function mapRow(mixed $row): array
    {
        return is_array($row) ? array_values($row) : [];
    }

    /** @return list<array<int, string|int|float|null>> */
    private function data(array $filters, User $requester): array
    {
        $asOf = (string) ($filters['as_of_date'] ?? CarbonImmutable::now(
            $this->tenantContext->tenant()->timezone,
        )->toDateString());
        $branchId = isset($filters['branch_id'])
            ? (int) $filters['branch_id']
            : null;
        $report = $this->service->build(
            $asOf,
            $requester,
            $branchId,
        );
        $rows = [];

        foreach ([
            'accounts_receivable' => 'Accounts Receivable',
            'accounts_payable' => 'Accounts Payable',
            'inventory' => 'Inventory',
        ] as $key => $label) {
            $item = $report[$key];
            $rows[] = [
                $label,
                $item['general_ledger'],
                $item['subledger'],
                $item['difference'],
                $item['status'],
            ];
        }

        $rows[] = [
            'Treasury Clearing',
            $report['treasury_clearing']['ledger_balance'],
            '0.000000',
            $report['treasury_clearing']['difference'],
            $report['treasury_clearing']['status'],
        ];

        foreach ($report['bank_accounts'] as $bank) {
            $rows[] = [
                "Bank {$bank['account_code']} / {$bank['branch_code']}",
                $bank['book_balance'],
                $bank['last_reconciliation_date'],
                $bank['difference_since_reconciliation'],
                $bank['status'],
            ];
        }

        return $rows;
    }
}
