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

final class OpenInvoiceExportDefinition implements ExportDefinition
{
    public function __construct(
        private readonly AccountsReceivableAgingService $agingService,
    ) {
    }

    public function key(): string
    {
        return 'accounts_receivable_open_invoices';
    }

    public function label(): string
    {
        return 'Accounts Receivable Open Invoices';
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
            'Open Item ID',
            'Customer Code',
            'Customer Name',
            'Branch Code',
            'Branch Name',
            'Invoice Number',
            'Document Date',
            'Posting Date',
            'Due Date',
            'Currency',
            'Exchange Rate',
            'Original Amount',
            'Allocated as of Date',
            'Outstanding as of Date',
            'Base Outstanding as of Date',
            'Days Overdue',
            'Aging Bucket',
            'Journal Reference',
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
                        'document_number',
                        'document_date',
                        'due_date',
                        'original_amount',
                        'outstanding_amount',
                        'days_overdue',
                    ]),
                ],
                'direction' => [
                    'nullable',
                    'string',
                    Rule::in(['asc', 'desc']),
                ],
                'overdue_only' => ['nullable', 'boolean'],
            ],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $overdueOnly = (bool) ($validated['overdue_only'] ?? false);

        return $this->agingService->normalizeOpenInvoiceExportFilters(
            filters: [
                'as_of_date' => $validated['as_of_date'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
                'customer_id' => $validated['customer_id'] ?? null,
                'currency_code' => isset($validated['currency_code'])
                    ? mb_strtoupper(trim((string) $validated['currency_code']))
                    : null,
                'search' => trim((string) ($validated['search'] ?? '')),
                'sort' => $validated['sort'] ?? 'due_date',
                'direction' => $validated['direction'] ?? 'asc',
                'per_page' => 100,
            ],
            actor: $requester,
            overdueOnly: $overdueOnly,
        );
    }

    public function totalRows(
        array $filters,
        User $requester,
    ): int {
        return $this->agingService->exportOpenInvoiceTotalRows(
            filters: $filters,
            actor: $requester,
            overdueOnly: (bool) ($filters['overdue_only'] ?? false),
        );
    }

    public function rows(
        array $filters,
        User $requester,
    ): LazyCollection {
        return $this->agingService->exportOpenInvoiceRows(
            filters: $filters,
            actor: $requester,
            overdueOnly: (bool) ($filters['overdue_only'] ?? false),
        );
    }

    public function mapRow(mixed $row): array
    {
        if (!is_array($row)) {
            throw new LogicException(
                'The Open Invoice exporter received an unsupported row.',
            );
        }

        return [
            (int) $row['id'],
            (string) $row['customer']['code'],
            (string) $row['customer']['name'],
            (string) $row['branch']['code'],
            (string) $row['branch']['name'],
            $row['document_number'],
            (string) $row['document_date'],
            (string) $row['posting_date'],
            $row['due_date'],
            (string) $row['currency_code'],
            (string) $row['exchange_rate'],
            (string) $row['original_amount'],
            (string) $row['historical_allocated_amount'],
            (string) $row['outstanding_amount'],
            (string) $row['base_outstanding_amount'],
            $row['days_overdue'],
            (string) $row['bucket_label'],
            (string) $row['journal_reference'],
            (string) $row['description'],
        ];
    }
}