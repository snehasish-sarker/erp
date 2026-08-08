<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\ProductionAcceptanceRun;
use App\Models\ReleaseCandidate;
use App\Models\ReleaseCandidateArtifact;
use App\Models\User;
use App\Support\Operations\ReleaseCandidateStatusRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReleaseCandidateService
{
    public function __construct(
        private readonly ReleaseFingerprintService $fingerprintService,
        private readonly TenantContext $tenantContext,
    ) {
    }

    /** @return array<string, mixed> */
    public function freeze(
        ?User $actor,
        string $version,
        ?string $notes = null,
        string $source = 'web',
    ): array {
        $tenantId = $this->activeTenantId();

        if (
            $actor instanceof User
            && (int) $actor->tenant_id !== $tenantId
        ) {
            throw ValidationException::withMessages([
                'tenant' => [
                    'The user does not belong to the active tenant.',
                ],
            ]);
        }

        $acceptance = ProductionAcceptanceRun::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('completed_at')
            ->latest('completed_at')
            ->latest('id')
            ->first();

        if (
            !$acceptance instanceof ProductionAcceptanceRun
            || $acceptance->status !== 'passed'
            || $acceptance->project_fingerprint === null
        ) {
            throw ValidationException::withMessages([
                'acceptance' => [
                    'The latest production acceptance run for this tenant must be PASSED and fingerprinted before freezing a release candidate.',
                ],
            ]);
        }

        $current = $this->fingerprintService->capture();

        if (
            !hash_equals(
                (string) $acceptance->project_fingerprint,
                $current['fingerprint'],
            )
        ) {
            throw ValidationException::withMessages([
                'acceptance' => [
                    'The project changed after the latest passed acceptance run. Rerun php artisan erp:acceptance, then freeze the release candidate.',
                ],
            ]);
        }

        $candidate = DB::transaction(
            function () use (
                $actor,
                $version,
                $notes,
                $source,
                $tenantId,
                $acceptance,
                $current,
            ): ReleaseCandidate {
                $lockedAcceptance =
                    ProductionAcceptanceRun::query()
                        ->where('tenant_id', $tenantId)
                        ->whereKey(
                            $acceptance->getKey(),
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if (
                    $lockedAcceptance->status !== 'passed'
                    || $lockedAcceptance->project_fingerprint === null
                    || !hash_equals(
                        (string) $lockedAcceptance
                            ->project_fingerprint,
                        $current['fingerprint'],
                    )
                ) {
                    throw ValidationException::withMessages([
                        'acceptance' => [
                            'The production acceptance state changed before the release candidate could be frozen. Rerun acceptance and try again.',
                        ],
                    ]);
                }

                $versionExists = ReleaseCandidate::query()
                    ->where('tenant_id', $tenantId)
                    ->where('version', $version)
                    ->lockForUpdate()
                    ->exists();

                if ($versionExists) {
                    throw ValidationException::withMessages([
                        'version' => [
                            'This release-candidate version already exists for the tenant.',
                        ],
                    ]);
                }

                ReleaseCandidate::query()
                    ->where('tenant_id', $tenantId)
                    ->where(
                        'status',
                        ReleaseCandidateStatusRegistry::FROZEN,
                    )
                    ->lockForUpdate()
                    ->get()
                    ->each(
                        static function (
                            ReleaseCandidate $existing,
                        ): void {
                            $existing->update([
                                'status' =>
                                    ReleaseCandidateStatusRegistry::SUPERSEDED,

                                'superseded_at' =>
                                    now(),
                            ]);
                        },
                    );

                $candidate =
                    ReleaseCandidate::query()->create([
                        'production_acceptance_run_id' =>
                            $lockedAcceptance->getKey(),

                        'version' =>
                            $version,

                        'status' =>
                            ReleaseCandidateStatusRegistry::FROZEN,

                        'environment' =>
                            app()->environment(),

                        'source' =>
                            $source,

                        'project_fingerprint' =>
                            $current['fingerprint'],

                        'git_commit' =>
                            $current['payload']['git_commit'],

                        'notes' =>
                            $notes,

                        'frozen_by_user_id' =>
                            $actor?->getKey(),

                        'frozen_at' =>
                            now(),

                        'verified_at' =>
                            now(),

                        'verification_status' =>
                            ReleaseCandidateStatusRegistry::VERIFICATION_MATCHED,

                        'verification_summary' => [
                            'matched' => true,
                            'drifted_artifacts' => [],
                        ],
                    ]);

                foreach (
                    $current['artifacts']
                    as $artifact
                ) {
                    $candidate->artifacts()->create([
                        'artifact_key' =>
                            $artifact['key'],

                        'label' =>
                            $artifact['label'],

                        'sha256' =>
                            $artifact['sha256'],

                        'metadata' =>
                            $artifact['metadata'],
                    ]);
                }

                return $candidate;
            },
            attempts: 5,
        );

        return $this->present(
            $candidate->fresh([
                'acceptanceRun',
                'frozenBy:id,name',
                'artifacts',
            ]),
        );
    }

    /** @return array<string, mixed> */
    public function verify(
        ReleaseCandidate $candidate,
    ): array {
        $tenantId = $this->activeTenantId();

        $this->ensureCandidateBelongsToTenant(
            $candidate,
            $tenantId,
        );

        $lockedCandidate = ReleaseCandidate::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($candidate->getKey())
            ->firstOrFail();

        $current = $this->fingerprintService->capture();

        $lockedCandidate->loadMissing(
            'artifacts',
        );

        $frozenArtifacts =
            $lockedCandidate->artifacts
                ->keyBy('artifact_key');

        $drifted = [];

        foreach (
            $current['artifacts']
            as $artifact
        ) {
            if ($artifact['key'] === 'git_commit') {
                continue;
            }

            $frozen = $frozenArtifacts->get(
                $artifact['key'],
            );

            $frozenHash =
                $frozen instanceof ReleaseCandidateArtifact
                    ? $frozen->sha256
                    : null;

            if (
                $frozenHash
                !== $artifact['sha256']
            ) {
                $drifted[] = [
                    'key' => $artifact['key'],
                    'label' => $artifact['label'],
                    'frozen_sha256' => $frozenHash,
                    'current_sha256' =>
                        $artifact['sha256'],
                ];
            }
        }

        $fingerprintMatches = hash_equals(
            (string) $lockedCandidate
                ->project_fingerprint,
            $current['fingerprint'],
        );

        if (
            !$fingerprintMatches
            && $drifted === []
        ) {
            $drifted[] = [
                'key' => 'project_fingerprint',
                'label' =>
                    'Release fingerprint material',

                'frozen_sha256' =>
                    $lockedCandidate
                        ->project_fingerprint,

                'current_sha256' =>
                    $current['fingerprint'],
            ];
        }

        $matched =
            $fingerprintMatches
            && $drifted === [];

        $lockedCandidate->update([
            'verified_at' => now(),

            'verification_status' =>
                $matched
                    ? ReleaseCandidateStatusRegistry::VERIFICATION_MATCHED
                    : ReleaseCandidateStatusRegistry::VERIFICATION_DRIFTED,

            'verification_summary' => [
                'matched' => $matched,

                'current_fingerprint' =>
                    $current['fingerprint'],

                'drifted_artifacts' =>
                    $drifted,
            ],
        ]);

        return $this->present(
            $lockedCandidate->fresh([
                'acceptanceRun',
                'frozenBy:id,name',
                'artifacts',
            ]),
        );
    }

    /** @return array<string, mixed> */
    public function present(
        ReleaseCandidate $candidate,
    ): array {
        $tenantId = $this->activeTenantId();

        $this->ensureCandidateBelongsToTenant(
            $candidate,
            $tenantId,
        );

        $candidate->loadMissing([
            'acceptanceRun',
            'frozenBy:id,name',
            'artifacts',
        ]);

        if (
            $candidate->acceptanceRun
                instanceof ProductionAcceptanceRun
            && (int) $candidate
                ->acceptanceRun
                ->tenant_id !== $tenantId
        ) {
            throw ValidationException::withMessages([
                'acceptance' => [
                    'The release candidate references a Production Acceptance run belonging to another tenant.',
                ],
            ]);
        }

        return [
            'id' =>
                (int) $candidate->getKey(),

            'version' =>
                $candidate->version,

            'status' =>
                $candidate->status,

            'environment' =>
                $candidate->environment,

            'source' =>
                $candidate->source,

            'project_fingerprint' =>
                $candidate->project_fingerprint,

            'git_commit' =>
                $candidate->git_commit,

            'notes' =>
                $candidate->notes,

            'verification_status' =>
                $candidate->verification_status,

            'verification_summary' =>
                $candidate->verification_summary,

            'frozen_at' =>
                $candidate->frozen_at
                    ?->toIso8601String(),

            'superseded_at' =>
                $candidate->superseded_at
                    ?->toIso8601String(),

            'verified_at' =>
                $candidate->verified_at
                    ?->toIso8601String(),

            'frozen_by' =>
                $candidate->frozenBy === null
                    ? null
                    : [
                        'id' =>
                            (int) $candidate
                                ->frozenBy
                                ->getKey(),

                        'name' =>
                            $candidate
                                ->frozenBy
                                ->name,
                    ],

            'acceptance' =>
                $candidate->acceptanceRun === null
                    ? null
                    : [
                        'id' =>
                            (int) $candidate
                                ->acceptanceRun
                                ->getKey(),

                        'uuid' =>
                            $candidate
                                ->acceptanceRun
                                ->uuid,

                        'status' =>
                            $candidate
                                ->acceptanceRun
                                ->status,

                        'blocking_failures' =>
                            (int) $candidate
                                ->acceptanceRun
                                ->blocking_failures,

                        'project_fingerprint' =>
                            $candidate
                                ->acceptanceRun
                                ->project_fingerprint,

                        'completed_at' =>
                            $candidate
                                ->acceptanceRun
                                ->completed_at
                                ?->toIso8601String(),
                    ],

            'artifacts' =>
                $candidate->artifacts
                    ->map(
                        static fn (
                            ReleaseCandidateArtifact $artifact,
                        ): array => [
                            'key' =>
                                $artifact
                                    ->artifact_key,

                            'label' =>
                                $artifact->label,

                            'sha256' =>
                                $artifact->sha256,

                            'metadata' =>
                                $artifact->metadata,
                        ],
                    )
                    ->values()
                    ->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function latestSummary(): ?array
    {
        $tenantId = $this->activeTenantId();

        $candidate = ReleaseCandidate::query()
            ->where('tenant_id', $tenantId)
            ->latest('frozen_at')
            ->latest('id')
            ->first();

        if (
            !$candidate
                instanceof ReleaseCandidate
        ) {
            return null;
        }

        return [
            'id' =>
                (int) $candidate->getKey(),

            'version' =>
                $candidate->version,

            'status' =>
                $candidate->status,

            'verification_status' =>
                $candidate->verification_status,

            'frozen_at' =>
                $candidate->frozen_at
                    ?->toIso8601String(),

            'verified_at' =>
                $candidate->verified_at
                    ?->toIso8601String(),
        ];
    }

    private function activeTenantId(): int
    {
        $tenantId = $this->tenantContext->id();

        if (
            $tenantId === null
            || $tenantId < 1
        ) {
            throw ValidationException::withMessages([
                'tenant' => [
                    'Tenant context is required for the release-candidate workflow.',
                ],
            ]);
        }

        return $tenantId;
    }

    private function ensureCandidateBelongsToTenant(
        ReleaseCandidate $candidate,
        int $tenantId,
    ): void {
        if (
            (int) $candidate->tenant_id
            === $tenantId
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'release_candidate' => [
                'The release candidate does not belong to the active tenant.',
            ],
        ]);
    }
}