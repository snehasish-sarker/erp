<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import {
    reactive,
} from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    InventoryBranchOption,
    InventoryTransferFilters,
    InventoryTransferPagination,
    InventoryTransferSort,
    InventoryTransferStatus,
} from '@/Types/inventory';

defineOptions({
    layout: ErpLayout,
});

interface FilterForm {
    search: string;
    branch_id: number | '';
    status: InventoryTransferStatus;
    sort: InventoryTransferSort;
    direction: 'asc' | 'desc';
    per_page: 10 | 25 | 50 | 100;
}

const props = defineProps<{
    transfers: InventoryTransferPagination;
    filters: InventoryTransferFilters;
    branchOptions: InventoryBranchOption[];
}>();

const form = reactive<FilterForm>({
    search: props.filters.search,
    branch_id: props.filters.branch_id ?? '',
    status: props.filters.status,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const query = (): Record<string, string | number> => {
    const params: Record<string, string | number> = {
        sort: form.sort,
        direction: form.direction,
        per_page: form.per_page,
    };

    if (form.search.trim() !== '') {
        params.search = form.search.trim();
    }

    if (form.branch_id !== '') {
        params.branch_id = form.branch_id;
    }

    if (form.status !== '') {
        params.status = form.status;
    }

    return params;
};

const navigate = (page = 1): void => {
    router.get(
        route('inventory.transfers.index'),
        {
            ...query(),
            page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const applyFilters = (): void => navigate();

const resetFilters = (): void => {
    form.search = '';
    form.branch_id = '';
    form.status = '';
    form.sort = 'transfer_date';
    form.direction = 'desc';
    form.per_page = 25;
    navigate();
};

const sortBy = (column: InventoryTransferSort): void => {
    if (form.sort === column) {
        form.direction = form.direction === 'asc'
            ? 'desc'
            : 'asc';
    } else {
        form.sort = column;
        form.direction = column === 'transfer_number'
            ? 'asc'
            : 'desc';
    }

    navigate();
};

const sortIndicator = (column: InventoryTransferSort): string => {
    if (form.sort !== column) {
        return '';
    }

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

const goToPage = (page: number): void => {
    if (
        page < 1
        || page > props.transfers.meta.last_page
        || page === props.transfers.meta.current_page
    ) {
        return;
    }

    navigate(page);
};
</script>

<template>
    <Head title="Inventory Transfers" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
                    Inventory Transfers
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Move stock between accessible warehouses with atomic inventory valuation.
                </p>
            </div>

            <Link
                :href="route('inventory.transfers.create')"
                class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
            >
                New Transfer
            </Link>
        </div>

        <form
            class="grid gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] md:grid-cols-2 xl:grid-cols-5"
            @submit.prevent="applyFilters"
        >
            <input
                v-model="form.search"
                type="search"
                placeholder="Transfer no. or warehouse"
                class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90"
            >

            <select
                v-model="form.branch_id"
                class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90"
            >
                <option value="">All branches</option>
                <option
                    v-for="branch in branchOptions"
                    :key="branch.id"
                    :value="branch.id"
                >
                    {{ branch.name }} ({{ branch.code }})
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
                <button
                    type="submit"
                    class="h-11 flex-1 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600"
                >
                    Apply
                </button>
                <button
                    type="button"
                    class="h-11 rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                    @click="resetFilters"
                >
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
                                <button type="button" @click="sortBy('transfer_number')">
                                    Transfer {{ sortIndicator('transfer_number') }}
                                </button>
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('transfer_date')">
                                    Date {{ sortIndicator('transfer_date') }}
                                </button>
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                From
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                To
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Lines
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('status')">
                                    Status {{ sortIndicator('status') }}
                                </button>
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Action
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr
                            v-for="transfer in transfers.data"
                            :key="transfer.id"
                        >
                            <td class="px-5 py-4 text-sm font-medium text-gray-800 dark:text-white/90">
                                {{ transfer.transfer_number ?? `Draft #${transfer.id}` }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                {{ transfer.transfer_date }}
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                <p class="font-medium text-gray-800 dark:text-white/90">
                                    {{ transfer.source_warehouse.name }}
                                </p>
                                <p class="text-xs">
                                    {{ transfer.source_branch.name }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                <p class="font-medium text-gray-800 dark:text-white/90">
                                    {{ transfer.destination_warehouse.name }}
                                </p>
                                <p class="text-xs">
                                    {{ transfer.destination_branch.name }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                {{ transfer.line_count }}
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize"
                                    :class="statusClass(transfer.status)"
                                >
                                    {{ transfer.status }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <Link
                                    :href="route('inventory.transfers.show', transfer.id)"
                                    class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                >
                                    View
                                </Link>
                            </td>
                        </tr>

                        <tr v-if="transfers.data.length === 0">
                            <td
                                colspan="7"
                                class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400"
                            >
                                No inventory transfers found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ transfers.meta.from ?? 0 }}–{{ transfers.meta.to ?? 0 }} of {{ transfers.meta.total }}
                </p>

                <div class="flex gap-2">
                    <button
                        type="button"
                        :disabled="transfers.meta.current_page <= 1"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        @click="goToPage(transfers.meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        type="button"
                        :disabled="transfers.meta.current_page >= transfers.meta.last_page"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        @click="goToPage(transfers.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
