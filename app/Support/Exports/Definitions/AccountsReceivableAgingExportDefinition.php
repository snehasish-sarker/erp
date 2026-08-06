<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;
use App\Services\Accounting\AccountsReceivableAgingService;
use App\Support\Exports\ExportDefinition;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

final class AccountsReceivableAgingExportDefinition implements ExportDefinition
{
    public function __construct(
        private readonly AccountsReceivableAgingService $agingService,
    ) {
    }

    public function key(): string
    {
        return 'accounts_receivable_aging';
    }

    public function label(): string
    {
        return 'Accounts Receivable Aging';
    }

    public function requiredPermission(): string
    {
        return 'reports.receivables';
    }

    public function isSelectableFromExportCenter(): bool
    {
        return false;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return [
            'Customer ID',
            'Customer Code',
            'Customer Name',
            'Customer Type',
            'Customer Status',
            'Open Invoice Count',
            'Gross Receivable (Base)',
            'Unapplied Credit (Base)',
            'Net Outstanding (Base)',
            'Customer Ledger Balance (Base)',
            'Reconciliation Difference (Base)',
            'Current (Base)',
            '1-30 Days (Base)',
            '31-60 Days (Base)',
            '61-90 Days (Base)',
            '91-120 Days (Base)',
            'Over 120 Days (Base)',
        ];
    }

    public function validateFilters(
        array $filters,
        User $requester,
    ): array {
        $validator = Validator::make(
            $filters,
            [
                'as_of_date' => ['nullable', 'date_format:Y-m-d'],
                'branch_id' => ['nullable', 'integer', 'min:1'],
                'customer_id' => ['nullable', 'integer', 'min:1'],
                'currency_code' => [
                    'nullable',
                    'string',
                    'size:3',
                    'regex:/^[A-Za-z]{3}$/',
                ],
                'search' => ['nullable', 'string', 'max:160'],
                'sort' => [
                    'nullable',
                    'string',
                    Rule::in([
                        'customer_name',
                        'total_receivable',
                        'unapplied_credit',
                        'net_outstanding',
                        'ledger_balance',
                        'difference',
                        'current',
                        'days_1_30',
                        'days_31_60',
                        'days_61_90',
                        'days_91_120',
                        'days_over_120',
                    ]),
                ],
                'direction' => [
                    'nullable',
                    'string',
                    Rule::in(['asc', 'desc']),
                ],
            ],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        return $this->agingService->normalizeExportFilters(
            filters: [
                'as_of_date' => $validated['as_of_date'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'currency_code' => isset($validated['currency_code'])
                    ? mb_strtoupper(trim((string) $validated['currency_code']))
                    : null,
                'search' => trim((string) ($validated['search'] ?? '')),
                'sort' => $validated['sort'] ?? 'net_outstanding',
                'direction' => $validated['direction'] ?? 'desc',
                'per_page' => 100,
            ],
            actor: $requester,
        );
    }

    public function totalRows(
        array $filters,
        User $requester,
    ): int {
        return $this->agingService->exportSummaryTotalRows(
            filters: $filters,
            actor: $requester,
        );
    }

    public function rows(
        array $filters,
        User $requester,
    ): LazyCollection {
        return $this->agingService->exportSummaryRows(
            filters: $filters,
            actor: $requester,
        );
    }

    public function mapRow(mixed $row): array
    {
        if (!is_array($row)) {
            throw new LogicException(
                'The Accounts Receivable aging exporter received an unsupported row.',
            );
        }

        return [
            (int) $row['customer']['id'],
            (string) $row['customer']['code'],
            (string) $row['customer']['name'],
            (string) $row['customer']['customer_type'],
            (string) $row['customer']['status'],
            (int) $row['open_invoice_count'],
            (string) $row['total_receivable'],
            (string) $row['unapplied_credit'],
            (string) $row['net_outstanding'],
            (string) $row['ledger_balance'],
            (string) $row['difference'],
            (string) $row['buckets']['current'],
            (string) $row['buckets']['days_1_30'],
            (string) $row['buckets']['days_31_60'],
            (string) $row['buckets']['days_61_90'],
            (string) $row['buckets']['days_91_120'],
            (string) $row['buckets']['days_over_120'],
        ];
    }
}