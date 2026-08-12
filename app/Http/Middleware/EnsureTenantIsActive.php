<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\TenantSubscription;
use App\Services\Saas\SaasSubscriptionLifecycleService;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureTenantIsActive
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly SaasSubscriptionLifecycleService $lifecycleService,
    ) {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(
        Request $request,
        Closure $next,
    ): Response {
        $tenant = $this->tenantContext->tenant();

        $subscription = $this->lifecycleService
            ->synchronizeTenant($tenant);

        abort_unless(
            $subscription instanceof TenantSubscription,
            Response::HTTP_FORBIDDEN,
            'This company does not have an active SaaS subscription.',
        );

        $tenant = $this->tenantContext->tenant();

        abort_unless(
            in_array(
                $tenant->status,
                [
                    'trial',
                    'active',
                    'past_due',
                ],
                true,
            )
            && $subscription->allowsTenantAccessAt(),
            Response::HTTP_FORBIDDEN,
            'This company subscription is not currently active.',
        );

        return $next($request);
    }
}