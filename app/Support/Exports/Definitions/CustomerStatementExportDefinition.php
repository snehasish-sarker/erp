<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\Customer;
use App\Models\User;
use App\Services\Accounting\CustomerStatementService;
use App\Support\Exports\ExportDefinition;
use Illuminate\Contracts\Validation\Validator as LaravelValidator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerStatementExportDefinition implements ExportDefinition
{
    public function __construct(
        private readonly CustomerStatementService $statementService,
    ) {
    }

    public function key(): string
    {
        return 'customer_statement';
    }

    public function label(): string
    {
        return 'Customer Statement';
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
            'Row Type',
            'Customer Code',
            'Customer Name',
            'Date From',
            'Date To',
            'Posting Date',
            'Document Date',
            'Due Date',
            'Reference',
            'Journal Reference',
            'Entry Type',
            'Source Document',
            'Branch ID',
            'Branch Code',
            'Branch Name',
            'Currency',
            'Exchange Rate',
            'Opening Balance',
            'Debit',
            'Credit',
            'Closing / Running Balance',
            'Base Debit',
            'Base Credit',
            'Base Running Balance',
            'Description',
        ];
    }

    public function validateFilters(
        array $filters,
        User $requester,
    ): array {
        $validator = Validator::make(
            $filters,
            [
                'customer_id' => ['required', 'integer', 'min:1'],
                'branch_id' => ['nullable', 'integer', 'min:1'],
                'currency_code' => [
                    'nullable',
                    'string',
                    'size:3',
                    'regex:/^[A-Za-z]{3}$/',
                ],
                'date_from' => ['nullable', 'date_format:Y-m-d'],
                'date_to' => ['nullable', 'date_format:Y-m-d'],
            ],
        );

        $validator->after(
            static function (LaravelValidator $validator) use ($filters): void {
                $from = trim((string) ($filters['date_from'] ?? ''));
                $to = trim((string) ($filters['date_to'] ?? ''));

                if ($from !== '' && $to !== '' && $to < $from) {
                    $validator->errors()->add(
                        'date_to',
                        'The ending date must be on or after the starting date.',
                    );
                }
            },
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $customer = $this->customer((int) $validated['customer_id']);

        return $this->statementService->normalizeExportFilters(
            customer: $customer,
            actor: $requester,
            filters: [
                'branch_id' => $validated['branch_id'] ?? null,
                'currency_code' => isset($validated['currency_code'])
                    ? mb_strtoupper(trim((string) $validated['currency_code']))
                    : null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'per_page' => 100,
            ],
        );
    }

    public function totalRows(
        array $filters,
        User $requester,
    ): int {
        return $this->statementService->exportTotalRows(
            customer: $this->customer((int) $filters['customer_id']),
            actor: $requester,
            filters: $filters,
        );
    }

    public function rows(
        array $filters,
        User $requester,
    ): LazyCollection {
        return $this->statementService->exportRows(
            customer: $this->customer((int) $filters['customer_id']),
            actor: $requester,
            filters: $filters,
        );
    }

    public function mapRow(mixed $row): array
    {
        if (!is_array($row)) {
            throw new LogicException(
                'The Customer Statement exporter received an unsupported row.',
            );
        }

        $rowType = (string) ($row['row_type'] ?? '');

        if (!in_array($rowType, ['base_summary', 'currency_summary', 'entry'], true)) {
            throw new LogicException(
                'The Customer Statement exporter received an unsupported statement row type.',
            );
        }

        return [
            $rowType,
            $row['customer_code'] ?? null,
            $row['customer_name'] ?? null,
            $row['date_from'] ?? null,
            $row['date_to'] ?? null,
            $row['posting_date'] ?? null,
            $row['document_date'] ?? null,
            $row['due_date'] ?? null,
            $row['reference'] ?? null,
            $row['journal_reference'] ?? null,
            $row['entry_type_label'] ?? null,
            $row['source_document_number'] ?? null,
            $row['branch']['id'] ?? null,
            $row['branch']['code'] ?? null,
            $row['branch']['name'] ?? null,
            $row['currency_code'] ?? null,
            $row['exchange_rate'] ?? null,
            $row['opening_balance'] ?? null,
            $row['period_debit'] ?? $row['debit_amount'] ?? null,
            $row['period_credit'] ?? $row['credit_amount'] ?? null,
            $row['closing_balance'] ?? $row['currency_running_balance'] ?? null,
            $row['base_debit_amount'] ?? null,
            $row['base_credit_amount'] ?? null,
            $row['base_running_balance'] ?? null,
            $row['description'] ?? null,
        ];
    }

    private function customer(int $customerId): Customer
    {
        return Customer::withTrashed()
            ->whereKey($customerId)
            ->firstOrFail();
    }
}