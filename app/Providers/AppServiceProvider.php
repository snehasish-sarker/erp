<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Accounting\CustomerArAdjustmentAccountingGateway;
use App\Contracts\Accounting\CustomerCreditApplicationAccountingGateway;
use App\Contracts\Accounting\CustomerCreditNoteAccountingGateway;
use App\Contracts\Accounting\CustomerDispatchAccountingGateway;
use App\Contracts\Accounting\CustomerReceiptAccountingGateway;
use App\Contracts\Accounting\CustomerRefundAccountingGateway;
use App\Contracts\Accounting\GoodsReceiptAccountingGateway;
use App\Contracts\Accounting\PurchaseReturnAccountingGateway;
use App\Contracts\Accounting\SalesInvoiceAccountingGateway;
use App\Contracts\Accounting\SupplierDebitNoteAccountingGateway;
use App\Contracts\Accounting\SupplierInvoiceAccountingGateway;
use App\Contracts\Accounting\SupplierPaymentAccountingGateway;
use App\Contracts\Accounting\TreasuryAdjustmentAccountingGateway;
use App\Contracts\Accounting\TreasuryTransferAccountingGateway;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\BankReconciliation;
use App\Models\BankStatementImport;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerArAdjustment;
use App\Models\CustomerCreditApplication;
use App\Models\CustomerCreditNote;
use App\Models\CustomerDispatch;
use App\Models\CustomerReceipt;
use App\Models\CustomerRefund;
use App\Models\DocumentSequence;
use App\Models\ExportRequest;
use App\Models\FiscalYear;
use App\Models\GoodsReceipt;
use App\Models\InventoryBalance;
use App\Models\JournalEntry;
use App\Models\ManagementBudget;
use App\Models\PeriodCloseRun;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionAcceptanceRun;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\ReleaseCandidate;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\SupplierDebitNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SystemBackup;
use App\Models\TenantFile;
use App\Models\TreasuryAdjustment;
use App\Models\TreasuryTransfer;
use App\Models\Unit;
use App\Models\UserNotification;
use App\Observers\CustomerDispatchObserver;
use App\Observers\InventoryBalanceObserver;
use App\Policies\AccountPolicy;
use App\Policies\AccountingPeriodPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BankReconciliationPolicy;
use App\Policies\BankStatementImportPolicy;
use App\Policies\BrandPolicy;
use App\Policies\CustomerArAdjustmentPolicy;
use App\Policies\CustomerCreditApplicationPolicy;
use App\Policies\CustomerCreditNotePolicy;
use App\Policies\CustomerDispatchPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\CustomerReceiptPolicy;
use App\Policies\CustomerRefundPolicy;
use App\Policies\DocumentSequencePolicy;
use App\Policies\ExportRequestPolicy;
use App\Policies\FiscalYearPolicy;
use App\Policies\GoodsReceiptPolicy;
use App\Policies\JournalEntryPolicy;
use App\Policies\ManagementBudgetPolicy;
use App\Policies\PeriodCloseRunPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductionAcceptanceRunPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseReturnPolicy;
use App\Policies\ReleaseCandidatePolicy;
use App\Policies\RolePolicy;
use App\Policies\SalesInvoicePolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\SupplierDebitNotePolicy;
use App\Policies\SupplierInvoicePolicy;
use App\Policies\SupplierPaymentPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\SystemBackupPolicy;
use App\Policies\TenantFilePolicy;
use App\Policies\TreasuryAdjustmentPolicy;
use App\Policies\TreasuryTransferPolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserNotificationPolicy;
use App\Services\Accounting\GeneralLedgerCustomerArAdjustmentAccountingGateway;
use App\Services\Accounting\GeneralLedgerCustomerCreditApplicationAccountingGateway;
use App\Services\Accounting\GeneralLedgerCustomerCreditNoteAccountingGateway;
use App\Services\Accounting\GeneralLedgerCustomerDispatchAccountingGateway;
use App\Services\Accounting\GeneralLedgerCustomerReceiptAccountingGateway;
use App\Services\Accounting\GeneralLedgerCustomerRefundAccountingGateway;
use App\Services\Accounting\GeneralLedgerGoodsReceiptAccountingGateway;
use App\Services\Accounting\GeneralLedgerPurchaseReturnAccountingGateway;
use App\Services\Accounting\GeneralLedgerSalesInvoiceAccountingGateway;
use App\Services\Accounting\GeneralLedgerSupplierDebitNoteAccountingGateway;
use App\Services\Accounting\GeneralLedgerSupplierInvoiceAccountingGateway;
use App\Services\Accounting\GeneralLedgerSupplierPaymentAccountingGateway;
use App\Services\Accounting\GeneralLedgerTreasuryAdjustmentAccountingGateway;
use App\Services\Accounting\GeneralLedgerTreasuryTransferAccountingGateway;
use App\Support\Exports\Definitions\AccountsPayableAgingExportDefinition;
use App\Support\Exports\Definitions\AccountsReceivableAgingExportDefinition;
use App\Support\Exports\Definitions\AuditLogExportDefinition;
use App\Support\Exports\Definitions\BalanceSheetExportDefinition;
use App\Support\Exports\Definitions\CashFlowExportDefinition;
use App\Support\Exports\Definitions\CustomerAgingExportDefinition;
use App\Support\Exports\Definitions\CustomerStatementExportDefinition;
use App\Support\Exports\Definitions\FinancialReconciliationExportDefinition;
use App\Support\Exports\Definitions\ManagementBranchProfitabilityExportDefinition;
use App\Support\Exports\Definitions\ManagementBudgetVsActualExportDefinition;
use App\Support\Exports\Definitions\ManagementCustomerProfitabilityExportDefinition;
use App\Support\Exports\Definitions\ManagementGrossMarginExportDefinition;
use App\Support\Exports\Definitions\ManagementProductProfitabilityExportDefinition;
use App\Support\Exports\Definitions\ManagementSupplierSpendExportDefinition;
use App\Support\Exports\Definitions\OpenInvoiceExportDefinition;
use App\Support\Exports\Definitions\ProfitAndLossExportDefinition;
use App\Support\Exports\Definitions\SupplierAgingExportDefinition;
use App\Support\Exports\Definitions\SupplierStatementExportDefinition;
use App\Support\Exports\Definitions\TrialBalanceExportDefinition;
use App\Support\Exports\ExportRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            TenantContext::class,
            static fn (): TenantContext => new TenantContext(),
        );

        $this->app->bind(
            CustomerArAdjustmentAccountingGateway::class,
            GeneralLedgerCustomerArAdjustmentAccountingGateway::class,
        );

        $this->app->bind(
            CustomerCreditApplicationAccountingGateway::class,
            GeneralLedgerCustomerCreditApplicationAccountingGateway::class,
        );

        $this->app->bind(
            CustomerCreditNoteAccountingGateway::class,
            GeneralLedgerCustomerCreditNoteAccountingGateway::class,
        );

        $this->app->bind(
            CustomerRefundAccountingGateway::class,
            GeneralLedgerCustomerRefundAccountingGateway::class,
        );

        $this->app->bind(
            CustomerDispatchAccountingGateway::class,
            GeneralLedgerCustomerDispatchAccountingGateway::class,
        );

        $this->app->bind(
            CustomerReceiptAccountingGateway::class,
            GeneralLedgerCustomerReceiptAccountingGateway::class,
        );

        $this->app->bind(
            GoodsReceiptAccountingGateway::class,
            GeneralLedgerGoodsReceiptAccountingGateway::class,
        );

        $this->app->bind(
            PurchaseReturnAccountingGateway::class,
            GeneralLedgerPurchaseReturnAccountingGateway::class,
        );

        $this->app->bind(
            SupplierInvoiceAccountingGateway::class,
            GeneralLedgerSupplierInvoiceAccountingGateway::class,
        );

        $this->app->bind(
            SupplierDebitNoteAccountingGateway::class,
            GeneralLedgerSupplierDebitNoteAccountingGateway::class,
        );

        $this->app->bind(
            SupplierPaymentAccountingGateway::class,
            GeneralLedgerSupplierPaymentAccountingGateway::class,
        );

        $this->app->bind(
            SalesInvoiceAccountingGateway::class,
            GeneralLedgerSalesInvoiceAccountingGateway::class,
        );

        $this->app->bind(
            TreasuryAdjustmentAccountingGateway::class,
            GeneralLedgerTreasuryAdjustmentAccountingGateway::class,
        );

        $this->app->bind(
            TreasuryTransferAccountingGateway::class,
            GeneralLedgerTreasuryTransferAccountingGateway::class,
        );

        $this->app->singleton(
            ExportRegistry::class,
            static fn (Container $container): ExportRegistry =>
                new ExportRegistry([
                    $container->make(
                        AuditLogExportDefinition::class,
                    ),
                    $container->make(
                        AccountsPayableAgingExportDefinition::class,
                    ),
                    $container->make(
                        AccountsReceivableAgingExportDefinition::class,
                    ),
                    $container->make(
                        CustomerAgingExportDefinition::class,
                    ),
                    $container->make(
                        CustomerStatementExportDefinition::class,
                    ),
                    $container->make(
                        OpenInvoiceExportDefinition::class,
                    ),
                    $container->make(
                        SupplierAgingExportDefinition::class,
                    ),
                    $container->make(
                        SupplierStatementExportDefinition::class,
                    ),
                    $container->make(
                        TrialBalanceExportDefinition::class,
                    ),
                    $container->make(
                        ProfitAndLossExportDefinition::class,
                    ),
                    $container->make(
                        BalanceSheetExportDefinition::class,
                    ),
                    $container->make(
                        CashFlowExportDefinition::class,
                    ),
                    $container->make(
                        FinancialReconciliationExportDefinition::class,
                    ),
                    $container->make(
                        ManagementBranchProfitabilityExportDefinition::class,
                    ),
                    $container->make(
                        ManagementBudgetVsActualExportDefinition::class,
                    ),
                    $container->make(
                        ManagementProductProfitabilityExportDefinition::class,
                    ),
                    $container->make(
                        ManagementCustomerProfitabilityExportDefinition::class,
                    ),
                    $container->make(
                        ManagementSupplierSpendExportDefinition::class,
                    ),
                    $container->make(
                        ManagementGrossMarginExportDefinition::class,
                    ),
                ]),
        );
    }

    public function boot(): void
    {
        CustomerDispatch::observe(
            CustomerDispatchObserver::class,
        );

        InventoryBalance::observe(
            InventoryBalanceObserver::class,
        );

        /*
         * InventoryReservationObserver is intentionally NOT registered here.
         *
         * Registering InventoryReservation::observe(...) was causing Laravel
         * to recursively boot App\Models\InventoryReservation during Artisan
         * startup, which blocked commands such as migrate and optimize:clear.
         */

        Gate::policy(
            Role::class,
            RolePolicy::class,
        );

        Gate::policy(
            AuditLog::class,
            AuditLogPolicy::class,
        );

        Gate::policy(
            BankReconciliation::class,
            BankReconciliationPolicy::class,
        );

        Gate::policy(
            BankStatementImport::class,
            BankStatementImportPolicy::class,
        );

        Gate::policy(
            DocumentSequence::class,
            DocumentSequencePolicy::class,
        );

        Gate::policy(
            FiscalYear::class,
            FiscalYearPolicy::class,
        );

        Gate::policy(
            AccountingPeriod::class,
            AccountingPeriodPolicy::class,
        );

        Gate::policy(
            Account::class,
            AccountPolicy::class,
        );

        Gate::policy(
            JournalEntry::class,
            JournalEntryPolicy::class,
        );

        Gate::policy(
            ManagementBudget::class,
            ManagementBudgetPolicy::class,
        );

        Gate::policy(
            TenantFile::class,
            TenantFilePolicy::class,
        );

        Gate::policy(
            TreasuryAdjustment::class,
            TreasuryAdjustmentPolicy::class,
        );

        Gate::policy(
            TreasuryTransfer::class,
            TreasuryTransferPolicy::class,
        );

        Gate::policy(
            ExportRequest::class,
            ExportRequestPolicy::class,
        );

        Gate::policy(
            UserNotification::class,
            UserNotificationPolicy::class,
        );

        Gate::policy(
            Unit::class,
            UnitPolicy::class,
        );

        Gate::policy(
            PeriodCloseRun::class,
            PeriodCloseRunPolicy::class,
        );

        Gate::policy(
            ProductCategory::class,
            ProductCategoryPolicy::class,
        );

        Gate::policy(
            ProductionAcceptanceRun::class,
            ProductionAcceptanceRunPolicy::class,
        );

        Gate::policy(
            ReleaseCandidate::class,
            ReleaseCandidatePolicy::class,
        );

        Gate::policy(
            Brand::class,
            BrandPolicy::class,
        );

        Gate::policy(
            Product::class,
            ProductPolicy::class,
        );

        Gate::policy(
            Supplier::class,
            SupplierPolicy::class,
        );

        Gate::policy(
            Customer::class,
            CustomerPolicy::class,
        );

        Gate::policy(
            CustomerArAdjustment::class,
            CustomerArAdjustmentPolicy::class,
        );

        Gate::policy(
            CustomerCreditApplication::class,
            CustomerCreditApplicationPolicy::class,
        );

        Gate::policy(
            CustomerCreditNote::class,
            CustomerCreditNotePolicy::class,
        );

        Gate::policy(
            CustomerRefund::class,
            CustomerRefundPolicy::class,
        );

        Gate::policy(
            CustomerReceipt::class,
            CustomerReceiptPolicy::class,
        );

        Gate::policy(
            PurchaseOrder::class,
            PurchaseOrderPolicy::class,
        );

        Gate::policy(
            SalesOrder::class,
            SalesOrderPolicy::class,
        );

        Gate::policy(
            CustomerDispatch::class,
            CustomerDispatchPolicy::class,
        );

        Gate::policy(
            SalesInvoice::class,
            SalesInvoicePolicy::class,
        );

        Gate::policy(
            GoodsReceipt::class,
            GoodsReceiptPolicy::class,
        );

        Gate::policy(
            SupplierInvoice::class,
            SupplierInvoicePolicy::class,
        );

        Gate::policy(
            PurchaseReturn::class,
            PurchaseReturnPolicy::class,
        );

        Gate::policy(
            SupplierDebitNote::class,
            SupplierDebitNotePolicy::class,
        );

        Gate::policy(
            SupplierPayment::class,
            SupplierPaymentPolicy::class,
        );

        Gate::policy(
            SystemBackup::class,
            SystemBackupPolicy::class,
        );
    }
}