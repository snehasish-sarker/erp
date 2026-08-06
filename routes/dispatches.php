<?php

declare(strict_types=1);

use App\Http\Controllers\Sales\CustomerDispatchController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'web',
    'auth',
    'tenant.context',
    'tenant.active',
])
    ->prefix('erp/dispatches')
    ->name('dispatches.')
    ->controller(
        CustomerDispatchController::class,
    )
    ->group(function (): void {
        Route::get('/', 'index')
            ->middleware(
                'permission:dispatches.view',
            )
            ->name('index');

        Route::get('/create', 'create')
            ->middleware(
                'permission:dispatches.create',
            )
            ->name('create');

        Route::post('/', 'store')
            ->middleware(
                'permission:dispatches.create',
            )
            ->name('store');

        Route::get(
            '/{customerDispatch}',
            'show',
        )
            ->middleware(
                'permission:dispatches.view',
            )
            ->name('show');

        Route::get(
            '/{customerDispatch}/edit',
            'edit',
        )
            ->middleware(
                'permission:dispatches.create',
            )
            ->name('edit');

        Route::put(
            '/{customerDispatch}',
            'update',
        )
            ->middleware(
                'permission:dispatches.create',
            )
            ->name('update');

        Route::delete(
            '/{customerDispatch}',
            'destroy',
        )
            ->middleware(
                'permission:dispatches.create',
            )
            ->name('destroy');

        Route::post(
            '/{customerDispatch}/post',
            'post',
        )
            ->middleware(
                'permission:dispatches.post',
            )
            ->name('post');

        Route::post(
            '/{customerDispatch}/reverse',
            'reverse',
        )
            ->middleware(
                'permission:dispatches.reverse',
            )
            ->name('reverse');

        Route::get(
            '/{customerDispatch}/print',
            'print',
        )
            ->middleware(
                'permission:dispatches.view',
            )
            ->name('print');
    });