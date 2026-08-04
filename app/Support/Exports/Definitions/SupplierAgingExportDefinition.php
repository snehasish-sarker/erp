<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\AccountsPayableAgingService;
use App\Support\Exports\ExportDefinition;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SupplierAgingExportDefinition implements ExportDefinition
{
    public function __construct(
        private readonly AccountsPayableAgingService $agingService,
    ) {
    }

    public function key(): string
    {
        return 'supplier_aging_detail';
    }

    public function label(): string
    {
        return 'Supplier Aging Detail';
    }

    public function requiredPermission(): string
    {
        return 'reports.payables';
    }

    public function isSelectableFromExportCenter(): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Open Item ID',
            'Supplier Ledger Entry ID',
            'Branch Code',
            'Branch Name',
            'Item Type',
            'Entry Type',
            'Balance Side',
            'Document Number',
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
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function validateFilters(
        array $filters,
        User $requester,
    ): array {
        $validator = Validator::make(
            $filters,
            [
                'supplier_id' => [
                    'required',
                    'integer',
                    'min:1',
                ],
                'as_of_date' => [
                    'nullable',
                    'date_format:Y-m-d',
                ],
                'branch_id' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],
                'currency_code' => [
                    'nullable',
                    'string',
                    'size:3',
                    'regex:/^[A-Za-z]{3}$/',
                ],
                'search' => [
                    'nullable',
                    'string',
                    'max:160',
                ],
            ],
        );

        if ($validator->fails()) {
            throw new ValidationException(
                $validator,
            );
        }

        $validated = $validator->validated();

        $supplier = $this->supplier(
            (int) $validated['supplier_id'],
        );

        $normalized = $this->agingService
            ->normalizeExportFilters(
                filters: [
                    'supplier_id' =>
                        $supplier->getKey(),

                    'as_of_date' =>
                        $validated['as_of_date']
                        ?? null,

                    'branch_id' =>
                        $validated['branch_id']
                        ?? null,

                    'currency_code' => isset(
                        $validated['currency_code'],
                    )
                        ? mb_strtoupper(
                            trim(
                                (string) $validated[
                                    'currency_code'
                                ],
                            ),
                        )
                        : null,

                    'search' => trim(
                        (string) (
                            $validated['search']
                            ?? ''
                        ),
                    ),

                    'sort' => 'supplier_name',

                    'direction' => 'asc',

                    'per_page' => 100,
                ],

                actor: $requester,
            );

        $normalized['supplier_id'] =
            (int) $supplier->getKey();

        return $normalized;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function totalRows(
        array $filters,
        User $requester,
    ): int {
        return $this->agingService
            ->exportSupplierDetailTotalRows(
                supplier: $this->supplier(
                    (int) $filters['supplier_id'],
                ),

                filters: $filters,

                actor: $requester,
            );
    }

    /**
     * @param array<string, mixed> $filters
     * @return LazyCollection<int, mixed>
     */
    public function rows(
        array $filters,
        User $requester,
    ): LazyCollection {
        return $this->agingService
            ->exportSupplierDetailRows(
                supplier: $this->supplier(
                    (int) $filters['supplier_id'],
                ),

                filters: $filters,

                actor: $requester,
            );
    }

    /**
     * @return list<string|int|float|null>
     */
    public function mapRow(mixed $row): array
    {
        if (!is_array($row)) {
            throw new LogicException(
                'The Supplier aging exporter received an unsupported row.',
            );
        }

        return [
            (int) $row['id'],
            (int) $row['ledger_entry_id'],
            (string) $row['branch_code'],
            (string) $row['branch_name'],
            (string) $row['item_type_label'],
            (string) $row['entry_type_label'],
            (string) $row['balance_side'],
            $row['document_number'],
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
        ];
    }

    private function supplier(int $supplierId): Supplier
    {
        return Supplier::withTrashed()
            ->whereKey($supplierId)
            ->firstOrFail();
    }
}