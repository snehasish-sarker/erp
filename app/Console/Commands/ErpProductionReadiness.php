<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Management\ProductionReadinessService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Spatie\Permission\PermissionRegistrar;

final class ErpProductionReadiness extends Command
{
    protected $signature = 'erp:production-readiness
        {--tenant= : Audit only one tenant ID}
        {--json : Output machine-readable JSON}';

    protected $description =
        'Run read-only ERP production-readiness checks.';

    public function handle(
        ProductionReadinessService $service,
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
            ->where('status', 'active')
            ->orderBy('id');

        if ($tenantOption !== '') {
            $query->whereKey(
                (int) $tenantOption,
            );
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            if ($this->option('json')) {
                $this->line(
                    (string) json_encode(
                        [
                            'status' => 'failed',
                            'message' => $tenantOption !== ''
                                ? 'The requested active tenant was not found.'
                                : 'No active tenants were found. Production readiness cannot pass without at least one active tenant.',
                            'reports' => [],
                        ],
                        JSON_PRETTY_PRINT
                        | JSON_UNESCAPED_SLASHES,
                    ),
                );
            } else {
                $this->error(
                    $tenantOption !== ''
                        ? 'The requested active tenant was not found.'
                        : 'No active tenants were found. Production readiness cannot pass without at least one active tenant.',
                );
            }

            return self::FAILURE;
        }

        $reports = [];
        $exit = self::SUCCESS;

        try {
            foreach ($tenants as $tenant) {
                $tenantId = (int) $tenant->getKey();

                $tenantContext->set($tenant);

                $permissionRegistrar
                    ->setPermissionsTeamId(
                        $tenantId,
                    );

                $report = $service->audit();

                $reports[] = [
                    'tenant' => [
                        'id' => $tenantId,
                        'code' => $tenant->code,
                        'name' => $tenant->name,
                    ],
                    'report' => $report,
                ];

                if (
                    ($report['summary']['ready'] ?? false)
                    !== true
                ) {
                    $exit = self::FAILURE;
                }
            }
        } finally {
            $permissionRegistrar
                ->setPermissionsTeamId(null);

            $tenantContext->clear();
        }

        if ($this->option('json')) {
            $this->line(
                (string) json_encode(
                    $reports,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES,
                ),
            );

            return $exit;
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

            foreach (
                $entry['report']['checks']
                as $check
            ) {
                $marker = $check['status'] === 'passed'
                    ? 'PASS'
                    : (
                        $check['blocking']
                            ? 'FAIL'
                            : 'WARN'
                    );

                $this->line(
                    sprintf(
                        '[%s] %s: %s',
                        $marker,
                        (string) $check['label'],
                        (string) $check['message'],
                    ),
                );
            }

            $summary =
                $entry['report']['summary'];

            $this->line(
                sprintf(
                    'Summary: %d passed, %d blocking failure(s), %d warning(s).',
                    (int) $summary['passed'],
                    (int) $summary['blocking_failures'],
                    (int) $summary['warnings'],
                ),
            );
        }

        return $exit;
    }
}