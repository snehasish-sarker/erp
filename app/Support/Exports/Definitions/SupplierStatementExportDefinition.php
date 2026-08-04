<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\SupplierStatementService;
use App\Support\Exports\ExportDefinition;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator as LaravelValidator;
use LogicException;

final class SupplierStatementExportDefinition implements ExportDefinition
{
    public function __construct(
        private readonly SupplierStatementService $statementService,
    ) {
    }

    public function key(): string
    {
        return 'supplier_statement';
    }

    public function label(): string
    {
        return 'Supplier Statement';
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
            'Row Type',
            'Supplier Code',
            'Supplier Name',
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
                'date_from' => [
                    'nullable',
                    'date_format:Y-m-d',
                ],
                'date_to' => [
                    'nullable',
                    'date_format:Y-m-d',
                ],
            ],
        );

        $validator->after(
            static function (
                LaravelValidator $validator,
            ) use ($filters): void {
                $dateFrom = trim(
                    (string) (
                        $filters['date_from']
                        ?? ''
                    ),
                );

                $dateTo = trim(
                    (string) (
                        $filters['date_to']
                        ?? ''
                    ),
                );

                if (
                    $dateFrom !== ''
                    && $dateTo !== ''
                    && $dateTo < $dateFrom
                ) {
                    $validator->errors()->add(
                        'date_to',
                        'The ending date must be on or after the starting date.',
                    );
                }
            },
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

        return $this->statementService
            ->normalizeExportFilters(
                supplier: $supplier,
                actor: $requester,
                filters: [
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

                    'date_from' =>
                        $validated['date_from']
                        ?? null,

                    'date_to' =>
                        $validated['date_to']
                        ?? null,

                    'per_page' => 100,
                ],
            );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function totalRows(
        array $filters,
        User $requester,
    ): int {
        return $this->statementService
            ->exportTotalRows(
                supplier: $this->supplier(
                    (int) $filters['supplier_id'],
                ),
                actor: $requester,
                filters: $filters,
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
        return $this->statementService
            ->exportRows(
                supplier: $this->supplier(
                    (int) $filters['supplier_id'],
                ),
                actor: $requester,
                filters: $filters,
            );
    }

    /**
     * @return list<string|int|float|null>
     */
    public function mapRow(mixed $row): array
    {
        if (!is_array($row)) {
            throw new LogicException(
                'The Supplier Statement exporter received an unsupported row.',
            );
        }

        $rowType = (string) (
            $row['row_type'] ?? ''
        );

        if (
            !in_array(
                $rowType,
                [
                    'base_summary',
                    'currency_summary',
                    'entry',
                ],
                true,
            )
        ) {
            throw new LogicException(
                'The Supplier Statement exporter received an unsupported statement row type.',
            );
        }

        return [
            $rowType,
            $row['supplier_code'] ?? null,
            $row['supplier_name'] ?? null,
            $row['date_from'] ?? null,
            $row['date_to'] ?? null,
            $row['posting_date'] ?? null,
            $row['document_date'] ?? null,
            $row['due_date'] ?? null,
            $row['reference'] ?? null,
            $row['journal_reference'] ?? null,
            $row['entry_type_label'] ?? null,
            $row['source_document_number'] ?? null,
            $row['branch_id'] ?? null,
            $row['branch_code'] ?? null,
            $row['branch_name'] ?? null,
            $row['currency_code'] ?? null,
            $row['exchange_rate'] ?? null,
            $row['opening_balance'] ?? null,

            $row['period_debit']
                ?? $row['debit_amount']
                ?? null,

            $row['period_credit']
                ?? $row['credit_amount']
                ?? null,

            $row['closing_balance']
                ?? $row['currency_running_balance']
                ?? null,

            $row['base_debit_amount'] ?? null,
            $row['base_credit_amount'] ?? null,
            $row['base_running_balance'] ?? null,
            $row['description'] ?? null,
        ];
    }

    private function supplier(int $supplierId): Supplier
    {
        return Supplier::withTrashed()
            ->whereKey($supplierId)
            ->firstOrFail();
    }
}