<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    InventoryBranchOption,
    InventoryStockCountFilters,
    InventoryStockCountPagination,
    InventoryStockCountSort,
    InventoryStockCountStatus,
    InventoryTransferWarehouseOption,
} from '@/Types/inventory';

defineOptions({ layout: ErpLayout });

const props = defineProps<{
    counts: InventoryStockCountPagination;
    filters: InventoryStockCountFilters;
    branchOptions: InventoryBranchOption[];
    warehouseOptions: InventoryTransferWarehouseOption[];
    canViewCost: boolean;
    currencyCode: string;
}>();

const form = reactive({
    search: props.filters.search,
    branch_id: props.filters.branch_id ?? '' as number | '',
    warehouse_id: props.filters.warehouse_id ?? '' as number | '',
    status: props.filters.status as InventoryStockCountStatus,
    sort: props.filters.sort as InventoryStockCountSort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const filteredWarehouses = computed(() => {
    if (form.branch_id === '') return props.warehouseOptions;

    return props.warehouseOptions.filter(
        (warehouse) => warehouse.branch_id === form.branch_id,
    );
});

const query = (): Record<string, string | number> => {
    const params: Record<string, string | number> = {
        sort: form.sort,
        direction: form.direction,
        per_page: form.per_page,
    };

    if (form.search.trim() !== '') params.search = form.search.trim();
    if (form.branch_id !== '') params.branch_id = form.branch_id;
    if (form.warehouse_id !== '') params.warehouse_id = form.warehouse_id;
    if (form.status !== '') params.status = form.status;

    return params;
};

const navigate = (page = 1): void => {
    router.get(
        route('inventory.counts.index'),
        { ...query(), page },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const resetFilters = (): void => {
    form.search = '';
    form.branch_id = '';
    form.warehouse_id = '';
    form.status = '';
    form.sort = 'count_date';
    form.direction = 'desc';
    form.per_page = 25;
    navigate();
};

const sortBy = (column: InventoryStockCountSort): void => {
    if (form.sort === column) {
        form.direction = form.direction === 'asc' ? 'desc' : 'asc';
    } else {
        form.sort = column;
        form.direction = column === 'count_number' ? 'asc' : 'desc';
    }

    navigate();
};

const sortIndicator = (column: InventoryStockCountSort): string => {
    if (form.sort !== column) return '';
    return form.direction === 'asc' ? '↑' : '↓';
};

const statusClass = (status: string): string => {
    if (status === 'posted') {
        return 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400';
    }

    if (status === 'cancelled') {
        return 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400';
    }

    return 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400';
};

const formatQuantity = (value: string): string => new Intl.NumberFormat(
    'en-US',
    { maximumFractionDigits: 6 },
).format(Number(value));

const formatMoney = (value: string | null): string => {
    if (value === null) return '—';

    try {
        return new Intl.NumberFormat(
            'en-US',
            { style: 'currency', currency: props.currencyCode },
        ).format(Number(value));
    } catch {
        return `${props.currencyCode} ${Number(value).toFixed(2)}`;
    }
};
</script>

<template>
    <Head title="Stock Counts" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">Stock Counts</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Compare physical quantities with system stock and post controlled inventory variances.
                </p>
            </div>

            <Link
                :href="route('inventory.counts.create')"
                class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600"
            >
                New Stock Count
            </Link>
        </div>

        <div class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] lg:grid-cols-6">
            <input
                v-model="form.search"
                type="search"
                placeholder="Count number or warehouse"
                class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90 lg:col-span-2"
                @keyup.enter="navigate()"
            >

            <select v-model="form.branch_id" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90" @change="form.warehouse_id = ''; navigate()">
                <option value="">All branches</option>
                <option v-for="branch in branchOptions" :key="branch.id" :value="branch.id">
                    {{ branch.name }} ({{ branch.code }})
                </option>
            </select>

            <select v-model="form.warehouse_id" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90" @change="navigate()">
                <option value="">All warehouses</option>
                <option v-for="warehouse in filteredWarehouses" :key="warehouse.id" :value="warehouse.id">
                    {{ warehouse.name }} ({{ warehouse.code }})
                </option>
            </select>

            <select v-model="form.status" class="h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90" @change="navigate()">
                <option value="">All statuses</option>
                <option value="draft">Draft</option>
                <option value="posted">Posted</option>
                <option value="cancelled">Cancelled</option>
            </select>

            <div class="flex gap-2">
                <button type="button" class="h-10 flex-1 rounded-lg bg-brand-500 px-3 text-sm font-medium text-white" @click="navigate()">Apply</button>
                <button type="button" class="h-10 flex-1 rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300" @click="resetFilters">Reset</button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('count_number')">Count {{ sortIndicator('count_number') }}</button>
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('count_date')">Date {{ sortIndicator('count_date') }}</button>
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Warehouse</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('status')">Status {{ sortIndicator('status') }}</button>
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Lines</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('variance_line_count')">Variance Lines {{ sortIndicator('variance_line_count') }}</button>
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Gain Qty</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Loss Qty</th>
                            <th v-if="canViewCost" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Value Variance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="count in counts.data" :key="count.id" class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]">
                            <td class="px-5 py-4 text-sm font-medium text-brand-600 dark:text-brand-400">
                                <Link :href="route('inventory.counts.show', count.id)">
                                    {{ count.count_number ?? `Draft #${count.id}` }}
                                </Link>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">{{ count.count_date }}</td>
                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                <div class="font-medium">{{ count.warehouse.name }}</div>
                                <div class="text-xs text-gray-500">{{ count.branch.name }} · {{ count.warehouse.code }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(count.status)">
                                    {{ count.status }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-400">{{ count.total_lines }}</td>
                            <td class="px-5 py-4 text-right text-sm font-medium text-gray-700 dark:text-gray-300">{{ count.variance_line_count }}</td>
                            <td class="px-5 py-4 text-right text-sm text-success-600 dark:text-success-400">{{ formatQuantity(count.total_positive_variance) }}</td>
                            <td class="px-5 py-4 text-right text-sm text-error-600 dark:text-error-400">{{ formatQuantity(count.total_negative_variance) }}</td>
                            <td v-if="canViewCost" class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                {{ formatMoney(String(Number(count.total_value_gain ?? 0) - Number(count.total_value_loss ?? 0))) }}
                            </td>
                        </tr>
                        <tr v-if="counts.data.length === 0">
                            <td :colspan="canViewCost ? 9 : 8" class="px-5 py-12 text-center text-sm text-gray-500">
                                No stock counts found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 text-sm text-gray-500 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <span>
                    Showing {{ counts.meta.from ?? 0 }}–{{ counts.meta.to ?? 0 }} of {{ counts.meta.total }}
                </span>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 disabled:opacity-40 dark:border-gray-700"
                        :disabled="counts.meta.current_page <= 1"
                        @click="navigate(counts.meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <span>Page {{ counts.meta.current_page }} of {{ counts.meta.last_page }}</span>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 disabled:opacity-40 dark:border-gray-700"
                        :disabled="counts.meta.current_page >= counts.meta.last_page"
                        @click="navigate(counts.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
