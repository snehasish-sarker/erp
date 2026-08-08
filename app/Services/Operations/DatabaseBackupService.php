<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\SystemBackup;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

final class DatabaseBackupService
{
    public function __construct(
        private readonly OperationsRuntimeStateService $runtimeStateService,
    ) {
    }

    public function create(string $initiatedBy = 'manual'): SystemBackup
    {
        $connectionName = (string) config('database.default');
        $connection = config('database.connections.'.$connectionName);
        if (!is_array($connection)) {
            throw new RuntimeException('The default database connection is not configured.');
        }

        $driver = (string) ($connection['driver'] ?? '');
        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Database backup creation currently supports MySQL/MariaDB only.');
        }

        $disk = (string) config('operations.backups.disk', 'operations_private');
        $diskConfig = config('filesystems.disks.'.$disk);
        if (!is_array($diskConfig) || (string) ($diskConfig['driver'] ?? '') !== 'local') {
            throw new RuntimeException('ERP database backups require a local filesystem disk.');
        }

        $database = (string) ($connection['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('The database name is not configured.');
        }

        $directory = trim((string) config('operations.backups.directory', 'backups'), '/');
        $timestamp = now()->utc()->format('Ymd-His');
        $suffix = bin2hex(random_bytes(3));
        $filename = 'erp-'.$this->safeName($database).'-'.$timestamp.'-'.$suffix.'.sql.gz';
        $relativePath = $directory.'/'.$filename;
        $temporaryRelativePath = $relativePath.'.part.sql';

        $backup = SystemBackup::query()->create([
            'scope' => 'database_full',
            'initiated_by' => $initiatedBy,
            'database_connection' => $connectionName,
            'database_name' => $database,
            'disk' => $disk,
            'path' => $relativePath,
            'filename' => $filename,
            'status' => 'processing',
            'verification_status' => 'not_verified',
            'started_at' => now(),
            'metadata' => [
                'driver' => $driver,
                'host' => (string) ($connection['host'] ?? ''),
                'port' => (string) ($connection['port'] ?? ''),
            ],
        ]);

        try {
            $storage = Storage::disk($disk);
            $storage->makeDirectory($directory);
            $sqlPath = $storage->path($temporaryRelativePath);
            $gzipPath = $storage->path($relativePath);

            $binary = (new ExecutableFinder())->find((string) config('operations.backups.mysqldump_binary', 'mysqldump'));
            if ($binary === null) {
                throw new RuntimeException('The mysqldump executable could not be found.');
            }

            $command = [
                $binary,
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                '--host='.(string) ($connection['host'] ?? '127.0.0.1'),
                '--port='.(string) ($connection['port'] ?? '3306'),
                '--user='.(string) ($connection['username'] ?? ''),
                '--result-file='.$sqlPath,
                $database,
            ];

            $environment = [];
            $password = (string) ($connection['password'] ?? '');
            if ($password !== '') {
                $environment['MYSQL_PWD'] = $password;
            }

            $process = new Process($command, null, $environment);
            $process->setTimeout(max(60, (int) config('operations.backups.timeout_seconds', 1800)));
            $process->mustRun();

            $this->compress($sqlPath, $gzipPath);
            @unlink($sqlPath);

            $size = filesize($gzipPath);
            $checksum = hash_file('sha256', $gzipPath);
            if ($size === false || $checksum === false) {
                throw new RuntimeException('The completed backup file could not be measured.');
            }

            $backup->forceFill([
                'size_bytes' => (int) $size,
                'checksum_sha256' => $checksum,
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();

            $this->runtimeStateService->touch('backup.last_completed', [
                'backup_id' => (int) $backup->getKey(),
                'filename' => $backup->filename,
                'size_bytes' => (int) $size,
            ]);

            return $backup->fresh() ?? $backup;
        } catch (Throwable $exception) {
            try {
                $storage = Storage::disk($disk);
                $storage->delete([$temporaryRelativePath, $relativePath]);
            } catch (Throwable) {
                // Preserve the original backup exception.
            }

            $backup->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        }
    }

    /** @return array{passed:bool,message:string} */
    public function verify(SystemBackup $backup): array
    {
        if (!$backup->isCompleted()) {
            return ['passed' => false, 'message' => 'Only completed backups can be verified.'];
        }

        try {
            $storage = Storage::disk($backup->disk);
            if (!$storage->exists($backup->path)) {
                throw new RuntimeException('The backup file does not exist on its configured disk.');
            }

            $absolutePath = $storage->path($backup->path);
            $size = filesize($absolutePath);
            $checksum = hash_file('sha256', $absolutePath);
            if ($size === false || $checksum === false) {
                throw new RuntimeException('The backup file could not be read.');
            }
            if ($backup->size_bytes !== null && (int) $backup->size_bytes !== (int) $size) {
                throw new RuntimeException('Backup size does not match the recorded metadata.');
            }
            if ($backup->checksum_sha256 !== null && !hash_equals($backup->checksum_sha256, $checksum)) {
                throw new RuntimeException('Backup SHA-256 checksum does not match the recorded metadata.');
            }

            $handle = gzopen($absolutePath, 'rb');
            if ($handle === false) {
                throw new RuntimeException('The gzip backup could not be opened.');
            }

            $preview = '';
            while (!gzeof($handle)) {
                $chunk = gzread($handle, 8192);
                if ($chunk === false) {
                    gzclose($handle);
                    throw new RuntimeException('The gzip stream could not be read completely.');
                }
                if (strlen($preview) < 65536) {
                    $preview .= $chunk;
                }
            }
            gzclose($handle);

            if ($preview === '' || (!str_contains($preview, 'MySQL dump') && !str_contains($preview, 'CREATE TABLE') && !str_contains($preview, 'INSERT INTO'))) {
                throw new RuntimeException('The decompressed backup does not look like a MySQL SQL dump.');
            }

            $message = 'File exists, checksum matches, gzip integrity is readable, and SQL dump markers were detected.';
            $backup->forceFill([
                'verification_status' => 'passed',
                'verification_message' => $message,
                'verified_at' => now(),
            ])->save();

            $this->runtimeStateService->touch('backup.last_verified', [
                'backup_id' => (int) $backup->getKey(),
                'filename' => $backup->filename,
            ]);

            return ['passed' => true, 'message' => $message];
        } catch (Throwable $exception) {
            report($exception);
            $message = mb_substr($exception->getMessage(), 0, 500);
            $backup->forceFill([
                'verification_status' => 'failed',
                'verification_message' => $message,
                'verified_at' => now(),
            ])->save();

            return ['passed' => false, 'message' => $message];
        }
    }

    private function compress(string $sourcePath, string $targetPath): void
    {
        $source = fopen($sourcePath, 'rb');
        if ($source === false) {
            throw new RuntimeException('Temporary SQL dump could not be opened.');
        }
        $target = gzopen($targetPath, 'wb9');
        if ($target === false) {
            fclose($source);
            throw new RuntimeException('Compressed backup file could not be created.');
        }

        try {
            while (!feof($source)) {
                $chunk = fread($source, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('Temporary SQL dump could not be read.');
                }
                if ($chunk !== '' && gzwrite($target, $chunk) === false) {
                    throw new RuntimeException('Compressed backup file could not be written.');
                }
            }
        } finally {
            fclose($source);
            gzclose($target);
        }
    }

    private function safeName(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value);

        return trim((string) $safe, '-');
    }
}
