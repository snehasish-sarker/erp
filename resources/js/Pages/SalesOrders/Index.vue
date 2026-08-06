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
    SalesOrderFilters,
    SalesOrderIndexProps,
    SalesOrderSort,
    SalesOrderStatus,
    SalesOrderSummary,
} from '@/Types/sales-order';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<SalesOrderIndexProps>();

const filters = reactive<SalesOrderFilters>({
    search: props.filters.search ?? '',

    branch_id:
        props.filters.branch_id ?? null,

    warehouse_id:
        props.filters.warehouse_id ?? null,

    customer_id:
        props.filters.customer_id ?? null,

    status:
        props.filters.status ?? '',

    order_date_from:
        props.filters.order_date_from ?? '',

    order_date_to:
        props.filters.order_date_to ?? '',

    requested_delivery_from:
        props.filters.requested_delivery_from
        ?? '',

    requested_delivery_to:
        props.filters.requested_delivery_to
        ?? '',

    sort:
        props.filters.sort ?? 'created_at',

    direction:
        props.filters.direction ?? 'desc',

    per_page:
        props.filters.per_page ?? 15,
});

let searchTimer:
    ReturnType<typeof setTimeout>
    | null = null;

const availableWarehouses = computed(() => {
    if (filters.branch_id === null) {
        return props.warehouses;
    }

    return props.warehouses.filter(
        (warehouse) =>
            warehouse.branch_id
            === filters.branch_id,
    );
});

const hasActiveFilters = computed(
    (): boolean => {
        return filters.search.trim() !== ''
            || filters.branch_id !== null
            || filters.warehouse_id !== null
            || filters.customer_id !== null
            || filters.status !== ''
            || filters.order_date_from !== ''
            || filters.order_date_to !== ''
            || filters.requested_delivery_from
                !== ''
            || filters.requested_delivery_to
                !== '';
    },
);

const queryParameters = (
    page?: number,
): Record<string, string | number> => {
    const query: Record<
        string,
        string | number
    > = {
        sort: filters.sort,
        direction: filters.direction,
        per_page: filters.per_page,
    };

    if (page !== undefined) {
        query.page = page;
    }

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

    if (filters.customer_id !== null) {
        query.customer_id =
            filters.customer_id;
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
        filters.requested_delivery_from
        !== ''
    ) {
        query.requested_delivery_from =
            filters.requested_delivery_from;
    }

    if (
        filters.requested_delivery_to
        !== ''
    ) {
        query.requested_delivery_to =
            filters.requested_delivery_to;
    }

    return query;
};

const visitIndex = (
    page?: number,
): void => {
    router.get(
        route('sales-orders.index'),
        queryParameters(page),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

watch(
    () => filters.search,
    () => {
        if (searchTimer !== null) {
            clearTimeout(searchTimer);
        }

        searchTimer = setTimeout(
            () => {
                visitIndex(1);
            },
            400,
        );
    },
);

watch(
    () => filters.branch_id,
    () => {
        const warehouseIsValid =
            props.warehouses.some(
                (warehouse) =>
                    warehouse.id
                        === filters.warehouse_id
                    && (
                        filters.branch_id
                            === null
                        || warehouse.branch_id
                            === filters.branch_id
                    ),
            );

        if (!warehouseIsValid) {
            filters.warehouse_id = null;
        }
    },
);

onBeforeUnmount(() => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
});

const applyFilters = (): void => {
    visitIndex(1);
};

const resetFilters = (): void => {
    filters.search = '';
    filters.branch_id = null;
    filters.warehouse_id = null;
    filters.customer_id = null;
    filters.status = '';
    filters.order_date_from = '';
    filters.order_date_to = '';
    filters.requested_delivery_from = '';
    filters.requested_delivery_to = '';
    filters.sort = 'created_at';
    filters.direction = 'desc';
    filters.per_page = 15;

    router.get(
        route('sales-orders.index'),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const sortBy = (
    column: SalesOrderSort,
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

    visitIndex(1);
};

const sortIndicator = (
    column: SalesOrderSort,
): string => {
    if (filters.sort !== column) {
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
            > props.salesOrders.meta.last_page
        || page
            === props.salesOrders.meta.current_page
    ) {
        return;
    }

    visitIndex(page);
};

const pageNumbers = computed(
    (): number[] => {
        const current =
            props.salesOrders.meta.current_page;

        const last =
            props.salesOrders.meta.last_page;

        const start = Math.max(
            current - 2,
            1,
        );

        const end = Math.min(
            current + 2,
            last,
        );

        const pages: number[] = [];

        for (
            let page = start;
            page <= end;
            page += 1
        ) {
            pages.push(page);
        }

        return pages;
    },
);

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
    value: string,
): string => {
    const amount = Number.parseFloat(value);

    if (!Number.isFinite(amount)) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(amount);
};

const statusClasses = (
    status: SalesOrderStatus,
): string => {
    const classes:
        Record<SalesOrderStatus, string> = {
            draft:
                'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

            submitted:
                'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',

            approved:
                'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-300',

            partially_allocated:
                'bg-warning-50 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300',

            allocated:
                'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',

            partially_dispatched:
                'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',

            dispatched:
                'bg-cyan-50 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',

            partially_invoiced:
                'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',

            invoiced:
                'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300',

            closed:
                'bg-success-100 text-success-800 dark:bg-success-900/50 dark:text-success-200',

            cancelled:
                'bg-error-50 text-error-700 dark:bg-error-900/30 dark:text-error-300',
        };

    return classes[status];
};

const deleteSalesOrder = (
    salesOrder: SalesOrderSummary,
): void => {
    if (
        !window.confirm(
            `Delete ${
                salesOrder.document_number
                ?? `Draft #${salesOrder.id}`
            }? This action cannot be undone.`,
        )
    ) {
        return;
    }

    router.delete(
        route(
            'sales-orders.destroy',
            salesOrder.id,
        ),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Sales Orders" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Sales Orders
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Manage customer orders from draft through
                    allocation, dispatch, invoicing, and
                    closure.
                </p>
            </div>

            <Link
                v-if="can.create"
                :href="route('sales-orders.create')"
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
            >
                Create Sales Order
            </Link>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
        >
            <div
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                <div class="md:col-span-2">
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Search
                    </label>

                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Document number, customer, reference, or notes"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Branch
                    </label>

                    <select
                        v-model="filters.branch_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">
                            All branches
                        </option>

                        <option
                            v-for="branch in branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }}
                            ({{ branch.code }})
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Warehouse
                    </label>

                    <select
                        v-model="filters.warehouse_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">
                            All warehouses
                        </option>

                        <option
                            v-for="warehouse in availableWarehouses"
                            :key="warehouse.id"
                            :value="warehouse.id"
                        >
                            {{ warehouse.name }}
                            ({{ warehouse.code }})
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Customer
                    </label>

                    <select
                        v-model="filters.customer_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">
                            All customers
                        </option>

                        <option
                            v-for="customer in customers"
                            :key="customer.id"
                            :value="customer.id"
                        >
                            {{ customer.name }}
                            ({{ customer.code }})
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Status
                    </label>

                    <select
                        v-model="filters.status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Order Date From
                    </label>

                    <input
                        v-model="filters.order_date_from"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Order Date To
                    </label>

                    <input
                        v-model="filters.order_date_to"
                        :min="filters.order_date_from"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Delivery From
                    </label>

                    <input
                        v-model="
                            filters.requested_delivery_from
                        "
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Delivery To
                    </label>

                    <input
                        v-model="
                            filters.requested_delivery_to
                        "
                        :min="
                            filters.requested_delivery_from
                        "
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Per Page
                    </label>

                    <select
                        v-model="filters.per_page"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>
            </div>

            <div
                class="mt-5 flex flex-wrap justify-end gap-3"
            >
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    @click="resetFilters"
                >
                    Reset
                </button>

                <button
                    type="button"
                    class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
                    @click="applyFilters"
                >
                    Apply Filters
                </button>
            </div>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-gray-200 dark:divide-gray-800"
                >
                    <thead
                        class="bg-gray-50 dark:bg-white/[0.03]"
                    >
                        <tr>
                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    class="hover:text-gray-800 dark:hover:text-gray-200"
                                    @click="
                                        sortBy(
                                            'document_number',
                                        )
                                    "
                                >
                                    Order
                                    {{
                                        sortIndicator(
                                            'document_number',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Customer
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    class="hover:text-gray-800 dark:hover:text-gray-200"
                                    @click="
                                        sortBy(
                                            'order_date',
                                        )
                                    "
                                >
                                    Order Date
                                    {{
                                        sortIndicator(
                                            'order_date',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    class="hover:text-gray-800 dark:hover:text-gray-200"
                                    @click="
                                        sortBy(
                                            'requested_delivery_date',
                                        )
                                    "
                                >
                                    Delivery
                                    {{
                                        sortIndicator(
                                            'requested_delivery_date',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Location
                            </th>

                            <th
                                class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    class="hover:text-gray-800 dark:hover:text-gray-200"
                                    @click="
                                        sortBy(
                                            'total_amount',
                                        )
                                    "
                                >
                                    Total
                                    {{
                                        sortIndicator(
                                            'total_amount',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    class="hover:text-gray-800 dark:hover:text-gray-200"
                                    @click="
                                        sortBy(
                                            'status',
                                        )
                                    "
                                >
                                    Status
                                    {{
                                        sortIndicator(
                                            'status',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3.5 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-if="
                                salesOrders.data.length
                                    === 0
                            "
                        >
                            <td
                                colspan="8"
                                class="px-5 py-14 text-center"
                            >
                                <p
                                    class="font-medium text-gray-700 dark:text-gray-300"
                                >
                                    No Sales Orders found
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500"
                                >
                                    Adjust the filters or create
                                    the first Sales Order.
                                </p>
                            </td>
                        </tr>

                        <tr
                            v-for="salesOrder in salesOrders.data"
                            :key="salesOrder.id"
                            class="transition hover:bg-gray-50/70 dark:hover:bg-white/[0.02]"
                        >
                            <td
                                class="whitespace-nowrap px-5 py-4"
                            >
                                <Link
                                    :href="
                                        route(
                                            'sales-orders.show',
                                            salesOrder.id,
                                        )
                                    "
                                    class="font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                >
                                    {{
                                        salesOrder.document_number
                                            ?? `Draft #${salesOrder.id}`
                                    }}
                                </Link>

                                <p
                                    v-if="
                                        salesOrder.customer_reference
                                    "
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Ref:
                                    {{
                                        salesOrder.customer_reference
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="font-medium text-gray-800 dark:text-gray-200"
                                >
                                    {{
                                        salesOrder.customer_name
                                    }}
                                </p>

                                <p
                                    class="text-xs text-gray-500"
                                >
                                    {{
                                        salesOrder.customer_code
                                    }}
                                </p>
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatDate(
                                        salesOrder.order_date,
                                    )
                                }}
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatDate(
                                        salesOrder.requested_delivery_date,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <p>
                                    {{
                                        salesOrder.branch
                                            ?.name
                                            ?? '—'
                                    }}
                                </p>

                                <p
                                    class="text-xs text-gray-500"
                                >
                                    {{
                                        salesOrder.warehouse
                                            ?.name
                                            ?? 'No warehouse'
                                    }}
                                </p>
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    salesOrder.currency_code
                                }}
                                {{
                                    formatAmount(
                                        salesOrder.total_amount,
                                    )
                                }}
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-4"
                            >
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                        statusClasses(
                                            salesOrder.status,
                                        ),
                                    ]"
                                >
                                    {{
                                        salesOrder.status_label
                                    }}
                                </span>
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right"
                            >
                                <div
                                    class="inline-flex items-center gap-2"
                                >
                                    <Link
                                        v-if="
                                            salesOrder.can.view
                                        "
                                        :href="
                                            route(
                                                'sales-orders.show',
                                                salesOrder.id,
                                            )
                                        "
                                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                    >
                                        View
                                    </Link>

                                    <Link
                                        v-if="
                                            salesOrder.can.update
                                        "
                                        :href="
                                            route(
                                                'sales-orders.edit',
                                                salesOrder.id,
                                            )
                                        "
                                        class="text-sm font-medium text-gray-700 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="
                                            salesOrder.can.delete
                                        "
                                        type="button"
                                        class="text-sm font-medium text-error-500 hover:text-error-600"
                                        @click="
                                            deleteSalesOrder(
                                                salesOrder,
                                            )
                                        "
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Showing
                    {{ salesOrders.meta.from ?? 0 }}
                    to
                    {{ salesOrders.meta.to ?? 0 }}
                    of
                    {{ salesOrders.meta.total }}
                    orders
                </p>

                <div
                    v-if="
                        salesOrders.meta.last_page > 1
                    "
                    class="flex items-center gap-1"
                >
                    <button
                        type="button"
                        :disabled="
                            salesOrders.meta.current_page
                                <= 1
                        "
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            goToPage(
                                salesOrders.meta.current_page
                                    - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <button
                        v-for="page in pageNumbers"
                        :key="page"
                        type="button"
                        :class="[
                            'min-w-10 rounded-lg px-3 py-2 text-sm font-medium transition',
                            page
                                === salesOrders.meta.current_page
                                ? 'bg-brand-500 text-white'
                                : 'border border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800',
                        ]"
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </button>

                    <button
                        type="button"
                        :disabled="
                            salesOrders.meta.current_page
                                >= salesOrders.meta.last_page
                        "
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-600 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            goToPage(
                                salesOrders.meta.current_page
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