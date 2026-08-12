<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformDashboardController extends Controller
{
    public function __invoke(
        Request $request,
    ): Response {
        $admin = Auth::guard('platform')->user();

        abort_unless(
            $admin instanceof PlatformAdmin,
            403,
        );

        $tenantCounts = Tenant::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render(
            'Platform/Dashboard',
            [
                'platformAdmin' => [
                    'id' => (int) $admin->getKey(),
                    'name' => $admin->name,
                    'email' => $admin->email,
                    'last_login_at' => $admin->last_login_at?->toIso8601String(),
                ],

                'metrics' => [
                    'tenants_total' => Tenant::query()->count(),
                    'tenants_trial' => (int) ($tenantCounts['trial'] ?? 0),
                    'tenants_active' => (int) ($tenantCounts['active'] ?? 0),
                    'tenants_suspended' => (int) ($tenantCounts['suspended'] ?? 0),
                    'tenants_past_due' => (int) ($tenantCounts['past_due'] ?? 0),
                    'tenant_users_total' => User::query()->count(),
                ],
            ],
        );
    }
}
