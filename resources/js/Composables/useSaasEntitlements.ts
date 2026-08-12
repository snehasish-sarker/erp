import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type {
    ComputedRef,
} from 'vue';

interface SaasSubscriptionSummary {
    status: string;
    trial_ends_at: string | null;
    current_period_ends_at: string | null;
    grace_ends_at: string | null;
    plan: {
        id: number;
        code: string;
        name: string;
    };
}

interface SaasEntitlementContext {
    subscription: ComputedRef<SaasSubscriptionSummary | null>;
    features: ComputedRef<readonly string[]>;
    limits: ComputedRef<Readonly<Record<string, number | null>>>;
    canUse: (feature: string) => boolean;
    canUseAll: (features: readonly string[]) => boolean;
    limit: (feature: string) => number | null | undefined;
}

export function useSaasEntitlements(): SaasEntitlementContext {
    const page = usePage();

    const subscription = computed(
        (): SaasSubscriptionSummary | null =>
            page.props.saas.subscription,
    );

    const features: ComputedRef<readonly string[]> = computed(
        (): readonly string[] => page.props.saas.features,
    );

    const limits: ComputedRef<
        Readonly<Record<string, number | null>>
    > = computed(
        (): Readonly<Record<string, number | null>> =>
            page.props.saas.limits,
    );

    const featureSet = computed(
        (): ReadonlySet<string> => new Set(features.value),
    );

    const canUse = (feature: string): boolean =>
        featureSet.value.has(feature);

    const canUseAll = (
        requiredFeatures: readonly string[],
    ): boolean => requiredFeatures.every(canUse);

    const limit = (
        feature: string,
    ): number | null | undefined => limits.value[feature];

    return {
        subscription,
        features,
        limits,
        canUse,
        canUseAll,
        limit,
    };
}