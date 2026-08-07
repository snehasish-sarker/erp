<?php

declare(strict_types=1);

use App\Http\Controllers\Accounting\BankReconciliationController;
use App\Http\Controllers\Accounting\BankStatementController;
use App\Http\Controllers\Accounting\TreasuryAdjustmentController;
use App\Http\Controllers\Accounting\TreasuryController;
use App\Http\Controllers\Accounting\TreasuryTransferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.context', 'tenant.active'])
    ->prefix('erp')
    ->group(function (): void {
        Route::get('/treasury', [TreasuryController::class, 'index'])
            ->middleware('permission:treasury.view')
            ->name('treasury.index');

        Route::get('/treasury/register', [TreasuryController::class, 'register'])
            ->middleware('permission:treasury.view')
            ->name('treasury.register');

        Route::prefix('treasury/transfers')
            ->name('treasury-transfers.')
            ->controller(TreasuryTransferController::class)
            ->group(function (): void {
                Route::get('/', 'index')->middleware('permission:treasury_transfers.view')->name('index');
                Route::get('/create', 'create')->middleware('permission:treasury_transfers.create')->name('create');
                Route::post('/', 'store')->middleware('permission:treasury_transfers.create')->name('store');
                Route::get('/{treasuryTransfer}', 'show')->middleware('permission:treasury_transfers.view')->name('show');
                Route::get('/{treasuryTransfer}/edit', 'edit')->middleware('permission:treasury_transfers.update')->name('edit');
                Route::put('/{treasuryTransfer}', 'update')->middleware('permission:treasury_transfers.update')->name('update');
                Route::delete('/{treasuryTransfer}', 'destroy')->middleware('permission:treasury_transfers.delete')->name('destroy');
                Route::post('/{treasuryTransfer}/submit', 'submit')->middleware('permission:treasury_transfers.submit')->name('submit');
                Route::post('/{treasuryTransfer}/return-to-draft', 'returnToDraft')->middleware('permission:treasury_transfers.update')->name('return-to-draft');
                Route::post('/{treasuryTransfer}/approve', 'approve')->middleware('permission:treasury_transfers.approve')->name('approve');
                Route::post('/{treasuryTransfer}/post', 'post')->middleware('permission:treasury_transfers.post')->name('post');
                Route::post('/{treasuryTransfer}/cancel', 'cancel')->middleware('permission:treasury_transfers.cancel')->name('cancel');
                Route::post('/{treasuryTransfer}/reverse', 'reverse')->middleware('permission:treasury_transfers.reverse')->name('reverse');
            });

        Route::prefix('treasury/adjustments')
            ->name('treasury-adjustments.')
            ->controller(TreasuryAdjustmentController::class)
            ->group(function (): void {
                Route::get('/', 'index')->middleware('permission:treasury_adjustments.view')->name('index');
                Route::get('/create', 'create')->middleware('permission:treasury_adjustments.create')->name('create');
                Route::post('/', 'store')->middleware('permission:treasury_adjustments.create')->name('store');
                Route::get('/{treasuryAdjustment}', 'show')->middleware('permission:treasury_adjustments.view')->name('show');
                Route::get('/{treasuryAdjustment}/edit', 'edit')->middleware('permission:treasury_adjustments.update')->name('edit');
                Route::put('/{treasuryAdjustment}', 'update')->middleware('permission:treasury_adjustments.update')->name('update');
                Route::delete('/{treasuryAdjustment}', 'destroy')->middleware('permission:treasury_adjustments.delete')->name('destroy');
                Route::post('/{treasuryAdjustment}/submit', 'submit')->middleware('permission:treasury_adjustments.submit')->name('submit');
                Route::post('/{treasuryAdjustment}/return-to-draft', 'returnToDraft')->middleware('permission:treasury_adjustments.update')->name('return-to-draft');
                Route::post('/{treasuryAdjustment}/approve', 'approve')->middleware('permission:treasury_adjustments.approve')->name('approve');
                Route::post('/{treasuryAdjustment}/post', 'post')->middleware('permission:treasury_adjustments.post')->name('post');
                Route::post('/{treasuryAdjustment}/cancel', 'cancel')->middleware('permission:treasury_adjustments.cancel')->name('cancel');
                Route::post('/{treasuryAdjustment}/reverse', 'reverse')->middleware('permission:treasury_adjustments.reverse')->name('reverse');
            });

        Route::prefix('treasury/bank-statements')
            ->name('bank-statements.')
            ->controller(BankStatementController::class)
            ->group(function (): void {
                Route::get('/', 'index')->middleware('permission:bank_statements.view')->name('index');
                Route::get('/create', 'create')->middleware('permission:bank_statements.import')->name('create');
                Route::get('/template', 'template')->middleware('permission:bank_statements.import')->name('template');
                Route::post('/', 'store')->middleware('permission:bank_statements.import')->name('store');
                Route::get('/{bankStatement}', 'show')->middleware('permission:bank_statements.view')->name('show');
                Route::delete('/{bankStatement}', 'destroy')->middleware('permission:bank_statements.delete')->name('destroy');
            });

        Route::prefix('treasury/bank-reconciliations')
            ->name('bank-reconciliations.')
            ->controller(BankReconciliationController::class)
            ->group(function (): void {
                Route::get('/', 'index')->middleware('permission:bank_reconciliations.view')->name('index');
                Route::get('/create', 'create')->middleware('permission:bank_reconciliations.create')->name('create');
                Route::post('/', 'store')->middleware('permission:bank_reconciliations.create')->name('store');
                Route::get('/{bankReconciliation}', 'show')->middleware('permission:bank_reconciliations.view')->name('show');
                Route::delete('/{bankReconciliation}', 'destroy')->middleware('permission:bank_reconciliations.match')->name('destroy');
                Route::post('/{bankReconciliation}/automatic-match', 'automaticMatch')->middleware('permission:bank_reconciliations.match')->name('automatic-match');
                Route::post('/{bankReconciliation}/manual-match', 'manualMatch')->middleware('permission:bank_reconciliations.match')->name('manual-match');
                Route::delete('/{bankReconciliation}/matches/{bankReconciliationMatch}', 'unmatch')->middleware('permission:bank_reconciliations.match')->name('unmatch');
                Route::post('/{bankReconciliation}/statement-lines/{bankStatementLine}/ignore', 'ignore')->middleware('permission:bank_reconciliations.match')->name('ignore');
                Route::post('/{bankReconciliation}/statement-lines/{bankStatementLine}/unignore', 'unignore')->middleware('permission:bank_reconciliations.match')->name('unignore');
                Route::post('/{bankReconciliation}/refresh', 'refresh')->middleware('permission:bank_reconciliations.view')->name('refresh');
                Route::post('/{bankReconciliation}/complete', 'complete')->middleware('permission:bank_reconciliations.complete')->name('complete');
                Route::post('/{bankReconciliation}/reverse', 'reverse')->middleware('permission:bank_reconciliations.reverse')->name('reverse');
                Route::get('/{bankReconciliation}/print', 'print')->middleware('permission:bank_reconciliations.view')->name('print');
            });
    });
