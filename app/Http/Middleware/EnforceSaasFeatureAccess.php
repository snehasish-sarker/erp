<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Saas\SaasEntitlementService;
use App\Services\Saas\SaasSubscriptionLifecycleService;
use App\Support\Saas\SaasFeatureRouteRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceSaasFeatureAccess
{
    public function __construct(
        private readonly SaasEntitlementService $entitlementService,
        private readonly SaasSubscriptionLifecycleService $lifecycleService,
        private readonly SaasFeatureRouteRegistry $routeRegistry,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $routeName = $request->route()?->getName();

        $requiredFeatures = $this->routeRegistry
            ->requiredFeatures($routeName);

        if ($requiredFeatures === []) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user instanceof User) {
            return $next($request);
        }

        $tenant = $user->tenant()->first();

        abort_unless(
            $tenant instanceof Tenant,
            Response::HTTP_FORBIDDEN,
            'The authenticated user is not assigned to a tenant.',
        );

        $this->lifecycleService->synchronizeTenant($tenant);
        $this->entitlementService->forget($tenant);

        foreach ($requiredFeatures as $featureKey) {
            abort_unless(
                $this->entitlementService->featureEnabled(
                    tenant: $tenant,
                    featureKey: $featureKey,
                ),
                Response::HTTP_FORBIDDEN,
                'Your current SaaS plan does not include this module.',
            );
        }

        return $next($request);
    }
}