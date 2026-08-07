<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class SalesRouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::group(
            [],
            base_path('routes/sales-allocation.php'),
        );

        Route::group(
            [],
            base_path('routes/dispatches.php'),
        );

        Route::group(
            [],
            base_path('routes/sales-invoices.php'),
        );

        Route::group(
            [],
            base_path('routes/customer-receipts.php'),
        );

        Route::group(
            [],
            base_path('routes/accounts-receivable-reports.php'),
        );

        Route::group(
            [],
            base_path('routes/sales-returns.php'),
        );

        Route::group(
            [],
            base_path('routes/customer-settlements.php'),
        );

        Route::group(
            [],
            base_path('routes/treasury.php'),
        );
    }
}