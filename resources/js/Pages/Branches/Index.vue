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
import type { ComputedRef } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import type {
    BranchFilters,
    BranchPagination,
    BranchRecord,
    BranchStatus,
    BranchStatusOption,
} from '@/Types/branch';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    branches: BranchPagination;
    filters: BranchFilters;
    statusOptions: BranchStatusOption[];
}>();

const { can } = useAuthorization();

const filterForm = reactive<BranchFilters>({
    search: props.filters.search,
    status: props.filters.status,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const deletingBranchId = ref<number | null>(null);

const hasActiveFilters: ComputedRef<boolean> = computed(
    (): boolean => filterForm.search !== ''
        || filterForm.status !== '',
);

const navigate = (page = 1): void => {
    router.get(
        '/erp/branches',
        {
            search: filterForm.search,
            status: filterForm.status,
            sort: filterForm.sort,
            direction: filterForm.direction,
            per_page: filterForm.per_page,
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
    navigate();
};

const resetFilters = (): void => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.sort = 'name';
    filterForm.direction = 'asc';
    filterForm.per_page = 25;

    navigate();
};

const sortBy = (
    column: BranchFilters['sort'],
): void => {
    if (filterForm.sort === column) {
        filterForm.direction = filterForm.direction === 'asc'
            ? 'desc'
            : 'asc';
    } else {
        filterForm.sort = column;
        filterForm.direction = 'asc';
    }

    navigate();
};

const sortIndicator = (
    column: BranchFilters['sort'],
): string => {
    if (filterForm.sort !== column) {
        return '';
    }

    return filterForm.direction === 'asc'
        ? '↑'
        : '↓';
};

const statusBadgeClass = (
    status: BranchStatus,
): string => {
    const classes: Record<BranchStatus, string> = {
        active: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
        inactive: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
        archived: 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-400',
    };

    return classes[status];
};

const statusLabel = (
    status: BranchStatus,
): string => props.statusOptions.find(
    (option: BranchStatusOption): boolean =>
        option.value === status,
)?.label ?? status;

const deleteBranch = (
    branch: BranchRecord,
): void => {
    const confirmed = window.confirm(
        `Delete the branch “${branch.name}”? This action will soft-delete the record.`,
    );

    if (!confirmed) {
        return;
    }

    deletingBranchId.value = branch.id;

    router.delete(
        `/erp/branches/${branch.id}`,
        {
            preserveScroll: true,
            onFinish: (): void => {
                deletingBranchId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Branches" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    Branches
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Manage company locations used by users, warehouses,
                    sales, purchasing, and reporting.
                </p>
            </div>

            <Link
                v-if="can('branches.create')"
                href="/erp/branches/create"
                class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
            >
                Add branch
            </Link>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <form
                class="grid gap-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:grid-cols-2 sm:p-6 xl:grid-cols-[minmax(240px,1fr)_200px_130px_auto]"
                @submit.prevent="applyFilters"
            >
                <div>
                    <label
                        for="branch-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search
                    </label>

                    <input
                        id="branch-search"
                        v-model="filterForm.search"
                        type="search"
                        placeholder="Name, code, email, or phone"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="branch-status"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                    </label>

                    <select
                        id="branch-status"
                        v-model="filterForm.status"
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
                        for="branch-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>

                    <select
                        id="branch-per-page"
                        v-model.number="filterForm.per_page"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button
                        type="submit"
                        class="inline-flex h-11 flex-1 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
                    >
                        Apply
                    </button>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
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
                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('name')"
                                >
                                    Branch {{ sortIndicator('name') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('code')"
                                >
                                    Code {{ sortIndicator('code') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('status')"
                                >
                                    Status {{ sortIndicator('status') }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6"
                            >
                                Contact
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="branch in branches.data"
                            :key="branch.id"
                            class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="px-5 py-4 sm:px-6">
                                <div
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ branch.name }}
                                </div>

                                <div
                                    v-if="branch.address"
                                    class="mt-1 max-w-sm truncate text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ branch.address }}
                                </div>
                            </td>

                            <td
                                class="px-5 py-4 text-sm font-medium text-gray-700 dark:text-gray-300 sm:px-6"
                            >
                                {{ branch.code }}
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="statusBadgeClass(branch.status)"
                                >
                                    {{ statusLabel(branch.status) }}
                                </span>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400 sm:px-6"
                            >
                                <div>
                                    {{ branch.email ?? '—' }}
                                </div>

                                <div
                                    v-if="branch.phone"
                                    class="mt-1"
                                >
                                    {{ branch.phone }}
                                </div>
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        v-if="can('branches.update')"
                                        :href="`/erp/branches/${branch.id}/edit`"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="can('branches.delete')"
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border border-error-300 bg-white px-3 text-sm font-medium text-error-600 shadow-theme-xs transition hover:bg-error-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-error-500/40 dark:bg-gray-800 dark:text-error-400 dark:hover:bg-error-500/10"
                                        :disabled="
                                            deletingBranchId === branch.id
                                        "
                                        @click="deleteBranch(branch)"
                                    >
                                        {{
                                            deletingBranchId === branch.id
                                                ? 'Deleting...'
                                                : 'Delete'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="branches.data.length === 0">
                            <td
                                colspan="5"
                                class="px-5 py-14 text-center sm:px-6"
                            >
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    No branches found
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Adjust the filters or add the first branch.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-6"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ branches.meta.from ?? 0 }}–{{
                        branches.meta.to ?? 0
                    }}
                    of {{ branches.meta.total }} branches
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                        :disabled="branches.meta.current_page <= 1"
                        @click="
                            navigate(
                                branches.meta.current_page - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <span
                        class="px-2 text-sm text-gray-600 dark:text-gray-400"
                    >
                        Page {{ branches.meta.current_page }} of
                        {{ branches.meta.last_page }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                        :disabled="
                            branches.meta.current_page
                                >= branches.meta.last_page
                        "
                        @click="
                            navigate(
                                branches.meta.current_page + 1,
                            )
                        "
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>