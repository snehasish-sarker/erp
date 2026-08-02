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
    WarehouseBranch,
    WarehouseFilters,
    WarehousePagination,
    WarehouseRecord,
    WarehouseStatus,
    WarehouseStatusOption,
    WarehouseType,
    WarehouseTypeOption,
} from '@/Types/warehouse';

defineOptions({
    layout: ErpLayout,
});

interface WarehouseFilterForm {
    search: string;
    branch_id: number | '';
    type: '' | WarehouseType;
    status: '' | WarehouseStatus;
    sort: WarehouseFilters['sort'];
    direction: WarehouseFilters['direction'];
    per_page: WarehouseFilters['per_page'];
}

const props = defineProps<{
    warehouses: WarehousePagination;
    filters: WarehouseFilters;
    branchOptions: WarehouseBranch[];
    typeOptions: WarehouseTypeOption[];
    statusOptions: WarehouseStatusOption[];
}>();

const { can } = useAuthorization();

const filterForm = reactive<WarehouseFilterForm>({
    search: props.filters.search,
    branch_id: props.filters.branch_id ?? '',
    type: props.filters.type,
    status: props.filters.status,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const deletingWarehouseId = ref<number | null>(null);

const hasActiveFilters: ComputedRef<boolean> = computed(
    (): boolean => filterForm.search !== ''
        || filterForm.branch_id !== ''
        || filterForm.type !== ''
        || filterForm.status !== '',
);

const navigate = (page = 1): void => {
    router.get(
        '/erp/warehouses',
        {
            search: filterForm.search,
            branch_id: filterForm.branch_id,
            type: filterForm.type,
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
    filterForm.branch_id = '';
    filterForm.type = '';
    filterForm.status = '';
    filterForm.sort = 'name';
    filterForm.direction = 'asc';
    filterForm.per_page = 25;

    navigate();
};

const sortBy = (
    column: WarehouseFilters['sort'],
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
    column: WarehouseFilters['sort'],
): string => {
    if (filterForm.sort !== column) {
        return '';
    }

    return filterForm.direction === 'asc'
        ? '↑'
        : '↓';
};

const statusBadgeClass = (
    status: WarehouseStatus,
): string => {
    const classes: Record<WarehouseStatus, string> = {
        active: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
        inactive: 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400',
        archived: 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-400',
    };

    return classes[status];
};

const statusLabel = (
    status: WarehouseStatus,
): string => props.statusOptions.find(
    (option: WarehouseStatusOption): boolean =>
        option.value === status,
)?.label ?? status;

const typeLabel = (
    type: WarehouseType,
): string => props.typeOptions.find(
    (option: WarehouseTypeOption): boolean =>
        option.value === type,
)?.label ?? type;

const deleteWarehouse = (
    warehouse: WarehouseRecord,
): void => {
    const confirmed = window.confirm(
        `Delete the warehouse “${warehouse.name}”? This action will soft-delete the record.`,
    );

    if (!confirmed) {
        return;
    }

    deletingWarehouseId.value = warehouse.id;

    router.delete(
        `/erp/warehouses/${warehouse.id}`,
        {
            preserveScroll: true,

            onFinish: (): void => {
                deletingWarehouseId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head title="Warehouses" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    Warehouses
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Manage inventory storage locations under company branches.
                </p>
            </div>

            <Link
                v-if="can('warehouses.create')"
                href="/erp/warehouses/create"
                class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
            >
                Add warehouse
            </Link>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <form
                class="grid gap-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:grid-cols-2 sm:p-6 xl:grid-cols-[minmax(220px,1fr)_180px_160px_160px_110px_auto]"
                @submit.prevent="applyFilters"
            >
                <div>
                    <label
                        for="warehouse-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search
                    </label>

                    <input
                        id="warehouse-search"
                        v-model="filterForm.search"
                        type="search"
                        placeholder="Warehouse, code, branch, or address"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="warehouse-branch"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Branch
                    </label>

                    <select
                        id="warehouse-branch"
                        v-model.number="filterForm.branch_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All branches
                        </option>

                        <option
                            v-for="branch in branchOptions"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="warehouse-type"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Type
                    </label>

                    <select
                        id="warehouse-type"
                        v-model="filterForm.type"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All types
                        </option>

                        <option
                            v-for="option in typeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="warehouse-status"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                    </label>

                    <select
                        id="warehouse-status"
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
                        for="warehouse-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>

                    <select
                        id="warehouse-per-page"
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
                                    Warehouse
                                    {{ sortIndicator('name') }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6"
                            >
                                Branch
                            </th>

                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('type')"
                                >
                                    Type {{ sortIndicator('type') }}
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
                                Default
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
                            v-for="warehouse in warehouses.data"
                            :key="warehouse.id"
                            class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="px-5 py-4 sm:px-6">
                                <div
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ warehouse.name }}
                                </div>

                                <div
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ warehouse.code }}
                                </div>

                                <div
                                    v-if="warehouse.address"
                                    class="mt-1 max-w-xs truncate text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ warehouse.address }}
                                </div>
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <div
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{ warehouse.branch.name }}
                                </div>

                                <div
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ warehouse.branch.code }}
                                </div>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400 sm:px-6"
                            >
                                {{ typeLabel(warehouse.type) }}
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="statusBadgeClass(
                                        warehouse.status,
                                    )"
                                >
                                    {{ statusLabel(warehouse.status) }}
                                </span>
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <span
                                    v-if="warehouse.is_default"
                                    class="inline-flex rounded-full bg-brand-50 px-2.5 py-1 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-400"
                                >
                                    Default
                                </span>

                                <span
                                    v-else
                                    class="text-sm text-gray-400"
                                >
                                    —
                                </span>
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        v-if="can('warehouses.update')"
                                        :href="`/erp/warehouses/${warehouse.id}/edit`"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="can('warehouses.delete')"
                                        type="button"
                                        class="inline-flex h-9 items-center justify-center rounded-lg border border-error-300 bg-white px-3 text-sm font-medium text-error-600 shadow-theme-xs transition hover:bg-error-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-error-500/40 dark:bg-gray-800 dark:text-error-400 dark:hover:bg-error-500/10"
                                        :disabled="
                                            deletingWarehouseId
                                                === warehouse.id
                                        "
                                        @click="
                                            deleteWarehouse(warehouse)
                                        "
                                    >
                                        {{
                                            deletingWarehouseId
                                                === warehouse.id
                                                ? 'Deleting...'
                                                : 'Delete'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="warehouses.data.length === 0">
                            <td
                                colspan="6"
                                class="px-5 py-14 text-center sm:px-6"
                            >
                                <p
                                    class="text-base font-medium text-gray-800 dark:text-white/90"
                                >
                                    No warehouses found
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Adjust the filters or add the first
                                    warehouse.
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
                    Showing {{ warehouses.meta.from ?? 0 }}–{{
                        warehouses.meta.to ?? 0
                    }}
                    of {{ warehouses.meta.total }} warehouses
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                        :disabled="warehouses.meta.current_page <= 1"
                        @click="
                            navigate(
                                warehouses.meta.current_page - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <span
                        class="px-2 text-sm text-gray-600 dark:text-gray-400"
                    >
                        Page {{ warehouses.meta.current_page }} of
                        {{ warehouses.meta.last_page }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                        :disabled="
                            warehouses.meta.current_page
                                >= warehouses.meta.last_page
                        "
                        @click="
                            navigate(
                                warehouses.meta.current_page + 1,
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