<?php

declare(strict_types=1);

use App\Http\Controllers\Sales\CustomerCreditNoteController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'tenant.context',
    'tenant.active',
])
    ->prefix('erp/sales-returns')
    ->name('sales-returns.')
    ->controller(CustomerCreditNoteController::class)
    ->group(function (): void {
        Route::get('/', 'index')
            ->middleware('permission:sales_returns.view')
            ->name('index');

        Route::get('/create', 'create')
            ->middleware('permission:sales_returns.create')
            ->name('create');

        Route::post('/', 'store')
            ->middleware('permission:sales_returns.create')
            ->name('store');

        Route::get('/{customerCreditNote}', 'show')
            ->middleware('permission:sales_returns.view')
            ->name('show');

        Route::get('/{customerCreditNote}/edit', 'edit')
            ->middleware('permission:sales_returns.create')
            ->name('edit');

        Route::put('/{customerCreditNote}', 'update')
            ->middleware('permission:sales_returns.create')
            ->name('update');

        Route::delete('/{customerCreditNote}', 'destroy')
            ->middleware('permission:sales_returns.create')
            ->name('destroy');

        Route::post('/{customerCreditNote}/submit', 'submit')
            ->middleware('permission:sales_returns.create')
            ->name('submit');

        Route::post('/{customerCreditNote}/return-to-draft', 'returnToDraft')
            ->middleware('permission:sales_returns.approve')
            ->name('return-to-draft');

        Route::post('/{customerCreditNote}/approve', 'approve')
            ->middleware('permission:sales_returns.approve')
            ->name('approve');

        Route::post('/{customerCreditNote}/cancel', 'cancel')
            ->middleware('permission:sales_returns.create')
            ->name('cancel');

        Route::post('/{customerCreditNote}/post', 'post')
            ->middleware('permission:sales_returns.post')
            ->name('post');

        Route::post('/{customerCreditNote}/reverse', 'reverse')
            ->middleware('permission:sales_returns.reverse')
            ->name('reverse');

        Route::get('/{customerCreditNote}/print', 'print')
            ->middleware('permission:sales_returns.view')
            ->name('print');
    });