<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ApplyTenantSubscriptionQuickActionRequest;
use App\Models\PlatformAdmin;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\Platform\SaasSubscriptionService;
use App\Services\Saas\SaasSubscriptionLifecycleService;
use App\Support\Responses\CommonResponseService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class PlatformTenantSubscriptionQuickActionController extends Controller
{
    public function __construct(
        private readonly SaasSubscriptionService $subscriptionService,
        private readonly SaasSubscriptionLifecycleService $lifecycleService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function __invoke(
        ApplyTenantSubscriptionQuickActionRequest $request,
        Tenant $tenant,
    ): JsonResponse|RedirectResponse {
        $action = (string) $request->validated('action');

        if ($tenant->status === 'archived') {
            return $this->responseService->error(
                message: 'An archived tenant cannot be renewed or extended. Restore the tenant lifecycle first.',
                code: 'SAAS_SUBSCRIPTION_QUICK_ACTION_ARCHIVED',
            );
        }

        try {
            $subscription = $this->isTrialExtension($action)
                ? $this->lifecycleService->extendTrial(
                    tenant: $tenant,
                    days: $this->trialExtensionDays($action),
                )
                : $this->subscriptionService->applyQuickAction(
                    tenant: $tenant,
                    action: $action,
                    assignedBy: $this->platformAdmin(),
                );
        } catch (DomainException $exception) {
            return $this->responseService->error(
                message: $exception->getMessage(),
                code: 'SAAS_SUBSCRIPTION_QUICK_ACTION_INVALID',
            );
        }

        return $this->responseService->success(
            message: $this->successMessage($action),
            data: $this->responseData($subscription),
        );
    }

    private function platformAdmin(): PlatformAdmin
    {
        $admin = Auth::guard('platform')->user();

        abort_unless($admin instanceof PlatformAdmin, 403);

        return $admin;
    }

    private function isTrialExtension(string $action): bool
    {
        return in_array(
            $action,
            [
                'extend_trial_7',
                'extend_trial_14',
                'extend_trial_30',
            ],
            true,
        );
    }

    private function trialExtensionDays(string $action): int
    {
        return match ($action) {
            'extend_trial_7' => 7,
            'extend_trial_14' => 14,
            'extend_trial_30' => 30,
            default => throw new DomainException(
                'The selected trial-extension action is not supported.',
            ),
        };
    }

    private function successMessage(string $action): string
    {
        return match ($action) {
            'extend_trial_7' => 'Tenant trial extended by 7 days.',
            'extend_trial_14' => 'Tenant trial extended by 14 days.',
            'extend_trial_30' => 'Tenant trial extended by 30 days.',
            'extend_month' => 'Active subscription extended by one month.',
            'extend_year' => 'Active subscription extended by one year.',
            'renew_monthly' => 'Tenant subscription renewed for one month.',
            'renew_annual' => 'Tenant subscription renewed for one year.',
            'activate_indefinite' => 'Tenant subscription activated indefinitely.',
            default => 'Tenant subscription updated successfully.',
        };
    }

    /** @return array<string, mixed> */
    private function responseData(
        TenantSubscription $subscription,
    ): array {
        return [
            'tenant_subscription_id' => (int) $subscription->getKey(),
            'status' => $subscription->status,
            'billing_cycle' => $subscription->billing_cycle,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'current_period_starts_at' => $subscription->current_period_starts_at?->toIso8601String(),
            'current_period_ends_at' => $subscription->current_period_ends_at?->toIso8601String(),
        ];
    }
}
