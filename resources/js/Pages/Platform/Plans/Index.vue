<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';
import type {
    PlatformSaasPlanIndexProps,
} from '@/Types/platform-admin';

defineOptions({
    layout: PlatformAdminLayout,
});

defineProps<PlatformSaasPlanIndexProps>();

const money = (
    minor: number | null,
    currencyCode: string,
    scale: number,
): string => {
    if (minor === null) {
        return 'Not configured';
    }

    const amount = minor / (10 ** scale);

    try {
        return new Intl.NumberFormat('en', {
            style: 'currency',
            currency: currencyCode,
        }).format(amount);
    } catch {
        return `${currencyCode} ${amount.toFixed(scale)}`;
    }
};
</script>

<template>
    <Head title="SaaS Plans" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    SaaS Plans
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Manage feature entitlements, usage limits, and monthly/annual billing prices.
                </p>
            </div>

            <Link
                :href="route('platform.plans.create')"
                class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
            >
                Create plan
            </Link>
        </div>

        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="plan in plans"
                :key="plan.id"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                                {{ plan.name }}
                            </h2>
                            <span
                                v-if="plan.is_default"
                                class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/10 dark:text-brand-300"
                            >
                                Default
                            </span>
                        </div>
                        <p class="mt-1 font-mono text-xs text-gray-400">
                            {{ plan.code }}
                        </p>
                    </div>

                    <span
                        class="rounded-full px-2 py-1 text-xs font-medium"
                        :class="plan.status === 'active'
                            ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300'
                            : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'"
                    >
                        {{ plan.status === 'active' ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <p class="mt-4 min-h-12 text-sm leading-6 text-gray-500 dark:text-gray-400">
                    {{ plan.description ?? 'No description.' }}
                </p>

                <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-900/60">
                        <dt class="text-xs text-gray-500">Monthly</dt>
                        <dd class="mt-1 font-semibold text-gray-900 dark:text-white">
                            {{ money(plan.monthly_price_minor, plan.billing_currency_code, plan.currency_scale) }}
                        </dd>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-900/60">
                        <dt class="text-xs text-gray-500">Annual</dt>
                        <dd class="mt-1 font-semibold text-gray-900 dark:text-white">
                            {{ money(plan.annual_price_minor, plan.billing_currency_code, plan.currency_scale) }}
                        </dd>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-900/60">
                        <dt class="text-xs text-gray-500">Tenants</dt>
                        <dd class="mt-1 font-semibold text-gray-900 dark:text-white">
                            {{ plan.subscriptions_count }}
                        </dd>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-900/60">
                        <dt class="text-xs text-gray-500">Enabled features</dt>
                        <dd class="mt-1 font-semibold text-gray-900 dark:text-white">
                            {{ plan.enabled_features_count }}
                        </dd>
                    </div>
                </dl>

                <Link
                    :href="route('platform.plans.edit', plan.id)"
                    class="mt-5 inline-flex text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                >
                    Edit entitlements →
                </Link>
            </article>
        </div>
    </div>
</template>
