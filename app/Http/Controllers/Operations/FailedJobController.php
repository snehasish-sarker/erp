<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Services\Operations\FailedJobOperationsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class FailedJobController extends Controller
{
    public function __construct(
        private readonly FailedJobOperationsService $failedJobService,
    ) {
    }

    public function index(Request $request): Response
    {
        $paginator = $this->failedJobService->paginate((int) $request->integer('per_page', 25));

        return Inertia::render('Operations/FailedJobs/Index', [
            'failedJobs' => [
                'data' => $paginator->items(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }
}
