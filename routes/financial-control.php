<?php

declare(strict_types=1);

use App\Http\Controllers\Accounting\FinancialControlController;
use App\Http\Controllers\Accounting\FinancialStatementController;
use App\Http\Controllers\Accounting\PeriodCloseController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'tenant.context',
    'tenant.active',
])
    ->prefix('erp')
    ->group(function (): void {
        Route::prefix('financial-control')
            ->name('financial-control.')
            ->group(function (): void {
                Route::middleware('permission:financial_control.view')
                    ->group(function (): void {
                        Route::get(
                            '/',
                            [FinancialControlController::class, 'index'],
                        )->name('index');

                        Route::get(
                            '/reconciliations',
                            [FinancialControlController::class, 'reconciliations'],
                        )->name('reconciliations');

                        Route::get(
                            '/period-close/{accountingPeriod}',
                            [PeriodCloseController::class, 'show'],
                        )
                            ->whereNumber('accountingPeriod')
                            ->name('period-close.show');
                    });

                Route::post(
                    '/period-close/{accountingPeriod}/prepare',
                    [PeriodCloseController::class, 'prepare'],
                )
                    ->middleware('permission:period_close.prepare')
                    ->whereNumber('accountingPeriod')
                    ->name('period-close.prepare');

                Route::post(
                    '/period-close/{accountingPeriod}/close',
                    [PeriodCloseController::class, 'close'],
                )
                    ->middleware('permission:period_close.close')
                    ->whereNumber('accountingPeriod')
                    ->name('period-close.close');

                Route::post(
                    '/period-close/{accountingPeriod}/reopen',
                    [PeriodCloseController::class, 'reopen'],
                )
                    ->middleware('permission:period_close.reopen')
                    ->whereNumber('accountingPeriod')
                    ->name('period-close.reopen');
            });

        Route::middleware('permission:financial_statements.view')
            ->prefix('reports/financial-statements')
            ->name('reports.financial-statements.')
            ->controller(FinancialStatementController::class)
            ->group(function (): void {
                Route::get('/trial-balance', 'trialBalance')
                    ->name('trial-balance');

                Route::get('/trial-balance/print', 'printTrialBalance')
                    ->name('trial-balance.print');

                Route::get('/profit-and-loss', 'profitAndLoss')
                    ->name('profit-and-loss');

                Route::get('/profit-and-loss/print', 'printProfitAndLoss')
                    ->name('profit-and-loss.print');

                Route::get('/balance-sheet', 'balanceSheet')
                    ->name('balance-sheet');

                Route::get('/balance-sheet/print', 'printBalanceSheet')
                    ->name('balance-sheet.print');

                Route::get('/cash-flow', 'cashFlow')
                    ->name('cash-flow');

                Route::get('/cash-flow/print', 'printCashFlow')
                    ->name('cash-flow.print');
            });
    });