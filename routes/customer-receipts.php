<?php

declare(strict_types=1);

use App\Http\Controllers\Accounting\CustomerReceiptController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'tenant.context',
    'tenant.active',
])
    ->prefix('erp/customer-receipts')
    ->name('customer-receipts.')
    ->controller(CustomerReceiptController::class)
    ->group(function (): void {
        Route::get('/', 'index')
            ->middleware('permission:customer_receipts.view')
            ->name('index');

        Route::get('/create', 'create')
            ->middleware('permission:customer_receipts.create')
            ->name('create');

        Route::post('/', 'store')
            ->middleware('permission:customer_receipts.create')
            ->name('store');

        Route::get('/{customerReceipt}', 'show')
            ->middleware('permission:customer_receipts.view')
            ->name('show');

        Route::get('/{customerReceipt}/edit', 'edit')
            ->middleware('permission:customer_receipts.update')
            ->name('edit');

        Route::put('/{customerReceipt}', 'update')
            ->middleware('permission:customer_receipts.update')
            ->name('update');

        Route::delete('/{customerReceipt}', 'destroy')
            ->middleware('permission:customer_receipts.delete')
            ->name('destroy');

        Route::post('/{customerReceipt}/submit', 'submit')
            ->middleware('permission:customer_receipts.submit')
            ->name('submit');

        Route::post('/{customerReceipt}/return-to-draft', 'returnToDraft')
            ->middleware('permission:customer_receipts.submit')
            ->name('return-to-draft');

        Route::post('/{customerReceipt}/approve', 'approve')
            ->middleware('permission:customer_receipts.approve')
            ->name('approve');

        Route::post('/{customerReceipt}/cancel', 'cancel')
            ->middleware('permission:customer_receipts.cancel')
            ->name('cancel');

        Route::post('/{customerReceipt}/post', 'post')
            ->middleware('permission:customer_receipts.post')
            ->name('post');

        Route::post('/{customerReceipt}/reverse', 'reverse')
            ->middleware('permission:customer_receipts.reverse')
            ->name('reverse');

        Route::get('/{customerReceipt}/print', 'print')
            ->middleware('permission:customer_receipts.view')
            ->name('print');
    });