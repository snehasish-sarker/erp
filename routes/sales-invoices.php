<?php

declare(strict_types=1);

use App\Http\Controllers\Sales\SalesInvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'tenant.context',
    'tenant.active',
])
    ->prefix('erp/sales-invoices')
    ->name('sales-invoices.')
    ->controller(SalesInvoiceController::class)
    ->group(function (): void {
        Route::get('/', 'index')
            ->middleware('permission:sales_invoices.view')
            ->name('index');

        Route::get('/create', 'create')
            ->middleware('permission:sales_invoices.create')
            ->name('create');

        Route::post('/', 'store')
            ->middleware('permission:sales_invoices.create')
            ->name('store');

        Route::get('/{salesInvoice}', 'show')
            ->middleware('permission:sales_invoices.view')
            ->name('show');

        Route::get('/{salesInvoice}/edit', 'edit')
            ->middleware('permission:sales_invoices.create')
            ->name('edit');

        Route::put('/{salesInvoice}', 'update')
            ->middleware('permission:sales_invoices.create')
            ->name('update');

        Route::delete('/{salesInvoice}', 'destroy')
            ->middleware('permission:sales_invoices.create')
            ->name('destroy');

        Route::post('/{salesInvoice}/post', 'post')
            ->middleware('permission:sales_invoices.post')
            ->name('post');

        Route::post('/{salesInvoice}/reverse', 'reverse')
            ->middleware('permission:sales_invoices.reverse')
            ->name('reverse');

        Route::get('/{salesInvoice}/print', 'print')
            ->middleware('permission:sales_invoices.view')
            ->name('print');
    });