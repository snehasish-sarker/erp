<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Dashboard\TenantDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly TenantDashboardService $dashboardService,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $actor = $request->user();

        if (!$actor instanceof User) {
            throw new LogicException(
                'An authenticated tenant user is required.',
            );
        }

        return Inertia::render(
            'Dashboard/Index',
            [
                'dashboard' => $this->dashboardService->build($actor),
            ],
        );
    }
}
