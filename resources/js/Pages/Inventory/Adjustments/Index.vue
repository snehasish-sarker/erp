<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    InventoryAdjustmentFilters,
    InventoryAdjustmentPagination,
    InventoryAdjustmentSort,
    InventoryAdjustmentStatus,
    InventoryBranchOption,
    InventoryTransferWarehouseOption,
} from '@/Types/inventory';

defineOptions({ layout: ErpLayout });

const props = defineProps<{
    adjustments: InventoryAdjustmentPagination;
    filters: InventoryAdjustmentFilters;
    branchOptions: InventoryBranchOption[];
    warehouseOptions: InventoryTransferWarehouseOption[];
    canViewCost: boolean;
    currencyCode: string;
}>();

const form = reactive({
    search: props.filters.search,
    branch_id: props.filters.branch_id ?? '' as number | '',
    warehouse_id: props.filters.warehouse_id ?? '' as number | '',
    status: props.filters.status as InventoryAdjustmentStatus,
    sort: props.filters.sort as InventoryAdjustmentSort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const filteredWarehouses = computed(() => {
    if (form.branch_id === '') {
        return props.warehouseOptions;
    }

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
        route('inventory.adjustments.index'),
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
    form.sort = 'adjustment_date';
    form.direction = 'desc';
    form.per_page = 25;
    navigate();
};

const sortBy = (column: InventoryAdjustmentSort): void => {
    if (form.sort === column) {
        form.direction = form.direction === 'asc' ? 'desc' : 'asc';
    } else {
        form.sort = column;
        form.direction = column === 'adjustment_number' ? 'asc' : 'desc';
    }

    navigate();
};

const sortIndicator = (column: InventoryAdjustmentSort): string => {
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
    <Head title="Inventory Adjustments" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
                    Inventory Adjustments
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Correct physical stock variances with an auditable stock-ledger entry.
                </p>
            </div>

            <Link
                :href="route('inventory.adjustments.create')"
                class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600"
            >
                New Adjustment
            </Link>
        </div>

        <form
            class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] md:grid-cols-2 xl:grid-cols-6"
            @submit.prevent="navigate()"
        >
            <input
                v-model="form.search"
                type="search"
                placeholder="Number, reason or warehouse"
                class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90"
            >

            <select
                v-model="form.branch_id"
                class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90"
                @change="form.warehouse_id = ''"
            >
                <option value="">All branches</option>
                <option v-for="branch in branchOptions" :key="branch.id" :value="branch.id">
                    {{ branch.name }} ({{ branch.code }})
                </option>
            </select>

            <select
                v-model="form.warehouse_id"
                class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90"
            >
                <option value="">All warehouses</option>
                <option v-for="warehouse in filteredWarehouses" :key="warehouse.id" :value="warehouse.id">
                    {{ warehouse.name }} ({{ warehouse.code }})
                </option>
            </select>

            <select
                v-model="form.status"
                class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90"
            >
                <option value="">All statuses</option>
                <option value="draft">Draft</option>
                <option value="posted">Posted</option>
                <option value="cancelled">Cancelled</option>
            </select>

            <select
                v-model="form.per_page"
                class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90"
            >
                <option :value="10">10 per page</option>
                <option :value="25">25 per page</option>
                <option :value="50">50 per page</option>
                <option :value="100">100 per page</option>
            </select>

            <div class="flex gap-2">
                <button type="submit" class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600">
                    Apply
                </button>
                <button type="button" class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300" @click="resetFilters">
                    Reset
                </button>
            </div>
        </form>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('adjustment_number')">Adjustment {{ sortIndicator('adjustment_number') }}</button>
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('adjustment_date')">Date {{ sortIndicator('adjustment_date') }}</button>
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Warehouse</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Reason</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Qty In</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Qty Out</th>
                            <th v-if="canViewCost" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Value In</th>
                            <th v-if="canViewCost" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Value Out</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('status')">Status {{ sortIndicator('status') }}</button>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="adjustment in adjustments.data" :key="adjustment.id">
                            <td class="px-5 py-4">
                                <Link :href="route('inventory.adjustments.show', adjustment.id)" class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400">
                                    {{ adjustment.adjustment_number ?? `Draft #${adjustment.id}` }}
                                </Link>
                                <p class="mt-1 text-xs text-gray-500">{{ adjustment.line_count }} line(s)</p>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">{{ adjustment.adjustment_date }}</td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">{{ adjustment.warehouse.name }}</p>
                                <p class="text-xs text-gray-500">{{ adjustment.branch.name }}</p>
                            </td>
                            <td class="max-w-xs px-5 py-4 text-sm text-gray-600 dark:text-gray-400">{{ adjustment.reason }}</td>
                            <td class="px-5 py-4 text-right text-sm font-medium text-success-600">{{ formatQuantity(adjustment.total_quantity_in) }}</td>
                            <td class="px-5 py-4 text-right text-sm font-medium text-error-600">{{ formatQuantity(adjustment.total_quantity_out) }}</td>
                            <td v-if="canViewCost" class="px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatMoney(adjustment.total_value_in) }}</td>
                            <td v-if="canViewCost" class="px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatMoney(adjustment.total_value_out) }}</td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(adjustment.status)">
                                    {{ adjustment.status }}
                                </span>
                            </td>
                        </tr>

                        <tr v-if="adjustments.data.length === 0">
                            <td :colspan="canViewCost ? 9 : 7" class="px-5 py-10 text-center text-sm text-gray-500">
                                No inventory adjustments found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 text-sm text-gray-500 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <span>
                    Showing {{ adjustments.meta.from ?? 0 }}–{{ adjustments.meta.to ?? 0 }} of {{ adjustments.meta.total }}
                </span>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 disabled:opacity-50 dark:border-gray-700"
                        :disabled="adjustments.meta.current_page <= 1"
                        @click="navigate(adjustments.meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 disabled:opacity-50 dark:border-gray-700"
                        :disabled="adjustments.meta.current_page >= adjustments.meta.last_page"
                        @click="navigate(adjustments.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
