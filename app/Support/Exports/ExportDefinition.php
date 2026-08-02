<?php

declare(strict_types=1);

namespace App\Support\Exports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;

interface ExportDefinition
{
    public function key(): string;

    public function label(): string;

    public function requiredPermission(): string;

    /**
     * @return list<string>
     */
    public function headings(): array;

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function validateFilters(array $filters): array;

    /**
     * @param array<string, mixed> $filters
     */
    public function totalRows(array $filters): int;

    /**
     * @param array<string, mixed> $filters
     * @return LazyCollection<int, Model>
     */
    public function rows(array $filters): LazyCollection;

    /**
     * @return list<string|int|float|null>
     */
    public function mapRow(Model $model): array;
}