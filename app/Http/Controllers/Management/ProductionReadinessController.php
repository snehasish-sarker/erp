<?php

declare(strict_types=1);

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Services\Management\ProductionReadinessService;
use Inertia\Inertia;
use Inertia\Response;

final class ProductionReadinessController extends Controller
{
    public function __construct(private readonly ProductionReadinessService $service)
    {
    }

    public function index(): Response
    {
        return Inertia::render('Management/ProductionReadiness', [
            'report' => $this->service->audit(),
        ]);
    }
}