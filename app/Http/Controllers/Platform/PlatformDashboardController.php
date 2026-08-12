<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Services\Platform\SaasOperationalDashboardService;
use App\Services\Platform\SaasSubscriptionHistoryService;
use App\Services\Platform\SaasUsageMonitoringService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformDashboardController extends Controller
{
    private const RECENT_ACTIVITY_LIMIT = 8;

    private const USAGE_ALERT_LIMIT = 6;

    private const SUBSCRIPTION_ALERT_LIMIT = 8;

    public function __construct(
        private readonly SaasOperationalDashboardService $dashboardService,
        private readonly SaasUsageMonitoringService $usageMonitoringService,
        private readonly SaasSubscriptionHistoryService $historyService,
    ) {
    }

    public function __invoke(): Response
    {
        $admin = Auth::guard('platform')->user();

        abort_unless(
            $admin instanceof PlatformAdmin,
            403,
        );

        $now = CarbonImmutable::now();
        $historyMetrics = $this->historyService->metrics();
        $recentActivity = $this->historyService->paginate([
            'sort' => 'created_at',
            'direction' => 'desc',
            'per_page' => self::RECENT_ACTIVITY_LIMIT,
        ]);

        return Inertia::render(
            'Platform/Dashboard',
            [
                'platformAdmin' => [
                    'id' => (int) $admin->getKey(),
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'last_login_at' => $admin->last_login_at?->toIso8601String(),
                ],
                'metrics' => $this->dashboardService->metrics($now),
                'usageMetrics' => $this->usageMonitoringService->metrics(),
                'recentChanges30Days' => $historyMetrics['last_30_days'],
                'packageDistribution' => $this->dashboardService
                    ->packageDistribution(),
                'subscriptionAlerts' => $this->dashboardService
                    ->subscriptionAlerts(
                        now: $now,
                        limit: self::SUBSCRIPTION_ALERT_LIMIT,
                    ),
                'usageAlerts' => $this->usageMonitoringService->alerts(
                    self::USAGE_ALERT_LIMIT,
                ),
                'recentActivity' => $recentActivity['data'],
                'expiringSoonDays' => SaasOperationalDashboardService::EXPIRING_SOON_DAYS,
            ],
        );
    }
}
