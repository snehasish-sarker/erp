<?php

declare(strict_types=1);

namespace App\Services\Operations;

use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

final class ReleaseFingerprintService
{
    /** @return array{fingerprint:string,payload:array<string, mixed>,artifacts:list<array{key:string,label:string,sha256:?string,metadata:array<string,mixed>|null}>} */
    public function capture(): array
    {
        $sourceHash = $this->sourceHash();
        $routeHash = $this->routeHash();
        $migrationFilesHash = $this->filesHash($this->filesUnder(base_path('database/migrations')));
        $migrationStateHash = $this->migrationStateHash();
        $permissionHash = $this->permissionHash();
        $composerLockHash = $this->fileHash(base_path('composer.lock'));
        $frontendLock = $this->frontendLock();
        $viteManifestHash = $this->fileHash(public_path('build/manifest.json'));
        $gitCommit = $this->gitCommit();

        $fingerprintMaterial = [
            'source_sha256' => $sourceHash,
            'routes_sha256' => $routeHash,
            'migration_files_sha256' => $migrationFilesHash,
            'migration_state_sha256' => $migrationStateHash,
            'permissions_sha256' => $permissionHash,
            'composer_lock_sha256' => $composerLockHash,
            'frontend_lock_file' => $frontendLock['file'],
            'frontend_lock_sha256' => $frontendLock['sha256'],
            'vite_manifest_sha256' => $viteManifestHash,
        ];

        $payload = [
            'capture_environment' => app()->environment(),
            'php_version' => PHP_VERSION,
            'git_commit' => $gitCommit,
            ...$fingerprintMaterial,
        ];

        $fingerprint = hash('sha256', (string) json_encode(
            $fingerprintMaterial,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));

        return [
            'fingerprint' => $fingerprint,
            'payload' => $payload,
            'artifacts' => [
                $this->artifact('source', 'ERP application source', $sourceHash),
                $this->artifact('routes', 'Registered route map', $routeHash),
                $this->artifact('migration_files', 'Migration source files', $migrationFilesHash),
                $this->artifact('migration_state', 'Applied migration state', $migrationStateHash),
                $this->artifact('permissions', 'Seeded permission registry', $permissionHash),
                $this->artifact('composer_lock', 'Composer lock file', $composerLockHash),
                $this->artifact(
                    'frontend_lock',
                    'Frontend dependency lock file',
                    $frontendLock['sha256'],
                    $frontendLock['file'] === null ? null : ['file' => $frontendLock['file']],
                ),
                $this->artifact('vite_manifest', 'Production Vite manifest', $viteManifestHash),
                $this->artifact(
                    'git_commit',
                    'Git commit (informational)',
                    $gitCommit === null ? null : hash('sha256', $gitCommit),
                    $gitCommit === null ? ['informational' => true] : ['informational' => true, 'commit' => $gitCommit],
                ),
            ],
        ];
    }

    /** @return list<string> */
    private function sourceFiles(): array
    {
        $files = [];
        foreach ([
            'app',
            'bootstrap',
            'config',
            'database/migrations',
            'database/seeders',
            'resources/js',
            'resources/css',
            'routes',
        ] as $directory) {
            $files = [...$files, ...$this->filesUnder(base_path($directory))];
        }

        foreach ([
            'composer.json',
            'package.json',
            'tsconfig.json',
            'tsconfig.app.json',
            'tsconfig.node.json',
            'vite.config.js',
            'vite.config.ts',
            'postcss.config.js',
            'postcss.config.mjs',
            'eslint.config.js',
            'eslint.config.mjs',
            'tailwind.config.js',
            'tailwind.config.ts',
        ] as $rootFile) {
            $path = base_path($rootFile);
            if (is_file($path)) {
                $files[] = $path;
            }
        }

        sort($files, SORT_STRING);

        return array_values(array_unique($files));
    }

    private function sourceHash(): string
    {
        return $this->filesHash($this->sourceFiles());
    }

    /** @return list<string> */
    private function filesUnder(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /** @param list<string> $files */
    private function filesHash(array $files): string
    {
        $context = hash_init('sha256');

        foreach ($files as $file) {
            if (!is_file($file) || !is_readable($file)) {
                continue;
            }

            $relative = $this->relativePath($file);
            hash_update($context, $relative."\0".(hash_file('sha256', $file) ?: '')."\n");
        }

        return hash_final($context);
    }

    private function routeHash(): string
    {
        $rows = [];

        foreach (Route::getRoutes() as $route) {
            if (!$route instanceof IlluminateRoute) {
                continue;
            }

            $action = $route->getActionName();
            if (!str_starts_with($route->uri(), 'erp/') && !str_starts_with($action, 'App\\')) {
                continue;
            }

            $methods = $route->methods();
            sort($methods, SORT_STRING);
            $middleware = array_map(
                static fn (mixed $item): string => is_string($item) ? $item : get_debug_type($item),
                $route->gatherMiddleware(),
            );
            sort($middleware, SORT_STRING);

            $rows[] = [
                'methods' => $methods,
                'uri' => $route->uri(),
                'name' => $route->getName(),
                'action' => $action,
                'middleware' => $middleware,
            ];
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => strcmp(
                (string) ($left['name'] ?? '').'|'.$left['uri'].'|'.implode(',', $left['methods']),
                (string) ($right['name'] ?? '').'|'.$right['uri'].'|'.implode(',', $right['methods']),
            ),
        );

        return hash('sha256', (string) json_encode(
            $rows,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));
    }

    private function permissionHash(): ?string
    {
        if (!Schema::hasTable('permissions')) {
            return null;
        }

        try {
            $rows = DB::table('permissions')
                ->orderBy('guard_name')
                ->orderBy('name')
                ->get(['guard_name', 'name'])
                ->map(static fn (object $row): string => (string) $row->guard_name.':'.(string) $row->name)
                ->all();

            return hash('sha256', implode("\n", $rows));
        } catch (Throwable) {
            return null;
        }
    }

    private function migrationStateHash(): ?string
    {
        if (!Schema::hasTable('migrations')) {
            return null;
        }

        try {
            $rows = DB::table('migrations')
                ->orderBy('migration')
                ->pluck('migration')
                ->map(static fn (mixed $migration): string => (string) $migration)
                ->all();

            return hash('sha256', implode("\n", $rows));
        } catch (Throwable) {
            return null;
        }
    }

    /** @return array{file:?string,sha256:?string} */
    private function frontendLock(): array
    {
        foreach (['package-lock.json', 'pnpm-lock.yaml', 'yarn.lock', 'bun.lockb', 'bun.lock'] as $name) {
            $path = base_path($name);
            if (is_file($path)) {
                return ['file' => $name, 'sha256' => $this->fileHash($path)];
            }
        }

        return ['file' => null, 'sha256' => null];
    }

    private function fileHash(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $hash = hash_file('sha256', $path);

        return $hash === false ? null : $hash;
    }

    private function gitCommit(): ?string
    {
        $gitDirectory = base_path('.git');
        $headPath = $gitDirectory.'/HEAD';
        if (!is_file($headPath) || !is_readable($headPath)) {
            return null;
        }

        $head = trim((string) file_get_contents($headPath));
        if ($head === '') {
            return null;
        }

        if (!str_starts_with($head, 'ref: ')) {
            return preg_match('/^[a-f0-9]{40}$/i', $head) === 1 ? strtolower($head) : null;
        }

        $ref = trim(substr($head, 5));
        $refPath = $gitDirectory.'/'.$ref;
        if (is_file($refPath) && is_readable($refPath)) {
            $commit = trim((string) file_get_contents($refPath));
            return preg_match('/^[a-f0-9]{40}$/i', $commit) === 1 ? strtolower($commit) : null;
        }

        $packedRefs = $gitDirectory.'/packed-refs';
        if (!is_file($packedRefs) || !is_readable($packedRefs)) {
            return null;
        }

        foreach (file($packedRefs, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '^')) {
                continue;
            }
            [$commit, $candidateRef] = array_pad(preg_split('/\s+/', trim($line), 2) ?: [], 2, null);
            if ($candidateRef === $ref && is_string($commit) && preg_match('/^[a-f0-9]{40}$/i', $commit) === 1) {
                return strtolower($commit);
            }
        }

        return null;
    }

    private function relativePath(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
        $normalized = str_replace('\\', '/', $path);

        return str_starts_with($normalized, $base) ? substr($normalized, strlen($base)) : $normalized;
    }

    /** @return array{key:string,label:string,sha256:?string,metadata:array<string,mixed>|null} */
    private function artifact(string $key, string $label, ?string $sha256, ?array $metadata = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'sha256' => $sha256,
            'metadata' => $metadata,
        ];
    }
}
