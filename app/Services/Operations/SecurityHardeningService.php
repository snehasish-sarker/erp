<?php

declare(strict_types=1);

namespace App\Services\Operations;

final class SecurityHardeningService
{
    /** @return array<string, mixed> */
    public function run(): array
    {
        $production = app()->environment('production');
        $checks = [];
        $checks[] = $this->check('app.debug', 'Production debug mode', !$production || !(bool) config('app.debug'), true, 'APP_DEBUG must be false in production.');
        $checks[] = $this->check('app.key', 'Application encryption key', trim((string) config('app.key')) !== '', true, 'APP_KEY must be configured.');
        $checks[] = $this->check('app.https', 'Production application URL', !$production || str_starts_with(strtolower((string) config('app.url')), 'https://'), true, 'Production APP_URL should use HTTPS.');
        $checks[] = $this->check('session.secure', 'Secure session cookie', !$production || (bool) config('session.secure'), true, 'Production sessions should use secure cookies.');
        $checks[] = $this->check('session.http_only', 'HTTP-only session cookie', (bool) config('session.http_only', true), true, 'Session cookies should be HTTP-only.');
        $sameSite = strtolower((string) config('session.same_site', 'lax'));
        $checks[] = $this->check('session.same_site', 'Session SameSite policy', in_array($sameSite, ['lax', 'strict'], true), false, 'Prefer lax or strict SameSite protection unless cross-site authentication requires otherwise.');
        
        $checks[] = $this->check(
            'queue.async',
            'Production asynchronous queue',
            !$production
                || (string) config('queue.default') !== 'sync',
            true,
            'Use a durable asynchronous queue in production.',
        );
        
        $checks[] = $this->check('cache.durable', 'Production cache store', !$production || !in_array((string) config('cache.default'), ['array', 'null'], true), false, 'Use a durable shared cache store in production.');
        $backupDisk = (string) config('operations.backups.disk', 'operations_private');
        $backupDiskConfig = config('filesystems.disks.'.$backupDisk);
        $privateBackupDisk = is_array($backupDiskConfig)
            && (string) ($backupDiskConfig['driver'] ?? '') === 'local'
            && (bool) ($backupDiskConfig['serve'] ?? false) === false;
        $checks[] = $this->check('backup.private_disk', 'Non-servable backup storage', $privateBackupDisk, true, 'Database backups must use a private local disk with serving disabled.');

        $criticalFailures = count(array_filter($checks, static fn (array $check): bool => !$check['passed'] && $check['critical']));
        $warnings = count(array_filter($checks, static fn (array $check): bool => !$check['passed'] && !$check['critical']));

        return [
            'checks' => $checks,
            'summary' => [
                'checks' => count($checks),
                'critical_failures' => $criticalFailures,
                'warnings' => $warnings,
                'secure' => $criticalFailures === 0,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function check(string $key, string $label, bool $passed, bool $critical, string $message): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'passed' => $passed,
            'critical' => $critical,
            'message' => $passed ? 'Configuration is acceptable.' : $message,
        ];
    }
}
