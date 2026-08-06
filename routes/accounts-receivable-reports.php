<?php

declare(strict_types=1);

use App\Http\Controllers\Accounting\AccountsReceivableReportController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'tenant.context',
    'tenant.active',
    'permission:reports.receivables',
])
    ->prefix('reports/accounts-receivable')
    ->name('reports.accounts-receivable.')
    ->controller(AccountsReceivableReportController::class)
    ->group(function (): void {
        Route::get('/aging', 'aging')
            ->name('aging');

        Route::get('/aging/print', 'printAging')
            ->name('aging.print');

        Route::get(
            '/aging/customers/{customerId}',
            'customerAging',
        )
            ->whereNumber('customerId')
            ->name('aging.customers.show');

        Route::get(
            '/aging/customers/{customerId}/print',
            'printCustomerAging',
        )
            ->whereNumber('customerId')
            ->name('aging.customers.print');

        Route::get(
            '/customer-statement',
            'customerStatement',
        )->name('customer-statement');

        Route::get(
            '/customer-statement/print',
            'printCustomerStatement',
        )->name('customer-statement.print');

        Route::get('/open-invoices', 'openInvoices')
            ->name('open-invoices');

        Route::get(
            '/open-invoices/print',
            'printOpenInvoices',
        )->name('open-invoices.print');

        Route::get(
            '/overdue-invoices',
            'overdueInvoices',
        )->name('overdue-invoices');

        Route::get(
            '/overdue-invoices/print',
            'printOverdueInvoices',
        )->name('overdue-invoices.print');
    });