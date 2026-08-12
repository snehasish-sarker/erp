<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdateManualTenantSubscriptionRequest;
use App\Models\PlatformAdmin;
use App\Models\SaasPlan;
use App\Models\Tenant;
use App\Services\Platform\SaasSubscriptionService;
use App\Support\Responses\CommonResponseService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class PlatformTenantSubscriptionController extends Controller
{
    public function __construct(
        private readonly SaasSubscriptionService $subscriptionService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function update(
        UpdateManualTenantSubscriptionRequest $request,
        Tenant $tenant,
    ): JsonResponse|RedirectResponse {
        $admin = $this->platformAdmin();
        $validated = $request->validated();

        $plan = SaasPlan::query()->findOrFail(
            (int) $validated['saas_plan_id'],
        );

        try {
            $subscription = $this->subscriptionService
                ->updateManualAllocation(
                    tenant: $tenant,
                    plan: $plan,
                    attributes: $validated,
                    assignedBy: $admin,
                );
        } catch (DomainException $exception) {
            return $this->responseService->error(
                message: $exception->getMessage(),
                code: 'SAAS_MANUAL_ALLOCATION_INVALID',
                redirectTo: route('platform.tenants.show', $tenant),
            );
        }

        return $this->responseService->success(
            message: 'Tenant package allocation updated successfully.',
            data: [
                'subscription_id' => (int) $subscription->getKey(),
                'saas_plan_id' => (int) $subscription->saas_plan_id,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'trial_ends_at' => $subscription->trial_ends_at,
                'current_period_starts_at' => $subscription->current_period_starts_at,
                'current_period_ends_at' => $subscription->current_period_ends_at,
                'grace_ends_at' => $subscription->grace_ends_at,
            ],
            redirectTo: route('platform.tenants.show', $tenant),
        );
    }

    private function platformAdmin(): PlatformAdmin
    {
        $admin = Auth::guard('platform')->user();

        abort_unless($admin instanceof PlatformAdmin, 403);

        return $admin;
    }
}
