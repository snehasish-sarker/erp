<?php

declare(strict_types=1);

namespace App\Services\Saas;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantFile;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Validation\ValidationException;

final class SaasUsageLimitService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SaasEntitlementService $entitlementService,
    ) {
    }

    public function assertCanActivateUser(): void
    {
        $tenant = $this->lockTenant();

        $currentUsage = User::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', 'active')
            ->count();

        $this->assertWithinLimit(
            tenant: $tenant,
            featureKey: 'users.limit',
            currentUsage: $currentUsage,
            additionalUsage: 1,
            field: 'status',
            label: 'active users',
        );
    }

    public function assertCanCreateBranch(): void
    {
        $tenant = $this->lockTenant();

        $currentUsage = Branch::query()
            ->count();

        $this->assertWithinLimit(
            tenant: $tenant,
            featureKey: 'branches.limit',
            currentUsage: $currentUsage,
            additionalUsage: 1,
            field: 'name',
            label: 'branches',
        );
    }

    public function assertCanCreateWarehouse(): void
    {
        $tenant = $this->lockTenant();

        $currentUsage = Warehouse::query()
            ->count();

        $this->assertWithinLimit(
            tenant: $tenant,
            featureKey: 'warehouses.limit',
            currentUsage: $currentUsage,
            additionalUsage: 1,
            field: 'name',
            label: 'warehouses',
        );
    }

    public function assertCanCreateProduct(): void
    {
        $tenant = $this->lockTenant();

        $currentUsage = Product::query()
            ->count();

        $this->assertWithinLimit(
            tenant: $tenant,
            featureKey: 'products.limit',
            currentUsage: $currentUsage,
            additionalUsage: 1,
            field: 'name',
            label: 'products',
        );
    }

    public function assertCanStoreFileBytes(int $additionalBytes): void
    {
        if ($additionalBytes < 0) {
            throw ValidationException::withMessages([
                'file' => [
                    'The file size is invalid.',
                ],
            ]);
        }

        $tenant = $this->lockTenant();
        $limitMb = $this->entitlementService->limit(
            tenant: $tenant,
            featureKey: 'storage_mb.limit',
        );

        if ($limitMb === null) {
            return;
        }

        $limitBytes = $limitMb * 1024 * 1024;

        $currentBytes = (int) TenantFile::query()
            ->sum('size_bytes');

        if (($currentBytes + $additionalBytes) <= $limitBytes) {
            return;
        }

        throw ValidationException::withMessages([
            'file' => [
                sprintf(
                    'The SaaS plan storage limit of %d MB has been reached.',
                    $limitMb,
                ),
            ],
        ]);
    }

    private function lockTenant(): Tenant
    {
        $tenant = $this->tenantContext->tenant();

        return Tenant::query()
            ->whereKey($tenant->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertWithinLimit(
        Tenant $tenant,
        string $featureKey,
        int $currentUsage,
        int $additionalUsage,
        string $field,
        string $label,
    ): void {
        $limit = $this->entitlementService->limit(
            tenant: $tenant,
            featureKey: $featureKey,
        );

        if ($limit === null) {
            return;
        }

        if (($currentUsage + $additionalUsage) <= $limit) {
            return;
        }

        throw ValidationException::withMessages([
            $field => [
                sprintf(
                    'The current SaaS plan allows a maximum of %d %s.',
                    $limit,
                    $label,
                ),
            ],
        ]);
    }
}
