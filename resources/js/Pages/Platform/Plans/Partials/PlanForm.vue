<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import type {
    PlatformSaasFeatureOption,
    PlatformSaasPlanDetails,
    PlatformSaasPlanEntitlementFormData,
    PlatformSaasPlanFormData,
} from '@/Types/platform-admin';

const props = defineProps<{
    mode: 'create' | 'edit';
    features: PlatformSaasFeatureOption[];
    plan?: PlatformSaasPlanDetails;
}>();

const existingEntitlements = new Map(
    (props.plan?.entitlements ?? []).map(
        (entitlement): [string, typeof entitlement] => [
            entitlement.feature_key,
            entitlement,
        ],
    ),
);

const initialEntitlements = (): PlatformSaasPlanEntitlementFormData[] =>
    props.features.map(
        (feature): PlatformSaasPlanEntitlementFormData => {
            const existing = existingEntitlements.get(feature.key);

            return {
                feature_key: feature.key,
                enabled: existing?.enabled ?? false,
                limit_value: existing?.limit_value === null
                    || existing?.limit_value === undefined
                    ? ''
                    : String(existing.limit_value),
            };
        },
    );

const form = useForm<PlatformSaasPlanFormData>({
    code: props.plan?.code ?? '',
    name: props.plan?.name ?? '',
    description: props.plan?.description ?? '',
    billing_currency_code: props.plan?.billing_currency_code ?? 'BDT',
    currency_scale: props.plan?.currency_scale ?? 2,
    monthly_price: props.plan?.monthly_price ?? '',
    annual_price: props.plan?.annual_price ?? '',
    status: props.plan?.status ?? 'active',
    is_default: props.plan?.is_default ?? false,
    sort_order: props.plan?.sort_order ?? 0,
    entitlements: initialEntitlements(),
});

const featureByKey = (
    featureKey: string,
): PlatformSaasFeatureOption | undefined =>
    props.features.find(
        (feature): boolean => feature.key === featureKey,
    );

const entitlementError = (
    index: number,
    field: 'feature_key' | 'enabled' | 'limit_value',
): string | undefined => {
    const errors = form.errors as Record<string, string | undefined>;

    return errors[`entitlements.${index}.${field}`];
};

const submit = (): void => {
    form.code = form.code.trim().toLowerCase();
    form.name = form.name.trim();
    form.description = form.description.trim();

    if (
        props.mode === 'edit'
        && props.plan !== undefined
    ) {
        form.put(
            route('platform.plans.update', props.plan.id),
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        route('platform.plans.store'),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <section
            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Plan details
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Configure the plan identity, billing currency, prices, and status. Prices remain provider-neutral and are stored as minor-unit snapshots on invoices.
                </p>
            </div>

            <div class="mt-6 grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Name
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        maxlength="120"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                    <p v-if="form.errors.name" class="mt-1 text-sm text-error-500">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Code
                    </label>
                    <input
                        v-model="form.code"
                        type="text"
                        maxlength="50"
                        placeholder="business"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm lowercase text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                    <p v-if="form.errors.code" class="mt-1 text-sm text-error-500">
                        {{ form.errors.code }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Status
                    </label>
                    <select
                        v-model="form.status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    >
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <p v-if="form.errors.status" class="mt-1 text-sm text-error-500">
                        {{ form.errors.status }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Sort order
                    </label>
                    <input
                        v-model.number="form.sort_order"
                        type="number"
                        min="0"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                    <p v-if="form.errors.sort_order" class="mt-1 text-sm text-error-500">
                        {{ form.errors.sort_order }}
                    </p>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Description
                    </label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        maxlength="2000"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                    <p v-if="form.errors.description" class="mt-1 text-sm text-error-500">
                        {{ form.errors.description }}
                    </p>
                </div>

                <label class="flex items-center gap-3 md:col-span-2">
                    <input
                        v-model="form.is_default"
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-300"
                    >
                    <span>
                        <span class="block text-sm font-medium text-gray-800 dark:text-gray-200">
                            Default plan for newly provisioned tenants
                        </span>
                        <span class="block text-xs text-gray-500 dark:text-gray-400">
                            Only one plan can be default. Marking this plan as default automatically clears the previous default.
                        </span>
                    </span>
                </label>
                <p v-if="form.errors.is_default" class="text-sm text-error-500 md:col-span-2">
                    {{ form.errors.is_default }}
                </p>
            </div>
        </section>

        <section
            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Billing
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Configure monthly and annual prices. Leave a price blank when that billing cycle is not offered.
                </p>
            </div>

            <div class="mt-6 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Currency
                    </label>
                    <input
                        v-model="form.billing_currency_code"
                        type="text"
                        maxlength="3"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm uppercase text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                    <p v-if="form.errors.billing_currency_code" class="mt-1 text-sm text-error-500">
                        {{ form.errors.billing_currency_code }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Decimal scale
                    </label>
                    <input
                        v-model.number="form.currency_scale"
                        type="number"
                        min="0"
                        max="4"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                    <p v-if="form.errors.currency_scale" class="mt-1 text-sm text-error-500">
                        {{ form.errors.currency_scale }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Monthly price
                    </label>
                    <input
                        v-model="form.monthly_price"
                        type="number"
                        min="0"
                        step="any"
                        placeholder="e.g. 5000.00"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                    <p v-if="form.errors.monthly_price" class="mt-1 text-sm text-error-500">
                        {{ form.errors.monthly_price }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Annual price
                    </label>
                    <input
                        v-model="form.annual_price"
                        type="number"
                        min="0"
                        step="any"
                        placeholder="e.g. 50000.00"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                    <p v-if="form.errors.annual_price" class="mt-1 text-sm text-error-500">
                        {{ form.errors.annual_price }}
                    </p>
                </div>
            </div>
        </section>

        <section
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div class="border-b border-gray-200 p-6 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Feature entitlements and limits
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Boolean features control module availability. For limit features, a blank enabled limit means unlimited.
                </p>
            </div>

            <div class="divide-y divide-gray-200 dark:divide-gray-800">
                <div
                    v-for="(entitlement, index) in form.entitlements"
                    :key="entitlement.feature_key"
                    class="grid gap-4 p-5 md:grid-cols-[minmax(0,1fr)_140px_180px] md:items-center"
                >
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">
                            {{ featureByKey(entitlement.feature_key)?.name ?? entitlement.feature_key }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ featureByKey(entitlement.feature_key)?.description ?? '' }}
                        </p>
                        <p class="mt-1 font-mono text-xs text-gray-400">
                            {{ entitlement.feature_key }}
                        </p>
                    </div>

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <input
                            v-model="entitlement.enabled"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300"
                        >
                        Enabled
                    </label>

                    <div
                        v-if="featureByKey(entitlement.feature_key)?.value_type === 'limit'"
                    >
                        <input
                            v-model="entitlement.limit_value"
                            type="number"
                            min="1"
                            :disabled="!entitlement.enabled"
                            :placeholder="`Unlimited ${featureByKey(entitlement.feature_key)?.unit ?? ''}`"
                            class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-white"
                        >
                        <p
                            v-if="entitlementError(index, 'limit_value')"
                            class="mt-1 text-xs text-error-500"
                        >
                            {{ entitlementError(index, 'limit_value') }}
                        </p>
                    </div>
                    <div v-else class="text-xs text-gray-400">
                        Module entitlement
                    </div>
                </div>
            </div>
        </section>

        <div class="flex flex-wrap justify-end gap-3">
            <Link
                :href="route('platform.plans.index')"
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
            >
                Cancel
            </Link>

            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{ form.processing
                    ? 'Saving...'
                    : mode === 'create'
                        ? 'Create plan'
                        : 'Save plan' }}
            </button>
        </div>
    </form>
</template>
