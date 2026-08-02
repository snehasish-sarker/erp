<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import {
    computed,
    reactive,
    ref,
} from 'vue';
import type {
    ComputedRef,
    Ref,
} from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import type {
    BrandFilters,
    BrandOption,
    BrandPagination,
    BrandRecord,
    BrandSort,
    BrandStatus,
} from '@/Types/brand';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    brands: BrandPagination;
    filters: BrandFilters;
    statusOptions: BrandOption<BrandStatus>[];
}>();

const { can } = useAuthorization();

const filters = reactive<BrandFilters>({
    search: props.filters.search,
    status: props.filters.status,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const deletingBrandId: Ref<number | null> =
    ref(null);

const hasActiveFilters: ComputedRef<boolean> =
    computed(
        (): boolean =>
            filters.search !== ''
            || filters.status !== '',
    );

const navigate = (page = 1): void => {
    router.get(
        '/erp/brands',
        {
            search: filters.search,
            status: filters.status,
            sort: filters.sort,
            direction: filters.direction,
            per_page: filters.per_page,
            page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const applyFilters = (): void => {
    filters.search = filters.search.trim();
    navigate();
};

const resetFilters = (): void => {
    filters.search = '';
    filters.status = '';
    filters.sort = 'sort_order';
    filters.direction = 'asc';
    filters.per_page = 25;

    navigate();
};

const sortBy = (
    column: BrandSort,
): void => {
    if (filters.sort === column) {
        filters.direction =
            filters.direction === 'asc'
                ? 'desc'
                : 'asc';
    } else {
        filters.sort = column;
        filters.direction = 'asc';
    }

    navigate();
};

const sortIndicator = (
    column: BrandSort,
): string => {
    if (filters.sort !== column) {
        return '';
    }

    return filters.direction === 'asc'
        ? '↑'
        : '↓';
};

const deleteBrand = (
    brand: BrandRecord,
): void => {
    const confirmed = window.confirm(
        `Delete brand “${brand.name} (${brand.code})”? Its code and slug will remain reserved.`,
    );

    if (!confirmed) {
        return;
    }

    deletingBrandId.value = brand.id;

    router.delete(
        `/erp/brands/${brand.id}`,
        {
            preserveScroll: true,

            onFinish: (): void => {
                deletingBrandId.value = null;
            },
        },
    );
};

const statusBadgeClass = (
    status: BrandStatus,
): string =>
    status === 'active'
        ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
        : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400';

const formatDate = (
    value: string | null,
): string => {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-US',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        },
    ).format(new Date(value));
};
</script>

<template>
    <Head title="Brands" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    Brands
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Manage tenant-isolated product brands,
                    identifiers, websites, and status.
                </p>
            </div>

            <Link
                v-if="can('brands.create')"
                href="/erp/brands/create"
                class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
            >
                Create brand
            </Link>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <form
                class="grid gap-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:grid-cols-2 sm:p-6 lg:grid-cols-4"
                @submit.prevent="applyFilters"
            >
                <div class="sm:col-span-2">
                    <label
                        for="brand-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search
                    </label>

                    <input
                        id="brand-search"
                        v-model="filters.search"
                        type="search"
                        maxlength="120"
                        placeholder="Name, code, slug, website, or description"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="brand-status-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                    </label>

                    <select
                        id="brand-status-filter"
                        v-model="filters.status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="brand-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>

                    <select
                        id="brand-per-page"
                        v-model.number="filters.per_page"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3 sm:col-span-2 lg:col-span-4"
                >
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white transition hover:bg-brand-600"
                    >
                        Apply filters
                    </button>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]"
                    >
                        <tr>
                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    @click="sortBy('name')"
                                >
                                    Brand
                                    {{ sortIndicator('name') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    @click="sortBy('code')"
                                >
                                    Code
                                    {{ sortIndicator('code') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    @click="sortBy('slug')"
                                >
                                    Slug
                                    {{ sortIndicator('slug') }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Website
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    @click="sortBy('sort_order')"
                                >
                                    Order
                                    {{ sortIndicator('sort_order') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    @click="sortBy('status')"
                                >
                                    Status
                                    {{ sortIndicator('status') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    @click="sortBy('created_at')"
                                >
                                    Created
                                    {{ sortIndicator('created_at') }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="brand in brands.data"
                            :key="brand.id"
                        >
                            <td class="px-5 py-4">
                                <p
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ brand.name }}
                                </p>

                                <p
                                    v-if="brand.description"
                                    class="mt-1 max-w-xs truncate text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ brand.description }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="rounded bg-gray-100 px-2 py-1 font-mono text-sm text-gray-700 dark:bg-white/10 dark:text-gray-300"
                                >
                                    {{ brand.code }}
                                </span>
                            </td>

                            <td
                                class="px-5 py-4 font-mono text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ brand.slug }}
                            </td>

                            <td class="px-5 py-4">
                                <a
                                    v-if="brand.website_url"
                                    :href="brand.website_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block max-w-56 truncate text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                >
                                    {{ brand.website_url }}
                                </a>

                                <span
                                    v-else
                                    class="text-sm text-gray-500 dark:text-gray-400"
                                >
                                    —
                                </span>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ brand.sort_order }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="statusBadgeClass(brand.status)"
                                >
                                    {{
                                        brand.status === 'active'
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </span>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ formatDate(brand.created_at) }}
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-3">
                                    <Link
                                        v-if="can('brands.update')"
                                        :href="`/erp/brands/${brand.id}/edit`"
                                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="can('brands.delete')"
                                        type="button"
                                        class="text-sm font-medium text-error-600 hover:text-error-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="
                                            deletingBrandId
                                            === brand.id
                                        "
                                        @click="deleteBrand(brand)"
                                    >
                                        {{
                                            deletingBrandId === brand.id
                                                ? 'Deleting...'
                                                : 'Delete'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="brands.data.length === 0">
                            <td
                                colspan="8"
                                class="px-5 py-12 text-center"
                            >
                                <p
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    No brands found.
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Create a brand or adjust
                                    the current filters.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Showing {{ brands.meta.from ?? 0 }}–{{
                        brands.meta.to ?? 0
                    }} of {{ brands.meta.total }}
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="brands.meta.current_page <= 1"
                        @click="navigate(brands.meta.current_page - 1)"
                    >
                        Previous
                    </button>

                    <span
                        class="px-2 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Page {{ brands.meta.current_page }}
                        of {{ brands.meta.last_page }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            brands.meta.current_page
                            >= brands.meta.last_page
                        "
                        @click="navigate(brands.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>