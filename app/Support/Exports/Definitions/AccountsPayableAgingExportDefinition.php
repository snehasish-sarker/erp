<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;
use App\Services\Accounting\AccountsPayableAgingService;
use App\Support\Exports\ExportDefinition;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LogicException;

final class AccountsPayableAgingExportDefinition implements ExportDefinition
{
    public function __construct(
        private readonly AccountsPayableAgingService $agingService,
    ) {
    }

    public function key(): string
    {
        return 'accounts_payable_aging';
    }

    public function label(): string
    {
        return 'Accounts Payable Aging';
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
            'Supplier ID',
            'Supplier Code',
            'Supplier Name',
            'Supplier Status',
            'Gross Payable (Base)',
            'Unapplied Credit (Base)',
            'Net Outstanding (Base)',
            'Current (Base)',
            '1-30 Days (Base)',
            '31-60 Days (Base)',
            '61-90 Days (Base)',
            '91-120 Days (Base)',
            'Over 120 Days (Base)',
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
                'as_of_date' => [
                    'nullable',
                    'date_format:Y-m-d',
                ],
                'branch_id' => [
                    'nullable',
                    'integer',
                    'min:1',
                ],
                'supplier_id' => [
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
                'sort' => [
                    'nullable',
                    'string',
                    Rule::in([
                        'supplier_name',
                        'total_payable',
                        'unapplied_credit',
                        'net_outstanding',
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
                    Rule::in([
                        'asc',
                        'desc',
                    ]),
                ],
            ],
        );

        if ($validator->fails()) {
            throw new ValidationException(
                $validator,
            );
        }

        $validated = $validator->validated();

        return $this->agingService
            ->normalizeExportFilters(
                filters: [
                    'as_of_date' =>
                        $validated['as_of_date']
                        ?? null,
                    'branch_id' =>
                        $validated['branch_id']
                        ?? null,
                    'supplier_id' =>
                        $validated['supplier_id']
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
                    'sort' =>
                        $validated['sort']
                        ?? 'net_outstanding',
                    'direction' =>
                        $validated['direction']
                        ?? 'desc',
                    'per_page' => 100,
                ],
                actor: $requester,
            );
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function totalRows(
        array $filters,
        User $requester,
    ): int {
        return $this->agingService
            ->exportSummaryTotalRows(
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
            ->exportSummaryRows(
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
                'The Accounts Payable aging exporter received an unsupported row.',
            );
        }

        return [
            (int) $row['supplier_id'],
            (string) $row['supplier_code'],
            (string) $row['supplier_name'],
            (string) $row['supplier_status'],
            (string) $row['total_payable'],
            (string) $row['unapplied_credit'],
            (string) $row['net_outstanding'],
            (string) $row['current'],
            (string) $row['days_1_30'],
            (string) $row['days_31_60'],
            (string) $row['days_61_90'],
            (string) $row['days_91_120'],
            (string) $row['days_over_120'],
        ];
    }
}