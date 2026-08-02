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
    PurchaseOrderFilters,
    PurchaseOrderIndexProps,
    PurchaseOrderSort,
    PurchaseOrderStatus,
    PurchaseOrderSummary,
} from '@/Types/purchase-order';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<PurchaseOrderIndexProps>();

const filters = reactive<PurchaseOrderFilters>({
    search: props.filters.search ?? '',

    branch_id:
        props.filters.branch_id ?? null,

    warehouse_id:
        props.filters.warehouse_id ?? null,

    supplier_id:
        props.filters.supplier_id ?? null,

    status:
        props.filters.status ?? '',

    order_date_from:
        props.filters.order_date_from ?? '',

    order_date_to:
        props.filters.order_date_to ?? '',

    expected_delivery_from:
        props.filters.expected_delivery_from ?? '',

    expected_delivery_to:
        props.filters.expected_delivery_to ?? '',

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

const hasActiveFilters = computed((): boolean => {
    return filters.search !== ''
        || filters.branch_id !== null
        || filters.warehouse_id !== null
        || filters.supplier_id !== null
        || filters.status !== ''
        || filters.order_date_from !== ''
        || filters.order_date_to !== ''
        || filters.expected_delivery_from !== ''
        || filters.expected_delivery_to !== '';
});

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
        query.search = filters.search.trim();
    }

    if (filters.branch_id !== null) {
        query.branch_id = filters.branch_id;
    }

    if (filters.warehouse_id !== null) {
        query.warehouse_id =
            filters.warehouse_id;
    }

    if (filters.supplier_id !== null) {
        query.supplier_id =
            filters.supplier_id;
    }

    if (filters.status !== '') {
        query.status = filters.status;
    }

    if (filters.order_date_from !== '') {
        query.order_date_from =
            filters.order_date_from;
    }

    if (filters.order_date_to !== '') {
        query.order_date_to =
            filters.order_date_to;
    }

    if (
        filters.expected_delivery_from
        !== ''
    ) {
        query.expected_delivery_from =
            filters.expected_delivery_from;
    }

    if (
        filters.expected_delivery_to
        !== ''
    ) {
        query.expected_delivery_to =
            filters.expected_delivery_to;
    }

    return query;
};

const applyFilters = (): void => {
    router.get(
        route('purchase-orders.index'),
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
    filters.status = '';
    filters.order_date_from = '';
    filters.order_date_to = '';
    filters.expected_delivery_from = '';
    filters.expected_delivery_to = '';
    filters.sort = 'created_at';
    filters.direction = 'desc';
    filters.per_page = 15;

    if (searchTimer !== null) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }

    applyFilters();
};

const toggleSort = (
    sort: PurchaseOrderSort,
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
    sort: PurchaseOrderSort,
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
    if (
        page < 1
        || page
            > props.purchaseOrders.meta
                .last_page
        || page
            === props.purchaseOrders.meta
                .current_page
    ) {
        return;
    }

    router.get(
        route('purchase-orders.index'),
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

type PaginationItem =
    | number
    | 'left-ellipsis'
    | 'right-ellipsis';

const paginationItems = computed<
    PaginationItem[]
>(() => {
    const current =
        props.purchaseOrders.meta
            .current_page;

    const last =
        props.purchaseOrders.meta
            .last_page;

    if (last <= 7) {
        return Array.from(
            {
                length: last,
            },
            (_, index) => index + 1,
        );
    }

    const items: PaginationItem[] = [1];

    if (current > 4) {
        items.push('left-ellipsis');
    }

    const start = Math.max(
        2,
        current - 1,
    );

    const end = Math.min(
        last - 1,
        current + 1,
    );

    for (
        let page = start;
        page <= end;
        page += 1
    ) {
        items.push(page);
    }

    if (current < last - 3) {
        items.push('right-ellipsis');
    }

    items.push(last);

    return items;
});

const statusClasses: Record<
    PurchaseOrderStatus,
    string
> = {
    draft:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    submitted:
        'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',

    approved:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    partially_received:
        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',

    received:
        'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-400',

    closed:
        'bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400',

    cancelled:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const statusClass = (
    status: PurchaseOrderStatus,
): string => {
    return statusClasses[status];
};

const displayDocumentNumber = (
    purchaseOrder: PurchaseOrderSummary,
): string => {
    return purchaseOrder.document_number
        ?? `Draft #${purchaseOrder.id}`;
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

    const year = Number(parts[0]);
    const month = Number(parts[1]);
    const day = Number(parts[2]);

    if (
        !Number.isInteger(year)
        || !Number.isInteger(month)
        || !Number.isInteger(day)
    ) {
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
            year,
            month - 1,
            day,
        ),
    );
};

const formatAmount = (
    amount: string,
): string => {
    const parsed = Number.parseFloat(amount);

    if (!Number.isFinite(parsed)) {
        return amount;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(parsed);
};

const deletePurchaseOrder = (
    purchaseOrder: PurchaseOrderSummary,
): void => {
    const confirmed = window.confirm(
        `Delete ${displayDocumentNumber(
            purchaseOrder,
        )}? This action cannot be undone from the interface.`,
    );

    if (!confirmed) {
        return;
    }

    router.delete(
        route(
            'purchase-orders.destroy',
            purchaseOrder.id,
        ),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Purchase Orders" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Purchase Orders
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Create, review, approve, and track
                    Supplier Purchase Orders.
                </p>
            </div>

            <Link
                v-if="props.can.create"
                :href="
                    route(
                        'purchase-orders.create',
                    )
                "
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
            >
                Create Purchase Order
            </Link>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                <div class="xl:col-span-2">
                    <label
                        for="purchase-order-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Search
                    </label>

                    <input
                        id="purchase-order-search"
                        v-model="filters.search"
                        type="search"
                        maxlength="160"
                        placeholder="Document, Supplier, reference, or notes"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        for="purchase-order-branch-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Branch
                    </label>

                    <select
                        id="purchase-order-branch-filter"
                        v-model.number="filters.branch_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
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
                    <label
                        for="purchase-order-warehouse-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Warehouse
                    </label>

                    <select
                        id="purchase-order-warehouse-filter"
                        v-model.number="filters.warehouse_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
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
                    <label
                        for="purchase-order-supplier-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier
                    </label>

                    <select
                        id="purchase-order-supplier-filter"
                        v-model.number="filters.supplier_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
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
                    <label
                        for="purchase-order-status-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Status
                    </label>

                    <select
                        id="purchase-order-status-filter"
                        v-model="filters.status"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
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
                    <label
                        for="purchase-order-date-from"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Order Date From
                    </label>

                    <input
                        id="purchase-order-date-from"
                        v-model="filters.order_date_from"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label
                        for="purchase-order-date-to"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Order Date To
                    </label>

                    <input
                        id="purchase-order-date-to"
                        v-model="filters.order_date_to"
                        type="date"
                        :min="filters.order_date_from || undefined"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label
                        for="purchase-order-delivery-from"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Delivery From
                    </label>

                    <input
                        id="purchase-order-delivery-from"
                        v-model="filters.expected_delivery_from"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label
                        for="purchase-order-delivery-to"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Delivery To
                    </label>

                    <input
                        id="purchase-order-delivery-to"
                        v-model="filters.expected_delivery_to"
                        type="date"
                        :min="
                            filters.expected_delivery_from
                            || undefined
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label
                        for="purchase-order-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Records Per Page
                    </label>

                    <select
                        id="purchase-order-per-page"
                        v-model.number="filters.per_page"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="10">
                            10
                        </option>

                        <option :value="15">
                            15
                        </option>

                        <option :value="25">
                            25
                        </option>

                        <option :value="50">
                            50
                        </option>

                        <option :value="100">
                            100
                        </option>
                    </select>
                </div>

                <div
                    class="flex items-end xl:col-span-3"
                >
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-gray-800"
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
                <table
                    class="min-w-[1200px] w-full"
                >
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-400"
                        >
                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
                                    @click="
                                        toggleSort(
                                            'document_number',
                                        )
                                    "
                                >
                                    Document

                                    <span>
                                        {{
                                            sortIndicator(
                                                'document_number',
                                            )
                                        }}
                                    </span>
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
                                    @click="
                                        toggleSort(
                                            'order_date',
                                        )
                                    "
                                >
                                    Order Date

                                    <span>
                                        {{
                                            sortIndicator(
                                                'order_date',
                                            )
                                        }}
                                    </span>
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
                                    @click="
                                        toggleSort(
                                            'expected_delivery_date',
                                        )
                                    "
                                >
                                    Expected Delivery

                                    <span>
                                        {{
                                            sortIndicator(
                                                'expected_delivery_date',
                                            )
                                        }}
                                    </span>
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
                                    @click="
                                        toggleSort(
                                            'supplier_name',
                                        )
                                    "
                                >
                                    Supplier

                                    <span>
                                        {{
                                            sortIndicator(
                                                'supplier_name',
                                            )
                                        }}
                                    </span>
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                Branch / Warehouse
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
                                    @click="
                                        toggleSort(
                                            'total_amount',
                                        )
                                    "
                                >
                                    Total

                                    <span>
                                        {{
                                            sortIndicator(
                                                'total_amount',
                                            )
                                        }}
                                    </span>
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
                                    @click="
                                        toggleSort(
                                            'status',
                                        )
                                    "
                                >
                                    Status

                                    <span>
                                        {{
                                            sortIndicator(
                                                'status',
                                            )
                                        }}
                                    </span>
                                </button>
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="purchaseOrder in props.purchaseOrders.data"
                            :key="purchaseOrder.id"
                            class="border-b border-gray-100 last:border-b-0 dark:border-gray-800"
                        >
                            <td class="px-5 py-4">
                                <Link
                                    v-if="purchaseOrder.can.view"
                                    :href="
                                        route(
                                            'purchase-orders.show',
                                            purchaseOrder.id,
                                        )
                                    "
                                    class="font-medium text-gray-900 transition hover:text-brand-500 dark:text-white"
                                >
                                    {{
                                        displayDocumentNumber(
                                            purchaseOrder,
                                        )
                                    }}
                                </Link>

                                <span
                                    v-else
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        displayDocumentNumber(
                                            purchaseOrder,
                                        )
                                    }}
                                </span>

                                <p
                                    v-if="purchaseOrder.supplier_reference"
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Ref:
                                    {{
                                        purchaseOrder.supplier_reference
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-400"
                                >
                                    Revision
                                    {{ purchaseOrder.revision }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatDate(
                                        purchaseOrder.order_date,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatDate(
                                        purchaseOrder.expected_delivery_date,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        purchaseOrder.supplier_name
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        purchaseOrder.supplier_code
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm text-gray-900 dark:text-white"
                                >
                                    {{
                                        purchaseOrder.branch.name
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        purchaseOrder.warehouse
                                            ? purchaseOrder
                                                .warehouse
                                                .name
                                            : 'No warehouse'
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-right"
                            >
                                <p
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{
                                        purchaseOrder.currency_code
                                    }}
                                    {{
                                        formatAmount(
                                            purchaseOrder.total_amount,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        statusClass(
                                            purchaseOrder.status,
                                        )
                                    "
                                >
                                    {{
                                        purchaseOrder.status_label
                                    }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <Link
                                        v-if="purchaseOrder.can.view"
                                        :href="
                                            route(
                                                'purchase-orders.show',
                                                purchaseOrder.id,
                                            )
                                        "
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-brand-600 transition hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-500/10"
                                    >
                                        View
                                    </Link>

                                    <Link
                                        v-if="purchaseOrder.can.update"
                                        :href="
                                            route(
                                                'purchase-orders.edit',
                                                purchaseOrder.id,
                                            )
                                        "
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="purchaseOrder.can.delete"
                                        type="button"
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                                        @click="
                                            deletePurchaseOrder(
                                                purchaseOrder,
                                            )
                                        "
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                props.purchaseOrders.data
                                    .length === 0
                            "
                        >
                            <td
                                colspan="8"
                                class="px-5 py-16 text-center"
                            >
                                <h3
                                    class="text-base font-semibold text-gray-900 dark:text-white"
                                >
                                    No Purchase Orders found
                                </h3>

                                <p
                                    class="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400"
                                >
                                    {{
                                        hasActiveFilters
                                            ? 'Try changing or clearing the current filters.'
                                            : 'Create the first Purchase Order to begin the purchasing workflow.'
                                    }}
                                </p>

                                <Link
                                    v-if="
                                        props.can.create
                                        && !hasActiveFilters
                                    "
                                    :href="
                                        route(
                                            'purchase-orders.create',
                                        )
                                    "
                                    class="mt-5 inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
                                >
                                    Create Purchase Order
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="
                    props.purchaseOrders.meta.total
                    > 0
                "
                class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Showing
                    {{
                        props.purchaseOrders.meta.from
                    }}
                    to
                    {{
                        props.purchaseOrders.meta.to
                    }}
                    of
                    {{
                        props.purchaseOrders.meta.total
                    }}
                    Purchase Orders
                </p>

                <div
                    class="flex flex-wrap items-center gap-1"
                >
                    <button
                        type="button"
                        :disabled="
                            props.purchaseOrders.meta
                                .current_page <= 1
                        "
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            goToPage(
                                props.purchaseOrders.meta
                                    .current_page - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <template
                        v-for="item in paginationItems"
                        :key="item"
                    >
                        <span
                            v-if="
                                item === 'left-ellipsis'
                                || item
                                    === 'right-ellipsis'
                            "
                            class="px-2 py-2 text-sm text-gray-400"
                        >
                            …
                        </span>

                        <button
                            v-else
                            type="button"
                            class="min-w-10 rounded-lg border px-3 py-2 text-sm font-medium transition"
                            :class="
                                item
                                === props
                                    .purchaseOrders
                                    .meta
                                    .current_page
                                    ? 'border-brand-500 bg-brand-500 text-white'
                                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800'
                            "
                            @click="goToPage(item)"
                        >
                            {{ item }}
                        </button>
                    </template>

                    <button
                        type="button"
                        :disabled="
                            props.purchaseOrders.meta
                                .current_page
                            >= props.purchaseOrders.meta
                                .last_page
                        "
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            goToPage(
                                props.purchaseOrders.meta
                                    .current_page + 1,
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