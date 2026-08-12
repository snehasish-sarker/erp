<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ExtendTenantTrialRequest;
use App\Models\Tenant;
use App\Support\Responses\CommonResponseService;
use App\Services\Saas\SaasSubscriptionLifecycleService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class PlatformTenantSubscriptionLifecycleController extends Controller
{
    public function __construct(
        private readonly SaasSubscriptionLifecycleService $lifecycleService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function extendTrial(
        ExtendTenantTrialRequest $request,
        Tenant $tenant,
    ): JsonResponse|RedirectResponse {
        try {
            $subscription = $this->lifecycleService->extendTrial(
                tenant: $tenant,
                days: (int) $request->validated('days'),
            );
        } catch (DomainException $exception) {
            return $this->responseService->error(
                message: $exception->getMessage(),
                code: 'SAAS_TRIAL_EXTENSION_INVALID',
                redirectTo: route(
                    'platform.tenants.show',
                    $tenant,
                ),
            );
        }

        return $this->responseService->success(
            message: 'Tenant trial extended successfully.',
            data: [
                'tenant_subscription_id' => (int) $subscription->getKey(),
                'status' => $subscription->status,
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            ],
            redirectTo: route(
                'platform.tenants.show',
                $tenant,
            ),
        );
    }
}