<?php

declare(strict_types=1);

namespace App\Support\Saas;

final class SaasFeatureRouteRegistry
{
    /**
     * @return list<string>
     */
    public function requiredFeatures(?string $routeName): array
    {
        if ($routeName === null || $routeName === '') {
            return [];
        }

        if (
            str_starts_with($routeName, 'inventory.transfers.')
            || str_starts_with($routeName, 'inventory.adjustments.')
            || str_starts_with($routeName, 'inventory.counts.')
        ) {
            return [
                'inventory.module',
                'inventory.advanced',
            ];
        }

        if (
            $routeName === 'inventory.index'
            || str_starts_with($routeName, 'inventory.ledger.')
        ) {
            return [
                'inventory.module',
            ];
        }

        $singleFeaturePrefixes = [
            'master_data.module' => [
                'units.',
                'product-categories.',
                'brands.',
                'products.',
                'suppliers.',
                'customers.',
            ],

            'purchasing.module' => [
                'purchase-orders.',
                'goods-receipts.',
                'purchase-returns.',
                'supplier-invoices.',
                'supplier-debit-notes.',
            ],

            'sales.module' => [
                'sales-orders.',
                'dispatches.',
                'sales-invoices.',
                'sales-returns.',
            ],

            'accounts_payable.module' => [
                'supplier-payments.',
                'reports.accounts-payable.',
            ],

            'accounts_receivable.module' => [
                'customer-receipts.',
                'customer-credits.',
                'customer-credit-applications.',
                'customer-refunds.',
                'customer-ar-adjustments.',
                'reports.accounts-receivable.',
            ],

            'financial_accounting.module' => [
                'accounting-periods.',
                'financial-control.',
                'reports.financial-statements.',
            ],

            'treasury.module' => [
                'treasury.',
                'treasury-transfers.',
                'treasury-adjustments.',
                'bank-statements.',
                'bank-reconciliations.',
            ],

            'management_reporting.module' => [
                'management.',
            ],

            'audit_exports.module' => [
                'audit-logs.',
                'exports.',
                'files.',
            ],
        ];

        foreach ($singleFeaturePrefixes as $featureKey => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($routeName, $prefix)) {
                    return [$featureKey];
                }
            }
        }

        return [];
    }
}
