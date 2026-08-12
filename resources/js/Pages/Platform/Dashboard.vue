<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';
import {
    computed,
} from 'vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';
import type {
    PlatformDashboardProps,
    PlatformDashboardSubscriptionAlertStatus,
    PlatformSaasUsageCapacityStatus,
    PlatformSaasUsageResource,
    PlatformSaasUsageRow,
} from '@/Types/platform-admin';

defineOptions({
    layout: PlatformAdminLayout,
});

const props = defineProps<PlatformDashboardProps>();

const metricCards = computed(() => [
    {
        label: 'Total tenants',
        value: props.metrics.tenants_total,
        hint: `${props.metrics.tenant_users_total} tenant users`,
        href: route('platform.tenants.index'),
    },
    {
        label: 'Active subscriptions',
        value: props.metrics.subscriptions_active,
        hint: `${props.metrics.subscriptions_indefinite_active} indefinite`,
        href: route('platform.subscriptions.index', {
            status: 'active',
        }),
    },
    {
        label: 'Trial',
        value: props.metrics.subscriptions_trial,
        hint: `${props.metrics.subscriptions_expiring_soon} expiring soon`,
        href: route('platform.subscriptions.index', {
            status: 'trial',
        }),
    },
    {
        label: 'Past due',
        value: props.metrics.subscriptions_past_due,
        hint: 'Grace-period attention',
        href: route('platform.subscriptions.index', {
            status: 'past_due',
        }),
    },
    {
        label: 'Suspended',
        value: props.metrics.subscriptions_suspended,
        hint: `${props.metrics.subscriptions_expired} lifecycle-expired`,
        href: route('platform.subscriptions.index', {
            status: 'suspended',
        }),
    },
    {
        label: 'Quota alerts',
        value: props.usageMetrics.over_limit
            + props.usageMetrics.at_limit
            + props.usageMetrics.near_limit,
        hint: `${props.usageMetrics.over_limit} over limit`,
        href: route('platform.usage.index'),
    },
]);

const formatDate = (value: string | null): string => {
    if (value === null) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const titleCase = (value: string): string => value
    .replaceAll('_', ' ')
    .replace(/\b\w/g, (character) => character.toUpperCase());

const subscriptionAlertClass = (
    status: PlatformDashboardSubscriptionAlertStatus,
): string => {
    if (status === 'expired' || status === 'suspended') {
        return 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300';
    }

    if (status === 'past_due') {
        return 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300';
    }

    return 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/10 dark:text-blue-light-300';
};

const usageStatusClass = (
    status: PlatformSaasUsageCapacityStatus,
): string => {
    if (status === 'over_limit') {
        return 'bg-error-50 text-error-700 dark:bg-error-500/10 dark:text-error-300';
    }

    if (status === 'at_limit') {
        return 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-300';
    }

    if (status === 'near_limit') {
        return 'bg-blue-light-50 text-blue-light-700 dark:bg-blue-light-500/10 dark:text-blue-light-300';
    }

    return 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-300';
};

const alertResources = (
    row: PlatformSaasUsageRow,
): PlatformSaasUsageResource[] => Object.values(row.resources).filter(
    (resource): boolean => [
        'over_limit',
        'at_limit',
        'near_limit',
    ].includes(resource.status),
);

const usageText = (resource: PlatformSaasUsageResource): string => {
    const usage = resource.unit === 'MB'
        ? `${resource.usage.toLocaleString()} MB`
        : resource.usage.toLocaleString();

    if (resource.limit === null) {
        return usage;
    }

    const limit = resource.unit === 'MB'
        ? `${resource.limit.toLocaleString()} MB`
        : resource.limit.toLocaleString();

    return `${usage} / ${limit}`;
};

const actorLabel = (
    type: 'platform_admin' | 'system',
    name: string | null,
): string => {
    if (type === 'system') {
        return 'System';
    }

    return name ?? 'Platform Admin';
};
</script>

<template>
    <Head title="Super Admin Dashboard" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium text-brand-600 dark:text-brand-400">
                    Platform control plane
                </p>

                <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                    SaaS Operational Dashboard
                </h1>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Signed in as {{ platformAdmin.name }} ({{ platformAdmin.email }}).
                    Monitor subscriptions, package capacity, and recent lifecycle activity.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('platform.subscriptions.index')"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Manage subscriptions
                </Link>

                <Link
                    :href="route('platform.tenants.create')"
                    class="rounded-lg bg-brand-500 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-600"
                >
                    Provision tenant
                </Link>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="card in metricCards"
                :key="card.label"
                :href="card.href"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm transition hover:border-brand-300 hover:shadow-theme-md dark:border-gray-800 dark:bg-white/[0.03] dark:hover:border-brand-700"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ card.label }}
                </p>

                <div class="mt-2 flex items-end justify-between gap-4">
                    <p class="text-3xl font-semibold text-gray-900 dark:text-white">
                        {{ card.value }}
                    </p>

                    <span class="text-right text-xs text-gray-500 dark:text-gray-400">
                        {{ card.hint }}
                    </span>
                </div>
            </Link>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <section
                class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] xl:col-span-2"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Subscription health
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Access remains manually controlled by Super Admin. These are monitoring signals only.
                        </p>
                    </div>

                    <Link
                        :href="route('platform.subscriptions.index')"
                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                    >
                        View all subscriptions
                    </Link>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-900/60">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Expiring soon
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ metrics.subscriptions_expiring_soon }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Next {{ expiringSoonDays }} days
                        </p>
                    </div>

                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-900/60">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Expired
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-error-600 dark:text-error-400">
                            {{ metrics.subscriptions_expired }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Lifecycle date reached
                        </p>
                    </div>

                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-900/60">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            No subscription
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ metrics.subscriptions_no_subscription }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Requires manual review
                        </p>
                    </div>

                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-900/60">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Active users
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                            {{ metrics.active_tenant_users }}
                        </p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Of {{ metrics.tenant_users_total }} tenant users
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Package distribution
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Current tenant subscriptions.
                        </p>
                    </div>

                    <Link
                        :href="route('platform.plans.index')"
                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                    >
                        Plans
                    </Link>
                </div>

                <div v-if="packageDistribution.length > 0" class="mt-5 space-y-4">
                    <div
                        v-for="item in packageDistribution"
                        :key="item.plan_id ?? item.name"
                    >
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <p class="truncate font-medium text-gray-800 dark:text-gray-200">
                                    {{ item.name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ item.code ?? 'Unknown' }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="font-semibold text-gray-900 dark:text-white">
                                    {{ item.subscriptions_count }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ item.percentage }}%
                                </p>
                            </div>
                        </div>

                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                            <div
                                class="h-full rounded-full bg-brand-500"
                                :style="{ width: `${Math.min(item.percentage, 100)}%` }"
                            />
                        </div>
                    </div>
                </div>

                <p v-else class="mt-5 text-sm text-gray-500 dark:text-gray-400">
                    No tenant subscriptions have been assigned yet.
                </p>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-800">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Subscription attention
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Expiry, grace-period, and suspension signals requiring review.
                        </p>
                    </div>

                    <Link
                        :href="route('platform.subscriptions.index', { expiry: 'expiring_soon' })"
                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                    >
                        Subscription dashboard
                    </Link>
                </div>

                <div v-if="subscriptionAlerts.length > 0" class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div
                        v-for="alert in subscriptionAlerts"
                        :key="`${alert.tenant.id}-${alert.alert_status}`"
                        class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    :href="route('platform.tenants.show', alert.tenant.id)"
                                    class="truncate font-medium text-gray-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                                >
                                    {{ alert.tenant.name }}
                                </Link>

                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="subscriptionAlertClass(alert.alert_status)"
                                >
                                    {{ titleCase(alert.alert_status) }}
                                </span>
                            </div>

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ alert.tenant.code }} · {{ alert.plan.name }} · {{ titleCase(alert.subscription_status) }}
                            </p>
                        </div>

                        <div class="shrink-0 text-left sm:text-right">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                {{ formatDate(alert.effective_expiry_at) }}
                            </p>
                            <p v-if="alert.days_remaining !== null" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                <template v-if="alert.days_remaining < 0">
                                    {{ Math.abs(alert.days_remaining) }} day(s) overdue
                                </template>
                                <template v-else-if="alert.days_remaining === 0">
                                    Due today
                                </template>
                                <template v-else>
                                    {{ alert.days_remaining }} day(s) remaining
                                </template>
                            </p>
                        </div>
                    </div>
                </div>

                <p v-else class="p-6 text-sm text-gray-500 dark:text-gray-400">
                    No subscription lifecycle alerts currently require attention.
                </p>
            </section>

            <section
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-800">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Package capacity alerts
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Near-limit, at-limit, and over-limit package resources.
                        </p>
                    </div>

                    <Link
                        :href="route('platform.usage.index')"
                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                    >
                        Usage dashboard
                    </Link>
                </div>

                <div v-if="usageAlerts.length > 0" class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div
                        v-for="row in usageAlerts"
                        :key="row.tenant.id"
                        class="p-5"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <Link
                                    :href="route('platform.tenants.show', row.tenant.id)"
                                    class="font-medium text-gray-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                                >
                                    {{ row.tenant.name }}
                                </Link>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ row.tenant.code }} · {{ row.plan?.name ?? 'No package' }}
                                </p>
                            </div>

                            <span
                                class="rounded-full px-2 py-1 text-xs font-medium"
                                :class="usageStatusClass(row.overall_status)"
                            >
                                {{ titleCase(row.overall_status) }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <span
                                v-for="resource in alertResources(row)"
                                :key="resource.key"
                                class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs dark:border-gray-700"
                            >
                                <span class="font-medium text-gray-700 dark:text-gray-300">
                                    {{ resource.label }}:
                                </span>
                                <span class="ml-1 text-gray-500 dark:text-gray-400">
                                    {{ usageText(resource) }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>

                <p v-else class="p-6 text-sm text-gray-500 dark:text-gray-400">
                    No package capacity alerts currently require attention.
                </p>
            </section>
        </div>

        <section
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-800">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Recent subscription activity
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ recentChanges30Days }} package/lifecycle change(s) recorded in the last 30 days.
                    </p>
                </div>

                <Link
                    :href="route('platform.subscriptions.history')"
                    class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                >
                    Full change history
                </Link>
            </div>

            <div v-if="recentActivity.length > 0" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-900/60">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Tenant
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Event
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Actor
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Time
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="activity in recentActivity" :key="activity.id">
                            <td class="px-5 py-4 text-sm">
                                <Link
                                    :href="route('platform.tenants.show', activity.tenant.id)"
                                    class="font-medium text-gray-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                                >
                                    {{ activity.tenant.name }}
                                </Link>
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ activity.tenant.code }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ activity.event_label }}
                                <p v-if="activity.reason" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ activity.reason }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ actorLabel(activity.actor.type, activity.actor.name) }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-500 dark:text-gray-400">
                                {{ formatDate(activity.created_at) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p v-else class="p-6 text-sm text-gray-500 dark:text-gray-400">
                No package or subscription changes have been recorded yet.
            </p>
        </section>
    </div>
</template>
