<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\Customer;
use App\Models\User;
use App\Services\Accounting\AccountsReceivableAgingService;
use App\Support\Exports\ExportDefinition;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CustomerAgingExportDefinition implements ExportDefinition
{
    public function __construct(
        private readonly AccountsReceivableAgingService $agingService,
    ) {
    }

    public function key(): string
    {
        return 'customer_aging_detail';
    }

    public function label(): string
    {
        return 'Customer Aging Detail';
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
            'Customer Ledger Entry ID',
            'Customer Code',
            'Customer Name',
            'Branch Code',
            'Branch Name',
            'Balance Side',
            'Item Type',
            'Entry Type',
            'Document Number',
            'Reference',
            'Journal Reference',
            'Document Date',
            'Posting Date',
            'Due Date',
            'Currency',
            'Exchange Rate',
            'Original Amount',
            'Allocated as of Date',
            'Outstanding as of Date',
            'Base Original Amount',
            'Base Allocated as of Date',
            'Base Outstanding as of Date',
            'Days Overdue',
            'Aging Bucket',
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
                'as_of_date' => ['nullable', 'date_format:Y-m-d'],
                'branch_id' => ['nullable', 'integer', 'min:1'],
                'currency_code' => [
                    'nullable',
                    'string',
                    'size:3',
                    'regex:/^[A-Za-z]{3}$/',
                ],
                'search' => ['nullable', 'string', 'max:160'],
            ],
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();
        $customer = $this->customer((int) $validated['customer_id']);

        return $this->agingService->normalizeExportFilters(
            filters: [
                'customer_id' => $customer->getKey(),
                'as_of_date' => $validated['as_of_date'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
                'currency_code' => isset($validated['currency_code'])
                    ? mb_strtoupper(trim((string) $validated['currency_code']))
                    : null,
                'search' => trim((string) ($validated['search'] ?? '')),
                'sort' => 'net_outstanding',
                'direction' => 'desc',
                'per_page' => 100,
            ],
            actor: $requester,
        );
    }

    public function totalRows(
        array $filters,
        User $requester,
    ): int {
        return $this->agingService->exportCustomerDetailTotalRows(
            customer: $this->customer((int) $filters['customer_id']),
            filters: $filters,
            actor: $requester,
        );
    }

    public function rows(
        array $filters,
        User $requester,
    ): LazyCollection {
        return $this->agingService->exportCustomerDetailRows(
            customer: $this->customer((int) $filters['customer_id']),
            filters: $filters,
            actor: $requester,
        );
    }

    public function mapRow(mixed $row): array
    {
        if (!is_array($row)) {
            throw new LogicException(
                'The Customer aging exporter received an unsupported row.',
            );
        }

        return [
            (int) $row['id'],
            (int) $row['ledger_entry_id'],
            (string) $row['customer']['code'],
            (string) $row['customer']['name'],
            (string) $row['branch']['code'],
            (string) $row['branch']['name'],
            (string) $row['balance_side'],
            (string) $row['item_type_label'],
            (string) $row['entry_type_label'],
            $row['document_number'],
            (string) $row['reference'],
            (string) $row['journal_reference'],
            (string) $row['document_date'],
            (string) $row['posting_date'],
            $row['due_date'],
            (string) $row['currency_code'],
            (string) $row['exchange_rate'],
            (string) $row['original_amount'],
            (string) $row['historical_allocated_amount'],
            (string) $row['outstanding_amount'],
            (string) $row['base_original_amount'],
            (string) $row['historical_base_allocated_amount'],
            (string) $row['base_outstanding_amount'],
            $row['days_overdue'],
            (string) $row['bucket_label'],
            (string) $row['description'],
        ];
    }

    private function customer(int $customerId): Customer
    {
        return Customer::withTrashed()
            ->whereKey($customerId)
            ->firstOrFail();
    }
}