<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreSaasPlanRequest;
use App\Http\Requests\Platform\UpdateSaasPlanRequest;
use App\Models\PlatformAdmin;
use App\Models\SaasFeature;
use App\Models\SaasPlan;
use App\Models\SaasPlanFeature;
use App\Services\Platform\SaasPlanService;
use App\Support\Responses\CommonResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformSaasPlanController extends Controller
{
    public function __construct(
        private readonly SaasPlanService $planService,
        private readonly CommonResponseService $responseService,
    ) {
    }

    public function index(): Response
    {
        $this->platformAdmin();

        $plans = SaasPlan::query()
            ->withCount([
                'subscriptions',
                'entitlements as enabled_features_count' => static fn ($query) =>
                    $query->where('enabled', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (SaasPlan $plan): array => [
                    'id' => (int) $plan->getKey(),
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'billing_currency_code' => $plan->billing_currency_code,
                    'currency_scale' => (int) $plan->currency_scale,
                    'monthly_price_minor' => $plan->monthly_price_minor,
                    'annual_price_minor' => $plan->annual_price_minor,
                    'status' => $plan->status,
                    'is_default' => (bool) $plan->is_default,
                    'sort_order' => (int) $plan->sort_order,
                    'subscriptions_count' => (int) $plan->getAttribute('subscriptions_count'),
                    'enabled_features_count' => (int) $plan->getAttribute('enabled_features_count'),
                ],
            )
            ->values()
            ->all();

        return Inertia::render(
            'Platform/Plans/Index',
            ['plans' => $plans],
        );
    }

    public function create(): Response
    {
        $this->platformAdmin();

        return Inertia::render(
            'Platform/Plans/Create',
            ['features' => $this->featureOptions()],
        );
    }

    public function store(
        StoreSaasPlanRequest $request,
    ): JsonResponse|RedirectResponse {
        $this->platformAdmin();

        $plan = $this->planService->create(
            $request->validated(),
        );

        return $this->responseService->success(
            message: 'SaaS plan created successfully.',
            data: ['id' => (int) $plan->getKey()],
            redirectTo: route('platform.plans.edit', $plan),
            status: 201,
        );
    }

    public function edit(SaasPlan $saasPlan): Response
    {
        $this->platformAdmin();
        $saasPlan->load('entitlements.feature');

        return Inertia::render(
            'Platform/Plans/Edit',
            [
                'features' => $this->featureOptions(),
                'plan' => $this->planDetails($saasPlan),
            ],
        );
    }

    public function update(
        UpdateSaasPlanRequest $request,
        SaasPlan $saasPlan,
    ): JsonResponse|RedirectResponse {
        $this->platformAdmin();

        $plan = $this->planService->update(
            plan: $saasPlan,
            attributes: $request->validated(),
        );

        return $this->responseService->success(
            message: 'SaaS plan updated successfully.',
            data: ['id' => (int) $plan->getKey()],
            redirectTo: route('platform.plans.edit', $plan),
        );
    }

    private function platformAdmin(): PlatformAdmin
    {
        $admin = Auth::guard('platform')->user();

        abort_unless($admin instanceof PlatformAdmin, 403);

        return $admin;
    }

    /** @return list<array<string, mixed>> */
    private function featureOptions(): array
    {
        return SaasFeature::query()
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                static fn (SaasFeature $feature): array => [
                    'key' => $feature->key,
                    'name' => $feature->name,
                    'description' => $feature->description,
                    'value_type' => $feature->value_type,
                    'unit' => $feature->unit,
                ],
            )
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function planDetails(SaasPlan $plan): array
    {
        /** @var array<string, SaasPlanFeature> $entitlements */
        $entitlements = $plan->entitlements
            ->keyBy(
                static fn (SaasPlanFeature $entitlement): string =>
                    $entitlement->feature?->key ?? '',
            )
            ->all();

        return [
            'id' => (int) $plan->getKey(),
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'billing_currency_code' => $plan->billing_currency_code,
            'currency_scale' => (int) $plan->currency_scale,
            'monthly_price' => $this->fromMinorUnits(
                $plan->monthly_price_minor,
                (int) $plan->currency_scale,
            ),
            'annual_price' => $this->fromMinorUnits(
                $plan->annual_price_minor,
                (int) $plan->currency_scale,
            ),
            'monthly_price_minor' => $plan->monthly_price_minor,
            'annual_price_minor' => $plan->annual_price_minor,
            'status' => $plan->status,
            'is_default' => (bool) $plan->is_default,
            'sort_order' => (int) $plan->sort_order,
            'entitlements' => collect($this->featureOptions())
                ->map(
                    static function (array $feature) use ($entitlements): array {
                        $entitlement = $entitlements[$feature['key']] ?? null;

                        return [
                            'feature_key' => $feature['key'],
                            'enabled' => $entitlement?->enabled ?? false,
                            'limit_value' => $entitlement?->limit_value,
                        ];
                    },
                )
                ->values()
                ->all(),
        ];
    }

    private function fromMinorUnits(?int $value, int $scale): string
    {
        if ($value === null) {
            return '';
        }

        $factor = 10 ** $scale;

        return number_format(
            $value / $factor,
            $scale,
            '.',
            '',
        );
    }
}
