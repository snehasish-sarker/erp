<?php

declare(strict_types=1);

use App\Http\Controllers\AccessControl\RoleController;
use App\Http\Controllers\AccessControl\UserController;
use App\Http\Controllers\Accounting\AccountingPeriodController;
use App\Http\Controllers\Accounting\FiscalYearController;
use App\Http\Controllers\Auditing\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Exports\ExportRequestController;
use App\Http\Controllers\Files\TenantFileController;
use App\Http\Controllers\MasterData\BrandController;
use App\Http\Controllers\MasterData\CustomerController;
use App\Http\Controllers\MasterData\ProductCategoryController;
use App\Http\Controllers\MasterData\ProductController;
use App\Http\Controllers\MasterData\ProductLocationConfigurationController;
use App\Http\Controllers\MasterData\SupplierController;
use App\Http\Controllers\MasterData\UnitController;
use App\Http\Controllers\Notifications\UserNotificationController;
use App\Http\Controllers\Organisation\BranchController;
use App\Http\Controllers\Organisation\WarehouseController;
use App\Http\Controllers\Purchasing\GoodsReceiptController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\PurchaseReturnController;
use App\Http\Controllers\Purchasing\SupplierInvoiceController;
use App\Http\Controllers\Settings\CompanySettingsController;
use App\Http\Controllers\Settings\DocumentSequenceController;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Purchasing\SupplierDebitNoteController;

/*
|--------------------------------------------------------------------------
| Guest Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function (): void {
    Route::get(
        '/login',
        [AuthenticatedSessionController::class, 'create'],
    )->name('login');

    Route::post(
        '/login',
        [AuthenticatedSessionController::class, 'store'],
    )->name('login.store');
});

/*
|--------------------------------------------------------------------------
| Tenant-Aware Route Bindings
|--------------------------------------------------------------------------
*/

Route::bind(
    'managedUser',
    static function (string $value): User {
        $tenantId = app(TenantContext::class)->id();

        abort_if(
            $tenantId === null,
            403,
            'Tenant context has not been initialized.',
        );

        return User::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail((int) $value);
    },
);

Route::bind(
    'role',
    static function (string $value): Role {
        $tenantId = app(TenantContext::class)->id();

        abort_if(
            $tenantId === null,
            403,
            'Tenant context has not been initialized.',
        );

        return Role::query()
            ->where('tenant_id', $tenantId)
            ->where('guard_name', 'web')
            ->findOrFail((int) $value);
    },
);

/*
|--------------------------------------------------------------------------
| Authenticated Tenant Routes
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    'tenant.context',
    'tenant.active',
])->group(function (): void {
    Route::inertia(
        '/',
        'Dashboard/Index',
    )
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::prefix('erp')->group(function (): void {
        /*
        |--------------------------------------------------------------------------
        | Company Settings
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/settings',
            [CompanySettingsController::class, 'edit'],
        )
            ->middleware('permission:company_settings.view')
            ->name('company-settings.edit');

        Route::patch(
            '/settings',
            [CompanySettingsController::class, 'update'],
        )
            ->middleware('permission:company_settings.update')
            ->name('company-settings.update');

        /*
        |--------------------------------------------------------------------------
        | Branch Management
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/branches',
            [BranchController::class, 'index'],
        )
            ->middleware('permission:branches.view')
            ->name('branches.index');

        Route::get(
            '/branches/create',
            [BranchController::class, 'create'],
        )
            ->middleware('permission:branches.create')
            ->name('branches.create');

        Route::post(
            '/branches',
            [BranchController::class, 'store'],
        )
            ->middleware('permission:branches.create')
            ->name('branches.store');

        Route::get(
            '/branches/{branch}/edit',
            [BranchController::class, 'edit'],
        )
            ->middleware('permission:branches.update')
            ->name('branches.edit');

        Route::put(
            '/branches/{branch}',
            [BranchController::class, 'update'],
        )
            ->middleware('permission:branches.update')
            ->name('branches.update');

        Route::delete(
            '/branches/{branch}',
            [BranchController::class, 'destroy'],
        )
            ->middleware('permission:branches.delete')
            ->name('branches.destroy');

        /*
        |--------------------------------------------------------------------------
        | Warehouse Management
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/warehouses',
            [WarehouseController::class, 'index'],
        )
            ->middleware('permission:warehouses.view')
            ->name('warehouses.index');

        Route::get(
            '/warehouses/create',
            [WarehouseController::class, 'create'],
        )
            ->middleware('permission:warehouses.create')
            ->name('warehouses.create');

        Route::post(
            '/warehouses',
            [WarehouseController::class, 'store'],
        )
            ->middleware('permission:warehouses.create')
            ->name('warehouses.store');

        Route::get(
            '/warehouses/{warehouse}/edit',
            [WarehouseController::class, 'edit'],
        )
            ->middleware('permission:warehouses.update')
            ->name('warehouses.edit');

        Route::put(
            '/warehouses/{warehouse}',
            [WarehouseController::class, 'update'],
        )
            ->middleware('permission:warehouses.update')
            ->name('warehouses.update');

        Route::delete(
            '/warehouses/{warehouse}',
            [WarehouseController::class, 'destroy'],
        )
            ->middleware('permission:warehouses.delete')
            ->name('warehouses.destroy');

        /*
        |--------------------------------------------------------------------------
        | Tenant User Management
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/users',
            [UserController::class, 'index'],
        )
            ->middleware('permission:users.view')
            ->name('users.index');

        Route::get(
            '/users/create',
            [UserController::class, 'create'],
        )
            ->middleware('permission:users.create')
            ->name('users.create');

        Route::post(
            '/users',
            [UserController::class, 'store'],
        )
            ->middleware('permission:users.create')
            ->name('users.store');

        Route::get(
            '/users/{managedUser}/edit',
            [UserController::class, 'edit'],
        )
            ->middleware('permission:users.update')
            ->name('users.edit');

        Route::put(
            '/users/{managedUser}',
            [UserController::class, 'update'],
        )
            ->middleware('permission:users.update')
            ->name('users.update');

        Route::patch(
            '/users/{managedUser}/status',
            [UserController::class, 'changeStatus'],
        )
            ->middleware('permission:users.change_status')
            ->name('users.change-status');

        Route::patch(
            '/users/{managedUser}/password',
            [UserController::class, 'resetPassword'],
        )
            ->middleware('permission:users.reset_password')
            ->name('users.reset-password');

        Route::delete(
            '/users/{managedUser}',
            [UserController::class, 'destroy'],
        )
            ->middleware('permission:users.delete')
            ->name('users.destroy');

        /*
        |--------------------------------------------------------------------------
        | Roles and Permissions
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/roles',
            [RoleController::class, 'index'],
        )
            ->middleware('permission:roles.view')
            ->name('roles.index');

        Route::get(
            '/roles/create',
            [RoleController::class, 'create'],
        )
            ->middleware('permission:roles.create')
            ->name('roles.create');

        Route::post(
            '/roles',
            [RoleController::class, 'store'],
        )
            ->middleware('permission:roles.create')
            ->name('roles.store');

        Route::get(
            '/roles/{role}/edit',
            [RoleController::class, 'edit'],
        )
            ->middleware('permission:roles.update')
            ->name('roles.edit');

        Route::patch(
            '/roles/{role}',
            [RoleController::class, 'update'],
        )
            ->middleware('permission:roles.update')
            ->name('roles.update');

        Route::patch(
            '/roles/{role}/permissions',
            [RoleController::class, 'syncPermissions'],
        )
            ->middleware('permission:roles.assign_permissions')
            ->name('roles.sync-permissions');

        Route::delete(
            '/roles/{role}',
            [RoleController::class, 'destroy'],
        )
            ->middleware('permission:roles.delete')
            ->name('roles.destroy');

        /*
        |--------------------------------------------------------------------------
        | Document Numbering
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/document-numbering',
            [DocumentSequenceController::class, 'index'],
        )
            ->middleware('permission:document_numbering.view')
            ->name('document-numbering.index');

        Route::get(
            '/document-numbering/create',
            [DocumentSequenceController::class, 'create'],
        )
            ->middleware('permission:document_numbering.create')
            ->name('document-numbering.create');

        Route::post(
            '/document-numbering',
            [DocumentSequenceController::class, 'store'],
        )
            ->middleware('permission:document_numbering.create')
            ->name('document-numbering.store');

        Route::get(
            '/document-numbering/{documentSequence}/edit',
            [DocumentSequenceController::class, 'edit'],
        )
            ->middleware('permission:document_numbering.update')
            ->name('document-numbering.edit');

        Route::put(
            '/document-numbering/{documentSequence}',
            [DocumentSequenceController::class, 'update'],
        )
            ->middleware('permission:document_numbering.update')
            ->name('document-numbering.update');

        Route::delete(
            '/document-numbering/{documentSequence}',
            [DocumentSequenceController::class, 'destroy'],
        )
            ->middleware('permission:document_numbering.delete')
            ->name('document-numbering.destroy');

        /*
        |--------------------------------------------------------------------------
        | Fiscal Years and Accounting Periods
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/accounting-periods',
            [FiscalYearController::class, 'index'],
        )
            ->middleware('permission:accounting_periods.view')
            ->name('accounting-periods.index');

        Route::get(
            '/accounting-periods/create',
            [FiscalYearController::class, 'create'],
        )
            ->middleware('permission:accounting_periods.generate')
            ->name('accounting-periods.create');

        Route::post(
            '/accounting-periods',
            [FiscalYearController::class, 'store'],
        )
            ->middleware('permission:accounting_periods.generate')
            ->name('accounting-periods.store');

        /*
         * Fixed period action routes must stay before the fiscal-year show
         * route to prevent "periods" being interpreted as a fiscal-year ID.
         */
        Route::patch(
            '/accounting-periods/periods/{accountingPeriod}/close',
            [AccountingPeriodController::class, 'close'],
        )
            ->middleware('permission:accounting_periods.close')
            ->name('accounting-periods.periods.close');

        Route::patch(
            '/accounting-periods/periods/{accountingPeriod}/reopen',
            [AccountingPeriodController::class, 'reopen'],
        )
            ->middleware('permission:accounting_periods.reopen')
            ->name('accounting-periods.periods.reopen');

        Route::get(
            '/accounting-periods/{fiscalYear}',
            [FiscalYearController::class, 'show'],
        )
            ->middleware('permission:accounting_periods.view')
            ->name('accounting-periods.show');

        /*
        |--------------------------------------------------------------------------
        | Audit Logs
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/audit-logs',
            [AuditLogController::class, 'index'],
        )
            ->middleware('permission:audit_logs.view')
            ->name('audit-logs.index');

        Route::get(
            '/audit-logs/{auditLog}',
            [AuditLogController::class, 'show'],
        )
            ->middleware('permission:audit_logs.view')
            ->name('audit-logs.show');

        /*
        |--------------------------------------------------------------------------
        | Queued Exports
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/exports',
            [ExportRequestController::class, 'index'],
        )
            ->middleware('permission:exports.view')
            ->name('exports.index');

        Route::post(
            '/exports',
            [ExportRequestController::class, 'store'],
        )
            ->middleware('permission:exports.create')
            ->name('exports.store');

        Route::get(
            '/exports/{exportRequest}/download',
            [ExportRequestController::class, 'download'],
        )
            ->middleware('permission:exports.download')
            ->name('exports.download');

        Route::patch(
            '/exports/{exportRequest}/cancel',
            [ExportRequestController::class, 'cancel'],
        )
            ->middleware('permission:exports.cancel')
            ->name('exports.cancel');

        /*
        |--------------------------------------------------------------------------
        | User Notifications
        |--------------------------------------------------------------------------
        */

        Route::get(
            '/notifications',
            [UserNotificationController::class, 'index'],
        )->name('notifications.index');

        /*
         * Keep this fixed route before the notification model route.
         */
        Route::patch(
            '/notifications/read-all',
            [UserNotificationController::class, 'markAllRead'],
        )->name('notifications.read-all');

        Route::patch(
            '/notifications/{userNotification}/read',
            [UserNotificationController::class, 'markRead'],
        )->name('notifications.read');

        /*
        |--------------------------------------------------------------------------
        | Tenant Files
        |--------------------------------------------------------------------------
        */

        Route::post(
            '/files',
            [TenantFileController::class, 'store'],
        )
            ->middleware('permission:files.upload')
            ->name('files.store');

        Route::get(
            '/files/{tenantFile}/download',
            [TenantFileController::class, 'download'],
        )
            ->middleware('permission:files.download')
            ->name('files.download');

        Route::delete(
            '/files/{tenantFile}',
            [TenantFileController::class, 'destroy'],
        )
            ->middleware('permission:files.delete')
            ->name('files.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Units of Measure
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/units',
        [UnitController::class, 'index'],
    )
        ->middleware('permission:units.view')
        ->name('units.index');

    Route::get(
        '/units/create',
        [UnitController::class, 'create'],
    )
        ->middleware('permission:units.create')
        ->name('units.create');

    Route::post(
        '/units',
        [UnitController::class, 'store'],
    )
        ->middleware('permission:units.create')
        ->name('units.store');

    Route::get(
        '/units/{unit}/edit',
        [UnitController::class, 'edit'],
    )
        ->middleware('permission:units.update')
        ->name('units.edit');

    Route::put(
        '/units/{unit}',
        [UnitController::class, 'update'],
    )
        ->middleware('permission:units.update')
        ->name('units.update');

    Route::delete(
        '/units/{unit}',
        [UnitController::class, 'destroy'],
    )
        ->middleware('permission:units.delete')
        ->name('units.destroy');

    /*
    |--------------------------------------------------------------------------
    | Product Categories
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/product-categories',
        [ProductCategoryController::class, 'index'],
    )
        ->middleware('permission:product_categories.view')
        ->name('product-categories.index');

    Route::get(
        '/product-categories/create',
        [ProductCategoryController::class, 'create'],
    )
        ->middleware('permission:product_categories.create')
        ->name('product-categories.create');

    Route::post(
        '/product-categories',
        [ProductCategoryController::class, 'store'],
    )
        ->middleware('permission:product_categories.create')
        ->name('product-categories.store');

    Route::get(
        '/product-categories/{productCategory}/edit',
        [ProductCategoryController::class, 'edit'],
    )
        ->middleware('permission:product_categories.update')
        ->name('product-categories.edit');

    Route::put(
        '/product-categories/{productCategory}',
        [ProductCategoryController::class, 'update'],
    )
        ->middleware('permission:product_categories.update')
        ->name('product-categories.update');

    Route::delete(
        '/product-categories/{productCategory}',
        [ProductCategoryController::class, 'destroy'],
    )
        ->middleware('permission:product_categories.delete')
        ->name('product-categories.destroy');

    /*
    |--------------------------------------------------------------------------
    | Brands
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/brands',
        [BrandController::class, 'index'],
    )
        ->middleware('permission:brands.view')
        ->name('brands.index');

    Route::get(
        '/brands/create',
        [BrandController::class, 'create'],
    )
        ->middleware('permission:brands.create')
        ->name('brands.create');

    Route::post(
        '/brands',
        [BrandController::class, 'store'],
    )
        ->middleware('permission:brands.create')
        ->name('brands.store');

    Route::get(
        '/brands/{brand}/edit',
        [BrandController::class, 'edit'],
    )
        ->middleware('permission:brands.update')
        ->name('brands.edit');

    Route::put(
        '/brands/{brand}',
        [BrandController::class, 'update'],
    )
        ->middleware('permission:brands.update')
        ->name('brands.update');

    Route::delete(
        '/brands/{brand}',
        [BrandController::class, 'destroy'],
    )
        ->middleware('permission:brands.delete')
        ->name('brands.destroy');

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/products',
        [ProductController::class, 'index'],
    )
        ->middleware('permission:products.view')
        ->name('products.index');

    Route::get(
        '/products/create',
        [ProductController::class, 'create'],
    )
        ->middleware('permission:products.create')
        ->name('products.create');

    Route::post(
        '/products',
        [ProductController::class, 'store'],
    )
        ->middleware('permission:products.create')
        ->name('products.store');

    Route::get(
        '/products/{product}/edit',
        [ProductController::class, 'edit'],
    )
        ->middleware('permission:products.update')
        ->name('products.edit');

    Route::put(
        '/products/{product}',
        [ProductController::class, 'update'],
    )
        ->middleware('permission:products.update')
        ->name('products.update');

    Route::delete(
        '/products/{product}',
        [ProductController::class, 'destroy'],
    )
        ->middleware('permission:products.delete')
        ->name('products.destroy');

    /*
    |--------------------------------------------------------------------------
    | Product Branch and Warehouse Configuration
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/products/{product}/locations',
        [
            ProductLocationConfigurationController::class,
            'show',
        ],
    )
        ->middleware('permission:products.update')
        ->name('products.locations.show');

    Route::put(
        '/products/{product}/locations/branch',
        [
            ProductLocationConfigurationController::class,
            'storeBranchSetting',
        ],
    )
        ->middleware('permission:products.update')
        ->name('products.locations.branches.store');

    Route::put(
        '/products/{product}/locations/warehouse',
        [
            ProductLocationConfigurationController::class,
            'storeWarehouseSetting',
        ],
    )
        ->middleware('permission:products.update')
        ->name('products.locations.warehouses.store');

    Route::delete(
        '/products/{product}/locations/branches/{productBranchSetting}',
        [
            ProductLocationConfigurationController::class,
            'destroyBranchSetting',
        ],
    )
        ->middleware('permission:products.update')
        ->name('products.locations.branches.destroy');

    Route::delete(
        '/products/{product}/locations/warehouses/{productWarehouseSetting}',
        [
            ProductLocationConfigurationController::class,
            'destroyWarehouseSetting',
        ],
    )
        ->middleware('permission:products.update')
        ->name('products.locations.warehouses.destroy');

    /*
    |--------------------------------------------------------------------------
    | Suppliers
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/suppliers',
        [SupplierController::class, 'index'],
    )
        ->middleware('permission:suppliers.view')
        ->name('suppliers.index');

    Route::get(
        '/suppliers/create',
        [SupplierController::class, 'create'],
    )
        ->middleware('permission:suppliers.create')
        ->name('suppliers.create');

    Route::post(
        '/suppliers',
        [SupplierController::class, 'store'],
    )
        ->middleware('permission:suppliers.create')
        ->name('suppliers.store');

    Route::get(
        '/suppliers/{supplier}/edit',
        [SupplierController::class, 'edit'],
    )
        ->middleware('permission:suppliers.update')
        ->name('suppliers.edit');

    Route::put(
        '/suppliers/{supplier}',
        [SupplierController::class, 'update'],
    )
        ->middleware('permission:suppliers.update')
        ->name('suppliers.update');

    Route::delete(
        '/suppliers/{supplier}',
        [SupplierController::class, 'destroy'],
    )
        ->middleware('permission:suppliers.delete')
        ->name('suppliers.destroy');

    /*
    |--------------------------------------------------------------------------
    | Customers
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/customers',
        [CustomerController::class, 'index'],
    )
        ->middleware('permission:customers.view')
        ->name('customers.index');

    Route::get(
        '/customers/create',
        [CustomerController::class, 'create'],
    )
        ->middleware('permission:customers.create')
        ->name('customers.create');

    Route::post(
        '/customers',
        [CustomerController::class, 'store'],
    )
        ->middleware('permission:customers.create')
        ->name('customers.store');

    Route::get(
        '/customers/{customer}/edit',
        [CustomerController::class, 'edit'],
    )
        ->middleware('permission:customers.update')
        ->name('customers.edit');

    Route::put(
        '/customers/{customer}',
        [CustomerController::class, 'update'],
    )
        ->middleware('permission:customers.update')
        ->name('customers.update');

    Route::delete(
        '/customers/{customer}',
        [CustomerController::class, 'destroy'],
    )
        ->middleware('permission:customers.delete')
        ->name('customers.destroy');

    /*
    |--------------------------------------------------------------------------
    | Purchase Orders
    |--------------------------------------------------------------------------
    */

    Route::prefix('purchase-orders')
        ->name('purchase-orders.')
        ->controller(PurchaseOrderController::class)
        ->group(function (): void {
            Route::get('/', 'index')
                ->middleware(
                    'permission:purchase_orders.view',
                )
                ->name('index');

            Route::get('/create', 'create')
                ->middleware(
                    'permission:purchase_orders.create',
                )
                ->name('create');

            Route::post('/', 'store')
                ->middleware(
                    'permission:purchase_orders.create',
                )
                ->name('store');

            Route::get(
                '/{purchaseOrder}',
                'show',
            )
                ->middleware(
                    'permission:purchase_orders.view',
                )
                ->name('show');

            Route::get(
                '/{purchaseOrder}/edit',
                'edit',
            )
                ->middleware(
                    'permission:purchase_orders.update',
                )
                ->name('edit');

            Route::put(
                '/{purchaseOrder}',
                'update',
            )
                ->middleware(
                    'permission:purchase_orders.update',
                )
                ->name('update');

            Route::delete(
                '/{purchaseOrder}',
                'destroy',
            )
                ->middleware(
                    'permission:purchase_orders.delete',
                )
                ->name('destroy');

            Route::post(
                '/{purchaseOrder}/submit',
                'submit',
            )
                ->middleware(
                    'permission:purchase_orders.submit',
                )
                ->name('submit');

            Route::post(
                '/{purchaseOrder}/return-to-draft',
                'returnToDraft',
            )
                ->middleware(
                    'permission:purchase_orders.update',
                )
                ->name('return-to-draft');

            Route::post(
                '/{purchaseOrder}/approve',
                'approve',
            )
                ->middleware(
                    'permission:purchase_orders.approve',
                )
                ->name('approve');

            Route::post(
                '/{purchaseOrder}/cancel',
                'cancel',
            )
                ->middleware(
                    'permission:purchase_orders.cancel',
                )
                ->name('cancel');
        });

    /*
    |--------------------------------------------------------------------------
    | Goods Receipts
    |--------------------------------------------------------------------------
    */

    Route::prefix('goods-receipts')
        ->name('goods-receipts.')
        ->controller(
            GoodsReceiptController::class,
        )
        ->group(function (): void {
            Route::get('/', 'index')
                ->middleware(
                    'permission:goods_receipts.view',
                )
                ->name('index');

            Route::get('/create', 'create')
                ->middleware(
                    'permission:goods_receipts.create',
                )
                ->name('create');

            Route::post('/', 'store')
                ->middleware(
                    'permission:goods_receipts.create',
                )
                ->name('store');

            Route::get(
                '/{goodsReceipt}',
                'show',
            )
                ->middleware(
                    'permission:goods_receipts.view',
                )
                ->name('show');

            Route::get(
                '/{goodsReceipt}/edit',
                'edit',
            )
                ->middleware(
                    'permission:goods_receipts.update',
                )
                ->name('edit');

            Route::put(
                '/{goodsReceipt}',
                'update',
            )
                ->middleware(
                    'permission:goods_receipts.update',
                )
                ->name('update');

            Route::delete(
                '/{goodsReceipt}',
                'destroy',
            )
                ->middleware(
                    'permission:goods_receipts.delete',
                )
                ->name('destroy');

            Route::post(
                '/{goodsReceipt}/post',
                'post',
            )
                ->middleware(
                    'permission:goods_receipts.post',
                )
                ->name('post');

            Route::post(
                '/{goodsReceipt}/reverse',
                'reverse',
            )
                ->middleware(
                    'permission:goods_receipts.reverse',
                )
                ->name('reverse');
        });

        /*
        |--------------------------------------------------------------------------
        | Supplier Invoices
        |--------------------------------------------------------------------------
        */

        Route::prefix('supplier-invoices')
            ->name('supplier-invoices.')
            ->controller(
                SupplierInvoiceController::class,
            )
            ->group(function (): void {
                Route::get('/', 'index')
                    ->middleware(
                        'permission:supplier_invoices.view',
                    )
                    ->name('index');

                Route::get('/create', 'create')
                    ->middleware(
                        'permission:supplier_invoices.create',
                    )
                    ->name('create');

                Route::post('/', 'store')
                    ->middleware(
                        'permission:supplier_invoices.create',
                    )
                    ->name('store');

                Route::get(
                    '/{supplierInvoice}',
                    'show',
                )
                    ->middleware(
                        'permission:supplier_invoices.view',
                    )
                    ->name('show');

                Route::get(
                    '/{supplierInvoice}/edit',
                    'edit',
                )
                    ->middleware(
                        'permission:supplier_invoices.update',
                    )
                    ->name('edit');

                Route::put(
                    '/{supplierInvoice}',
                    'update',
                )
                    ->middleware(
                        'permission:supplier_invoices.update',
                    )
                    ->name('update');

                Route::delete(
                    '/{supplierInvoice}',
                    'destroy',
                )
                    ->middleware(
                        'permission:supplier_invoices.delete',
                    )
                    ->name('destroy');

                Route::post(
                    '/{supplierInvoice}/validate',
                    'validateInvoice',
                )
                    ->middleware(
                        'permission:supplier_invoices.validate',
                    )
                    ->name('validate');

                Route::post(
                    '/{supplierInvoice}/return-to-draft',
                    'returnToDraft',
                )
                    ->middleware(
                        'permission:supplier_invoices.validate',
                    )
                    ->name('return-to-draft');

                Route::post(
                    '/{supplierInvoice}/approve',
                    'approve',
                )
                    ->middleware(
                        'permission:supplier_invoices.approve',
                    )
                    ->name('approve');

                Route::post(
                    '/{supplierInvoice}/dispute',
                    'dispute',
                )
                    ->middleware(
                        'permission:supplier_invoices.dispute',
                    )
                    ->name('dispute');

                Route::post(
                    '/{supplierInvoice}/cancel',
                    'cancel',
                )
                    ->middleware(
                        'permission:supplier_invoices.cancel',
                    )
                    ->name('cancel');

                Route::post(
                    '/{supplierInvoice}/post',
                    'post',
                )
                    ->middleware(
                        'permission:supplier_invoices.post',
                    )
                    ->name('post');

                Route::post(
                    '/{supplierInvoice}/reverse',
                    'reverse',
                )
                    ->middleware(
                        'permission:supplier_invoices.reverse',
                    )
                    ->name('reverse');
            });
        });

        /*
|--------------------------------------------------------------------------
| Purchase Returns
|--------------------------------------------------------------------------
*/

Route::prefix('purchase-returns')
    ->name('purchase-returns.')
    ->controller(
        PurchaseReturnController::class,
    )
    ->group(function (): void {
        Route::get('/', 'index')
            ->middleware(
                'permission:purchase_returns.view',
            )
            ->name('index');

        Route::get('/create', 'create')
            ->middleware(
                'permission:purchase_returns.create',
            )
            ->name('create');

        Route::post('/', 'store')
            ->middleware(
                'permission:purchase_returns.create',
            )
            ->name('store');

        Route::get(
            '/{purchaseReturn}',
            'show',
        )
            ->middleware(
                'permission:purchase_returns.view',
            )
            ->name('show');

        Route::get(
            '/{purchaseReturn}/edit',
            'edit',
        )
            ->middleware(
                'permission:purchase_returns.update',
            )
            ->name('edit');

        Route::put(
            '/{purchaseReturn}',
            'update',
        )
            ->middleware(
                'permission:purchase_returns.update',
            )
            ->name('update');

        Route::delete(
            '/{purchaseReturn}',
            'destroy',
        )
            ->middleware(
                'permission:purchase_returns.delete',
            )
            ->name('destroy');

        Route::post(
            '/{purchaseReturn}/submit',
            'submit',
        )
            ->middleware(
                'permission:purchase_returns.submit',
            )
            ->name('submit');

        Route::post(
            '/{purchaseReturn}/return-to-draft',
            'returnToDraft',
        )
            ->middleware(
                'permission:purchase_returns.submit',
            )
            ->name('return-to-draft');

        Route::post(
            '/{purchaseReturn}/approve',
            'approve',
        )
            ->middleware(
                'permission:purchase_returns.approve',
            )
            ->name('approve');

        Route::post(
            '/{purchaseReturn}/cancel',
            'cancel',
        )
            ->middleware(
                'permission:purchase_returns.cancel',
            )
            ->name('cancel');

        Route::post(
            '/{purchaseReturn}/post',
            'post',
        )
            ->middleware(
                'permission:purchase_returns.post',
            )
            ->name('post');

        Route::post(
            '/{purchaseReturn}/reverse',
            'reverse',
        )
            ->middleware(
                'permission:purchase_returns.reverse',
            )
            ->name('reverse');

            /*
|--------------------------------------------------------------------------
| Supplier Debit Notes
|--------------------------------------------------------------------------
*/

Route::prefix('supplier-debit-notes')
    ->name('supplier-debit-notes.')
    ->controller(
        SupplierDebitNoteController::class,
    )
    ->group(function (): void {
        Route::get('/', 'index')
            ->middleware(
                'permission:supplier_debit_notes.view',
            )
            ->name('index');

        Route::get('/create', 'create')
            ->middleware(
                'permission:supplier_debit_notes.create',
            )
            ->name('create');

        Route::post('/', 'store')
            ->middleware(
                'permission:supplier_debit_notes.create',
            )
            ->name('store');

        Route::get(
            '/{supplierDebitNote}',
            'show',
        )
            ->middleware(
                'permission:supplier_debit_notes.view',
            )
            ->name('show');

        Route::get(
            '/{supplierDebitNote}/edit',
            'edit',
        )
            ->middleware(
                'permission:supplier_debit_notes.update',
            )
            ->name('edit');

        Route::put(
            '/{supplierDebitNote}',
            'update',
        )
            ->middleware(
                'permission:supplier_debit_notes.update',
            )
            ->name('update');

        Route::delete(
            '/{supplierDebitNote}',
            'destroy',
        )
            ->middleware(
                'permission:supplier_debit_notes.delete',
            )
            ->name('destroy');

        Route::post(
            '/{supplierDebitNote}/submit',
            'submit',
        )
            ->middleware(
                'permission:supplier_debit_notes.submit',
            )
            ->name('submit');

        Route::post(
            '/{supplierDebitNote}/return-to-draft',
            'returnToDraft',
        )
            ->middleware(
                'permission:supplier_debit_notes.submit',
            )
            ->name('return-to-draft');

        Route::post(
            '/{supplierDebitNote}/approve',
            'approve',
        )
            ->middleware(
                'permission:supplier_debit_notes.approve',
            )
            ->name('approve');

        Route::post(
            '/{supplierDebitNote}/cancel',
            'cancel',
        )
            ->middleware(
                'permission:supplier_debit_notes.cancel',
            )
            ->name('cancel');

        Route::post(
            '/{supplierDebitNote}/post',
            'post',
        )
            ->middleware(
                'permission:supplier_debit_notes.post',
            )
            ->name('post');

        Route::post(
            '/{supplierDebitNote}/reverse',
            'reverse',
        )
            ->middleware(
                'permission:supplier_debit_notes.reverse',
            )
            ->name('reverse');
    });
    });

/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

Route::post(
    '/logout',
    [AuthenticatedSessionController::class, 'destroy'],
)
    ->middleware('auth')
    ->name('logout');