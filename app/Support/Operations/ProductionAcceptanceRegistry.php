<?php

declare(strict_types=1);

namespace App\Support\Operations;

final class ProductionAcceptanceRegistry
{
    /** @return list<string> */
    public function requiredProjectFiles(): array
    {
        return [
            'routes/web.php',
            'routes/sales-allocation.php',
            'routes/dispatches.php',
            'routes/sales-invoices.php',
            'routes/customer-receipts.php',
            'routes/accounts-receivable-reports.php',
            'routes/sales-returns.php',
            'routes/customer-settlements.php',
            'routes/treasury.php',
            'routes/financial-control.php',
            'routes/management.php',
            'routes/operations.php',
            'app/Models/SalesOrder.php',
            'app/Models/CustomerDispatch.php',
            'app/Models/SalesInvoice.php',
            'app/Models/CustomerReceipt.php',
            'app/Models/CustomerCreditNote.php',
            'app/Models/CustomerRefund.php',
            'app/Models/SupplierInvoice.php',
            'app/Models/SupplierPayment.php',
            'app/Models/JournalEntry.php',
            'app/Models/InventoryBalance.php',
            'app/Models/ReleaseCandidate.php',
            'resources/js/Pages/SalesOrders/Index.vue',
            'resources/js/Pages/Dispatches/Index.vue',
            'resources/js/Pages/SalesInvoices/Index.vue',
            'resources/js/Pages/CustomerReceipts/Index.vue',
            'resources/js/Pages/FinancialStatements/TrialBalance.vue',
            'resources/js/Pages/Treasury/Index.vue',
            'resources/js/Pages/Management/Index.vue',
            'resources/js/Pages/Operations/Index.vue',
            'resources/js/Pages/Operations/ReleaseCandidates/Index.vue',
        ];
    }

    /** @return list<string> */
    public function requiredRouteNames(): array
    {
        return [
            'sales-orders.index',
            'dispatches.index',
            'sales-invoices.index',
            'customer-receipts.index',
            'sales-returns.index',
            'customer-credits.index',
            'customer-refunds.index',
            'treasury.index',
            'bank-reconciliations.index',
            'financial-control.index',
            'reports.financial-statements.trial-balance',
            'management.index',
            'operations.index',
            'operations.preflight',
            'release-candidates.index',
        ];
    }

    /** @return list<string> */
    public function branchOwnedTables(): array
    {
        return [
            'users',
            'warehouses',
            'product_branch_settings',
            'product_warehouse_settings',
            'inventory_balances',
            'stock_ledger_entries',
            'sales_orders',
            'customer_dispatches',
            'sales_invoices',
            'customer_ledger_entries',
            'customer_open_items',
            'customer_open_item_allocations',
            'customer_receipts',
            'customer_credit_notes',
            'customer_credit_applications',
            'customer_refunds',
            'customer_ar_adjustments',
            'purchase_orders',
            'goods_receipts',
            'purchase_returns',
            'supplier_invoices',
            'supplier_ledger_entries',
            'supplier_open_items',
            'supplier_open_item_allocations',
            'supplier_payments',
            'supplier_debit_notes',
            'treasury_adjustments',
            'bank_statement_imports',
            'bank_statement_lines',
            'bank_reconciliations',
            'bank_reconciliation_matches',
            'management_budgets',
            'management_budget_lines',
            'management_report_schedules',
            'journal_entries',
            'journal_entry_lines',
        ];
    }
}
