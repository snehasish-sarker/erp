<?php

declare(strict_types=1);

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use Throwable;

final class DatabasePerformanceDiagnosticsService
{
    /** @return array<string, mixed> */
    public function run(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $result = [
            'driver' => $driver,
            'version' => null,
            'database_size_bytes' => null,
            'top_tables' => [],
            'long_running_queries' => [],
            'notes' => [],
        ];

        try {
            $versionRow = DB::selectOne('SELECT VERSION() AS version');
            $result['version'] = is_object($versionRow) ? (string) ($versionRow->version ?? '') : null;
        } catch (Throwable $exception) {
            report($exception);
            $result['notes'][] = 'Database version could not be read.';
        }

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            $result['notes'][] = 'Detailed performance diagnostics are currently implemented for MySQL/MariaDB.';

            return $result;
        }

        $databaseName = (string) config('database.connections.'.config('database.default').'.database', '');
        if ($databaseName === '') {
            $result['notes'][] = 'Database name is not configured.';

            return $result;
        }

        try {
            $size = DB::selectOne(
                'SELECT COALESCE(SUM(data_length + index_length), 0) AS bytes FROM information_schema.tables WHERE table_schema = ?',
                [$databaseName],
            );
            $result['database_size_bytes'] = is_object($size) ? (int) ($size->bytes ?? 0) : null;
        } catch (Throwable $exception) {
            report($exception);
            $result['notes'][] = 'Database size could not be calculated.';
        }

        try {
            $rows = DB::select(
                'SELECT table_name, table_rows, data_length, index_length '
                .'FROM information_schema.tables WHERE table_schema = ? '
                .'ORDER BY (data_length + index_length) DESC LIMIT 12',
                [$databaseName],
            );
            $result['top_tables'] = array_map(
                static fn (object $row): array => [
                    'table' => (string) ($row->table_name ?? ''),
                    'estimated_rows' => (int) ($row->table_rows ?? 0),
                    'data_bytes' => (int) ($row->data_length ?? 0),
                    'index_bytes' => (int) ($row->index_length ?? 0),
                ],
                $rows,
            );
        } catch (Throwable $exception) {
            report($exception);
            $result['notes'][] = 'Table-size diagnostics could not be read from information_schema.';
        }

        try {
            $seconds = max(1, (int) config('operations.health.long_query_seconds', 30));
            $rows = DB::select(
                'SELECT id, user, time, state, UPPER(SUBSTRING_INDEX(TRIM(info), \' \', 1)) AS statement_type '
                .'FROM information_schema.processlist WHERE db = ? AND command <> ? AND time >= ? '
                .'ORDER BY time DESC LIMIT 20',
                [$databaseName, 'Sleep', $seconds],
            );
            $result['long_running_queries'] = array_map(
                static fn (object $row): array => [
                    'connection_id' => (int) ($row->id ?? 0),
                    'user' => (string) ($row->user ?? ''),
                    'seconds' => (int) ($row->time ?? 0),
                    'state' => (string) ($row->state ?? ''),
                    'statement_type' => (string) ($row->statement_type ?? 'UNKNOWN'),
                ],
                $rows,
            );
        } catch (Throwable $exception) {
            report($exception);
            $result['notes'][] = 'Long-running-query diagnostics require information_schema process privileges.';
        }

        return $result;
    }
}
