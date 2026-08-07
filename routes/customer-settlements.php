<?php

declare(strict_types=1);

use App\Http\Controllers\Accounting\CustomerArAdjustmentController;
use App\Http\Controllers\Accounting\CustomerCreditApplicationController;
use App\Http\Controllers\Accounting\CustomerCreditBalanceController;
use App\Http\Controllers\Accounting\CustomerRefundController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'tenant.context',
    'tenant.active',
])
    ->prefix('erp')
    ->group(function (): void {
        Route::get(
            '/customer-credits',
            [CustomerCreditBalanceController::class, 'index'],
        )
            ->middleware('permission:customer_credits.view')
            ->name('customer-credits.index');

        Route::prefix('customer-credit-applications')
            ->name('customer-credit-applications.')
            ->controller(CustomerCreditApplicationController::class)
            ->group(function (): void {
                Route::get('/', 'index')
                    ->middleware('permission:customer_credit_applications.view')
                    ->name('index');

                Route::get('/create', 'create')
                    ->middleware('permission:customer_credit_applications.create')
                    ->name('create');

                Route::post('/', 'store')
                    ->middleware('permission:customer_credit_applications.create')
                    ->name('store');

                Route::get('/{customerCreditApplication}', 'show')
                    ->middleware('permission:customer_credit_applications.view')
                    ->name('show');

                Route::get('/{customerCreditApplication}/edit', 'edit')
                    ->middleware('permission:customer_credit_applications.update')
                    ->name('edit');

                Route::put('/{customerCreditApplication}', 'update')
                    ->middleware('permission:customer_credit_applications.update')
                    ->name('update');

                Route::delete('/{customerCreditApplication}', 'destroy')
                    ->middleware('permission:customer_credit_applications.delete')
                    ->name('destroy');

                Route::post('/{customerCreditApplication}/submit', 'submit')
                    ->middleware('permission:customer_credit_applications.submit')
                    ->name('submit');

                Route::post(
                    '/{customerCreditApplication}/return-to-draft',
                    'returnToDraft',
                )
                    ->middleware('permission:customer_credit_applications.submit')
                    ->name('return-to-draft');

                Route::post('/{customerCreditApplication}/approve', 'approve')
                    ->middleware('permission:customer_credit_applications.approve')
                    ->name('approve');

                Route::post('/{customerCreditApplication}/post', 'post')
                    ->middleware('permission:customer_credit_applications.post')
                    ->name('post');

                Route::post('/{customerCreditApplication}/cancel', 'cancel')
                    ->middleware('permission:customer_credit_applications.cancel')
                    ->name('cancel');

                Route::post('/{customerCreditApplication}/reverse', 'reverse')
                    ->middleware('permission:customer_credit_applications.reverse')
                    ->name('reverse');
            });

        Route::prefix('customer-refunds')
            ->name('customer-refunds.')
            ->controller(CustomerRefundController::class)
            ->group(function (): void {
                Route::get('/', 'index')
                    ->middleware('permission:customer_refunds.view')
                    ->name('index');

                Route::get('/create', 'create')
                    ->middleware('permission:customer_refunds.create')
                    ->name('create');

                Route::post('/', 'store')
                    ->middleware('permission:customer_refunds.create')
                    ->name('store');

                Route::get('/{customerRefund}', 'show')
                    ->middleware('permission:customer_refunds.view')
                    ->name('show');

                Route::get('/{customerRefund}/edit', 'edit')
                    ->middleware('permission:customer_refunds.update')
                    ->name('edit');

                Route::put('/{customerRefund}', 'update')
                    ->middleware('permission:customer_refunds.update')
                    ->name('update');

                Route::delete('/{customerRefund}', 'destroy')
                    ->middleware('permission:customer_refunds.delete')
                    ->name('destroy');

                Route::post('/{customerRefund}/submit', 'submit')
                    ->middleware('permission:customer_refunds.submit')
                    ->name('submit');

                Route::post('/{customerRefund}/return-to-draft', 'returnToDraft')
                    ->middleware('permission:customer_refunds.submit')
                    ->name('return-to-draft');

                Route::post('/{customerRefund}/approve', 'approve')
                    ->middleware('permission:customer_refunds.approve')
                    ->name('approve');

                Route::post('/{customerRefund}/post', 'post')
                    ->middleware('permission:customer_refunds.post')
                    ->name('post');

                Route::post('/{customerRefund}/cancel', 'cancel')
                    ->middleware('permission:customer_refunds.cancel')
                    ->name('cancel');

                Route::post('/{customerRefund}/reverse', 'reverse')
                    ->middleware('permission:customer_refunds.reverse')
                    ->name('reverse');

                Route::get('/{customerRefund}/print', 'print')
                    ->middleware('permission:customer_refunds.view')
                    ->name('print');
            });

        Route::prefix('customer-ar-adjustments')
            ->name('customer-ar-adjustments.')
            ->controller(CustomerArAdjustmentController::class)
            ->group(function (): void {
                Route::get('/', 'index')
                    ->middleware('permission:customer_ar_adjustments.view')
                    ->name('index');

                Route::get('/create', 'create')
                    ->middleware('permission:customer_ar_adjustments.create')
                    ->name('create');

                Route::post('/', 'store')
                    ->middleware('permission:customer_ar_adjustments.create')
                    ->name('store');

                Route::get('/{customerArAdjustment}', 'show')
                    ->middleware('permission:customer_ar_adjustments.view')
                    ->name('show');

                Route::get('/{customerArAdjustment}/edit', 'edit')
                    ->middleware('permission:customer_ar_adjustments.update')
                    ->name('edit');

                Route::put('/{customerArAdjustment}', 'update')
                    ->middleware('permission:customer_ar_adjustments.update')
                    ->name('update');

                Route::delete('/{customerArAdjustment}', 'destroy')
                    ->middleware('permission:customer_ar_adjustments.delete')
                    ->name('destroy');

                Route::post('/{customerArAdjustment}/submit', 'submit')
                    ->middleware('permission:customer_ar_adjustments.submit')
                    ->name('submit');

                Route::post(
                    '/{customerArAdjustment}/return-to-draft',
                    'returnToDraft',
                )
                    ->middleware('permission:customer_ar_adjustments.submit')
                    ->name('return-to-draft');

                Route::post('/{customerArAdjustment}/approve', 'approve')
                    ->middleware('permission:customer_ar_adjustments.approve')
                    ->name('approve');

                Route::post('/{customerArAdjustment}/post', 'post')
                    ->middleware('permission:customer_ar_adjustments.post')
                    ->name('post');

                Route::post('/{customerArAdjustment}/cancel', 'cancel')
                    ->middleware('permission:customer_ar_adjustments.cancel')
                    ->name('cancel');

                Route::post('/{customerArAdjustment}/reverse', 'reverse')
                    ->middleware('permission:customer_ar_adjustments.reverse')
                    ->name('reverse');
            });
    });