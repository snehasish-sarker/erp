<script setup lang="ts">
import {
    Head,
    router,
} from '@inertiajs/vue3';
import {
    computed,
    reactive,
} from 'vue';
import type { ComputedRef } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    InventoryBranchOption,
    InventoryFilters,
    InventoryPagination,
    InventorySort,
    InventoryStockState,
    InventorySummary,
    InventoryWarehouseOption,
} from '@/Types/inventory';

defineOptions({
    layout: ErpLayout,
});

interface InventoryFilterForm {
    search: string;
    branch_id: number | '';
    warehouse_id: number | '';
    stock_state: InventoryStockState;
    sort: InventorySort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

const props = defineProps<{
    inventory: InventoryPagination;
    summary: InventorySummary;
    filters: InventoryFilters;
    branchOptions: InventoryBranchOption[];
    warehouseOptions: InventoryWarehouseOption[];
    canViewCost: boolean;
    currencyCode: string;
}>();

const filterForm = reactive<InventoryFilterForm>({
    search: props.filters.search,
    branch_id: props.filters.branch_id ?? '',
    warehouse_id: props.filters.warehouse_id ?? '',
    stock_state: props.filters.stock_state,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const filteredWarehouseOptions: ComputedRef<InventoryWarehouseOption[]> = computed(
    (): InventoryWarehouseOption[] => {
        if (filterForm.branch_id === '') {
            return props.warehouseOptions;
        }

        return props.warehouseOptions.filter(
            (warehouse: InventoryWarehouseOption): boolean =>
                warehouse.branch_id === filterForm.branch_id,
        );
    },
);

const hasActiveFilters: ComputedRef<boolean> = computed(
    (): boolean => filterForm.search !== ''
        || filterForm.branch_id !== ''
        || filterForm.warehouse_id !== ''
        || filterForm.stock_state !== '',
);

const navigate = (page = 1): void => {
    router.get(
        '/erp/inventory',
        {
            search: filterForm.search,
            branch_id: filterForm.branch_id,
            warehouse_id: filterForm.warehouse_id,
            stock_state: filterForm.stock_state,
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
    filterForm.warehouse_id = '';
    filterForm.stock_state = '';
    filterForm.sort = 'updated_at';
    filterForm.direction = 'desc';
    filterForm.per_page = 25;

    navigate();
};

const onBranchChanged = (): void => {
    if (filterForm.warehouse_id === '') {
        return;
    }

    const selectedWarehouse = props.warehouseOptions.find(
        (warehouse: InventoryWarehouseOption): boolean =>
            warehouse.id === filterForm.warehouse_id,
    );

    if (
        selectedWarehouse === undefined
        || (
            filterForm.branch_id !== ''
            && selectedWarehouse.branch_id !== filterForm.branch_id
        )
    ) {
        filterForm.warehouse_id = '';
    }
};

const sortBy = (
    column: InventorySort,
): void => {
    if (filterForm.sort === column) {
        filterForm.direction = filterForm.direction === 'asc'
            ? 'desc'
            : 'asc';
    } else {
        filterForm.sort = column;
        filterForm.direction = 'desc';
    }

    navigate();
};

const sortIndicator = (
    column: InventorySort,
): string => {
    if (filterForm.sort !== column) {
        return '';
    }

    return filterForm.direction === 'asc'
        ? '↑'
        : '↓';
};

const formatQuantity = (
    value: string,
): string => new Intl.NumberFormat(
    'en-US',
    {
        minimumFractionDigits: 0,
        maximumFractionDigits: 6,
    },
).format(Number(value));

const formatMoney = (
    value: string | null,
): string => {
    if (value === null) {
        return '—';
    }

    try {
        return new Intl.NumberFormat(
            'en-US',
            {
                style: 'currency',
                currency: props.currencyCode,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            },
        ).format(Number(value));
    } catch {
        return `${props.currencyCode} ${Number(value).toFixed(2)}`;
    }
};

const formatDateTime = (
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
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
        },
    ).format(new Date(value));
};
</script>

<template>
    <Head title="Inventory" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
                Inventory Stock Summary
            </h1>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Review current stock, reservations, and available quantities by branch and warehouse.
            </p>
        </div>

        <div
            class="grid gap-4 sm:grid-cols-2"
            :class="canViewCost ? 'xl:grid-cols-5' : 'xl:grid-cols-4'"
        >
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Stock locations
                </p>
                <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                    {{ summary.location_count }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    On hand
                </p>
                <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                    {{ formatQuantity(summary.quantity_on_hand) }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Reserved
                </p>
                <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                    {{ formatQuantity(summary.quantity_reserved) }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Available
                </p>
                <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                    {{ formatQuantity(summary.quantity_available) }}
                </p>
            </div>

            <div
                v-if="canViewCost"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Inventory value
                </p>
                <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                    {{ formatMoney(summary.inventory_value) }}
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <form
                class="grid gap-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:grid-cols-2 sm:p-6 xl:grid-cols-[minmax(220px,1fr)_180px_200px_160px_110px_auto]"
                @submit.prevent="applyFilters"
            >
                <div>
                    <label
                        for="inventory-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search
                    </label>
                    <input
                        id="inventory-search"
                        v-model="filterForm.search"
                        type="search"
                        placeholder="Product, SKU, warehouse"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="inventory-branch"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Branch
                    </label>
                    <select
                        id="inventory-branch"
                        v-model.number="filterForm.branch_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        @change="onBranchChanged"
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
                        for="inventory-warehouse"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Warehouse
                    </label>
                    <select
                        id="inventory-warehouse"
                        v-model.number="filterForm.warehouse_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All warehouses
                        </option>
                        <option
                            v-for="warehouse in filteredWarehouseOptions"
                            :key="warehouse.id"
                            :value="warehouse.id"
                        >
                            {{ warehouse.name }}
                            <template v-if="filterForm.branch_id === '' && warehouse.branch_name">
                                — {{ warehouse.branch_name }}
                            </template>
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="inventory-stock-state"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Stock state
                    </label>
                    <select
                        id="inventory-stock-state"
                        v-model="filterForm.stock_state"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All stock
                        </option>
                        <option value="available">
                            Available
                        </option>
                        <option value="reserved">
                            Reserved
                        </option>
                        <option value="out_of_stock">
                            Out of stock
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="inventory-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>
                    <select
                        id="inventory-per-page"
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
                    <thead class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6">
                                Product
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6">
                                Location
                            </th>
                            <th class="px-5 py-3 text-right sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('quantity_on_hand')"
                                >
                                    On hand {{ sortIndicator('quantity_on_hand') }}
                                </button>
                            </th>
                            <th class="px-5 py-3 text-right sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('quantity_reserved')"
                                >
                                    Reserved {{ sortIndicator('quantity_reserved') }}
                                </button>
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400 sm:px-6">
                                Available
                            </th>
                            <th
                                v-if="canViewCost"
                                class="px-5 py-3 text-right sm:px-6"
                            >
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('average_unit_cost')"
                                >
                                    Avg cost {{ sortIndicator('average_unit_cost') }}
                                </button>
                            </th>
                            <th
                                v-if="canViewCost"
                                class="px-5 py-3 text-right sm:px-6"
                            >
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('inventory_value')"
                                >
                                    Value {{ sortIndicator('inventory_value') }}
                                </button>
                            </th>
                            <th class="px-5 py-3 text-left sm:px-6">
                                <button
                                    type="button"
                                    class="text-xs font-medium tracking-wide text-gray-500 uppercase dark:text-gray-400"
                                    @click="sortBy('updated_at')"
                                >
                                    Updated {{ sortIndicator('updated_at') }}
                                </button>
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr
                            v-for="balance in inventory.data"
                            :key="balance.id"
                            class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]"
                        >
                            <td class="px-5 py-4 sm:px-6">
                                <div class="font-medium text-gray-800 dark:text-white/90">
                                    {{ balance.product.name }}
                                </div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ balance.product.sku }} · {{ balance.unit.symbol ?? balance.unit.code }}
                                </div>
                            </td>

                            <td class="px-5 py-4 sm:px-6">
                                <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ balance.warehouse.name }}
                                </div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ balance.branch.name }} · {{ balance.warehouse.code }}
                                </div>
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-medium text-gray-700 dark:text-gray-300 sm:px-6">
                                {{ formatQuantity(balance.quantity_on_hand) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm text-warning-600 dark:text-warning-400 sm:px-6">
                                {{ formatQuantity(balance.quantity_reserved) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-medium text-success-600 dark:text-success-400 sm:px-6">
                                {{ formatQuantity(balance.quantity_available) }}
                            </td>

                            <td
                                v-if="canViewCost"
                                class="px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-400 sm:px-6"
                            >
                                {{ formatMoney(balance.average_unit_cost) }}
                            </td>

                            <td
                                v-if="canViewCost"
                                class="px-5 py-4 text-right text-sm font-medium text-gray-700 dark:text-gray-300 sm:px-6"
                            >
                                {{ formatMoney(balance.inventory_value) }}
                            </td>

                            <td class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400 sm:px-6">
                                {{ formatDateTime(balance.updated_at) }}
                            </td>
                        </tr>

                        <tr v-if="inventory.data.length === 0">
                            <td
                                :colspan="canViewCost ? 8 : 6"
                                class="px-5 py-14 text-center sm:px-6"
                            >
                                <p class="text-base font-medium text-gray-800 dark:text-white/90">
                                    No inventory balances found
                                </p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Stock balances will appear after inventory-posting transactions are completed.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ inventory.meta.from ?? 0 }}–{{ inventory.meta.to ?? 0 }}
                    of {{ inventory.meta.total }} stock locations
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                        :disabled="inventory.meta.current_page <= 1"
                        @click="navigate(inventory.meta.current_page - 1)"
                    >
                        Previous
                    </button>

                    <span class="px-2 text-sm text-gray-600 dark:text-gray-400">
                        Page {{ inventory.meta.current_page }} of {{ inventory.meta.last_page }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                        :disabled="inventory.meta.current_page >= inventory.meta.last_page"
                        @click="navigate(inventory.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
