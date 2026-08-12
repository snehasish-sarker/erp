<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import {
    reactive,
} from 'vue';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';
import type {
    PlatformTenantIndexProps,
    PlatformTenantStatus,
} from '@/Types/platform-admin';

defineOptions({
    layout: PlatformAdminLayout,
});

const props = defineProps<PlatformTenantIndexProps>();

const filters = reactive({
    search: props.filters.search,
    status: props.filters.status,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const applyFilters = (): void => {
    router.get(
        route('platform.tenants.index'),
        {
            search: filters.search || undefined,
            status: filters.status || undefined,
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
    filters.status = '';
    filters.sort = 'name';
    filters.direction = 'asc';
    filters.per_page = 25;

    applyFilters();
};

const statusClass = (
    status: PlatformTenantStatus,
): string => {
    const classes: Record<PlatformTenantStatus, string> = {
        trial: 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300',
        active: 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-300',
        suspended: 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        past_due: 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-300',
        cancelled: 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
        archived: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
    };

    return classes[status];
};

const formatStatus = (status: PlatformTenantStatus): string =>
    status
        .replace('_', ' ')
        .replace(/\b\w/g, (character: string): string =>
            character.toUpperCase(),
        );
</script>

<template>
    <Head title="Tenant Management" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-medium text-brand-600 dark:text-brand-400">
                    Platform control plane
                </p>
                <h1 class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                    Tenant Management
                </h1>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Review tenant accounts and control ERP access at the platform level.
                </p>
            </div>

            <Link
                :href="route('platform.tenants.create')"
                class="inline-flex items-center justify-center rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-700"
            >
                Provision Tenant
            </Link>
        </div>

        <form
            class="grid gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] md:grid-cols-5"
            @submit.prevent="applyFilters"
        >
            <input
                v-model="filters.search"
                type="search"
                placeholder="Search tenant, code, slug, email"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white md:col-span-2"
            >

            <select
                v-model="filters.status"
                class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
            >
                <option value="">All statuses</option>
                <option
                    v-for="option in statusOptions"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
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

            <div class="flex gap-2">
                <button
                    type="submit"
                    class="flex-1 rounded-lg bg-brand-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-brand-700"
                >
                    Apply
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    @click="resetFilters"
                >
                    Reset
                </button>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-900/60">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Tenant</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Users</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Branches</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Warehouses</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Currency</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr
                            v-for="tenant in tenants.data"
                            :key="tenant.id"
                            class="hover:bg-gray-50/70 dark:hover:bg-gray-900/40"
                        >
                            <td class="px-4 py-4">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    {{ tenant.name }}
                                </div>
                                <div class="mt-1 text-xs text-gray-500">
                                    {{ tenant.code }} · {{ tenant.slug }}
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="statusClass(tenant.status)"
                                >
                                    {{ formatStatus(tenant.status) }}
                                </span>
                            </td>
                            <td class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                {{ tenant.users_count }}
                            </td>
                            <td class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                {{ tenant.branches_count }}
                            </td>
                            <td class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                {{ tenant.warehouses_count }}
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ tenant.currency_code }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                <Link
                                    :href="route('platform.tenants.show', tenant.id)"
                                    class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                >
                                    View
                                </Link>
                            </td>
                        </tr>

                        <tr v-if="tenants.data.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-10 text-center text-sm text-gray-500"
                            >
                                No tenants matched the current filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 px-4 py-4 text-sm dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-gray-500 dark:text-gray-400">
                    Showing {{ tenants.meta.from ?? 0 }}–{{ tenants.meta.to ?? 0 }} of {{ tenants.meta.total }} tenants
                </p>

                <div class="flex gap-2">
                    <Link
                        v-if="tenants.meta.previous_page_url"
                        :href="tenants.meta.previous_page_url"
                        preserve-scroll
                        preserve-state
                        class="rounded-lg border border-gray-300 px-3 py-2 font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    >
                        Previous
                    </Link>

                    <Link
                        v-if="tenants.meta.next_page_url"
                        :href="tenants.meta.next_page_url"
                        preserve-scroll
                        preserve-state
                        class="rounded-lg border border-gray-300 px-3 py-2 font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    >
                        Next
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
