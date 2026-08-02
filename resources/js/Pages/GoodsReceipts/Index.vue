<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import {
    computed,
    onBeforeUnmount,
    reactive,
    watch,
} from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    GoodsReceiptFilters,
    GoodsReceiptIndexProps,
    GoodsReceiptSort,
    GoodsReceiptStatus,
    GoodsReceiptSummary,
} from '@/Types/goods-receipt';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<GoodsReceiptIndexProps>();

const filters = reactive<GoodsReceiptFilters>({
    search:
        props.filters.search ?? '',

    branch_id:
        props.filters.branch_id ?? null,

    warehouse_id:
        props.filters.warehouse_id ?? null,

    supplier_id:
        props.filters.supplier_id ?? null,

    purchase_order_id:
        props.filters.purchase_order_id ?? null,

    status:
        props.filters.status ?? '',

    inspection_status:
        props.filters.inspection_status ?? '',

    receipt_date_from:
        props.filters.receipt_date_from ?? '',

    receipt_date_to:
        props.filters.receipt_date_to ?? '',

    sort:
        props.filters.sort ?? 'created_at',

    direction:
        props.filters.direction ?? 'desc',

    per_page:
        props.filters.per_page ?? 15,
});

const availableWarehouseOptions = computed(() => {
    if (filters.branch_id === null) {
        return props.warehouseOptions;
    }

    return props.warehouseOptions.filter(
        (warehouse) =>
            warehouse.branch_id
            === filters.branch_id,
    );
});

const hasActiveFilters = computed(
    (): boolean =>
        filters.search !== ''
        || filters.branch_id !== null
        || filters.warehouse_id !== null
        || filters.supplier_id !== null
        || filters.purchase_order_id !== null
        || filters.status !== ''
        || filters.inspection_status !== ''
        || filters.receipt_date_from !== ''
        || filters.receipt_date_to !== '',
);

const queryParameters = (): Record<
    string,
    string | number
> => {
    const query: Record<
        string,
        string | number
    > = {
        sort: filters.sort,
        direction: filters.direction,
        per_page: filters.per_page,
    };

    if (filters.search.trim() !== '') {
        query.search =
            filters.search.trim();
    }

    if (filters.branch_id !== null) {
        query.branch_id =
            filters.branch_id;
    }

    if (filters.warehouse_id !== null) {
        query.warehouse_id =
            filters.warehouse_id;
    }

    if (filters.supplier_id !== null) {
        query.supplier_id =
            filters.supplier_id;
    }

    if (
        filters.purchase_order_id !== null
    ) {
        query.purchase_order_id =
            filters.purchase_order_id;
    }

    if (filters.status !== '') {
        query.status = filters.status;
    }

    if (
        filters.inspection_status !== ''
    ) {
        query.inspection_status =
            filters.inspection_status;
    }

    if (
        filters.receipt_date_from !== ''
    ) {
        query.receipt_date_from =
            filters.receipt_date_from;
    }

    if (
        filters.receipt_date_to !== ''
    ) {
        query.receipt_date_to =
            filters.receipt_date_to;
    }

    return query;
};

const applyFilters = (): void => {
    router.get(
        route('goods-receipts.index'),
        queryParameters(),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

let searchTimer:
    | ReturnType<typeof setTimeout>
    | null = null;

watch(
    () => filters.search,
    () => {
        if (searchTimer !== null) {
            clearTimeout(searchTimer);
        }

        searchTimer = setTimeout(
            applyFilters,
            400,
        );
    },
);

watch(
    () => filters.branch_id,
    () => {
        if (
            filters.warehouse_id !== null
            && !availableWarehouseOptions
                .value
                .some(
                    (warehouse) =>
                        warehouse.value
                        === filters.warehouse_id,
                )
        ) {
            filters.warehouse_id = null;
        }

        applyFilters();
    },
);

onBeforeUnmount(() => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
});

const resetFilters = (): void => {
    filters.search = '';
    filters.branch_id = null;
    filters.warehouse_id = null;
    filters.supplier_id = null;
    filters.purchase_order_id = null;
    filters.status = '';
    filters.inspection_status = '';
    filters.receipt_date_from = '';
    filters.receipt_date_to = '';
    filters.sort = 'created_at';
    filters.direction = 'desc';
    filters.per_page = 15;

    applyFilters();
};

const toggleSort = (
    sort: GoodsReceiptSort,
): void => {
    if (filters.sort === sort) {
        filters.direction =
            filters.direction === 'asc'
                ? 'desc'
                : 'asc';
    } else {
        filters.sort = sort;
        filters.direction = 'asc';
    }

    applyFilters();
};

const sortIndicator = (
    sort: GoodsReceiptSort,
): string => {
    if (filters.sort !== sort) {
        return '';
    }

    return filters.direction === 'asc'
        ? '↑'
        : '↓';
};

const goToPage = (
    page: number,
): void => {
    const meta =
        props.goodsReceipts.meta;

    if (
        page < 1
        || page > meta.last_page
        || page === meta.current_page
    ) {
        return;
    }

    router.get(
        route('goods-receipts.index'),
        {
            ...queryParameters(),
            page,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const statusClasses: Record<
    GoodsReceiptStatus,
    string
> = {
    draft:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    posted:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    reversed:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const inspectionClasses: Record<
    string,
    string
> = {
    not_required:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    pending:
        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',

    passed:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    partial:
        'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',

    failed:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const displayNumber = (
    goodsReceipt: GoodsReceiptSummary,
): string => {
    return goodsReceipt.receipt_number
        ?? `Draft #${goodsReceipt.id}`;
};

const formatDate = (
    value: string | null,
): string => {
    if (value === null || value === '') {
        return '—';
    }

    const parts = value.split('-');

    if (parts.length !== 3) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        },
    ).format(
        new Date(
            Number(parts[0]),
            Number(parts[1]) - 1,
            Number(parts[2]),
        ),
    );
};

const formatNumber = (
    value: string,
): string => {
    const parsed = Number.parseFloat(value);

    if (!Number.isFinite(parsed)) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        },
    ).format(parsed);
};
</script>

<template>
    <Head title="Goods Receipts" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Goods Receipts
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Record Supplier deliveries and post
                    accepted stock into inventory.
                </p>
            </div>

            <Link
                v-if="props.can.create"
                :href="
                    route(
                        'goods-receipts.create',
                    )
                "
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
            >
                Create Goods Receipt
            </Link>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                <div class="xl:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Search
                    </label>

                    <input
                        v-model="filters.search"
                        type="search"
                        maxlength="160"
                        placeholder="Receipt, PO, Supplier, delivery note, or notes"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Branch
                    </label>

                    <select
                        v-model.number="filters.branch_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option :value="null">
                            All branches
                        </option>

                        <option
                            v-for="branch in props.branchOptions"
                            :key="branch.value"
                            :value="branch.value"
                        >
                            {{ branch.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Warehouse
                    </label>

                    <select
                        v-model.number="filters.warehouse_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="null">
                            All warehouses
                        </option>

                        <option
                            v-for="warehouse in availableWarehouseOptions"
                            :key="warehouse.value"
                            :value="warehouse.value"
                        >
                            {{ warehouse.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Supplier
                    </label>

                    <select
                        v-model.number="filters.supplier_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="null">
                            All Suppliers
                        </option>

                        <option
                            v-for="supplier in props.supplierOptions"
                            :key="supplier.value"
                            :value="supplier.value"
                        >
                            {{ supplier.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Purchase Order
                    </label>

                    <select
                        v-model.number="filters.purchase_order_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="null">
                            All Purchase Orders
                        </option>

                        <option
                            v-for="purchaseOrder in props.purchaseOrderFilterOptions"
                            :key="purchaseOrder.value"
                            :value="purchaseOrder.value"
                        >
                            {{ purchaseOrder.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Receipt Status
                    </label>

                    <select
                        v-model="filters.status"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="status in props.statusOptions"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Inspection Status
                    </label>

                    <select
                        v-model="filters.inspection_status"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option value="">
                            All inspections
                        </option>

                        <option
                            v-for="status in props.inspectionStatusOptions"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Receipt Date From
                    </label>

                    <input
                        v-model="filters.receipt_date_from"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Receipt Date To
                    </label>

                    <input
                        v-model="filters.receipt_date_to"
                        type="date"
                        :min="
                            filters.receipt_date_from
                            || undefined
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Records Per Page
                    </label>

                    <select
                        v-model.number="filters.per_page"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300"
                        @click="resetFilters"
                    >
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="overflow-x-auto">
                <table class="min-w-[1250px] w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-400"
                        >
                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    @click="
                                        toggleSort(
                                            'receipt_number',
                                        )
                                    "
                                >
                                    Receipt
                                    {{
                                        sortIndicator(
                                            'receipt_number',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    @click="
                                        toggleSort(
                                            'receipt_date',
                                        )
                                    "
                                >
                                    Date
                                    {{
                                        sortIndicator(
                                            'receipt_date',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                Purchase Order
                            </th>

                            <th class="px-5 py-3.5">
                                Supplier
                            </th>

                            <th class="px-5 py-3.5">
                                Branch / Warehouse
                            </th>

                            <th class="px-5 py-3.5 text-right">
                                Accepted / Rejected
                            </th>

                            <th class="px-5 py-3.5 text-right">
                                Inventory Value
                            </th>

                            <th class="px-5 py-3.5">
                                Inspection
                            </th>

                            <th class="px-5 py-3.5">
                                Status
                            </th>

                            <th class="px-5 py-3.5 text-right">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="goodsReceipt in props.goodsReceipts.data"
                            :key="goodsReceipt.id"
                            class="border-b border-gray-100 last:border-b-0 dark:border-gray-800"
                        >
                            <td class="px-5 py-4">
                                <Link
                                    v-if="goodsReceipt.can.view"
                                    :href="
                                        route(
                                            'goods-receipts.show',
                                            goodsReceipt.id,
                                        )
                                    "
                                    class="font-medium text-gray-900 hover:text-brand-500 dark:text-white"
                                >
                                    {{ displayNumber(goodsReceipt) }}
                                </Link>

                                <p
                                    v-if="goodsReceipt.supplier_delivery_note"
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    DN:
                                    {{
                                        goodsReceipt.supplier_delivery_note
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{
                                    formatDate(
                                        goodsReceipt.receipt_date,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <Link
                                    :href="
                                        route(
                                            'purchase-orders.show',
                                            goodsReceipt.purchase_order_id,
                                        )
                                    "
                                    class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                >
                                    {{
                                        goodsReceipt.purchase_order_number
                                        ?? `PO #${goodsReceipt.purchase_order_id}`
                                    }}
                                </Link>
                            </td>

                            <td class="px-5 py-4">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">
                                    {{ goodsReceipt.supplier_name }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    {{ goodsReceipt.supplier_code }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p class="text-sm text-gray-900 dark:text-white">
                                    {{ goodsReceipt.branch.name }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    {{
                                        goodsReceipt.warehouse
                                            ?.name
                                        ?? 'No warehouse'
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-right">
                                <p class="text-sm font-medium text-emerald-600">
                                    {{
                                        formatNumber(
                                            goodsReceipt.total_accepted_quantity,
                                        )
                                    }}
                                </p>

                                <p class="mt-1 text-xs text-red-500">
                                    Rejected:
                                    {{
                                        formatNumber(
                                            goodsReceipt.total_rejected_quantity,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                {{
                                    formatNumber(
                                        goodsReceipt.total_inventory_value,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        inspectionClasses[
                                            goodsReceipt.inspection_status
                                        ]
                                    "
                                >
                                    {{
                                        goodsReceipt.inspection_status_label
                                    }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        statusClasses[
                                            goodsReceipt.status
                                        ]
                                    "
                                >
                                    {{ goodsReceipt.status_label }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <Link
                                        v-if="goodsReceipt.can.view"
                                        :href="
                                            route(
                                                'goods-receipts.show',
                                                goodsReceipt.id,
                                            )
                                        "
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:text-brand-400"
                                    >
                                        View
                                    </Link>

                                    <Link
                                        v-if="goodsReceipt.can.update"
                                        :href="
                                            route(
                                                'goods-receipts.edit',
                                                goodsReceipt.id,
                                            )
                                        "
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        Edit
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="props.goodsReceipts.data.length === 0"
                        >
                            <td
                                colspan="10"
                                class="px-5 py-16 text-center"
                            >
                                <h3
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    No Goods Receipts found
                                </h3>

                                <p
                                    class="mt-2 text-sm text-gray-500"
                                >
                                    Create a receipt from an approved
                                    Purchase Order or change the current
                                    filters.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="props.goodsReceipts.meta.total > 0"
                class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p class="text-sm text-gray-500">
                    Showing
                    {{ props.goodsReceipts.meta.from }}
                    to
                    {{ props.goodsReceipts.meta.to }}
                    of
                    {{ props.goodsReceipts.meta.total }}
                </p>

                <div class="flex gap-2">
                    <button
                        type="button"
                        :disabled="
                            props.goodsReceipts.meta.current_page
                            <= 1
                        "
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                        @click="
                            goToPage(
                                props.goodsReceipts.meta.current_page
                                - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <span
                        class="rounded-lg bg-gray-100 px-3 py-2 text-sm dark:bg-gray-800"
                    >
                        {{
                            props.goodsReceipts.meta.current_page
                        }}
                        /
                        {{
                            props.goodsReceipts.meta.last_page
                        }}
                    </span>

                    <button
                        type="button"
                        :disabled="
                            props.goodsReceipts.meta.current_page
                            >= props.goodsReceipts.meta.last_page
                        "
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                        @click="
                            goToPage(
                                props.goodsReceipts.meta.current_page
                                + 1,
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