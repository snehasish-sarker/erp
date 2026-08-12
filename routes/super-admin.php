<?php

declare(strict_types=1);

use App\Http\Controllers\Platform\PlatformAuthenticatedSessionController;
use App\Http\Controllers\Platform\PlatformDashboardController;
use App\Http\Controllers\Platform\PlatformSaasBillingController;
use App\Http\Controllers\Platform\PlatformSaasPlanController;
use App\Http\Controllers\Platform\PlatformSaasUsageController;
use App\Http\Controllers\Platform\PlatformSubscriptionDashboardController;
use App\Http\Controllers\Platform\PlatformSubscriptionHistoryController;
use App\Http\Controllers\Platform\PlatformTenantController;
use App\Http\Controllers\Platform\PlatformTenantSubscriptionController;
use App\Http\Controllers\Platform\PlatformTenantSubscriptionLifecycleController;
use App\Http\Controllers\Platform\PlatformTenantSubscriptionQuickActionController;
use App\Http\Middleware\EnsurePlatformAdminAuthenticated;
use App\Http\Middleware\RedirectIfPlatformAdminAuthenticated;
use Illuminate\Support\Facades\Route;

Route::prefix('super-admin')
    ->name('platform.')
    ->group(function (): void {
        Route::middleware(RedirectIfPlatformAdminAuthenticated::class)
            ->group(function (): void {
                Route::get(
                    '/login',
                    [
                        PlatformAuthenticatedSessionController::class,
                        'create',
                    ],
                )->name('login');

                Route::post(
                    '/login',
                    [
                        PlatformAuthenticatedSessionController::class,
                        'store',
                    ],
                )->name('login.store');
            });

        Route::middleware(EnsurePlatformAdminAuthenticated::class)
            ->group(function (): void {
                Route::get(
                    '/',
                    PlatformDashboardController::class,
                )->name('dashboard');

                Route::prefix('tenants')
                    ->name('tenants.')
                    ->controller(PlatformTenantController::class)
                    ->group(function (): void {
                        Route::get('/', 'index')
                            ->name('index');

                        Route::get('/create', 'create')
                            ->name('create');

                        Route::post('/', 'store')
                            ->name('store');

                        Route::get('/{tenant}', 'show')
                            ->name('show');

                        Route::patch('/{tenant}/activate', 'activate')
                            ->name('activate');

                        Route::patch('/{tenant}/suspend', 'suspend')
                            ->name('suspend');
                    });

                Route::patch(
                    '/tenants/{tenant}/subscription',
                    [
                        PlatformTenantSubscriptionController::class,
                        'update',
                    ],
                )->name('tenants.subscription.update');

                Route::patch(
                    '/tenants/{tenant}/subscription/trial',
                    [
                        PlatformTenantSubscriptionLifecycleController::class,
                        'extendTrial',
                    ],
                )->name('tenants.subscription.trial.extend');

                Route::patch(
                    '/tenants/{tenant}/subscription/quick-action',
                    PlatformTenantSubscriptionQuickActionController::class,
                )->name('tenants.subscription.quick-action');

                Route::get(
                    '/subscriptions',
                    PlatformSubscriptionDashboardController::class,
                )->name('subscriptions.index');

                Route::get(
                    '/subscriptions/history',
                    PlatformSubscriptionHistoryController::class,
                )->name('subscriptions.history');

                Route::get(
                    '/usage',
                    PlatformSaasUsageController::class,
                )->name('usage.index');

                Route::prefix('plans')
                    ->name('plans.')
                    ->controller(PlatformSaasPlanController::class)
                    ->group(function (): void {
                        Route::get('/', 'index')
                            ->name('index');

                        Route::get('/create', 'create')
                            ->name('create');

                        Route::post('/', 'store')
                            ->name('store');

                        Route::get('/{saasPlan}/edit', 'edit')
                            ->name('edit');

                        Route::put('/{saasPlan}', 'update')
                            ->name('update');
                    });



                Route::prefix('billing')
                    ->name('billing.')
                    ->controller(PlatformSaasBillingController::class)
                    ->group(function (): void {
                        Route::get('/invoices', 'index')
                            ->name('invoices.index');

                        Route::get('/invoices/{saasInvoice}', 'show')
                            ->name('invoices.show');

                        Route::post('/tenants/{tenant}/invoices', 'generate')
                            ->name('tenants.invoices.generate');

                        Route::post(
                            '/invoices/{saasInvoice}/manual-payment',
                            'recordManualPayment',
                        )->name('invoices.manual-payment');
                    });

                Route::post(
                    '/logout',
                    [
                        PlatformAuthenticatedSessionController::class,
                        'destroy',
                    ],
                )->name('logout');
            });
    });