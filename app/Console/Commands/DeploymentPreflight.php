<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Operations\DeploymentPreflightService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

final class DeploymentPreflight extends Command
{
    protected $signature = 'erp:deploy:preflight
        {--tenant= : Check only one active tenant ID}
        {--json : Output machine-readable JSON}';

    protected $description =
        'Run ERP production-readiness and live operations preflight checks before deployment.';

    public function handle(
        DeploymentPreflightService $preflightService,
        TenantContext $tenantContext,
        PermissionRegistrar $permissionRegistrar,
    ): int {
        $tenantOption = trim(
            (string) $this->option('tenant'),
        );

        if (
            $tenantOption !== ''
            && (
                !ctype_digit($tenantOption)
                || (int) $tenantOption < 1
            )
        ) {
            $this->error(
                'The --tenant option must be a positive integer.',
            );

            return self::INVALID;
        }

        $query = Tenant::query()
            ->where(
                'status',
                'active',
            )
            ->orderBy('id');

        if ($tenantOption !== '') {
            $query->whereKey(
                (int) $tenantOption,
            );
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $message = $tenantOption !== ''
                ? 'The requested active tenant was not found.'
                : 'No active tenants were found. Deployment preflight cannot pass without at least one active tenant.';

            if ($this->option('json')) {
                $this->line(
                    (string) json_encode(
                        [
                            'status' => 'failed',
                            'message' => $message,
                            'reports' => [],
                        ],
                        JSON_PRETTY_PRINT
                        | JSON_UNESCAPED_SLASHES,
                    ),
                );
            } else {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $reports = [];
        $exitCode = self::SUCCESS;

        try {
            foreach ($tenants as $tenant) {
                if (!$tenant instanceof Tenant) {
                    continue;
                }

                $tenantId = (int) $tenant->getKey();

                $tenantContext->set($tenant);

                $permissionRegistrar
                    ->setPermissionsTeamId(
                        $tenantId,
                    );

                $report = $preflightService->run();

                $reports[] = [
                    'tenant' => [
                        'id' => $tenantId,
                        'code' => $tenant->code,
                        'name' => $tenant->name,
                    ],
                    'report' => $report,
                ];

                if (
                    ($report['ready'] ?? false)
                    !== true
                ) {
                    $exitCode = self::FAILURE;
                }
            }
        } finally {
            $permissionRegistrar
                ->setPermissionsTeamId(null);

            $tenantContext->clear();
        }

        if ($reports === []) {
            $message =
                'Deployment preflight did not validate any active tenant.';

            if ($this->option('json')) {
                $this->line(
                    (string) json_encode(
                        [
                            'status' => 'failed',
                            'message' => $message,
                            'reports' => [],
                        ],
                        JSON_PRETTY_PRINT
                        | JSON_UNESCAPED_SLASHES,
                    ),
                );
            } else {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(
                (string) json_encode(
                    $reports,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES,
                ),
            );

            return $exitCode;
        }

        foreach ($reports as $entry) {
            $this->newLine();

            $this->info(
                sprintf(
                    'Tenant %s — %s',
                    (string) $entry['tenant']['code'],
                    (string) $entry['tenant']['name'],
                ),
            );

            $report = $entry['report'];

            $this->line(
                (
                    ($report['ready'] ?? false)
                    ? '[READY]'
                    : '[BLOCKED]'
                )
                .' deployment preflight',
            );

            $health =
                $report['operations_health']['summary']
                ?? [];

            $this->line(
                sprintf(
                    'Operations health: %d critical failure(s), %d warning(s).',
                    (int) (
                        $health['critical_failures']
                        ?? 0
                    ),
                    (int) (
                        $health['warnings']
                        ?? 0
                    ),
                ),
            );

            $readiness =
                $report['production_readiness']['summary']
                ?? [];

            $this->line(
                sprintf(
                    'Production readiness: %d blocking failure(s), %d warning(s).',
                    (int) (
                        $readiness['blocking_failures']
                        ?? 0
                    ),
                    (int) (
                        $readiness['warnings']
                        ?? 0
                    ),
                ),
            );

            $security =
                $report['security']['summary']
                ?? null;

            if (is_array($security)) {
                $this->line(
                    sprintf(
                        'Security hardening: %d critical failure(s), %d warning(s).',
                        (int) (
                            $security['critical_failures']
                            ?? 0
                        ),
                        (int) (
                            $security['warnings']
                            ?? 0
                        ),
                    ),
                );
            }
        }

        return $exitCode;
    }
}