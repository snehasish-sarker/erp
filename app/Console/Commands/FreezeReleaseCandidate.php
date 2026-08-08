<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Operations\ReleaseCandidateService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

final class FreezeReleaseCandidate extends Command
{
    protected $signature = 'erp:release:freeze
        {version : Release-candidate version, for example 1.0.0-rc.1}
        {--tenant= : Active tenant ID}
        {--notes= : Optional release notes}
        {--json : Output machine-readable JSON}';

    protected $description = 'Freeze a release candidate only when the current project exactly matches the latest passed production acceptance run.';

    public function handle(
        ReleaseCandidateService $service,
        TenantContext $tenantContext,
        PermissionRegistrar $permissionRegistrar,
    ): int {
        $tenantOption = trim((string) $this->option('tenant'));
        if ($tenantOption === '' || !ctype_digit($tenantOption) || (int) $tenantOption < 1) {
            $this->error('The --tenant option is required and must be a positive integer.');
            return self::INVALID;
        }

        $tenant = Tenant::query()
            ->where('status', 'active')
            ->whereKey((int) $tenantOption)
            ->first();

        if (!$tenant instanceof Tenant) {
            $this->error('The requested active tenant was not found.');
            return self::FAILURE;
        }

        $version = trim((string) $this->argument('version'));
        if ($version === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $version) !== 1) {
            $this->error('The version may contain only letters, numbers, dots, underscores, and hyphens.');
            return self::INVALID;
        }

        $notes = $this->option('notes') === null ? null : trim((string) $this->option('notes'));
        if ($notes !== null && mb_strlen($notes) > 2000) {
            $this->error('The --notes value may not exceed 2000 characters.');
            return self::INVALID;
        }

        try {
            $tenantContext->set($tenant);
            $permissionRegistrar->setPermissionsTeamId((int) $tenant->getKey());

            $candidate = $service->freeze(
                actor: null,
                version: $version,
                notes: $notes,
                source: 'cli',
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }
            return self::FAILURE;
        } finally {
            $permissionRegistrar->setPermissionsTeamId(null);
            $tenantContext->clear();
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($candidate, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->info('Release candidate frozen.');
        $this->line('Version: '.$candidate['version']);
        $this->line('Fingerprint: '.$candidate['project_fingerprint']);
        $this->line('Acceptance run: #'.(string) ($candidate['acceptance']['id'] ?? '—'));

        return self::SUCCESS;
    }
}