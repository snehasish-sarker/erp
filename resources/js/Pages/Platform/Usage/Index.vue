<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import {
    computed,
    reactive,
} from 'vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';
import type {
    PlatformManualSubscriptionStatus,
    PlatformSaasUsageCapacityStatus,
    PlatformSaasUsageProps,
    PlatformSaasUsageResource,
    PlatformSaasUsageResourceKey,
    PlatformTenantStatus,
} from '@/Types/platform-admin';

defineOptions({
    layout: PlatformAdminLayout,
});

const props = defineProps<PlatformSaasUsageProps>();

const filters = reactive({
    search: props.filters.search,
    saas_plan_id: props.filters.saas_plan_id,
    tenant_status: props.filters.tenant_status,
    subscription_status: props.filters.subscription_status,
    resource: props.filters.resource,
    capacity: props.filters.capacity,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const resourceKeys: PlatformSaasUsageResourceKey[] = [
    'users',
    'branches',
    'warehouses',
    'products',
    'storage',
];

const resourceLabels: Record<PlatformSaasUsageResourceKey, string> = {
    users: 'Active users',
    branches: 'Branches',
    warehouses: 'Warehouses',
    products: 'Products',
    storage: 'Storage',
};

const visibleResourceKeys = computed<PlatformSaasUsageResourceKey[]>(
    (): PlatformSaasUsageResourceKey[] => filters.resource === 'all'
        ? resourceKeys
        : [filters.resource],
);

const capacityScopeLabel = computed((): string => {
    if (filters.resource === 'all') {
        return 'all monitored resources';
    }

    return resourceLabels[filters.resource].toLowerCase();
});

const metricCards = computed(() => [
    {
        label: 'Monitored tenants',
        value: props.metrics.tenants_total,
        help: 'All non-deleted tenant records, including tenants without subscriptions',
    },
    {
        label: 'Healthy',
        value: props.metrics.healthy,
        help: `All finite limits below ${props.nearLimitPercent}%`,
    },
    {
        label: 'Near limit',
        value: props.metrics.near_limit,
        help: `${props.nearLimitPercent}% or more but still below the limit`,
    },
    {
        label: 'At limit',
        value: props.metrics.at_limit,
        help: 'At least one resource has no remaining capacity',
    },
    {
        label: 'Over limit',
        value: props.metrics.over_limit,
        help: 'Existing usage exceeds the currently assigned package allowance',
    },
    {
        label: 'No subscription',
        value: props.metrics.no_subscription,
        help: 'No usable package allocation is attached',
    },
]);

const applyFilters = (): void => {
    router.get(
        route('platform.usage.index'),
        {
            search: filters.search || undefined,
            saas_plan_id: filters.saas_plan_id ?? undefined,
            tenant_status: filters.tenant_status || undefined,
            subscription_status: filters.subscription_status || undefined,
            resource: filters.resource,
            capacity: filters.capacity || undefined,
            sort: filters.sort,
            direction: filters.direction,
            per_page: filters.per_page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const resetFilters = (): void => {
    filters.search = '';
    filters.saas_plan_id = null;
    filters.tenant_status = '';
    filters.subscription_status = '';
    filters.resource = 'all';
    filters.capacity = '';
    filters.sort = 'tenant_name';
    filters.direction = 'asc';
    filters.per_page = 25;

    applyFilters();
};

const formatStatus = (status: string): string =>
    status
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character: string): string =>
            character.toUpperCase(),
        );

const tenantStatusClass = (status: PlatformTenantStatus): string => {
    const classes: Record<PlatformTenantStatus, string> = {
        trial: 'text-blue-600 dark:text-blue-300',
        active: 'text-green-600 dark:text-green-300',
        suspended: 'text-amber-600 dark:text-amber-300',
        past_due: 'text-orange-600 dark:text-orange-300',
        cancelled: 'text-red-600 dark:text-red-300',
        archived: 'text-gray-500 dark:text-gray-400',
    };

    return classes[status];
};

const subscriptionStatusClass = (
    status: PlatformManualSubscriptionStatus | 'no_subscription',
): string => {
    const classes: Record<
        PlatformManualSubscriptionStatus | 'no_subscription',
        string
    > = {
        trial: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
        active: 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300',
        past_due: 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-300',
        suspended: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        cancelled: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
        no_subscription: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
    };

    return classes[status];
};

const capacityStatusClass = (
    status: PlatformSaasUsageCapacityStatus,
): string => {
    const classes: Record<PlatformSaasUsageCapacityStatus, string> = {
        healthy: 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300',
        near_limit: 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-300',
        at_limit: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        over_limit: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
        unlimited: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
        not_included: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
        no_subscription: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
    };

    return classes[status];
};

const overallStatusClass = (
    status: 'healthy' | 'near_limit' | 'at_limit' | 'over_limit' | 'no_subscription',
): string => capacityStatusClass(status);

const progressBarClass = (
    status: PlatformSaasUsageCapacityStatus,
): string => {
    if (status === 'over_limit') {
        return 'bg-red-500';
    }

    if (status === 'at_limit') {
        return 'bg-amber-500';
    }

    if (status === 'near_limit') {
        return 'bg-orange-500';
    }

    if (status === 'healthy') {
        return 'bg-green-500';
    }

    return 'bg-gray-300 dark:bg-gray-700';
};

const progressWidth = (resource: PlatformSaasUsageResource): string => {
    if (
        resource.status === 'at_limit'
        || resource.status === 'over_limit'
    ) {
        return '100%';
    }

    if (resource.percentage === null) {
        return '0%';
    }

    return `${Math.min(Math.max(resource.percentage, 0), 100)}%`;
};

const formatUsageValue = (resource: PlatformSaasUsageResource): string => {
    if (resource.unit === 'MB') {
        return `${resource.usage.toLocaleString(undefined, {
            maximumFractionDigits: 2,
        })} MB`;
    }

    return resource.usage.toLocaleString();
};

const formatLimitValue = (resource: PlatformSaasUsageResource): string => {
    if (resource.status === 'no_subscription') {
        return '—';
    }

    if (resource.status === 'unlimited') {
        return 'Unlimited';
    }

    if (resource.status === 'not_included') {
        return 'Not included';
    }

    if (resource.limit === null) {
        return '—';
    }

    if (resource.unit === 'MB') {
        return `${resource.limit.toLocaleString()} MB`;
    }

    return resource.limit.toLocaleString();
};

const formatRemaining = (resource: PlatformSaasUsageResource): string => {
    if (resource.status === 'unlimited') {
        return 'No cap';
    }

    if (
        resource.status === 'not_included'
        || resource.status === 'no_subscription'
        || resource.remaining === null
    ) {
        return '—';
    }

    if (resource.unit === 'MB') {
        return `${resource.remaining.toLocaleString(undefined, {
            maximumFractionDigits: 2,
        })} MB left`;
    }

    return `${resource.remaining.toLocaleString()} left`;
};

const formatPercentage = (resource: PlatformSaasUsageResource): string => {
    if (resource.percentage === null) {
        return '—';
    }

    return `${resource.percentage.toLocaleString(undefined, {
        maximumFractionDigits: 1,
    })}%`;
};
</script>

<template>
    <Head title="SaaS Usage & Package Limits" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <p class="text-sm font-medium text-brand-600 dark:text-brand-400">
                    Platform control plane
                </p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                    SaaS Usage & Package Limits
                </h1>
                <p class="mt-2 max-w-4xl text-sm text-gray-500 dark:text-gray-400">
                    Monitor actual tenant usage against the current package limits. This page is read-only monitoring: it never upgrades, downgrades, suspends, or changes a tenant automatically.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('platform.subscriptions.index')"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Manage Subscriptions
                </Link>
                <Link
                    :href="route('platform.plans.index')"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Manage Plans
                </Link>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div
                v-for="card in metricCards"
                :key="card.label"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                    {{ card.label }}
                </p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ card.value }}
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    {{ card.help }}
                </p>
            </div>
        </div>

        <form
            class="rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            @submit.prevent="applyFilters"
        >
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <input
                    v-model="filters.search"
                    type="search"
                    placeholder="Search tenant, code, email, package"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >

                <select
                    v-model="filters.saas_plan_id"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option :value="null">All packages</option>
                    <option
                        v-for="plan in planOptions"
                        :key="plan.id"
                        :value="plan.id"
                    >
                        {{ plan.name }}{{ plan.status === 'inactive' ? ' (Inactive)' : '' }}
                    </option>
                </select>

                <select
                    v-model="filters.tenant_status"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="">All tenant statuses</option>
                    <option value="trial">Trial</option>
                    <option value="active">Active</option>
                    <option value="past_due">Past Due</option>
                    <option value="suspended">Suspended</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="archived">Archived</option>
                </select>

                <select
                    v-model="filters.subscription_status"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="">All subscription statuses</option>
                    <option value="trial">Trial</option>
                    <option value="active">Active</option>
                    <option value="past_due">Past Due</option>
                    <option value="suspended">Suspended</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="no_subscription">No Subscription</option>
                </select>
            </div>

            <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <select
                    v-model="filters.resource"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="all">All monitored resources</option>
                    <option value="users">Active Users</option>
                    <option value="branches">Branches</option>
                    <option value="warehouses">Warehouses</option>
                    <option value="products">Products</option>
                    <option value="storage">Storage</option>
                </select>

                <select
                    v-model="filters.capacity"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="">All capacity states</option>
                    <option value="healthy">Healthy</option>
                    <option value="near_limit">Near Limit</option>
                    <option value="at_limit">At Limit</option>
                    <option value="over_limit">Over Limit</option>
                </select>

                <select
                    v-model="filters.sort"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="tenant_name">Sort: Tenant</option>
                    <option value="company_code">Sort: Company code</option>
                    <option value="package">Sort: Package</option>
                    <option value="tenant_status">Sort: Tenant status</option>
                    <option value="subscription_status">Sort: Subscription status</option>
                    <option value="users_usage">Sort: Active users</option>
                    <option value="branches_usage">Sort: Branches</option>
                    <option value="warehouses_usage">Sort: Warehouses</option>
                    <option value="products_usage">Sort: Products</option>
                    <option value="storage_usage">Sort: Storage</option>
                </select>

                <div class="grid grid-cols-2 gap-3">
                    <select
                        v-model="filters.direction"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    >
                        <option value="asc">Ascending</option>
                        <option value="desc">Descending</option>
                    </select>

                    <select
                        v-model="filters.per_page"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    >
                        <option :value="10">10 / page</option>
                        <option :value="25">25 / page</option>
                        <option :value="50">50 / page</option>
                        <option :value="100">100 / page</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Capacity filtering currently evaluates
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        {{ capacityScopeLabel }}
                    </span>.
                    “Near Limit” begins at {{ nearLimitPercent }}%.
                </p>

                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                    <button
                        type="submit"
                        class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600"
                    >
                        Apply Filters
                    </button>
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-900/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Tenant
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Package / Status
                            </th>
                            <th
                                v-for="resourceKey in visibleResourceKeys"
                                :key="resourceKey"
                                class="min-w-52 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                {{ resourceLabels[resourceKey] }}
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Overall
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr
                            v-for="row in usagePage.data"
                            :key="row.tenant.id"
                            class="align-top"
                        >
                            <td class="px-4 py-4">
                                <Link
                                    :href="route('platform.tenants.show', row.tenant.id)"
                                    class="font-medium text-gray-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                                >
                                    {{ row.tenant.name }}
                                </Link>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ row.tenant.code }}
                                </p>
                                <p
                                    class="mt-1 text-xs font-medium"
                                    :class="tenantStatusClass(row.tenant.status)"
                                >
                                    {{ formatStatus(row.tenant.status) }}
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ row.plan?.name ?? 'No package' }}
                                </p>
                                <p
                                    v-if="row.plan?.status === 'inactive'"
                                    class="mt-1 text-xs text-red-600 dark:text-red-300"
                                >
                                    Package inactive
                                </p>
                                <span
                                    class="mt-2 inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="subscriptionStatusClass(row.subscription_status ?? 'no_subscription')"
                                >
                                    {{ formatStatus(row.subscription_status ?? 'no_subscription') }}
                                </span>
                            </td>

                            <td
                                v-for="resourceKey in visibleResourceKeys"
                                :key="`${row.tenant.id}-${resourceKey}`"
                                class="px-4 py-4"
                            >
                                <div class="space-y-2">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                                {{ formatUsageValue(row.resources[resourceKey]) }}
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                of {{ formatLimitValue(row.resources[resourceKey]) }}
                                            </p>
                                        </div>
                                        <span
                                            class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                            :class="capacityStatusClass(row.resources[resourceKey].status)"
                                        >
                                            {{ formatStatus(row.resources[resourceKey].status) }}
                                        </span>
                                    </div>

                                    <div class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="progressBarClass(row.resources[resourceKey].status)"
                                            :style="{ width: progressWidth(row.resources[resourceKey]) }"
                                        />
                                    </div>

                                    <div class="flex justify-between gap-3 text-[11px] text-gray-500 dark:text-gray-400">
                                        <span>{{ formatRemaining(row.resources[resourceKey]) }}</span>
                                        <span>{{ formatPercentage(row.resources[resourceKey]) }}</span>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="overallStatusClass(row.overall_status)"
                                >
                                    {{ formatStatus(row.overall_status) }}
                                </span>
                            </td>

                            <td class="px-4 py-4 text-right">
                                <div class="flex flex-col items-end gap-2">
                                    <Link
                                        :href="route('platform.tenants.show', row.tenant.id)"
                                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                                    >
                                        Tenant details
                                    </Link>
                                    <Link
                                        :href="route('platform.subscriptions.index', { search: row.tenant.code })"
                                        class="text-sm font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                                    >
                                        Manage package
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="usagePage.data.length === 0">
                            <td
                                :colspan="visibleResourceKeys.length + 4"
                                class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                No tenants match the selected usage filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ usagePage.meta.from ?? 0 }}–{{ usagePage.meta.to ?? 0 }}
                    of {{ usagePage.meta.total }} tenants
                </p>

                <div class="flex gap-2">
                    <Link
                        v-if="usagePage.meta.previous_page_url"
                        :href="usagePage.meta.previous_page_url"
                        preserve-state
                        preserve-scroll
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Previous
                    </Link>
                    <span
                        v-else
                        class="cursor-not-allowed rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-400 dark:border-gray-800 dark:text-gray-600"
                    >
                        Previous
                    </span>

                    <span class="px-2 py-2 text-sm text-gray-500 dark:text-gray-400">
                        Page {{ usagePage.meta.current_page }} of {{ usagePage.meta.last_page }}
                    </span>

                    <Link
                        v-if="usagePage.meta.next_page_url"
                        :href="usagePage.meta.next_page_url"
                        preserve-state
                        preserve-scroll
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Next
                    </Link>
                    <span
                        v-else
                        class="cursor-not-allowed rounded-lg border border-gray-200 px-3 py-2 text-sm text-gray-400 dark:border-gray-800 dark:text-gray-600"
                    >
                        Next
                    </span>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900/60 dark:bg-blue-500/10 dark:text-blue-200">
            Usage follows the same hard-limit counting rules as the ERP: active users only; all non-deleted branches, warehouses, and products; and all non-deleted tenant file bytes. Unlimited package limits remain unlimited. This dashboard does not bypass tenant isolation in normal ERP requests and does not make commercial decisions automatically.
        </div>
    </div>
</template>
