<?php

declare(strict_types=1);

use App\Http\Controllers\Operations\ProductionAcceptanceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.context', 'tenant.active'])
    ->prefix('erp/operations/production-acceptance')
    ->name('production-acceptance.')
    ->group(function (): void {
        Route::get('/', [ProductionAcceptanceController::class, 'index'])
            ->middleware('permission:production_acceptance.view')
            ->name('index');

        Route::post('/', [ProductionAcceptanceController::class, 'store'])
            ->middleware('permission:production_acceptance.run')
            ->name('store');

        Route::get('/{productionAcceptanceRun}', [ProductionAcceptanceController::class, 'show'])
            ->middleware('permission:production_acceptance.view')
            ->whereNumber('productionAcceptanceRun')
            ->name('show');
    });