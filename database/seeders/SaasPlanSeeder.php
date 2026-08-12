<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SaasFeature;
use App\Models\SaasPlan;
use App\Models\SaasPlanFeature;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class SaasPlanSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    private const FEATURES = [
        [
            'key' => 'master_data.module',
            'name' => 'Master Data',
            'description' => 'Products, categories, brands, units, suppliers, and customers.',
            'value_type' => 'boolean',
            'unit' => null,
            'sort_order' => 10,
        ],
        [
            'key' => 'purchasing.module',
            'name' => 'Purchasing',
            'description' => 'Purchase orders, goods receipts, returns, supplier invoices, debit notes, and payments.',
            'value_type' => 'boolean',
            'unit' => null,
            'sort_order' => 20,
        ],
        [
            'key' => 'sales.module',
            'name' => 'Sales',
            'description' => 'Sales orders, allocation, dispatches, invoices, returns, receipts, and customer settlement.',
            'value_type' => 'boolean',
            'unit' => null,
            'sort_order' => 30,
        ],
        [
            'key' => 'inventory.module',
            'name' => 'Inventory',
            'description' => 'Stock summary and stock ledger.',
            'value_type' => 'boolean',
            'unit' => null,
            'sort_order' => 40,
        ],
        [
            'key' => 'inventory.advanced',
            'name' => 'Advanced Inventory',
            'description' => 'Inventory transfers, adjustments, and stock counts.',
            'value_type' => 'boolean',
            'unit' => null,
            'sort_order' => 50,
        ],
        [
            'key' => 'accounts_payable.module',
            'name' => 'Accounts Payable',
            'description' => 'Supplier open items, payable settlement, aging, and supplier statements.',
            'value_type' => 'boolean',
            'unit' => null,
            'sort_order' => 60,
        ],
        [
            'key' => 'accounts_receivable.module',
            'name' => 'Accounts Receivable',
            'description' => 'Customer open items, receipts, credits, refunds, adjustments, aging, and statements.',
            'value_type' => 'boolean',
            'unit' => null,
            'sort_order' => 70,
        ],
        [
            'key' => 'financial_accounting.module',
            'name' => 'Financial Accounting',
            'description' => 'Chart of accounts, journals, periods, financial control, and financial statements.',
            'value_type' => 'boolean',
            'unit' => null,
            'sort_order' => 80,
        ],
        [
            'key' => 'treasury.module',
            'name' => 'Treasury',
            'description' => 'Treasury register, transfers, adjustments, bank statements, and reconciliations.',
            'value_type' => 'boolean',
            'unit' => null,
            'sort_order' => 90,
        ],
        [
            'key' => 'management_reporting.module',
            'name' => 'Management Reporting',
            'description' => 'Management budgets, profitability reporting, schedules, and readiness dashboards.',
            'value_type' => 'boolean',
            'unit' => null,
            'sort_order' => 100,
        ],
        [
            'key' => 'audit_exports.module',
            'name' => 'Audit and Exports',
            'description' => 'Audit logs, queued exports, and tenant file workflows.',
            'value_type' => 'boolean',
            'unit' => null,
            'sort_order' => 110,
        ],
        [
            'key' => 'users.limit',
            'name' => 'Users',
            'description' => 'Maximum tenant users. Blank plan limit means unlimited.',
            'value_type' => 'limit',
            'unit' => 'users',
            'sort_order' => 200,
        ],
        [
            'key' => 'branches.limit',
            'name' => 'Branches',
            'description' => 'Maximum tenant branches. Blank plan limit means unlimited.',
            'value_type' => 'limit',
            'unit' => 'branches',
            'sort_order' => 210,
        ],
        [
            'key' => 'warehouses.limit',
            'name' => 'Warehouses',
            'description' => 'Maximum tenant warehouses. Blank plan limit means unlimited.',
            'value_type' => 'limit',
            'unit' => 'warehouses',
            'sort_order' => 220,
        ],
        [
            'key' => 'products.limit',
            'name' => 'Products',
            'description' => 'Maximum tenant products. Blank plan limit means unlimited.',
            'value_type' => 'limit',
            'unit' => 'products',
            'sort_order' => 230,
        ],
        [
            'key' => 'storage_mb.limit',
            'name' => 'Tenant Storage',
            'description' => 'Maximum tenant file storage in megabytes. Blank plan limit means unlimited.',
            'value_type' => 'limit',
            'unit' => 'MB',
            'sort_order' => 240,
        ],
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    private const PLANS = [
        'starter' => [
            'name' => 'Starter',
            'description' => 'Core ERP operations for smaller single-branch businesses.',
            'status' => 'active',
            'is_default' => false,
            'sort_order' => 10,
            'features' => [
                'master_data.module' => true,
                'purchasing.module' => true,
                'sales.module' => true,
                'inventory.module' => true,
                'inventory.advanced' => false,
                'accounts_payable.module' => true,
                'accounts_receivable.module' => true,
                'financial_accounting.module' => true,
                'treasury.module' => false,
                'management_reporting.module' => false,
                'audit_exports.module' => true,
                'users.limit' => 5,
                'branches.limit' => 1,
                'warehouses.limit' => 2,
                'products.limit' => 5000,
                'storage_mb.limit' => 2048,
            ],
        ],
        'professional' => [
            'name' => 'Professional',
            'description' => 'Multi-branch ERP with advanced inventory and treasury operations.',
            'status' => 'active',
            'is_default' => false,
            'sort_order' => 20,
            'features' => [
                'master_data.module' => true,
                'purchasing.module' => true,
                'sales.module' => true,
                'inventory.module' => true,
                'inventory.advanced' => true,
                'accounts_payable.module' => true,
                'accounts_receivable.module' => true,
                'financial_accounting.module' => true,
                'treasury.module' => true,
                'management_reporting.module' => false,
                'audit_exports.module' => true,
                'users.limit' => 25,
                'branches.limit' => 5,
                'warehouses.limit' => 10,
                'products.limit' => 25000,
                'storage_mb.limit' => 10240,
            ],
        ],
        'business' => [
            'name' => 'Business',
            'description' => 'Full ERP feature set for established multi-branch organisations.',
            'status' => 'active',
            'is_default' => true,
            'sort_order' => 30,
            'features' => [
                'master_data.module' => true,
                'purchasing.module' => true,
                'sales.module' => true,
                'inventory.module' => true,
                'inventory.advanced' => true,
                'accounts_payable.module' => true,
                'accounts_receivable.module' => true,
                'financial_accounting.module' => true,
                'treasury.module' => true,
                'management_reporting.module' => true,
                'audit_exports.module' => true,
                'users.limit' => 100,
                'branches.limit' => 25,
                'warehouses.limit' => 50,
                'products.limit' => 100000,
                'storage_mb.limit' => 51200,
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'description' => 'Full ERP feature set with unlimited seeded usage limits.',
            'status' => 'active',
            'is_default' => false,
            'sort_order' => 40,
            'features' => [
                'master_data.module' => true,
                'purchasing.module' => true,
                'sales.module' => true,
                'inventory.module' => true,
                'inventory.advanced' => true,
                'accounts_payable.module' => true,
                'accounts_receivable.module' => true,
                'financial_accounting.module' => true,
                'treasury.module' => true,
                'management_reporting.module' => true,
                'audit_exports.module' => true,
                'users.limit' => null,
                'branches.limit' => null,
                'warehouses.limit' => null,
                'products.limit' => null,
                'storage_mb.limit' => null,
            ],
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::FEATURES as $featureData) {
                SaasFeature::query()->updateOrCreate(
                    ['key' => $featureData['key']],
                    [
                        ...$featureData,
                        'status' => 'active',
                    ],
                );
            }

            SaasPlan::query()->update(['is_default' => false]);

            foreach (self::PLANS as $code => $planData) {
                $plan = SaasPlan::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $planData['name'],
                        'description' => $planData['description'],
                        'status' => $planData['status'],
                        'is_default' => $planData['is_default'],
                        'sort_order' => $planData['sort_order'],
                    ],
                );

                /** @var array<string, bool|int|null> $featureValues */
                $featureValues = $planData['features'];

                foreach (self::FEATURES as $featureData) {
                    $feature = SaasFeature::query()
                        ->where('key', $featureData['key'])
                        ->firstOrFail();

                    $value = $featureValues[$feature->key] ?? false;
                    $enabled = $feature->value_type === 'boolean'
                        ? $value === true
                        : array_key_exists($feature->key, $featureValues);

                    $limitValue = $feature->value_type === 'limit'
                        && is_int($value)
                            ? $value
                            : null;

                    SaasPlanFeature::query()->updateOrCreate(
                        [
                            'saas_plan_id' => (int) $plan->getKey(),
                            'saas_feature_id' => (int) $feature->getKey(),
                        ],
                        [
                            'enabled' => $enabled,
                            'limit_value' => $limitValue,
                        ],
                    );
                }
            }

            $defaultPlan = SaasPlan::query()
                ->where('is_default', true)
                ->where('status', 'active')
                ->firstOrFail();

            Tenant::query()
                ->orderBy('id')
                ->each(
                    static function (Tenant $tenant) use ($defaultPlan): void {
                        TenantSubscription::query()->firstOrCreate(
                            [
                                'tenant_id' => (int) $tenant->getKey(),
                            ],
                            [
                                'saas_plan_id' => (int) $defaultPlan->getKey(),
                                'assigned_by_platform_admin_id' => null,
                                'status' => match ($tenant->status) {
                                    'trial' => 'trial',
                                    'active' => 'active',
                                    'past_due' => 'past_due',
                                    'suspended' => 'suspended',
                                    default => 'cancelled',
                                },
                                'starts_at' => now(),
                            ],
                        );
                    },
                );
        });
    }
}
