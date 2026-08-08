<?php

declare(strict_types=1);

use App\Http\Controllers\Operations\DeploymentPreflightController;
use App\Http\Controllers\Operations\FailedJobController;
use App\Http\Controllers\Operations\OperationsDashboardController;
use App\Http\Controllers\Operations\SystemBackupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.context', 'tenant.active'])
    ->prefix('erp/operations')
    ->name('operations.')
    ->group(function (): void {
        Route::get('/', [OperationsDashboardController::class, 'index'])
            ->middleware('permission:operations.view')
            ->name('index');

        Route::get('/backups', [SystemBackupController::class, 'index'])
            ->middleware('permission:operations.backups.view')
            ->name('backups.index');
        Route::post('/backups/{systemBackup}/verify', [SystemBackupController::class, 'verify'])
            ->middleware('permission:operations.backups.verify')
            ->whereNumber('systemBackup')
            ->name('backups.verify');

        Route::get('/failed-jobs', [FailedJobController::class, 'index'])
            ->middleware('permission:operations.failed_jobs.view')
            ->name('failed-jobs.index');

        Route::get('/preflight', [DeploymentPreflightController::class, 'index'])
            ->middleware('permission:operations.preflight.view')
            ->name('preflight');
    });