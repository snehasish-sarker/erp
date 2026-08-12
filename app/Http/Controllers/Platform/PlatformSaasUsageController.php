<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\IndexPlatformSaasUsageRequest;
use App\Models\PlatformAdmin;
use App\Models\SaasPlan;
use App\Services\Platform\SaasUsageMonitoringService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformSaasUsageController extends Controller
{
    private const NEAR_LIMIT_PERCENT = 80;

    public function __construct(
        private readonly SaasUsageMonitoringService $usageMonitoringService,
    ) {
    }

    public function __invoke(
        IndexPlatformSaasUsageRequest $request,
    ): Response {
        $this->platformAdmin();

        $validated = $request->validated();

        $filters = [
            'search' => $validated['search'] ?? null,
            'saas_plan_id' => isset($validated['saas_plan_id'])
                ? (int) $validated['saas_plan_id']
                : null,
            'tenant_status' => $validated['tenant_status'] ?? null,
            'subscription_status' => $validated['subscription_status'] ?? null,
            'resource' => (string) $validated['resource'],
            'capacity' => $validated['capacity'] ?? null,
            'sort' => (string) $validated['sort'],
            'direction' => (string) $validated['direction'],
            'per_page' => (int) $validated['per_page'],
        ];

        return Inertia::render(
            'Platform/Usage/Index',
            [
                'usagePage' => $this->usageMonitoringService->paginate(
                    $filters,
                ),
                'filters' => [
                    'search' => $filters['search'] ?? '',
                    'saas_plan_id' => $filters['saas_plan_id'],
                    'tenant_status' => $filters['tenant_status'] ?? '',
                    'subscription_status' => $filters['subscription_status'] ?? '',
                    'resource' => $filters['resource'],
                    'capacity' => $filters['capacity'] ?? '',
                    'sort' => $filters['sort'],
                    'direction' => $filters['direction'],
                    'per_page' => $filters['per_page'],
                ],
                'planOptions' => $this->planOptions(),
                'metrics' => $this->usageMonitoringService->metrics(),
                'nearLimitPercent' => self::NEAR_LIMIT_PERCENT,
            ],
        );
    }

    private function platformAdmin(): PlatformAdmin
    {
        $admin = Auth::guard('platform')->user();

        abort_unless($admin instanceof PlatformAdmin, 403);

        return $admin;
    }

    /**
     * @return list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     status: string
     * }>
     */
    private function planOptions(): array
    {
        return SaasPlan::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'status',
            ])
            ->map(
                static fn (SaasPlan $plan): array => [
                    'id' => (int) $plan->getKey(),
                    'code' => (string) $plan->code,
                    'name' => (string) $plan->name,
                    'status' => (string) $plan->status,
                ],
            )
            ->values()
            ->all();
    }
}
