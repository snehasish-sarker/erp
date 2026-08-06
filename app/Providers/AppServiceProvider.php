<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Accounting\CustomerCreditNoteAccountingGateway;
use App\Contracts\Accounting\CustomerDispatchAccountingGateway;
use App\Contracts\Accounting\CustomerReceiptAccountingGateway;
use App\Contracts\Accounting\GoodsReceiptAccountingGateway;
use App\Contracts\Accounting\PurchaseReturnAccountingGateway;
use App\Contracts\Accounting\SalesInvoiceAccountingGateway;
use App\Contracts\Accounting\SupplierDebitNoteAccountingGateway;
use App\Contracts\Accounting\SupplierInvoiceAccountingGateway;
use App\Contracts\Accounting\SupplierPaymentAccountingGateway;
use App\Models\Account;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\CustomerCreditNote;
use App\Models\CustomerDispatch;
use App\Models\CustomerReceipt;
use App\Models\DocumentSequence;
use App\Models\ExportRequest;
use App\Models\FiscalYear;
use App\Models\GoodsReceipt;
use App\Models\InventoryBalance;
use App\Models\InventoryReservation;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\Supplier;
use App\Models\SupplierDebitNote;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\TenantFile;
use App\Models\Unit;
use App\Models\UserNotification;
use App\Observers\CustomerDispatchObserver;
use App\Observers\InventoryBalanceObserver;
use App\Observers\InventoryReservationObserver;
use App\Policies\AccountPolicy;
use App\Policies\AccountingPeriodPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BrandPolicy;
use App\Policies\CustomerCreditNotePolicy;
use App\Policies\CustomerDispatchPolicy;
use App\Policies\CustomerReceiptPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DocumentSequencePolicy;
use App\Policies\ExportRequestPolicy;
use App\Policies\FiscalYearPolicy;
use App\Policies\GoodsReceiptPolicy;
use App\Policies\JournalEntryPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseReturnPolicy;
use App\Policies\RolePolicy;
use App\Policies\SalesInvoicePolicy;
use App\Policies\SalesOrderPolicy;
use App\Policies\SupplierDebitNotePolicy;
use App\Policies\SupplierInvoicePolicy;
use App\Policies\SupplierPaymentPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\TenantFilePolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserNotificationPolicy;
use App\Services\Accounting\GeneralLedgerCustomerCreditNoteAccountingGateway;
use App\Services\Accounting\GeneralLedgerCustomerDispatchAccountingGateway;
use App\Services\Accounting\GeneralLedgerCustomerReceiptAccountingGateway;
use App\Services\Accounting\GeneralLedgerGoodsReceiptAccountingGateway;
use App\Services\Accounting\GeneralLedgerPurchaseReturnAccountingGateway;
use App\Services\Accounting\GeneralLedgerSalesInvoiceAccountingGateway;
use App\Services\Accounting\GeneralLedgerSupplierDebitNoteAccountingGateway;
use App\Services\Accounting\GeneralLedgerSupplierInvoiceAccountingGateway;
use App\Services\Accounting\GeneralLedgerSupplierPaymentAccountingGateway;
use App\Support\Exports\Definitions\AccountsPayableAgingExportDefinition;
use App\Support\Exports\Definitions\AccountsReceivableAgingExportDefinition;
use App\Support\Exports\Definitions\AuditLogExportDefinition;
use App\Support\Exports\Definitions\CustomerAgingExportDefinition;
use App\Support\Exports\Definitions\CustomerStatementExportDefinition;
use App\Support\Exports\Definitions\OpenInvoiceExportDefinition;
use App\Support\Exports\Definitions\SupplierAgingExportDefinition;
use App\Support\Exports\Definitions\SupplierStatementExportDefinition;
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
            CustomerCreditNoteAccountingGateway::class,
            GeneralLedgerCustomerCreditNoteAccountingGateway::class,
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

        InventoryReservation::observe(
            InventoryReservationObserver::class,
        );

        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(DocumentSequence::class, DocumentSequencePolicy::class);
        Gate::policy(FiscalYear::class, FiscalYearPolicy::class);
        Gate::policy(AccountingPeriod::class, AccountingPeriodPolicy::class);
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(JournalEntry::class, JournalEntryPolicy::class);
        Gate::policy(TenantFile::class, TenantFilePolicy::class);
        Gate::policy(ExportRequest::class, ExportRequestPolicy::class);
        Gate::policy(UserNotification::class, UserNotificationPolicy::class);
        Gate::policy(Unit::class, UnitPolicy::class);
        Gate::policy(ProductCategory::class, ProductCategoryPolicy::class);
        Gate::policy(Brand::class, BrandPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerCreditNote::class, CustomerCreditNotePolicy::class);
        Gate::policy(CustomerReceipt::class, CustomerReceiptPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(SalesOrder::class, SalesOrderPolicy::class);
        Gate::policy(CustomerDispatch::class, CustomerDispatchPolicy::class);
        Gate::policy(SalesInvoice::class, SalesInvoicePolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
        Gate::policy(SupplierInvoice::class, SupplierInvoicePolicy::class);
        Gate::policy(PurchaseReturn::class, PurchaseReturnPolicy::class);
        Gate::policy(SupplierDebitNote::class, SupplierDebitNotePolicy::class);
        Gate::policy(SupplierPayment::class, SupplierPaymentPolicy::class);
    }
}