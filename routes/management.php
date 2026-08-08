<?php

declare(strict_types=1);

use App\Http\Controllers\Management\ManagementBudgetController;
use App\Http\Controllers\Management\ManagementDashboardController;
use App\Http\Controllers\Management\ManagementReportController;
use App\Http\Controllers\Management\ManagementReportScheduleController;
use App\Http\Controllers\Management\ProductionReadinessController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.context', 'tenant.active'])
    ->prefix('erp/management')
    ->name('management.')
    ->group(function (): void {
        Route::get('/', [ManagementDashboardController::class, 'index'])
            ->middleware('permission:management_dashboard.view')
            ->name('index');

        Route::prefix('budgets')->name('budgets.')->group(function (): void {
            Route::get('/', [ManagementBudgetController::class, 'index'])->middleware('permission:management_budgets.view')->name('index');
            Route::get('/create', [ManagementBudgetController::class, 'create'])->middleware('permission:management_budgets.create')->name('create');
            Route::post('/', [ManagementBudgetController::class, 'store'])->middleware('permission:management_budgets.create')->name('store');
            Route::get('/{managementBudget}/edit', [ManagementBudgetController::class, 'edit'])->middleware('permission:management_budgets.update')->whereNumber('managementBudget')->name('edit');
            Route::get('/{managementBudget}', [ManagementBudgetController::class, 'show'])->middleware('permission:management_budgets.view')->whereNumber('managementBudget')->name('show');
            Route::put('/{managementBudget}', [ManagementBudgetController::class, 'update'])->middleware('permission:management_budgets.update')->whereNumber('managementBudget')->name('update');
            Route::delete('/{managementBudget}', [ManagementBudgetController::class, 'destroy'])->middleware('permission:management_budgets.delete')->whereNumber('managementBudget')->name('destroy');
            Route::post('/{managementBudget}/approve', [ManagementBudgetController::class, 'approve'])->middleware('permission:management_budgets.approve')->whereNumber('managementBudget')->name('approve');
            Route::post('/{managementBudget}/reopen', [ManagementBudgetController::class, 'reopen'])->middleware('permission:management_budgets.reopen')->whereNumber('managementBudget')->name('reopen');
        });

        Route::middleware('permission:management_reports.view')->prefix('reports')->name('reports.')->group(function (): void {
            Route::get('/branch-profitability', [ManagementReportController::class, 'branchProfitability'])->name('branch-profitability');
            Route::get('/budget-vs-actual', [ManagementReportController::class, 'budgetVsActual'])->name('budget-vs-actual');
            Route::get('/product-profitability', [ManagementReportController::class, 'productProfitability'])->name('product-profitability');
            Route::get('/customer-profitability', [ManagementReportController::class, 'customerProfitability'])->name('customer-profitability');
            Route::get('/supplier-spend', [ManagementReportController::class, 'supplierSpend'])->name('supplier-spend');
            Route::get('/gross-margin', [ManagementReportController::class, 'grossMargin'])->name('gross-margin');
        });

        Route::middleware('permission:management_report_schedules.view')->prefix('schedules')->name('schedules.')->group(function (): void {
            Route::get('/', [ManagementReportScheduleController::class, 'index'])->name('index');
            Route::post('/', [ManagementReportScheduleController::class, 'store'])->middleware('permission:management_report_schedules.create')->name('store');
            Route::post('/{managementReportSchedule}/toggle', [ManagementReportScheduleController::class, 'toggle'])->middleware('permission:management_report_schedules.update')->whereNumber('managementReportSchedule')->name('toggle');
            Route::delete('/{managementReportSchedule}', [ManagementReportScheduleController::class, 'destroy'])->middleware('permission:management_report_schedules.delete')->whereNumber('managementReportSchedule')->name('destroy');
        });

        Route::get('/production-readiness', [ProductionReadinessController::class, 'index'])
            ->middleware('permission:management_readiness.view')
            ->name('production-readiness');
    });