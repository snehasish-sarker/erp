<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class PermissionSeeder extends Seeder
{
    private const GUARD_NAME = 'web';

    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        'dashboard.view',

        'company_settings.view',
        'company_settings.update',

        'document_numbering.view',
        'document_numbering.create',
        'document_numbering.update',
        'document_numbering.delete',

        'files.view',
        'files.upload',
        'files.download',
        'files.delete',

        'branches.view',
        'branches.access_all',
        'branches.create',
        'branches.update',
        'branches.delete',

        'warehouses.view',
        'warehouses.create',
        'warehouses.update',
        'warehouses.delete',

        'users.view',
        'users.create',
        'users.update',
        'users.delete',
        'users.change_status',
        'users.reset_password',

        'roles.view',
        'roles.create',
        'roles.update',
        'roles.delete',
        'roles.assign_permissions',

        'units.view',
        'units.create',
        'units.update',
        'units.delete',

        'product_categories.view',
        'product_categories.create',
        'product_categories.update',
        'product_categories.delete',

        'brands.view',
        'brands.create',
        'brands.update',
        'brands.delete',

        'products.view',
        'products.create',
        'products.update',
        'products.delete',
        'products.import',
        'products.export',
        'products.view_cost',

        'suppliers.view',
        'suppliers.create',
        'suppliers.update',
        'suppliers.delete',
        'suppliers.view_balance',

        'customers.view',
        'customers.create',
        'customers.update',
        'customers.delete',
        'customers.view_balance',
        'customers.override_credit_limit',

        'purchase_orders.view',
        'purchase_orders.create',
        'purchase_orders.update',
        'purchase_orders.delete',
        'purchase_orders.submit',
        'purchase_orders.approve',
        'purchase_orders.cancel',

        'goods_receipts.view',
        'goods_receipts.create',
        'goods_receipts.update',
        'goods_receipts.delete',
        'goods_receipts.post',
        'goods_receipts.reverse',

        'supplier_invoices.view',
        'supplier_invoices.create',
        'supplier_invoices.update',
        'supplier_invoices.delete',
        'supplier_invoices.validate',
        'supplier_invoices.approve',
        'supplier_invoices.dispute',
        'supplier_invoices.cancel',
        'supplier_invoices.post',
        'supplier_invoices.reverse',

        'purchase_returns.view',
        'purchase_returns.create',
        'purchase_returns.update',
        'purchase_returns.delete',
        'purchase_returns.submit',
        'purchase_returns.approve',
        'purchase_returns.cancel',
        'purchase_returns.post',
        'purchase_returns.reverse',

        'supplier_debit_notes.view',
        'supplier_debit_notes.create',
        'supplier_debit_notes.update',
        'supplier_debit_notes.delete',
        'supplier_debit_notes.submit',
        'supplier_debit_notes.approve',
        'supplier_debit_notes.cancel',
        'supplier_debit_notes.post',
        'supplier_debit_notes.reverse',

        'sales_orders.view',
        'sales_orders.create',
        'sales_orders.update',
        'sales_orders.delete',
        'sales_orders.submit',
        'sales_orders.approve',
        'sales_orders.allocate',
        'sales_orders.cancel',
        'sales_orders.override_price',
        'sales_orders.override_discount',

        'dispatches.view',
        'dispatches.create',
        'dispatches.post',
        'dispatches.reverse',

        'sales_invoices.view',
        'sales_invoices.create',
        'sales_invoices.post',
        'sales_invoices.reverse',

        'sales_returns.view',
        'sales_returns.create',
        'sales_returns.approve',
        'sales_returns.post',
        'sales_returns.reverse',

        'inventory.view',
        'inventory.view_ledger',
        'inventory.view_cost',
        'inventory.adjust',
        'inventory.transfer',
        'inventory.count',

        'customer_payments.view',
        'customer_payments.create',
        'customer_payments.approve',
        'customer_payments.post',
        'customer_payments.reverse',

        'customer_receipts.view',
        'customer_receipts.create',
        'customer_receipts.update',
        'customer_receipts.delete',
        'customer_receipts.submit',
        'customer_receipts.approve',
        'customer_receipts.cancel',
        'customer_receipts.post',
        'customer_receipts.reverse',

        'customer_credits.view',

        'customer_credit_applications.view',
        'customer_credit_applications.create',
        'customer_credit_applications.update',
        'customer_credit_applications.delete',
        'customer_credit_applications.submit',
        'customer_credit_applications.approve',
        'customer_credit_applications.cancel',
        'customer_credit_applications.post',
        'customer_credit_applications.reverse',

        'customer_refunds.view',
        'customer_refunds.create',
        'customer_refunds.update',
        'customer_refunds.delete',
        'customer_refunds.submit',
        'customer_refunds.approve',
        'customer_refunds.cancel',
        'customer_refunds.post',
        'customer_refunds.reverse',

        'customer_ar_adjustments.view',
        'customer_ar_adjustments.create',
        'customer_ar_adjustments.update',
        'customer_ar_adjustments.delete',
        'customer_ar_adjustments.submit',
        'customer_ar_adjustments.approve',
        'customer_ar_adjustments.cancel',
        'customer_ar_adjustments.post',
        'customer_ar_adjustments.reverse',

        'supplier_payments.view',
        'supplier_payments.create',
        'supplier_payments.update',
        'supplier_payments.delete',
        'supplier_payments.submit',
        'supplier_payments.approve',
        'supplier_payments.cancel',
        'supplier_payments.post',
        'supplier_payments.reverse',

        'treasury.view',

        'treasury_transfers.view',
        'treasury_transfers.create',
        'treasury_transfers.update',
        'treasury_transfers.delete',
        'treasury_transfers.submit',
        'treasury_transfers.approve',
        'treasury_transfers.cancel',
        'treasury_transfers.post',
        'treasury_transfers.reverse',

        'treasury_adjustments.view',
        'treasury_adjustments.create',
        'treasury_adjustments.update',
        'treasury_adjustments.delete',
        'treasury_adjustments.submit',
        'treasury_adjustments.approve',
        'treasury_adjustments.cancel',
        'treasury_adjustments.post',
        'treasury_adjustments.reverse',

        'bank_statements.view',
        'bank_statements.import',
        'bank_statements.delete',

        'bank_reconciliations.view',
        'bank_reconciliations.create',
        'bank_reconciliations.match',
        'bank_reconciliations.complete',
        'bank_reconciliations.reverse',

        'accounts.view',
        'accounts.create',
        'accounts.update',
        'accounts.delete',

        'journals.view',
        'journals.create',
        'journals.approve',
        'journals.post',
        'journals.reverse',

        'accounting_periods.view',
        'accounting_periods.generate',
        'accounting_periods.close',
        'accounting_periods.reopen',

        'expenses.view',
        'expenses.create',
        'expenses.update',
        'expenses.approve',
        'expenses.post',
        'expenses.reverse',

        'reports.sales',
        'reports.purchases',
        'reports.inventory',
        'reports.receivables',
        'reports.payables',
        'reports.profit',
        'reports.accounting',
        'reports.audit',

        'audit_logs.view',

        'imports.create',
        'imports.view',

        'exports.view',
        'exports.create',
        'exports.download',
        'exports.cancel',
    ];

    public function run(): void
    {
        $permissionRegistrar = app(
            PermissionRegistrar::class,
        );

        $permissionRegistrar->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permissionName) {
            Permission::query()->firstOrCreate([
                'name' => $permissionName,
                'guard_name' => self::GUARD_NAME,
            ]);
        }

        Tenant::query()
            ->orderBy('id')
            ->each(
                function (
                    Tenant $tenant,
                ) use ($permissionRegistrar): void {
                    $this->seedTenantRoles(
                        tenant: $tenant,
                        permissionRegistrar:
                            $permissionRegistrar,
                    );
                },
            );

        $permissionRegistrar->setPermissionsTeamId(
            null,
        );

        $permissionRegistrar->forgetCachedPermissions();
    }

    private function seedTenantRoles(
        Tenant $tenant,
        PermissionRegistrar $permissionRegistrar,
    ): void {
        $tenantId = (int) $tenant->getKey();

        $permissionRegistrar->setPermissionsTeamId(
            $tenantId,
        );

        /**
         * @var array<string, list<string>> $roles
         */
        $roles = [
            'Tenant Owner' => self::PERMISSIONS,

            'System Administrator' => self::PERMISSIONS,

            'Branch Manager' => [
                'dashboard.view',

                'branches.view',
                'warehouses.view',

                'products.view',
                'products.view_cost',

                'suppliers.view',
                'suppliers.view_balance',

                'customers.view',
                'customers.view_balance',

                'purchase_orders.view',
                'purchase_orders.create',
                'purchase_orders.update',
                'purchase_orders.submit',
                'purchase_orders.approve',
                'purchase_orders.cancel',

                'goods_receipts.view',

                'sales_orders.view',
                'sales_orders.create',
                'sales_orders.update',
                'sales_orders.submit',
                'sales_orders.approve',
                'sales_orders.allocate',
                'sales_orders.cancel',

                'dispatches.view',

                'sales_invoices.view',

                'inventory.view',
                'inventory.view_ledger',
                'inventory.view_cost',

                'customer_payments.view',

                'customer_receipts.view',
                'customer_receipts.create',
                'customer_receipts.submit',

                'customer_credits.view',
                'customer_credit_applications.view',
                'customer_refunds.view',
                'customer_ar_adjustments.view',

                'treasury.view',
                'treasury_transfers.view',
                'treasury_transfers.create',
                'treasury_transfers.submit',
                'treasury_adjustments.view',
                'bank_statements.view',
                'bank_reconciliations.view',

                'supplier_payments.view',
                'expenses.view',

                'reports.sales',
                'reports.purchases',
                'reports.inventory',
                'reports.receivables',
                'reports.payables',
                'reports.profit',

                'exports.view',
                'exports.create',
                'exports.download',
                'exports.cancel',

                'files.view',
                'files.upload',
                'files.download',
                'files.delete',
            ],

            'Procurement Manager' => [
                'dashboard.view',

                'products.view',
                'products.view_cost',

                'suppliers.view',
                'suppliers.create',
                'suppliers.update',
                'suppliers.view_balance',

                'purchase_orders.view',
                'purchase_orders.create',
                'purchase_orders.update',
                'purchase_orders.submit',
                'purchase_orders.approve',
                'purchase_orders.cancel',

                'goods_receipts.view',
                'goods_receipts.create',
                'goods_receipts.delete',

                'supplier_invoices.view',

                'purchase_returns.view',
                'purchase_returns.create',
                'purchase_returns.approve',

                'supplier_debit_notes.view',
                'supplier_debit_notes.create',
                'supplier_debit_notes.update',
                'supplier_debit_notes.delete',
                'supplier_debit_notes.submit',
                'supplier_debit_notes.approve',
                'supplier_debit_notes.cancel',

                'inventory.view',
                'inventory.view_ledger',
                'inventory.view_cost',

                'reports.purchases',
                'reports.inventory',

                'exports.view',
                'exports.create',
                'exports.download',
                'exports.cancel',

                'files.view',
                'files.upload',
                'files.download',
                'files.delete',
            ],

            'Warehouse Manager' => [
                'dashboard.view',

                'warehouses.view',

                'products.view',
                'products.view_cost',

                'purchase_orders.view',

                'goods_receipts.view',
                'goods_receipts.create',
                'goods_receipts.update',
                'goods_receipts.delete',
                'goods_receipts.post',
                'goods_receipts.reverse',

                'purchase_returns.view',
                'purchase_returns.create',
                'purchase_returns.post',

                'supplier_debit_notes.view',

                'sales_orders.view',
                'sales_orders.allocate',

                'dispatches.view',
                'dispatches.create',
                'dispatches.post',
                'dispatches.reverse',

                'sales_returns.view',
                'sales_returns.create',
                'sales_returns.post',

                'inventory.view',
                'inventory.view_ledger',
                'inventory.view_cost',
                'inventory.adjust',
                'inventory.transfer',
                'inventory.count',

                'reports.inventory',

                'exports.view',
                'exports.create',
                'exports.download',
                'exports.cancel',

                'files.view',
                'files.upload',
                'files.download',
                'files.delete',
            ],

            'Sales Manager' => [
                'dashboard.view',

                'products.view',

                'customers.view',
                'customers.create',
                'customers.update',
                'customers.view_balance',
                'customers.override_credit_limit',

                'sales_orders.view',
                'sales_orders.create',
                'sales_orders.update',
                'sales_orders.submit',
                'sales_orders.approve',
                'sales_orders.allocate',
                'sales_orders.cancel',
                'sales_orders.override_price',
                'sales_orders.override_discount',

                'dispatches.view',
                'sales_invoices.view',

                'sales_returns.view',
                'sales_returns.create',
                'sales_returns.approve',

                'inventory.view',

                'customer_payments.view',

                'customer_receipts.view',
                'customer_receipts.create',
                'customer_receipts.submit',
                'customer_receipts.approve',

                'customer_credits.view',
                'customer_credit_applications.view',
                'customer_credit_applications.create',
                'customer_credit_applications.submit',
                'customer_refunds.view',
                'customer_refunds.create',
                'customer_refunds.submit',
                'customer_ar_adjustments.view',

                'reports.sales',
                'reports.receivables',
                'reports.profit',

                'exports.view',
                'exports.create',
                'exports.download',
                'exports.cancel',

                'files.view',
                'files.upload',
                'files.download',
                'files.delete',
            ],

            'Accountant' => [
                'dashboard.view',

                'suppliers.view',
                'suppliers.view_balance',

                'customers.view',
                'customers.view_balance',

                'supplier_invoices.view',
                'supplier_invoices.create',
                'supplier_invoices.update',
                'supplier_invoices.post',
                'supplier_invoices.reverse',

                'supplier_debit_notes.view',
                'supplier_debit_notes.create',
                'supplier_debit_notes.update',
                'supplier_debit_notes.submit',
                'supplier_debit_notes.post',
                'supplier_debit_notes.reverse',

                'sales_invoices.view',
                'sales_invoices.create',
                'sales_invoices.post',
                'sales_invoices.reverse',

                'customer_payments.view',
                'customer_payments.create',
                'customer_payments.post',
                'customer_payments.reverse',

                'customer_receipts.view',
                'customer_receipts.create',
                'customer_receipts.update',
                'customer_receipts.delete',
                'customer_receipts.submit',
                'customer_receipts.approve',
                'customer_receipts.cancel',
                'customer_receipts.post',
                'customer_receipts.reverse',

                'customer_credits.view',
                'customer_credit_applications.view',
                'customer_credit_applications.create',
                'customer_credit_applications.update',
                'customer_credit_applications.delete',
                'customer_credit_applications.submit',
                'customer_credit_applications.approve',
                'customer_credit_applications.cancel',
                'customer_credit_applications.post',
                'customer_credit_applications.reverse',
                'customer_refunds.view',
                'customer_refunds.create',
                'customer_refunds.update',
                'customer_refunds.delete',
                'customer_refunds.submit',
                'customer_refunds.approve',
                'customer_refunds.cancel',
                'customer_refunds.post',
                'customer_refunds.reverse',
                'customer_ar_adjustments.view',
                'customer_ar_adjustments.create',
                'customer_ar_adjustments.update',
                'customer_ar_adjustments.delete',
                'customer_ar_adjustments.submit',
                'customer_ar_adjustments.approve',
                'customer_ar_adjustments.cancel',
                'customer_ar_adjustments.post',
                'customer_ar_adjustments.reverse',

                'supplier_payments.view',
                'supplier_payments.create',
                'supplier_payments.update',
                'supplier_payments.delete',
                'supplier_payments.submit',
                'supplier_payments.cancel',
                'supplier_payments.post',
                'supplier_payments.reverse',

                'treasury.view',
                'treasury_transfers.view',
                'treasury_transfers.create',
                'treasury_transfers.update',
                'treasury_transfers.delete',
                'treasury_transfers.submit',
                'treasury_transfers.approve',
                'treasury_transfers.cancel',
                'treasury_transfers.post',
                'treasury_transfers.reverse',
                'treasury_adjustments.view',
                'treasury_adjustments.create',
                'treasury_adjustments.update',
                'treasury_adjustments.delete',
                'treasury_adjustments.submit',
                'treasury_adjustments.approve',
                'treasury_adjustments.cancel',
                'treasury_adjustments.post',
                'treasury_adjustments.reverse',
                'bank_statements.view',
                'bank_statements.import',
                'bank_statements.delete',
                'bank_reconciliations.view',
                'bank_reconciliations.create',
                'bank_reconciliations.match',
                'bank_reconciliations.complete',
                'bank_reconciliations.reverse',

                'accounts.view',
                'accounts.create',
                'accounts.update',

                'journals.view',
                'journals.create',
                'journals.post',
                'journals.reverse',

                'accounting_periods.view',
                'accounting_periods.generate',
                'accounting_periods.close',
                'accounting_periods.reopen',

                'expenses.view',
                'expenses.create',
                'expenses.update',
                'expenses.post',
                'expenses.reverse',

                'reports.receivables',
                'reports.payables',
                'reports.profit',
                'reports.accounting',

                'exports.view',
                'exports.create',
                'exports.download',
                'exports.cancel',

                'document_numbering.view',
                'document_numbering.create',
                'document_numbering.update',
                'document_numbering.delete',

                'files.view',
                'files.upload',
                'files.download',
                'files.delete',
            ],

            'Auditor' => [
                'dashboard.view',

                'branches.view',
                'warehouses.view',
                'users.view',
                'roles.view',

                'units.view',
                'product_categories.view',
                'brands.view',
                'products.view',
                'products.view_cost',

                'suppliers.view',
                'suppliers.view_balance',

                'customers.view',
                'customers.view_balance',

                'purchase_orders.view',
                'goods_receipts.view',
                'supplier_invoices.view',
                'purchase_returns.view',
                'supplier_debit_notes.view',

                'sales_orders.view',
                'dispatches.view',
                'sales_invoices.view',
                'sales_returns.view',

                'inventory.view',
                'inventory.view_ledger',
                'inventory.view_cost',

                'customer_payments.view',
                'customer_receipts.view',
                'customer_credits.view',
                'customer_credit_applications.view',
                'customer_refunds.view',
                'customer_ar_adjustments.view',
                'supplier_payments.view',

                'treasury.view',
                'treasury_transfers.view',
                'treasury_adjustments.view',
                'bank_statements.view',
                'bank_reconciliations.view',

                'accounts.view',
                'journals.view',
                'accounting_periods.view',
                'expenses.view',

                'reports.sales',
                'reports.purchases',
                'reports.inventory',
                'reports.receivables',
                'reports.payables',
                'reports.profit',
                'reports.accounting',
                'reports.audit',

                'audit_logs.view',

                'document_numbering.view',

                'exports.view',
                'exports.create',
                'exports.download',
                'exports.cancel',

                'files.view',
                'files.download',
            ],
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::query()->firstOrCreate([
                'tenant_id' => $tenantId,
                'name' => $roleName,
                'guard_name' => self::GUARD_NAME,
            ]);

            $this->synchronizeRolePermissions(
                role: $role,
                roleName: $roleName,
                permissions: $permissions,
            );
        }

        $this->assignDemoTenantOwner(
            tenant: $tenant,
        );
    }

    /**
     * @param list<string> $permissions
     */
    private function synchronizeRolePermissions(
        Role $role,
        string $roleName,
        array $permissions,
    ): void {
        /*
         * Tenant Owner and System Administrator are authoritative roles.
         * They must always contain every currently registered permission.
         */
        if (
            in_array(
                $roleName,
                [
                    'Tenant Owner',
                    'System Administrator',
                ],
                true,
            )
        ) {
            $role->syncPermissions(
                $permissions,
            );

            return;
        }

        /*
         * A newly created default role should receive its exact baseline.
         */
        if ($role->wasRecentlyCreated) {
            $role->syncPermissions(
                $permissions,
            );

            return;
        }

        /*
         * Existing operational roles receive newly introduced baseline
         * permissions without removing additional manually assigned ones.
         */
        $role->givePermissionTo(
            $permissions,
        );
    }

    private function assignDemoTenantOwner(
        Tenant $tenant,
    ): void {
        $user = User::query()
            ->where(
                'tenant_id',
                $tenant->getKey(),
            )
            ->where(
                'email',
                'admin@erp.test',
            )
            ->first();

        if (!$user instanceof User) {
            return;
        }

        $user->syncRoles([
            'Tenant Owner',
        ]);
    }
}