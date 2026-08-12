<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    DashboardMetric,
    DashboardTone,
    TenantDashboardData,
} from '@/Types/dashboard';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    dashboard: TenantDashboardData;
}>();

const currencyCode = computed<string>(
    () => props.dashboard.tenant.currency_code,
);

const trendMaximum = computed<number>(() => {
    const values = props.dashboard.trend.flatMap((point) => [
        point.sales === null ? 0 : Number(point.sales),
        point.purchases === null ? 0 : Number(point.purchases),
    ]);

    return Math.max(1, ...values);
});

const showSalesTrend = computed<boolean>(() =>
    props.dashboard.trend.some((point) => point.sales !== null),
);

const showPurchaseTrend = computed<boolean>(() =>
    props.dashboard.trend.some((point) => point.purchases !== null),
);

const formatMoney = (
    value: string | number,
    currency: string = currencyCode.value,
): string => new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
}).format(Number(value));

const formatNumber = (value: string | number): string =>
    new Intl.NumberFormat('en-US').format(Number(value));

const formatMetric = (metric: DashboardMetric): string =>
    metric.format === 'money'
        ? formatMoney(metric.value)
        : formatNumber(metric.value);

const formatStatus = (status: string): string =>
    status
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');

const formatDate = (value: string | null): string => {
    if (value === null || value === '') {
        return '—';
    }

    const parsed = new Date(`${value}T00:00:00`);

    return new Intl.DateTimeFormat('en-US', {
        month: 'short',
        day: '2-digit',
        year: 'numeric',
    }).format(parsed);
};

const metricAccentClass = (tone: DashboardTone): string => {
    switch (tone) {
        case 'success':
            return 'bg-success-500';
        case 'info':
            return 'bg-brand-500';
        case 'warning':
            return 'bg-warning-500';
        case 'danger':
            return 'bg-error-500';
        default:
            return 'bg-gray-400 dark:bg-gray-600';
    }
};

const badgeClass = (tone: DashboardTone): string => {
    switch (tone) {
        case 'success':
            return 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400';
        case 'info':
            return 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400';
        case 'warning':
            return 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400';
        case 'danger':
            return 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400';
        default:
            return 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300';
    }
};

const statusClass = (status: string): string => {
    if (
        ['posted', 'approved', 'closed', 'received', 'invoiced'].includes(status)
    ) {
        return badgeClass('success');
    }

    if (
        ['submitted', 'validated', 'partially_received', 'partially_dispatched', 'partially_allocated'].includes(status)
    ) {
        return badgeClass('warning');
    }

    if (['cancelled', 'reversed', 'disputed'].includes(status)) {
        return badgeClass('danger');
    }

    return badgeClass('neutral');
};

const trendHeight = (value: string | null): string => {
    if (value === null) {
        return '0%';
    }

    const percent = (Number(value) / trendMaximum.value) * 100;

    return `${Math.max(3, Math.min(100, percent))}%`;
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="space-y-6">
        <section
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="flex flex-col gap-4 px-5 py-5 sm:px-6 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                        >
                            {{ dashboard.tenant.name }}
                        </h1>

                        <span
                            class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-white/10 dark:text-gray-300"
                        >
                            {{ dashboard.tenant.code }}
                        </span>
                    </div>

                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Operational overview for {{ dashboard.period.label }}
                    </p>
                </div>

                <div
                    class="grid gap-2 text-xs text-gray-500 dark:text-gray-400 sm:grid-cols-2 lg:text-right"
                >
                    <div>
                        <div class="font-medium text-gray-700 dark:text-gray-300">
                            Branch scope
                        </div>
                        <div>{{ dashboard.branch_scope.label }}</div>
                    </div>
                    <div>
                        <div class="font-medium text-gray-700 dark:text-gray-300">
                            Timezone
                        </div>
                        <div>{{ dashboard.tenant.timezone }}</div>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="dashboard.metrics.length > 0"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <Link
                v-for="metric in dashboard.metrics"
                :key="metric.key"
                :href="metric.href"
                class="group relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm transition hover:-translate-y-0.5 hover:border-gray-300 hover:shadow-theme-md dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-gray-700"
            >
                <div
                    class="absolute inset-x-0 top-0 h-1"
                    :class="metricAccentClass(metric.tone)"
                />

                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ metric.label }}
                </p>

                <p
                    class="mt-2 text-2xl font-semibold tracking-tight text-gray-800 dark:text-white/90"
                >
                    {{ formatMetric(metric) }}
                </p>

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    {{ metric.hint }}
                </p>
            </Link>
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6 xl:col-span-2"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                            Six-month activity
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Posted document values in {{ currencyCode }}.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3 text-xs">
                        <span
                            v-if="showSalesTrend"
                            class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-300"
                        >
                            <span class="h-2.5 w-2.5 rounded-sm bg-success-500" />
                            Sales
                        </span>
                        <span
                            v-if="showPurchaseTrend"
                            class="inline-flex items-center gap-1.5 text-gray-600 dark:text-gray-300"
                        >
                            <span class="h-2.5 w-2.5 rounded-sm bg-brand-500" />
                            Purchases
                        </span>
                    </div>
                </div>

                <div
                    v-if="dashboard.trend.length > 0"
                    class="mt-6 grid h-64 grid-cols-6 gap-2 sm:gap-4"
                >
                    <div
                        v-for="point in dashboard.trend"
                        :key="point.period"
                        class="flex min-w-0 flex-col"
                    >
                        <div class="flex flex-1 items-end justify-center gap-1 sm:gap-2">
                            <div
                                v-if="point.sales !== null"
                                class="w-2/5 rounded-t-md bg-success-500/80 transition-all"
                                :style="{ height: trendHeight(point.sales) }"
                                :title="`Sales: ${formatMoney(point.sales)}`"
                            />
                            <div
                                v-if="point.purchases !== null"
                                class="w-2/5 rounded-t-md bg-brand-500/80 transition-all"
                                :style="{ height: trendHeight(point.purchases) }"
                                :title="`Purchases: ${formatMoney(point.purchases)}`"
                            />
                        </div>

                        <div
                            class="mt-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400"
                        >
                            {{ point.label }}
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="mt-6 rounded-xl border border-dashed border-gray-300 px-4 py-12 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
                >
                    Sales or purchasing trend data is not available for your current access.
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6"
            >
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Work queue
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Items that currently need your permitted actions.
                </p>

                <div v-if="dashboard.action_items.length > 0" class="mt-5 space-y-3">
                    <Link
                        v-for="item in dashboard.action_items"
                        :key="item.key"
                        :href="item.href"
                        class="flex items-center justify-between gap-3 rounded-xl border border-gray-200 px-4 py-3 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.04]"
                    >
                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ item.label }}
                        </span>
                        <span
                            class="inline-flex min-w-7 items-center justify-center rounded-full px-2 py-1 text-xs font-semibold"
                            :class="badgeClass(item.tone)"
                        >
                            {{ item.count }}
                        </span>
                    </Link>
                </div>

                <div
                    v-else
                    class="mt-5 rounded-xl bg-success-50 px-4 py-5 text-sm text-success-700 dark:bg-success-500/10 dark:text-success-400"
                >
                    Nothing is waiting for your approval or posting right now.
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-3">
            <div
                class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] xl:col-span-2"
            >
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-800 sm:px-6">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                        Recent documents
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Latest records from modules you are allowed to view.
                    </p>
                </div>

                <div v-if="dashboard.recent_documents.length > 0" class="divide-y divide-gray-100 dark:divide-gray-800">
                    <Link
                        v-for="document in dashboard.recent_documents"
                        :key="document.key"
                        :href="document.href"
                        class="grid gap-3 px-5 py-4 transition hover:bg-gray-50 dark:hover:bg-white/[0.03] sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center sm:px-6"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate text-sm font-semibold text-gray-800 dark:text-white/90">
                                    {{ document.number }}
                                </span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="statusClass(document.status)"
                                >
                                    {{ formatStatus(document.status) }}
                                </span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ document.type }} · {{ formatDate(document.date) }}
                            </div>
                        </div>

                        <div class="text-sm font-medium text-gray-700 dark:text-gray-300 sm:text-right">
                            {{ formatMoney(document.amount, document.currency_code) }}
                        </div>

                        <span class="text-xs font-medium text-brand-600 dark:text-brand-400">
                            Open
                        </span>
                    </Link>
                </div>

                <div
                    v-else
                    class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6"
                >
                    No recent documents are available for your current access.
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6"
            >
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Quick actions
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Shortcuts are filtered by permission and package entitlement.
                </p>

                <div v-if="dashboard.quick_links.length > 0" class="mt-5 space-y-2">
                    <Link
                        v-for="item in dashboard.quick_links"
                        :key="item.key"
                        :href="item.href"
                        class="block rounded-xl border border-gray-200 px-4 py-3 transition hover:border-brand-300 hover:bg-brand-50/40 dark:border-gray-800 dark:hover:border-brand-800 dark:hover:bg-brand-500/5"
                    >
                        <div class="text-sm font-semibold text-gray-800 dark:text-white/90">
                            {{ item.label }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ item.description }}
                        </div>
                    </Link>
                </div>

                <div
                    v-else
                    class="mt-5 rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400"
                >
                    No create shortcuts are available for your current role.
                </div>
            </div>
        </section>
    </div>
</template>
