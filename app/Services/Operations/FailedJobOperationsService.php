<?php

declare(strict_types=1);

namespace App\Services\Operations;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class FailedJobOperationsService
{
    /** @return LengthAwarePaginator<int, array<string, mixed>> */
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        if (!Schema::hasTable('failed_jobs')) {
            return new LengthAwarePaginator([], 0, $perPage);
        }

        $paginator = DB::table('failed_jobs')
            ->select(['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->orderByDesc('failed_at')
            ->paginate(max(10, min(100, $perPage)));

        $paginator->setCollection(
            $paginator->getCollection()->map(fn (object $row): array => [
                'uuid' => (string) $row->uuid,
                'connection' => (string) $row->connection,
                'queue' => (string) $row->queue,
                'job' => $this->jobName((string) $row->payload),
                'exception_class' => $this->exceptionClass((string) $row->exception),
                'failed_at' => (string) $row->failed_at,
            ]),
        );

        /** @var LengthAwarePaginator<int, array<string, mixed>> $paginator */
        return $paginator;
    }

    public function retry(string $uuid): void
    {
        $this->ensureExists($uuid);
        $exitCode = Artisan::call('queue:retry', ['id' => [$uuid]]);
        if ($exitCode !== 0) {
            throw new RuntimeException('Laravel could not retry the selected failed job.');
        }
    }

    public function forget(string $uuid): void
    {
        $this->ensureExists($uuid);
        $exitCode = Artisan::call('queue:forget', ['id' => $uuid]);
        if ($exitCode !== 0) {
            throw new RuntimeException('Laravel could not forget the selected failed job.');
        }
    }

    private function ensureExists(string $uuid): void
    {
        if (!Schema::hasTable('failed_jobs') || !DB::table('failed_jobs')->where('uuid', $uuid)->exists()) {
            throw new RuntimeException('The selected failed job no longer exists.');
        }
    }

    private function jobName(string $payload): string
    {
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return 'Unknown job';
        }

        $name = $decoded['displayName'] ?? $decoded['job'] ?? 'Unknown job';

        return is_string($name) ? $name : 'Unknown job';
    }

    private function exceptionClass(string $exception): string
    {
        $firstLine = strtok($exception, "\n");
        if (!is_string($firstLine) || trim($firstLine) === '') {
            return 'Unknown exception';
        }

        $class = trim((string) strstr($firstLine, ':', true));

        return $class !== '' ? $class : 'Application exception';
    }
}
