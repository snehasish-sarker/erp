<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\SaasFeature;
use App\Models\SaasPlan;
use App\Models\SaasPlanFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SaasPlanService
{
    /**
     * @param array{
     *     code: string,
     *     name: string,
     *     description: string|null,
     *     billing_currency_code: string,
     *     currency_scale: int,
     *     monthly_price: string|int|float|null,
     *     annual_price: string|int|float|null,
     *     status: string,
     *     is_default: bool,
     *     sort_order: int,
     *     entitlements: list<array{
     *         feature_key: string,
     *         enabled: bool,
     *         limit_value: int|null
     *     }>
     * } $attributes
     */
    public function create(array $attributes): SaasPlan
    {
        return DB::transaction(
            fn (): SaasPlan => $this->persist(
                plan: new SaasPlan(),
                attributes: $attributes,
            ),
            attempts: 5,
        );
    }

    /**
     * @param array{
     *     code: string,
     *     name: string,
     *     description: string|null,
     *     billing_currency_code: string,
     *     currency_scale: int,
     *     monthly_price: string|int|float|null,
     *     annual_price: string|int|float|null,
     *     status: string,
     *     is_default: bool,
     *     sort_order: int,
     *     entitlements: list<array{
     *         feature_key: string,
     *         enabled: bool,
     *         limit_value: int|null
     *     }>
     * } $attributes
     */
    public function update(
        SaasPlan $plan,
        array $attributes,
    ): SaasPlan {
        return DB::transaction(
            fn (): SaasPlan => $this->persist(
                plan: $plan,
                attributes: $attributes,
            ),
            attempts: 5,
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function persist(
        SaasPlan $plan,
        array $attributes,
    ): SaasPlan {
        if ((bool) $attributes['is_default']) {
            $query = SaasPlan::query();

            if ($plan->exists) {
                $query->where('id', '!=', $plan->getKey());
            }

            $query->update(['is_default' => false]);
        }

        $currencyScale = (int) $attributes['currency_scale'];

        $plan->fill([
            'code' => Str::lower((string) $attributes['code']),
            'name' => $attributes['name'],
            'description' => $attributes['description'],
            'billing_currency_code' => Str::upper(
                (string) $attributes['billing_currency_code'],
            ),
            'currency_scale' => $currencyScale,
            'monthly_price_minor' => $this->toMinorUnits(
                $attributes['monthly_price'],
                $currencyScale,
            ),
            'annual_price_minor' => $this->toMinorUnits(
                $attributes['annual_price'],
                $currencyScale,
            ),
            'status' => $attributes['status'],
            'is_default' => $attributes['is_default'],
            'sort_order' => $attributes['sort_order'],
        ]);

        $plan->save();

        $features = SaasFeature::query()
            ->whereIn(
                'key',
                collect($attributes['entitlements'])
                    ->pluck('feature_key')
                    ->all(),
            )
            ->get()
            ->keyBy('key');

        foreach ($attributes['entitlements'] as $entitlement) {
            $feature = $features->get($entitlement['feature_key']);

            if (!$feature instanceof SaasFeature) {
                continue;
            }

            SaasPlanFeature::query()->updateOrCreate(
                [
                    'saas_plan_id' => (int) $plan->getKey(),
                    'saas_feature_id' => (int) $feature->getKey(),
                ],
                [
                    'enabled' => (bool) $entitlement['enabled'],
                    'limit_value' => $feature->value_type === 'limit'
                        && $entitlement['enabled']
                        && $entitlement['limit_value'] !== null
                            ? (int) $entitlement['limit_value']
                            : null,
                ],
            );
        }

        return $plan->refresh()->load('entitlements.feature');
    }

    private function toMinorUnits(
        string|int|float|null $value,
        int $scale,
    ): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        $factor = 10 ** $scale;

        return (int) round((float) $value * $factor);
    }
}