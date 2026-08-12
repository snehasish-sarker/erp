<script setup lang="ts">
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/vue3';
import {
    computed,
    reactive,
    ref,
} from 'vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';
import type {
    PlatformManualSubscriptionFormData,
    PlatformManualSubscriptionStatus,
    PlatformSubscriptionDashboardProps,
    PlatformSubscriptionDashboardRow,
    PlatformSubscriptionQuickAction,
    PlatformTenantStatus,
} from '@/Types/platform-admin';

defineOptions({
    layout: PlatformAdminLayout,
});

const props = defineProps<PlatformSubscriptionDashboardProps>();

const filters = reactive({
    search: props.filters.search,
    saas_plan_id: props.filters.saas_plan_id,
    status: props.filters.status,
    expiry: props.filters.expiry,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const selectedRow = ref<PlatformSubscriptionDashboardRow | null>(null);

const activePlanOptions = computed(
    () => props.planOptions.filter(
        (plan): boolean => plan.status === 'active',
    ),
);

const toDateTimeLocal = (value: string | null): string => {
    if (value === null) {
        return '';
    }

    const date = new Date(value);
    const local = new Date(
        date.getTime() - (date.getTimezoneOffset() * 60_000),
    );

    return local.toISOString().slice(0, 16);
};

const localNow = (): string => {
    const date = new Date();
    const local = new Date(
        date.getTime() - (date.getTimezoneOffset() * 60_000),
    );

    return local.toISOString().slice(0, 16);
};

const localDaysFromNow = (days: number): string => {
    const date = new Date();
    date.setDate(date.getDate() + days);

    const local = new Date(
        date.getTime() - (date.getTimezoneOffset() * 60_000),
    );

    return local.toISOString().slice(0, 16);
};

const allocationForm = useForm<PlatformManualSubscriptionFormData>({
    saas_plan_id: null,
    billing_cycle: 'monthly',
    status: 'trial',
    starts_at: localNow(),
    trial_ends_at: localDaysFromNow(14),
    current_period_starts_at: '',
    current_period_ends_at: '',
    past_due_at: '',
    grace_ends_at: '',
    ends_at: '',
});
const quickActionForm = useForm<{ action: PlatformSubscriptionQuickAction }>({
    action: 'renew_monthly',
});


const openManagement = (row: PlatformSubscriptionDashboardRow): void => {
    selectedRow.value = row;
    allocationForm.clearErrors();

    allocationForm.saas_plan_id = row.plan?.status === 'active'
        ? row.plan.id
        : activePlanOptions.value[0]?.id ?? null;
    allocationForm.billing_cycle = row.billing_cycle ?? 'monthly';
    allocationForm.status = row.subscription_status ?? 'trial';
    allocationForm.starts_at = toDateTimeLocal(row.starts_at) || localNow();
    allocationForm.trial_ends_at = toDateTimeLocal(row.trial_ends_at)
        || localDaysFromNow(14);
    allocationForm.current_period_starts_at = toDateTimeLocal(
        row.current_period_starts_at,
    );
    allocationForm.current_period_ends_at = toDateTimeLocal(
        row.current_period_ends_at,
    );
    allocationForm.past_due_at = toDateTimeLocal(row.past_due_at);
    allocationForm.grace_ends_at = toDateTimeLocal(row.grace_ends_at);
    allocationForm.ends_at = toDateTimeLocal(row.ends_at);
};

const closeManagement = (): void => {
    if (allocationForm.processing || quickActionForm.processing) {
        return;
    }

    selectedRow.value = null;
    allocationForm.clearErrors();
};

const saveAllocation = (): void => {
    const row = selectedRow.value;

    if (row === null || allocationForm.saas_plan_id === null) {
        return;
    }

    const plan = activePlanOptions.value.find(
        (option): boolean => option.id === allocationForm.saas_plan_id,
    );

    if (
        !window.confirm(
            `Apply ${plan?.name ?? 'selected package'} to ${row.tenant.name} with status ${formatStatus(allocationForm.status)}?`,
        )
    ) {
        return;
    }

    allocationForm.patch(
        route('platform.tenants.subscription.update', row.tenant.id),
        {
            preserveScroll: true,
            onSuccess: (): void => {
                selectedRow.value = null;
            },
        },
    );
};

const applyQuickAction = (
    action: PlatformSubscriptionQuickAction,
    label: string,
): void => {
    const row = selectedRow.value;

    if (row === null || row.subscription_id === null) {
        return;
    }

    if (
        !window.confirm(
            `${label} for ${row.tenant.name}? This changes subscription access immediately and does not use invoice/payment status.`,
        )
    ) {
        return;
    }

    quickActionForm.action = action;
    quickActionForm.patch(
        route(
            'platform.tenants.subscription.quick-action',
            row.tenant.id,
        ),
        {
            preserveScroll: true,
            onSuccess: (): void => {
                selectedRow.value = null;
            },
        },
    );
};

const applyFilters = (): void => {
    router.get(
        route('platform.subscriptions.index'),
        {
            search: filters.search || undefined,
            saas_plan_id: filters.saas_plan_id ?? undefined,
            status: filters.status || undefined,
            expiry: filters.expiry || undefined,
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
    filters.status = '';
    filters.expiry = '';
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

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const daysRemainingLabel = (
    row: PlatformSubscriptionDashboardRow,
): string => {
    if (row.is_indefinite) {
        return 'Indefinite';
    }

    if (row.days_remaining === null) {
        return '—';
    }

    if (row.is_expired) {
        return '0 days';
    }

    return `${row.days_remaining} ${row.days_remaining === 1 ? 'day' : 'days'}`;
};

const metricCards = [
    {
        label: 'All tenants',
        value: props.metrics.tenants_total,
        help: 'Platform tenant accounts',
    },
    {
        label: 'Trial',
        value: props.metrics.trial,
        help: 'Trial subscriptions',
    },
    {
        label: 'Active',
        value: props.metrics.active,
        help: 'Active subscriptions',
    },
    {
        label: 'Past due',
        value: props.metrics.past_due,
        help: 'Inside or awaiting grace handling',
    },
    {
        label: 'Suspended',
        value: props.metrics.suspended,
        help: 'Subscription access suspended',
    },
    {
        label: 'Expiring soon',
        value: props.metrics.expiring_soon,
        help: `Within ${props.expiringSoonDays} days`,
    },
    {
        label: 'Expired',
        value: props.metrics.expired,
        help: 'Lifecycle deadline has passed',
    },
    {
        label: 'No subscription',
        value: props.metrics.no_subscription,
        help: 'Requires manual review',
    },
];
</script>

<template>
    <Head title="Subscription & Package Management" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-medium text-brand-600 dark:text-brand-400">
                    Platform control plane
                </p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                    Subscription & Package Management
                </h1>
                <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    Monitor tenant package allocation, lifecycle dates, expiry risk, and ERP access. Package changes remain manual and are performed through the existing tenant package editor.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('platform.subscriptions.history')"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Change History
                </Link>

                <Link
                    :href="route('platform.plans.index')"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Manage Plans
                </Link>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
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
                    v-model="filters.status"
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

                <select
                    v-model="filters.expiry"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                >
                    <option value="">All expiry states</option>
                    <option value="expiring_soon">
                        Expiring within {{ expiringSoonDays }} days
                    </option>
                    <option value="expired">Expired</option>
                    <option value="indefinite">Indefinite active period</option>
                </select>
            </div>

            <div class="mt-3 grid gap-3 md:grid-cols-4 xl:grid-cols-6">
                <select
                    v-model="filters.sort"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white xl:col-span-2"
                >
                    <option value="tenant_name">Sort: Tenant</option>
                    <option value="company_code">Sort: Company code</option>
                    <option value="package">Sort: Package</option>
                    <option value="subscription_status">Sort: Subscription status</option>
                    <option value="billing_cycle">Sort: Billing cycle</option>
                    <option value="trial_ends_at">Sort: Trial end</option>
                    <option value="current_period_starts_at">Sort: Period start</option>
                    <option value="current_period_ends_at">Sort: Period end</option>
                    <option value="grace_ends_at">Sort: Grace end</option>
                    <option value="expiry">Sort: Effective expiry</option>
                </select>

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
                    <option :value="10">10 per page</option>
                    <option :value="25">25 per page</option>
                    <option :value="50">50 per page</option>
                    <option :value="100">100 per page</option>
                </select>

                <button
                    type="submit"
                    class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700"
                >
                    Apply Filters
                </button>

                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                    @click="resetFilters"
                >
                    Reset
                </button>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="overflow-x-auto">
                <table class="min-w-[1560px] w-full">
                    <thead class="bg-gray-50 dark:bg-gray-900/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tenant / Company</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Package</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Cycle</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Subscription</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Trial End</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Current Period</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Grace End</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Days Remaining</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">ERP Access</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr
                            v-for="row in subscriptionPage.data"
                            :key="row.tenant.id"
                            class="align-top hover:bg-gray-50/70 dark:hover:bg-gray-900/40"
                        >
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ row.tenant.name }}
                                </div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ row.tenant.code }}
                                    <span v-if="row.tenant.email"> · {{ row.tenant.email }}</span>
                                </div>
                                <div
                                    class="mt-1 text-xs font-medium"
                                    :class="tenantStatusClass(row.tenant.status)"
                                >
                                    Tenant: {{ formatStatus(row.tenant.status) }}
                                </div>
                            </td>

                            <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                <template v-if="row.plan">
                                    <div class="font-medium text-gray-900 dark:text-white">
                                        {{ row.plan.name }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        {{ row.plan.code }}
                                        <span v-if="row.plan.status === 'inactive'"> · Inactive plan</span>
                                    </div>
                                </template>
                                <span v-else class="text-gray-400">No package</span>
                            </td>

                            <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ row.billing_cycle ? formatStatus(row.billing_cycle) : '—' }}
                            </td>

                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="subscriptionStatusClass(row.subscription_status ?? 'no_subscription')"
                                >
                                    {{ row.subscription_status ? formatStatus(row.subscription_status) : 'No Subscription' }}
                                </span>

                                <div
                                    v-if="row.is_expired"
                                    class="mt-2 inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 dark:bg-red-500/10 dark:text-red-300"
                                >
                                    Expired
                                </div>
                            </td>

                            <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ formatDate(row.trial_ends_at) }}
                            </td>

                            <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                <div>{{ formatDate(row.current_period_starts_at) }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    to {{ row.is_indefinite ? 'Indefinite' : formatDate(row.current_period_ends_at) }}
                                </div>
                            </td>

                            <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ formatDate(row.grace_ends_at) }}
                            </td>

                            <td class="px-4 py-4 text-sm">
                                <div
                                    class="font-medium"
                                    :class="row.is_expired
                                        ? 'text-red-600 dark:text-red-300'
                                        : 'text-gray-900 dark:text-white'"
                                >
                                    {{ daysRemainingLabel(row) }}
                                </div>
                                <div
                                    v-if="row.effective_expiry_at"
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ formatDate(row.effective_expiry_at) }}
                                </div>
                            </td>

                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="row.access_active
                                        ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300'
                                        : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'"
                                >
                                    {{ row.access_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>

                            <td class="px-4 py-4 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-700"
                                        @click="openManagement(row)"
                                    >
                                        Manage
                                    </button>

                                    <Link
                                        :href="route('platform.tenants.show', row.tenant.id)"
                                        class="inline-flex rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        Details
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="subscriptionPage.data.length === 0">
                            <td
                                colspan="10"
                                class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                No tenant subscriptions matched the current filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-4 text-sm dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-gray-500 dark:text-gray-400">
                    Showing {{ subscriptionPage.meta.from ?? 0 }}–{{ subscriptionPage.meta.to ?? 0 }} of {{ subscriptionPage.meta.total }} tenants
                </p>

                <div class="flex gap-2">
                    <Link
                        v-if="subscriptionPage.meta.previous_page_url"
                        :href="subscriptionPage.meta.previous_page_url"
                        preserve-scroll
                        preserve-state
                        class="rounded-lg border border-gray-300 px-3 py-2 font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    >
                        Previous
                    </Link>

                    <Link
                        v-if="subscriptionPage.meta.next_page_url"
                        :href="subscriptionPage.meta.next_page_url"
                        preserve-scroll
                        preserve-state
                        class="rounded-lg border border-gray-300 px-3 py-2 font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    >
                        Next
                    </Link>
                </div>
            </div>
        </div>

        <div
            v-if="selectedRow"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
            @click.self="closeManagement"
        >
            <div class="max-h-[92vh] w-full max-w-5xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-brand-600 dark:text-brand-400">
                            Manual package management
                        </p>
                        <h2 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">
                            {{ selectedRow.tenant.name }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ selectedRow.tenant.code }} · Changes use the existing manual subscription workflow.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                        @click="closeManagement"
                    >
                        Close
                    </button>
                </div>

                <section
                    v-if="selectedRow.subscription_id !== null && selectedRow.tenant.status !== 'archived'"
                    class="mt-6 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950/60"
                >
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                            Quick renewal & extension
                        </h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            These are manual Super Admin actions. They never inspect or settle an invoice/payment.
                        </p>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <template v-if="selectedRow.can_extend_trial">
                            <button
                                type="button"
                                :disabled="quickActionForm.processing"
                                class="rounded-lg border border-blue-300 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700 transition hover:bg-blue-100 disabled:opacity-50 dark:border-blue-800 dark:bg-blue-500/10 dark:text-blue-300"
                                @click="applyQuickAction('extend_trial_7', 'Extend trial by 7 days')"
                            >
                                Trial +7 Days
                            </button>
                            <button
                                type="button"
                                :disabled="quickActionForm.processing"
                                class="rounded-lg border border-blue-300 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700 transition hover:bg-blue-100 disabled:opacity-50 dark:border-blue-800 dark:bg-blue-500/10 dark:text-blue-300"
                                @click="applyQuickAction('extend_trial_14', 'Extend trial by 14 days')"
                            >
                                Trial +14 Days
                            </button>
                            <button
                                type="button"
                                :disabled="quickActionForm.processing"
                                class="rounded-lg border border-blue-300 bg-blue-50 px-3 py-2 text-xs font-medium text-blue-700 transition hover:bg-blue-100 disabled:opacity-50 dark:border-blue-800 dark:bg-blue-500/10 dark:text-blue-300"
                                @click="applyQuickAction('extend_trial_30', 'Extend trial by 30 days')"
                            >
                                Trial +30 Days
                            </button>
                        </template>

                        <template
                            v-if="selectedRow.subscription_status === 'active' && selectedRow.current_period_ends_at !== null"
                        >
                            <button
                                type="button"
                                :disabled="quickActionForm.processing"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-100 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                @click="applyQuickAction('extend_month', 'Extend active period by one month')"
                            >
                                Extend +1 Month
                            </button>
                            <button
                                type="button"
                                :disabled="quickActionForm.processing"
                                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-100 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                @click="applyQuickAction('extend_year', 'Extend active period by one year')"
                            >
                                Extend +1 Year
                            </button>
                        </template>

                        <button
                            type="button"
                            :disabled="quickActionForm.processing"
                            class="rounded-lg bg-green-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-green-700 disabled:opacity-50"
                            @click="applyQuickAction('renew_monthly', 'Renew subscription for one month')"
                        >
                            Renew Monthly
                        </button>
                        <button
                            type="button"
                            :disabled="quickActionForm.processing"
                            class="rounded-lg bg-green-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-green-700 disabled:opacity-50"
                            @click="applyQuickAction('renew_annual', 'Renew subscription for one year')"
                        >
                            Renew Annual
                        </button>
                        <button
                            type="button"
                            :disabled="quickActionForm.processing"
                            class="rounded-lg bg-violet-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-violet-700 disabled:opacity-50"
                            @click="applyQuickAction('activate_indefinite', 'Activate subscription indefinitely')"
                        >
                            Activate Indefinitely
                        </button>
                    </div>
                </section>

                <form class="mt-6 space-y-5" @submit.prevent="saveAllocation">
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Package
                            </label>
                            <select
                                v-model="allocationForm.saas_plan_id"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                                <option :value="null" disabled>Select package</option>
                                <option
                                    v-for="plan in activePlanOptions"
                                    :key="plan.id"
                                    :value="plan.id"
                                >
                                    {{ plan.name }} ({{ plan.code }})
                                </option>
                            </select>
                            <p v-if="allocationForm.errors.saas_plan_id" class="mt-1 text-sm text-error-500">
                                {{ allocationForm.errors.saas_plan_id }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Billing cycle
                            </label>
                            <select
                                v-model="allocationForm.billing_cycle"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                                <option value="monthly">Monthly</option>
                                <option value="annual">Annual</option>
                            </select>
                            <p v-if="allocationForm.errors.billing_cycle" class="mt-1 text-sm text-error-500">
                                {{ allocationForm.errors.billing_cycle }}
                            </p>
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Subscription status
                            </label>
                            <select
                                v-model="allocationForm.status"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                                <option value="trial">Trial</option>
                                <option value="active">Active</option>
                                <option value="past_due">Past Due</option>
                                <option value="suspended">Suspended</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <p v-if="allocationForm.errors.status" class="mt-1 text-sm text-error-500">
                                {{ allocationForm.errors.status }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Subscription starts
                            </label>
                            <input
                                v-model="allocationForm.starts_at"
                                type="datetime-local"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                            <p v-if="allocationForm.errors.starts_at" class="mt-1 text-sm text-error-500">
                                {{ allocationForm.errors.starts_at }}
                            </p>
                        </div>

                        <div v-if="allocationForm.status === 'trial'">
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Trial ends
                            </label>
                            <input
                                v-model="allocationForm.trial_ends_at"
                                type="datetime-local"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                            <p v-if="allocationForm.errors.trial_ends_at" class="mt-1 text-sm text-error-500">
                                {{ allocationForm.errors.trial_ends_at }}
                            </p>
                        </div>

                        <template v-if="allocationForm.status !== 'trial'">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Current period starts
                                </label>
                                <input
                                    v-model="allocationForm.current_period_starts_at"
                                    type="datetime-local"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                >
                                <p v-if="allocationForm.errors.current_period_starts_at" class="mt-1 text-sm text-error-500">
                                    {{ allocationForm.errors.current_period_starts_at }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Current period ends
                                </label>
                                <input
                                    v-model="allocationForm.current_period_ends_at"
                                    type="datetime-local"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                >
                                <p v-if="allocationForm.errors.current_period_ends_at" class="mt-1 text-sm text-error-500">
                                    {{ allocationForm.errors.current_period_ends_at }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Leave blank for an indefinite active period.
                                </p>
                            </div>
                        </template>

                        <template v-if="allocationForm.status === 'past_due'">
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Past due since
                                </label>
                                <input
                                    v-model="allocationForm.past_due_at"
                                    type="datetime-local"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                >
                                <p v-if="allocationForm.errors.past_due_at" class="mt-1 text-sm text-error-500">
                                    {{ allocationForm.errors.past_due_at }}
                                </p>
                            </div>

                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Grace ends
                                </label>
                                <input
                                    v-model="allocationForm.grace_ends_at"
                                    type="datetime-local"
                                    class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                >
                                <p v-if="allocationForm.errors.grace_ends_at" class="mt-1 text-sm text-error-500">
                                    {{ allocationForm.errors.grace_ends_at }}
                                </p>
                            </div>
                        </template>

                        <div v-if="allocationForm.status === 'cancelled'">
                            <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Subscription ends
                            </label>
                            <input
                                v-model="allocationForm.ends_at"
                                type="datetime-local"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                            <p v-if="allocationForm.errors.ends_at" class="mt-1 text-sm text-error-500">
                                {{ allocationForm.errors.ends_at }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-500/10 dark:text-blue-200">
                        Saving here directly updates the tenant package allocation through the same audited, atomic manual-allocation service used on the tenant detail page. No invoice or payment is required.
                    </div>

                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            :disabled="allocationForm.processing"
                            class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                            @click="closeManagement"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            :disabled="allocationForm.processing || allocationForm.saas_plan_id === null"
                            class="rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {{ allocationForm.processing ? 'Saving…' : 'Save Package Allocation' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
