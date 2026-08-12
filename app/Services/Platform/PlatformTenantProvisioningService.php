<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\Branch;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\DefaultChartOfAccountsService;
use App\Services\Auditing\AuditLogService;
use App\Services\Settings\DocumentSequenceService;
use App\Support\DocumentNumbers\DocumentTypeRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class PlatformTenantProvisioningService
{
    private const TENANT_OWNER_ROLE = 'Tenant Owner';

    /**
     * These permissions expose infrastructure-wide operational data and must
     * never be granted to a normal tenant owner.
     *
     * @var list<string>
     */
    private const PLATFORM_OPERATION_PERMISSION_PREFIXES = [
        'operations.',
        'production_acceptance.',
        'release_candidates.',
    ];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PermissionRegistrar $permissionRegistrar,
        private readonly DefaultChartOfAccountsService $chartOfAccountsService,
        private readonly DocumentSequenceService $documentSequenceService,
        private readonly DocumentTypeRegistry $documentTypeRegistry,
        private readonly AuditLogService $auditLogService,
        private readonly SaasSubscriptionService $subscriptionService,
    ) {
    }

    /**
     * @param array{
     *     name: string,
     *     code: string,
     *     status: string,
     *     currency_code: string,
     *     timezone: string,
     *     email: string|null,
     *     phone: string|null,
     *     address: string|null,
     *     admin_name: string,
     *     admin_email: string,
     *     admin_password: string
     * } $attributes
     */
    public function provision(
        array $attributes,
        ?PlatformAdmin $platformAdmin = null,
    ): Tenant {
        $previousTenant = $this->tenantContext->get();
        $previousTeamId = $previousTenant?->getKey();

        try {
            return DB::transaction(
                function () use (
                    $attributes,
                    $platformAdmin,
                ): Tenant {
                    $tenant = Tenant::withoutEvents(
                        fn (): Tenant => Tenant::query()->create([
                            'name' => $attributes['name'],
                            'code' => $attributes['code'],
                            'slug' => Str::lower($attributes['code']),
                            'status' => $attributes['status'],
                            'currency_code' => $attributes['currency_code'],
                            'timezone' => $attributes['timezone'],
                            'email' => $attributes['email'],
                            'phone' => $attributes['phone'],
                            'address' => $attributes['address'],
                        ]),
                    );

                    $this->tenantContext->set($tenant);
                    $this->permissionRegistrar->setPermissionsTeamId(
                        (int) $tenant->getKey(),
                    );

                    $this->auditLogService->recordCreated($tenant);

                    $branch = Branch::query()->create([
                        'name' => 'Main Branch',
                        'code' => 'MAIN',
                        'status' => 'active',
                        'email' => $attributes['email'],
                        'phone' => $attributes['phone'],
                        'address' => $attributes['address'],
                    ]);

                    $warehouse = Warehouse::query()->create([
                        'branch_id' => (int) $branch->getKey(),
                        'name' => 'Main Warehouse',
                        'code' => 'MAIN-WH',
                        'type' => 'general',
                        'status' => 'active',
                        'is_default' => true,
                        'address' => $attributes['address'],
                    ]);

                    $admin = new User();
                    $admin->forceFill([
                        'tenant_id' => (int) $tenant->getKey(),
                        'branch_id' => null,
                        'name' => $attributes['admin_name'],
                        'email' => $attributes['admin_email'],
                        'password' => $attributes['admin_password'],
                        'status' => 'active',
                        'email_verified_at' => now(),
                    ])->save();

                    $ownerRole = $this->provisionTenantOwnerRole(
                        $tenant,
                    );

                    $admin->syncRoles([
                        $ownerRole,
                    ]);

                    $this->chartOfAccountsService
                        ->provisionForTenant($tenant);

                    $sequenceCount = $this
                        ->provisionDocumentSequences();

                    $subscription = $this->subscriptionService
                        ->assignDefaultPlan(
                            tenant: $tenant,
                            assignedBy: $platformAdmin,
                        );

                    $this->auditLogService->recordCustomEvent(
                        subject: $tenant,
                        event: 'tenant_provisioned',
                        metadata: [
                            'tenant_owner_user_id' => (int) $admin->getKey(),
                            'main_branch_id' => (int) $branch->getKey(),
                            'main_warehouse_id' => (int) $warehouse->getKey(),
                            'document_sequences_created' => $sequenceCount,
                            'tenant_subscription_id' => (int) $subscription->getKey(),
                            'saas_plan_id' => (int) $subscription->saas_plan_id,
                        ],
                    );

                    $this->permissionRegistrar
                        ->forgetCachedPermissions();

                    return $tenant->refresh();
                },
                attempts: 5,
            );
        } finally {
            if ($previousTenant instanceof Tenant) {
                $this->tenantContext->set($previousTenant);
            } else {
                $this->tenantContext->clear();
            }

            $this->permissionRegistrar->setPermissionsTeamId(
                $previousTeamId,
            );

            $this->permissionRegistrar->forgetCachedPermissions();
        }
    }

    private function provisionTenantOwnerRole(
        Tenant $tenant,
    ): Role {
        $permissions = $this->tenantOwnerPermissions();

        if ($permissions->isEmpty()) {
            throw new LogicException(
                'Tenant provisioning requires the web permissions to be seeded first.',
            );
        }

        $role = Role::query()->firstOrCreate([
            'tenant_id' => (int) $tenant->getKey(),
            'name' => self::TENANT_OWNER_ROLE,
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * @return Collection<int, Permission>
     */
    private function tenantOwnerPermissions(): Collection
    {
        return Permission::query()
            ->where('guard_name', 'web')
            ->where(
                function ($query): void {
                    foreach (
                        self::PLATFORM_OPERATION_PERMISSION_PREFIXES
                        as $prefix
                    ) {
                        $query->where(
                            'name',
                            'not like',
                            $prefix.'%',
                        );
                    }
                },
            )
            ->orderBy('name')
            ->get();
    }

    private function provisionDocumentSequences(): int
    {
        $created = 0;

        foreach ($this->documentTypeRegistry->options() as $option) {
            $this->documentSequenceService->create([
                'branch_id' => null,
                'name' => $option['label'].' Numbering',
                'document_type' => $option['value'],
                'prefix' => $option['default_prefix'],
                'suffix' => null,
                'current_number' => 0,
                'number_padding' => 6,
                'reset_policy' => 'calendar_year',
                'fiscal_year_start_month' => null,
                'status' => 'active',
            ]);

            $created++;
        }

        return $created;
    }
}
