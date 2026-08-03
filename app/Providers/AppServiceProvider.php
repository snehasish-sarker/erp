<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Accounting\SupplierDebitNoteAccountingGateway;
use App\Contracts\Accounting\SupplierInvoiceAccountingGateway;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\ExportRequest;
use App\Models\FiscalYear;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierDebitNote;
use App\Models\SupplierInvoice;
use App\Models\TenantFile;
use App\Models\Unit;
use App\Models\UserNotification;
use App\Policies\AccountingPeriodPolicy;
use App\Policies\AuditLogPolicy;
use App\Policies\BrandPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DocumentSequencePolicy;
use App\Policies\ExportRequestPolicy;
use App\Policies\FiscalYearPolicy;
use App\Policies\GoodsReceiptPolicy;
use App\Policies\ProductCategoryPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\PurchaseReturnPolicy;
use App\Policies\RolePolicy;
use App\Policies\SupplierDebitNotePolicy;
use App\Policies\SupplierInvoicePolicy;
use App\Policies\SupplierPolicy;
use App\Policies\TenantFilePolicy;
use App\Policies\UnitPolicy;
use App\Policies\UserNotificationPolicy;
use App\Services\Accounting\UnconfiguredSupplierDebitNoteAccountingGateway;
use App\Services\Accounting\UnconfiguredSupplierInvoiceAccountingGateway;
use App\Support\Exports\Definitions\AuditLogExportDefinition;
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

        /*
         * Supplier Invoice posting and reversal remain fail-closed
         * until the Accounts Payable/GRNI journal module replaces
         * this implementation with a real accounting gateway.
         */
        $this->app->bind(
            SupplierInvoiceAccountingGateway::class,
            UnconfiguredSupplierInvoiceAccountingGateway::class,
        );

        /*
         * Supplier Debit Note posting and reversal remain fail-closed
         * until the Accounts Payable and journal-entry module replaces
         * this implementation with a real accounting gateway.
         */
        $this->app->bind(
            SupplierDebitNoteAccountingGateway::class,
            UnconfiguredSupplierDebitNoteAccountingGateway::class,
        );

        $this->app->singleton(
            ExportRegistry::class,
            static fn (
                Container $container,
            ): ExportRegistry => new ExportRegistry([
                $container->make(
                    AuditLogExportDefinition::class,
                ),
            ]),
        );
    }

    public function boot(): void
    {
        Gate::policy(
            Role::class,
            RolePolicy::class,
        );

        Gate::policy(
            AuditLog::class,
            AuditLogPolicy::class,
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
            TenantFile::class,
            TenantFilePolicy::class,
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
            ProductCategory::class,
            ProductCategoryPolicy::class,
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
            PurchaseOrder::class,
            PurchaseOrderPolicy::class,
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
    }
}