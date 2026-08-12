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
import type { ComputedRef } from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    InventoryBranchOption,
    InventoryWarehouseOption,
    StockLedgerFilters,
    StockLedgerMovementOption,
    StockLedgerMovementType,
    StockLedgerPagination,
    StockLedgerSort,
    StockLedgerSummary,
} from '@/Types/inventory';

defineOptions({
    layout: ErpLayout,
});

interface StockLedgerFilterForm {
    search: string;
    branch_id: number | '';
    warehouse_id: number | '';
    movement_type: StockLedgerMovementType;
    date_from: string;
    date_to: string;
    sort: StockLedgerSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

const props = defineProps<{
    ledger: StockLedgerPagination;
    summary: StockLedgerSummary;
    filters: StockLedgerFilters;
    movementOptions: StockLedgerMovementOption[];
    branchOptions: InventoryBranchOption[];
    warehouseOptions: InventoryWarehouseOption[];
    canViewCost: boolean;
    currencyCode: string;
}>();

const filterForm = reactive<StockLedgerFilterForm>({
    search: props.filters.search,
    branch_id: props.filters.branch_id ?? '',
    warehouse_id: props.filters.warehouse_id ?? '',
    movement_type: props.filters.movement_type,
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
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
        || filterForm.movement_type !== ''
        || filterForm.date_from !== ''
        || filterForm.date_to !== '',
);

const queryParameters = (): Record<
    string,
    string | number
> => {
    const query: Record<string, string | number> = {
        sort: filterForm.sort,
        direction: filterForm.direction,
        per_page: filterForm.per_page,
    };

    if (filterForm.search.trim() !== '') {
        query.search = filterForm.search.trim();
    }

    if (filterForm.branch_id !== '') {
        query.branch_id = filterForm.branch_id;
    }

    if (filterForm.warehouse_id !== '') {
        query.warehouse_id = filterForm.warehouse_id;
    }

    if (filterForm.movement_type !== '') {
        query.movement_type = filterForm.movement_type;
    }

    if (filterForm.date_from !== '') {
        query.date_from = filterForm.date_from;
    }

    if (filterForm.date_to !== '') {
        query.date_to = filterForm.date_to;
    }

    return query;
};

const navigate = (
    page = 1,
): void => {
    router.get(
        route('inventory.ledger.index'),
        {
            ...queryParameters(),
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
    filterForm.movement_type = '';
    filterForm.date_from = '';
    filterForm.date_to = '';
    filterForm.sort = 'occurred_at';
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
    column: StockLedgerSort,
): void => {
    if (filterForm.sort === column) {
        filterForm.direction = filterForm.direction === 'asc'
            ? 'desc'
            : 'asc';
    } else {
        filterForm.sort = column;
        filterForm.direction = column === 'document_number'
            ? 'asc'
            : 'desc';
    }

    navigate();
};

const sortIndicator = (
    column: StockLedgerSort,
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

const movementBadgeClass = (
    movementType: StockLedgerMovementType,
    quantityIn: string,
): string => {
    if (movementType.endsWith('_reversal')) {
        return 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400';
    }

    if (Number(quantityIn) > 0) {
        return 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400';
    }

    return 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400';
};

const goToPage = (
    page: number,
): void => {
    if (
        page < 1
        || page > props.ledger.meta.last_page
        || page === props.ledger.meta.current_page
    ) {
        return;
    }

    navigate(page);
};
</script>

<template>
    <Head title="Stock Ledger" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
                    Stock Ledger
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Trace every posted inventory movement and its running stock balance.
                </p>
            </div>

            <Link
                :href="route('inventory.index')"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
            >
                Stock Summary
            </Link>
        </div>

        <div
            class="grid gap-4 sm:grid-cols-2"
            :class="canViewCost ? 'xl:grid-cols-5' : 'xl:grid-cols-4'"
        >
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Ledger entries
                </p>
                <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                    {{ summary.entry_count }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Quantity in
                </p>
                <p class="mt-2 text-2xl font-semibold text-success-600 dark:text-success-400">
                    {{ formatQuantity(summary.quantity_in) }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Quantity out
                </p>
                <p class="mt-2 text-2xl font-semibold text-error-600 dark:text-error-400">
                    {{ formatQuantity(summary.quantity_out) }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Net movement
                </p>
                <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                    {{ formatQuantity(summary.net_movement) }}
                </p>
            </div>

            <div
                v-if="canViewCost"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Movement value
                </p>
                <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">
                    {{ formatMoney(summary.movement_value) }}
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <form
                class="grid gap-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:grid-cols-2 sm:p-6 xl:grid-cols-4"
                @submit.prevent="applyFilters"
            >
                <div>
                    <label
                        for="ledger-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search
                    </label>
                    <input
                        id="ledger-search"
                        v-model="filterForm.search"
                        type="search"
                        placeholder="Product, SKU, document, posting key"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="ledger-branch"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Branch
                    </label>
                    <select
                        id="ledger-branch"
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
                            {{ branch.name }} ({{ branch.code }})
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="ledger-warehouse"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Warehouse
                    </label>
                    <select
                        id="ledger-warehouse"
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
                        for="ledger-movement-type"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Movement type
                    </label>
                    <select
                        id="ledger-movement-type"
                        v-model="filterForm.movement_type"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All movements
                        </option>
                        <option
                            v-for="movement in movementOptions"
                            :key="movement.value"
                            :value="movement.value"
                        >
                            {{ movement.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="ledger-date-from"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Date from
                    </label>
                    <input
                        id="ledger-date-from"
                        v-model="filterForm.date_from"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="ledger-date-to"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Date to
                    </label>
                    <input
                        id="ledger-date-to"
                        v-model="filterForm.date_to"
                        type="date"
                        :min="filterForm.date_from || undefined"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="ledger-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>
                    <select
                        id="ledger-per-page"
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
                    <thead class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="sortBy('occurred_at')"
                                >
                                    Date
                                    <span>{{ sortIndicator('occurred_at') }}</span>
                                </button>
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="sortBy('document_number')"
                                >
                                    Document
                                    <span>{{ sortIndicator('document_number') }}</span>
                                </button>
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Product
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Location
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Movement
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="sortBy('quantity_in')"
                                >
                                    Qty In
                                    <span>{{ sortIndicator('quantity_in') }}</span>
                                </button>
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="sortBy('quantity_out')"
                                >
                                    Qty Out
                                    <span>{{ sortIndicator('quantity_out') }}</span>
                                </button>
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="sortBy('balance_quantity')"
                                >
                                    Balance
                                    <span>{{ sortIndicator('balance_quantity') }}</span>
                                </button>
                            </th>
                            <th
                                v-if="canViewCost"
                                class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="sortBy('unit_cost')"
                                >
                                    Unit Cost
                                    <span>{{ sortIndicator('unit_cost') }}</span>
                                </button>
                            </th>
                            <th
                                v-if="canViewCost"
                                class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="sortBy('total_cost')"
                                >
                                    Movement Value
                                    <span>{{ sortIndicator('total_cost') }}</span>
                                </button>
                            </th>
                            <th
                                v-if="canViewCost"
                                class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1"
                                    @click="sortBy('balance_value')"
                                >
                                    Balance Value
                                    <span>{{ sortIndicator('balance_value') }}</span>
                                </button>
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Posted By
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr
                            v-for="entry in ledger.data"
                            :key="entry.id"
                            class="transition hover:bg-gray-50/80 dark:hover:bg-white/[0.02]"
                        >
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                {{ formatDateTime(entry.occurred_at) }}
                            </td>

                            <td class="px-5 py-4">
                                <p class="whitespace-nowrap text-sm font-medium text-gray-800 dark:text-white/90">
                                    {{ entry.document_number ?? '—' }}
                                </p>
                                <p class="mt-1 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                    {{ entry.source_type }} #{{ entry.source_id }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p class="min-w-44 text-sm font-medium text-gray-800 dark:text-white/90">
                                    {{ entry.product.name }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ entry.product.sku }} · {{ entry.unit.symbol ?? entry.unit.code }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p class="min-w-40 text-sm text-gray-700 dark:text-gray-300">
                                    {{ entry.warehouse.name }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ entry.branch.name }} · {{ entry.warehouse.code }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="movementBadgeClass(entry.movement_type, entry.quantity_in)"
                                >
                                    {{ entry.movement_label }}
                                </span>
                                <p
                                    v-if="entry.reversal_of_id !== null"
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Reverses ledger #{{ entry.reversal_of_id }}
                                </p>
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-medium text-success-600 dark:text-success-400">
                                {{ Number(entry.quantity_in) === 0 ? '—' : formatQuantity(entry.quantity_in) }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-medium text-error-600 dark:text-error-400">
                                {{ Number(entry.quantity_out) === 0 ? '—' : formatQuantity(entry.quantity_out) }}
                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold text-gray-800 dark:text-white/90">
                                {{ formatQuantity(entry.balance_quantity) }}
                            </td>

                            <td
                                v-if="canViewCost"
                                class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-400"
                            >
                                {{ formatMoney(entry.unit_cost) }}
                            </td>

                            <td
                                v-if="canViewCost"
                                class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-400"
                            >
                                {{ formatMoney(entry.total_cost) }}
                            </td>

                            <td
                                v-if="canViewCost"
                                class="whitespace-nowrap px-5 py-4 text-right text-sm font-medium text-gray-800 dark:text-white/90"
                            >
                                {{ formatMoney(entry.balance_value) }}
                            </td>

                            <td class="px-5 py-4">
                                <p class="whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                    {{ entry.created_by.name || '—' }}
                                </p>
                                <p class="mt-1 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                    {{ entry.created_by.email }}
                                </p>
                            </td>
                        </tr>

                        <tr v-if="ledger.data.length === 0">
                            <td
                                :colspan="canViewCost ? 12 : 9"
                                class="px-5 py-14 text-center"
                            >
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    No stock ledger entries found.
                                </p>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Adjust the filters or post an inventory movement first.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="ledger.meta.total > 0"
                class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Showing
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        {{ ledger.meta.from }}–{{ ledger.meta.to }}
                    </span>
                    of
                    <span class="font-medium text-gray-700 dark:text-gray-300">
                        {{ ledger.meta.total }}
                    </span>
                    entries
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="ledger.meta.current_page <= 1"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                        @click="goToPage(ledger.meta.current_page - 1)"
                    >
                        Previous
                    </button>

                    <span class="px-2 text-sm text-gray-500 dark:text-gray-400">
                        Page {{ ledger.meta.current_page }} of {{ ledger.meta.last_page }}
                    </span>

                    <button
                        type="button"
                        :disabled="ledger.meta.current_page >= ledger.meta.last_page"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]"
                        @click="goToPage(ledger.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
