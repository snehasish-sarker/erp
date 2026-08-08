<?php

declare(strict_types=1);

use App\Http\Controllers\Operations\ReleaseCandidateController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'tenant.context', 'tenant.active'])
    ->prefix('erp/operations/release-candidates')
    ->name('release-candidates.')
    ->group(function (): void {
        Route::get('/', [ReleaseCandidateController::class, 'index'])
            ->middleware('permission:release_candidates.view')
            ->name('index');

        Route::post('/', [ReleaseCandidateController::class, 'store'])
            ->middleware('permission:release_candidates.create')
            ->name('store');

        Route::get('/{releaseCandidate}', [ReleaseCandidateController::class, 'show'])
            ->middleware('permission:release_candidates.view')
            ->whereNumber('releaseCandidate')
            ->name('show');

        Route::post('/{releaseCandidate}/verify', [ReleaseCandidateController::class, 'verify'])
            ->middleware('permission:release_candidates.verify')
            ->whereNumber('releaseCandidate')
            ->name('verify');
    });