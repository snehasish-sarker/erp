<?php

declare(strict_types=1);

use App\Http\Controllers\Sales\SalesOrderAllocationController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'tenant.context',
    'tenant.active',
    'permission:sales_orders.allocate',
])
    ->prefix(
        'erp/sales-orders/{salesOrder}/allocation',
    )
    ->name(
        'sales-orders.allocation.',
    )
    ->controller(
        SalesOrderAllocationController::class,
    )
    ->group(function (): void {
        Route::get(
            '/',
            'show',
        )->name('show');

        Route::put(
            '/',
            'store',
        )->name('store');

        Route::post(
            '/release',
            'release',
        )->name('release');
    });