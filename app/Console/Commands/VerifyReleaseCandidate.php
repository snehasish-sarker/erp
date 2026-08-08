<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReleaseCandidate;
use App\Models\Tenant;
use App\Services\Operations\ReleaseCandidateService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

final class VerifyReleaseCandidate extends Command
{
    protected $signature = 'erp:release:verify
        {--tenant= : Active tenant ID}
        {--candidate= : Release candidate ID; defaults to the latest candidate}
        {--json : Output machine-readable JSON}';

    protected $description = 'Verify that the current code, routes, migrations, permissions, and frontend build still match a frozen release candidate.';

    public function handle(
        ReleaseCandidateService $service,
        TenantContext $tenantContext,
        PermissionRegistrar $permissionRegistrar,
    ): int {
        $tenantOption = trim(
            (string) $this->option('tenant'),
        );

        if (
            $tenantOption === ''
            || !ctype_digit($tenantOption)
            || (int) $tenantOption < 1
        ) {
            $this->error(
                'The --tenant option is required and must be a positive integer.',
            );

            return self::INVALID;
        }

        $tenantId = (int) $tenantOption;

        $tenant = Tenant::query()
            ->where('status', 'active')
            ->whereKey($tenantId)
            ->first();

        if (!$tenant instanceof Tenant) {
            $this->error(
                'The requested active tenant was not found.',
            );

            return self::FAILURE;
        }

        $report = null;

        try {
            $tenantContext->set($tenant);

            $permissionRegistrar
                ->setPermissionsTeamId(
                    $tenantId,
                );

            $candidateOption = trim(
                (string) $this->option(
                    'candidate',
                ),
            );

            if (
                $candidateOption !== ''
                && (
                    !ctype_digit($candidateOption)
                    || (int) $candidateOption < 1
                )
            ) {
                $this->error(
                    'The --candidate option must be a positive integer.',
                );

                return self::INVALID;
            }

            $candidateQuery =
                ReleaseCandidate::query()
                    ->where(
                        'tenant_id',
                        $tenantId,
                    );

            $candidate =
                $candidateOption === ''
                    ? $candidateQuery
                        ->latest('frozen_at')
                        ->latest('id')
                        ->first()

                    : $candidateQuery
                        ->whereKey(
                            (int) $candidateOption,
                        )
                        ->first();

            if (
                !$candidate
                    instanceof ReleaseCandidate
            ) {
                $this->error(
                    'Release candidate not found for this tenant.',
                );

                return self::FAILURE;
            }

            $report = $service->verify(
                $candidate,
            );
        } catch (
            ValidationException $exception
        ) {
            foreach (
                $exception->errors()
                as $messages
            ) {
                foreach (
                    $messages
                    as $message
                ) {
                    $this->error(
                        $message,
                    );
                }
            }

            return self::FAILURE;
        } finally {
            $permissionRegistrar
                ->setPermissionsTeamId(
                    null,
                );

            $tenantContext->clear();
        }

        if (!is_array($report)) {
            $this->error(
                'Release candidate verification did not return a report.',
            );

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(
                (string) json_encode(
                    $report,
                    JSON_PRETTY_PRINT
                    | JSON_UNESCAPED_SLASHES,
                ),
            );
        } else {
            $this->line(
                'Version: '
                .$report['version'],
            );

            $this->line(
                'Verification: '
                .strtoupper(
                    (string) $report[
                        'verification_status'
                    ],
                ),
            );

            $this->line(
                'Fingerprint: '
                .$report[
                    'project_fingerprint'
                ],
            );

            foreach (
                (
                    $report[
                        'verification_summary'
                    ][
                        'drifted_artifacts'
                    ]
                    ?? []
                )
                as $artifact
            ) {
                $this->error(
                    'Drift: '
                    .(string) (
                        $artifact['label']
                        ?? $artifact['key']
                        ?? 'unknown artifact'
                    ),
                );
            }
        }

        return $report[
            'verification_status'
        ] === 'matched'
            ? self::SUCCESS
            : self::FAILURE;
    }
}