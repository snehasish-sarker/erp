<?php

declare(strict_types=1);

namespace App\Support\Exports\Definitions;

use App\Models\User;
use App\Services\Accounting\FinancialStatementService;
use App\Support\Exports\ExportDefinition;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

abstract class AbstractFinancialStatementExportDefinition implements ExportDefinition
{
    public function __construct(protected readonly FinancialStatementService $service)
    {
    }

    public function requiredPermission(): string
    {
        return 'financial_statements.view';
    }

    public function isSelectableFromExportCenter(): bool
    {
        return false;
    }

    public function validateFilters(array $filters, User $requester): array
    {
        $validator = Validator::make($filters, [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'as_of_date' => ['nullable', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'integer', 'min:1'],
            'comparison' => ['nullable', Rule::in(['none', 'previous_period', 'previous_year'])],
            'method' => ['nullable', Rule::in(['direct', 'indirect'])],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function totalRows(array $filters, User $requester): int
    {
        return count($this->exportRows($filters, $requester));
    }

    public function rows(array $filters, User $requester): LazyCollection
    {
        $rows = $this->exportRows($filters, $requester);

        return LazyCollection::make(static function () use ($rows): \Generator {
            foreach ($rows as $row) {
                yield $row;
            }
        });
    }

    public function mapRow(mixed $row): array
    {
        return is_array($row) ? array_values($row) : [];
    }

    /** @param array<string, mixed> $filters @return list<array<int, string|int|float|null>> */
    abstract protected function exportRows(array $filters, User $requester): array;
}
