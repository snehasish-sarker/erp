<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Services\Operations\OperationsDashboardService;
use Inertia\Inertia;
use Inertia\Response;

final class OperationsDashboardController extends Controller
{
    public function __construct(
        private readonly OperationsDashboardService $dashboardService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Operations/Index', [
            'dashboard' => $this->dashboardService->build(),
        ]);
    }
}
