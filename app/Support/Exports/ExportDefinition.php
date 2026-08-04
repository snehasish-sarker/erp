<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\User;
use Illuminate\Support\LazyCollection;

interface ExportDefinition
{
    public function key(): string;

    public function label(): string;

    public function requiredPermission(): string;

    public function isSelectableFromExportCenter(): bool;

    /**
     * @return list<string>
     */
    public function headings(): array;

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function validateFilters(
        array $filters,
        User $requester,
    ): array;

    /**
     * @param array<string, mixed> $filters
     */
    public function totalRows(
        array $filters,
        User $requester,
    ): int;

    /**
     * @param array<string, mixed> $filters
     * @return LazyCollection<int, mixed>
     */
    public function rows(
        array $filters,
        User $requester,
    ): LazyCollection;

    /**
     * @return list<string|int|float|null>
     */
    public function mapRow(mixed $row): array;
}