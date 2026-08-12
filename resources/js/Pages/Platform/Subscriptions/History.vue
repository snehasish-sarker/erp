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
    PlatformSubscriptionHistoryActorType,
    PlatformSubscriptionHistoryChange,
    PlatformSubscriptionHistoryEvent,
    PlatformSubscriptionHistoryProps,
} from '@/Types/platform-admin';

defineOptions({
    layout: PlatformAdminLayout,
});

const props = defineProps<PlatformSubscriptionHistoryProps>();

const filters = reactive({
    search: props.filters.search,
    tenant_id: props.filters.tenant_id,
    event: props.filters.event,
    actor_type: props.filters.actor_type,
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const metricCards = computed(() => [
    {
        label: 'History events',
        value: props.metrics.total_events,
        help: 'All recorded SaaS package and lifecycle events',
    },
    {
        label: 'Manual actions',
        value: props.metrics.manual_actions,
        help: 'Package allocation and manual access changes',
    },
    {
        label: 'Lifecycle actions',
        value: props.metrics.lifecycle_actions,
        help: 'Past-due and suspension transitions',
    },
    {
        label: 'Trial extensions',
        value: props.metrics.trial_extensions,
        help: 'Super Admin trial-extension actions',
    },
    {
        label: 'Last 30 days',
        value: props.metrics.last_30_days,
        help: 'Recent package and lifecycle activity',
    },
]);

const applyFilters = (): void => {
    router.get(
        route('platform.subscriptions.history'),
        {
            search: filters.search || undefined,
            tenant_id: filters.tenant_id ?? undefined,
            event: filters.event || undefined,
            actor_type: filters.actor_type || undefined,
            date_from: filters.date_from || undefined,
            date_to: filters.date_to || undefined,
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
    filters.tenant_id = null;
    filters.event = '';
    filters.actor_type = '';
    filters.date_from = '';
    filters.date_to = '';
    filters.sort = 'created_at';
    filters.direction = 'desc';
    filters.per_page = 25;

    applyFilters();
};

const clearTenantScope = (): void => {
    filters.tenant_id = null;
    applyFilters();
};

const formatStatusLikeValue = (value: string): string =>
    value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (character: string): string =>
            character.toUpperCase(),
        );

const dateFields = new Set([
    'starts_at',
    'trial_ends_at',
    'current_period_starts_at',
    'current_period_ends_at',
    'past_due_at',
    'grace_ends_at',
    'suspended_at',
    'cancelled_at',
    'ends_at',
]);

const statusFields = new Set([
    'subscription_status',
    'tenant_status',
    'billing_cycle',
]);

const displayValue = (
    change: PlatformSubscriptionHistoryChange,
    value: string | null,
): string => {
    if (value === null || value === '') {
        return '—';
    }

    if (dateFields.has(change.field)) {
        const date = new Date(value);

        if (!Number.isNaN(date.getTime())) {
            return new Intl.DateTimeFormat(undefined, {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            }).format(date);
        }
    }

    if (statusFields.has(change.field)) {
        return formatStatusLikeValue(value);
    }

    return value;
};

const formatDateTime = (value: string | null): string => {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const actorLabel = (
    actorType: PlatformSubscriptionHistoryActorType,
    actorName: string | null,
): string => {
    if (actorType === 'system') {
        return 'System lifecycle processor';
    }

    return actorName ?? 'Platform Admin';
};

const actorClass = (actorType: PlatformSubscriptionHistoryActorType): string =>
    actorType === 'system'
        ? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300'
        : 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300';

const eventClass = (event: PlatformSubscriptionHistoryEvent): string => {
    if (event === 'saas_subscription_suspended') {
        return 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300';
    }

    if (event === 'saas_subscription_past_due') {
        return 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-300';
    }

    if (event === 'saas_trial_extended') {
        return 'bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-300';
    }

    if (event === 'saas_subscription_manually_suspended') {
        return 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
    }

    return 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300';
};
</script>

<template>
    <Head title="Subscription Change History" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <Link
                    :href="route('platform.subscriptions.index')"
                    class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                >
                    ← Subscription Management
                </Link>

                <h1 class="mt-3 text-2xl font-semibold text-gray-900 dark:text-white">
                    Package & Subscription Change History
                </h1>
                <p class="mt-2 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
                    Immutable audit timeline for package allocation, manual lifecycle changes, trial extensions, and scheduled subscription transitions.
                </p>
            </div>

            <Link
                :href="route('platform.subscriptions.index')"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Manage Subscriptions
            </Link>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
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

        <div
            v-if="selectedTenant"
            class="flex flex-col gap-3 rounded-xl border border-brand-200 bg-brand-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-brand-500/20 dark:bg-brand-500/10"
        >
            <div>
                <p class="text-sm font-medium text-brand-800 dark:text-brand-200">
                    Tenant history scope
                </p>
                <p class="mt-1 text-sm text-brand-700 dark:text-brand-300">
                    {{ selectedTenant.name }} · {{ selectedTenant.code }}
                </p>
            </div>

            <button
                type="button"
                class="text-sm font-medium text-brand-700 hover:text-brand-800 dark:text-brand-300"
                @click="clearTenantScope"
            >
                Show all tenants
            </button>
        </div>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <form
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                @submit.prevent="applyFilters"
            >
                <div class="xl:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Search
                    </label>
                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Tenant, company code, admin name or email"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Change type
                    </label>
                    <select
                        v-model="filters.event"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    >
                        <option value="">All changes</option>
                        <option
                            v-for="option in eventOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Actor
                    </label>
                    <select
                        v-model="filters.actor_type"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    >
                        <option value="">All actors</option>
                        <option value="platform_admin">Platform Admin</option>
                        <option value="system">System</option>
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        From date
                    </label>
                    <input
                        v-model="filters.date_from"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        To date
                    </label>
                    <input
                        v-model="filters.date_to"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Sort
                    </label>
                    <select
                        v-model="filters.sort"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                    >
                        <option value="created_at">Date/time</option>
                        <option value="tenant">Tenant</option>
                        <option value="event">Change type</option>
                        <option value="actor">Actor</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Direction
                        </label>
                        <select
                            v-model="filters.direction"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option value="desc">Newest / Z-A</option>
                            <option value="asc">Oldest / A-Z</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Per page
                        </label>
                        <select
                            v-model="filters.per_page"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        >
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 md:col-span-2 xl:col-span-4">
                    <button
                        type="submit"
                        class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
                    >
                        Apply filters
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </form>
        </section>

        <section class="space-y-4">
            <div
                v-if="historyPage.data.length === 0"
                class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-gray-700 dark:bg-white/[0.03]"
            >
                <p class="font-medium text-gray-900 dark:text-white">
                    No subscription history matches the current filters.
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Future manual package and lifecycle changes will appear here automatically.
                </p>
            </div>

            <article
                v-for="item in historyPage.data"
                :key="item.id"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="eventClass(item.event)"
                            >
                                {{ item.event_label }}
                            </span>
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="actorClass(item.actor.type)"
                            >
                                {{ actorLabel(item.actor.type, item.actor.name) }}
                            </span>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <Link
                                :href="route('platform.tenants.show', item.tenant.id)"
                                class="font-semibold text-gray-900 hover:text-brand-600 dark:text-white dark:hover:text-brand-400"
                            >
                                {{ item.tenant.name }}
                            </Link>
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                {{ item.tenant.code }}
                            </span>
                        </div>

                        <p
                            v-if="item.reason"
                            class="mt-2 text-sm text-gray-600 dark:text-gray-300"
                        >
                            {{ item.reason }}
                        </p>
                    </div>

                    <div class="shrink-0 text-left lg:text-right">
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ formatDateTime(item.created_at) }}
                        </p>
                        <p
                            v-if="item.actor.email"
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            {{ item.actor.email }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="item.changes.length > 0"
                    class="mt-5 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800"
                >
                    <div class="grid grid-cols-[minmax(130px,0.9fr)_minmax(0,1.2fr)_minmax(0,1.2fr)] bg-gray-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-gray-900/60 dark:text-gray-400">
                        <div>Field</div>
                        <div>Previous</div>
                        <div>New</div>
                    </div>

                    <div
                        v-for="change in item.changes"
                        :key="change.field"
                        class="grid grid-cols-[minmax(130px,0.9fr)_minmax(0,1.2fr)_minmax(0,1.2fr)] border-t border-gray-100 px-4 py-3 text-sm dark:border-gray-800"
                    >
                        <div class="font-medium text-gray-700 dark:text-gray-300">
                            {{ change.label }}
                        </div>
                        <div class="break-words text-gray-500 dark:text-gray-400">
                            {{ displayValue(change, change.old_value) }}
                        </div>
                        <div class="break-words font-medium text-gray-900 dark:text-white">
                            {{ displayValue(change, change.new_value) }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="item.metadata.length > 0 || item.request_id || item.route_name || item.ip_address"
                    class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-500 dark:text-gray-400"
                >
                    <span
                        v-for="meta in item.metadata"
                        :key="`${item.id}-${meta.label}`"
                    >
                        <strong class="font-medium text-gray-700 dark:text-gray-300">
                            {{ meta.label }}:
                        </strong>
                        {{ meta.value }}
                    </span>
                    <span v-if="item.route_name">
                        <strong class="font-medium text-gray-700 dark:text-gray-300">Route:</strong>
                        {{ item.route_name }}
                    </span>
                    <span v-if="item.ip_address">
                        <strong class="font-medium text-gray-700 dark:text-gray-300">IP:</strong>
                        {{ item.ip_address }}
                    </span>
                    <span v-if="item.request_id">
                        <strong class="font-medium text-gray-700 dark:text-gray-300">Request:</strong>
                        {{ item.request_id }}
                    </span>
                </div>
            </article>
        </section>

        <div
            v-if="historyPage.meta.total > 0"
            class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Showing {{ historyPage.meta.from ?? 0 }}–{{ historyPage.meta.to ?? 0 }} of {{ historyPage.meta.total }} events
            </p>

            <div class="flex items-center gap-2">
                <Link
                    v-if="historyPage.meta.previous_page_url"
                    :href="historyPage.meta.previous_page_url"
                    preserve-scroll
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Previous
                </Link>
                <span class="px-2 text-sm text-gray-500 dark:text-gray-400">
                    Page {{ historyPage.meta.current_page }} of {{ historyPage.meta.last_page }}
                </span>
                <Link
                    v-if="historyPage.meta.next_page_url"
                    :href="historyPage.meta.next_page_url"
                    preserve-scroll
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Next
                </Link>
            </div>
        </div>
    </div>
</template>
