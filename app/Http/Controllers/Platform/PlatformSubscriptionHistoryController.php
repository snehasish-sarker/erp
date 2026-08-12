<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\IndexPlatformSubscriptionHistoryRequest;
use App\Models\PlatformAdmin;
use App\Services\Platform\SaasSubscriptionHistoryService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformSubscriptionHistoryController extends Controller
{
    public function __construct(
        private readonly SaasSubscriptionHistoryService $historyService,
    ) {
    }

    public function __invoke(
        IndexPlatformSubscriptionHistoryRequest $request,
    ): Response {
        $this->platformAdmin();

        $validated = $request->validated();

        $filters = [
            'search' => $validated['search'] ?? null,
            'tenant_id' => isset($validated['tenant_id'])
                ? (int) $validated['tenant_id']
                : null,
            'event' => $validated['event'] ?? null,
            'actor_type' => $validated['actor_type'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'sort' => (string) $validated['sort'],
            'direction' => (string) $validated['direction'],
            'per_page' => (int) $validated['per_page'],
        ];

        return Inertia::render(
            'Platform/Subscriptions/History',
            [
                'historyPage' => $this->historyService->paginate($filters),
                'filters' => [
                    'search' => $filters['search'] ?? '',
                    'tenant_id' => $filters['tenant_id'],
                    'event' => $filters['event'] ?? '',
                    'actor_type' => $filters['actor_type'] ?? '',
                    'date_from' => $filters['date_from'] ?? '',
                    'date_to' => $filters['date_to'] ?? '',
                    'sort' => $filters['sort'],
                    'direction' => $filters['direction'],
                    'per_page' => $filters['per_page'],
                ],
                'eventOptions' => $this->historyService->eventOptions(),
                'metrics' => $this->historyService->metrics(),
                'selectedTenant' => $this->historyService->selectedTenant(
                    $filters['tenant_id'],
                ),
            ],
        );
    }

    private function platformAdmin(): PlatformAdmin
    {
        $admin = Auth::guard('platform')->user();

        abort_unless($admin instanceof PlatformAdmin, 403);

        return $admin;
    }
}
