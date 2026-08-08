<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Services\Operations\DeploymentPreflightService;
use Inertia\Inertia;
use Inertia\Response;

final class DeploymentPreflightController extends Controller
{
    public function __construct(
        private readonly DeploymentPreflightService $preflightService,
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Operations/Preflight', [
            'report' => $this->preflightService->run(),
        ]);
    }
}
