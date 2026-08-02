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
    SupplierInvoiceFilters,
    SupplierInvoiceIndexProps,
    SupplierInvoiceMatchStatus,
    SupplierInvoiceSort,
    SupplierInvoiceStatus,
    SupplierInvoiceSummary,
} from '@/Types/supplier-invoice';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<SupplierInvoiceIndexProps>();

const filters = reactive<SupplierInvoiceFilters>({
    search: props.filters.search ?? '',
    branch_id: props.filters.branch_id ?? null,
    supplier_id: props.filters.supplier_id ?? null,
    purchase_order_id:
        props.filters.purchase_order_id ?? null,
    status: props.filters.status ?? '',
    match_status:
        props.filters.match_status ?? '',
    invoice_date_from:
        props.filters.invoice_date_from ?? '',
    invoice_date_to:
        props.filters.invoice_date_to ?? '',
    due_date_from:
        props.filters.due_date_from ?? '',
    due_date_to:
        props.filters.due_date_to ?? '',
    sort: props.filters.sort ?? 'created_at',
    direction:
        props.filters.direction ?? 'desc',
    per_page: props.filters.per_page ?? 15,
});

const availablePurchaseOrders = computed(() => {
    return props.purchaseOrders.filter(
        (purchaseOrder): boolean => {
            if (
                filters.branch_id !== null
                && purchaseOrder.branch_id
                    !== filters.branch_id
            ) {
                return false;
            }

            if (
                filters.supplier_id !== null
                && purchaseOrder.supplier_id
                    !== filters.supplier_id
            ) {
                return false;
            }

            return true;
        },
    );
});

const hasActiveFilters = computed(
    (): boolean =>
        filters.search !== ''
        || filters.branch_id !== null
        || filters.supplier_id !== null
        || filters.purchase_order_id !== null
        || filters.status !== ''
        || filters.match_status !== ''
        || filters.invoice_date_from !== ''
        || filters.invoice_date_to !== ''
        || filters.due_date_from !== ''
        || filters.due_date_to !== '',
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
        query.search = filters.search.trim();
    }

    if (filters.branch_id !== null) {
        query.branch_id = filters.branch_id;
    }

    if (filters.supplier_id !== null) {
        query.supplier_id = filters.supplier_id;
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

    if (filters.match_status !== '') {
        query.match_status =
            filters.match_status;
    }

    if (
        filters.invoice_date_from !== ''
    ) {
        query.invoice_date_from =
            filters.invoice_date_from;
    }

    if (filters.invoice_date_to !== '') {
        query.invoice_date_to =
            filters.invoice_date_to;
    }

    if (filters.due_date_from !== '') {
        query.due_date_from =
            filters.due_date_from;
    }

    if (filters.due_date_to !== '') {
        query.due_date_to =
            filters.due_date_to;
    }

    return query;
};

const applyFilters = (): void => {
    router.get(
        route('supplier-invoices.index'),
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
    [
        () => filters.branch_id,
        () => filters.supplier_id,
    ],
    () => {
        if (
            filters.purchase_order_id !== null
            && !availablePurchaseOrders
                .value
                .some(
                    (purchaseOrder): boolean =>
                        purchaseOrder.id
                        === filters.purchase_order_id,
                )
        ) {
            filters.purchase_order_id = null;
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
    filters.supplier_id = null;
    filters.purchase_order_id = null;
    filters.status = '';
    filters.match_status = '';
    filters.invoice_date_from = '';
    filters.invoice_date_to = '';
    filters.due_date_from = '';
    filters.due_date_to = '';
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
    sort: SupplierInvoiceSort,
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
    sort: SupplierInvoiceSort,
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
        props.supplierInvoices.meta;

    if (
        page < 1
        || page > meta.last_page
        || page === meta.current_page
    ) {
        return;
    }

    router.get(
        route('supplier-invoices.index'),
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
    SupplierInvoiceStatus,
    string
> = {
    draft:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    validated:
        'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',

    approved:
        'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',

    posted:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    disputed:
        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',

    reversed:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',

    cancelled:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const matchStatusClasses: Record<
    SupplierInvoiceMatchStatus,
    string
> = {
    unmatched:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    matched:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    variance:
        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',

    blocked:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const displayNumber = (
    supplierInvoice: SupplierInvoiceSummary,
): string => {
    return supplierInvoice.document_number
        ?? `Draft #${supplierInvoice.id}`;
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
            Date.UTC(
                year,
                month - 1,
                day,
            ),
        ),
    );
};

const formatAmount = (
    value: string,
): string => {
    const parsed = Number.parseFloat(value);

    if (!Number.isFinite(parsed)) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(parsed);
};
</script>

<template>
    <Head title="Supplier Invoices" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Supplier Invoices
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Record supplier invoices and manage
                    Purchase Order, Goods Receipt, and
                    invoice matching.
                </p>
            </div>

            <Link
                v-if="props.can.create"
                :href="
                    route(
                        'supplier-invoices.create',
                    )
                "
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
            >
                Create Supplier Invoice
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
                        for="supplier_invoice_search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Search
                    </label>

                    <input
                        id="supplier_invoice_search"
                        v-model="filters.search"
                        type="search"
                        maxlength="160"
                        placeholder="Internal number, supplier invoice, PO, supplier, or notes"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        for="supplier_invoice_branch"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Branch
                    </label>

                    <select
                        id="supplier_invoice_branch"
                        v-model.number="
                            filters.branch_id
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option :value="null">
                            All branches
                        </option>

                        <option
                            v-for="branch in props.branches"
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
                        for="supplier_invoice_supplier"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier
                    </label>

                    <select
                        id="supplier_invoice_supplier"
                        v-model.number="
                            filters.supplier_id
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option :value="null">
                            All suppliers
                        </option>

                        <option
                            v-for="supplier in props.suppliers"
                            :key="supplier.id"
                            :value="supplier.id"
                        >
                            {{ supplier.name }}
                            ({{ supplier.code }})
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier_invoice_purchase_order"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Purchase Order
                    </label>

                    <select
                        id="supplier_invoice_purchase_order"
                        v-model.number="
                            filters.purchase_order_id
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="null">
                            All Purchase Orders
                        </option>

                        <option
                            v-for="purchaseOrder in availablePurchaseOrders"
                            :key="purchaseOrder.id"
                            :value="purchaseOrder.id"
                        >
                            {{
                                purchaseOrder.document_number
                                ?? `PO #${purchaseOrder.id}`
                            }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier_invoice_status"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Invoice Status
                    </label>

                    <select
                        id="supplier_invoice_status"
                        v-model="filters.status"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="status in props.statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier_invoice_match_status"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Match Status
                    </label>

                    <select
                        id="supplier_invoice_match_status"
                        v-model="filters.match_status"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option value="">
                            All match statuses
                        </option>

                        <option
                            v-for="status in props.matchStatuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier_invoice_date_from"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Invoice Date From
                    </label>

                    <input
                        id="supplier_invoice_date_from"
                        v-model="
                            filters.invoice_date_from
                        "
                        type="date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label
                        for="supplier_invoice_date_to"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Invoice Date To
                    </label>

                    <input
                        id="supplier_invoice_date_to"
                        v-model="
                            filters.invoice_date_to
                        "
                        type="date"
                        :min="
                            filters.invoice_date_from
                            || undefined
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label
                        for="supplier_invoice_due_from"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Due Date From
                    </label>

                    <input
                        id="supplier_invoice_due_from"
                        v-model="
                            filters.due_date_from
                        "
                        type="date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label
                        for="supplier_invoice_due_to"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Due Date To
                    </label>

                    <input
                        id="supplier_invoice_due_to"
                        v-model="
                            filters.due_date_to
                        "
                        type="date"
                        :min="
                            filters.due_date_from
                            || undefined
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label
                        for="supplier_invoice_per_page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Records Per Page
                    </label>

                    <select
                        id="supplier_invoice_per_page"
                        v-model.number="
                            filters.per_page
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
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

                <div class="flex items-end">
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-gray-800"
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
                <table class="min-w-[1450px] w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-400"
                        >
                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="text-left"
                                    @click="
                                        toggleSort(
                                            'document_number',
                                        )
                                    "
                                >
                                    Internal Number
                                    {{
                                        sortIndicator(
                                            'document_number',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="text-left"
                                    @click="
                                        toggleSort(
                                            'supplier_invoice_number',
                                        )
                                    "
                                >
                                    Supplier Invoice
                                    {{
                                        sortIndicator(
                                            'supplier_invoice_number',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="text-left"
                                    @click="
                                        toggleSort(
                                            'invoice_date',
                                        )
                                    "
                                >
                                    Invoice / Due Date
                                    {{
                                        sortIndicator(
                                            'invoice_date',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="text-left"
                                    @click="
                                        toggleSort(
                                            'purchase_order_number',
                                        )
                                    "
                                >
                                    Purchase Order
                                    {{
                                        sortIndicator(
                                            'purchase_order_number',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="text-left"
                                    @click="
                                        toggleSort(
                                            'supplier_name',
                                        )
                                    "
                                >
                                    Supplier
                                    {{
                                        sortIndicator(
                                            'supplier_name',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                Branch
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                <button
                                    type="button"
                                    @click="
                                        toggleSort(
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

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    @click="
                                        toggleSort(
                                            'match_status',
                                        )
                                    "
                                >
                                    Match
                                    {{
                                        sortIndicator(
                                            'match_status',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    @click="
                                        toggleSort(
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
                                class="px-5 py-3.5 text-right"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="supplierInvoice in props.supplierInvoices.data"
                            :key="supplierInvoice.id"
                            class="border-b border-gray-100 last:border-b-0 dark:border-gray-800"
                        >
                            <td class="px-5 py-4">
                                <Link
                                    v-if="
                                        supplierInvoice.can.view
                                    "
                                    :href="
                                        route(
                                            'supplier-invoices.show',
                                            supplierInvoice.id,
                                        )
                                    "
                                    class="font-medium text-gray-900 transition hover:text-brand-500 dark:text-white"
                                >
                                    {{
                                        displayNumber(
                                            supplierInvoice,
                                        )
                                    }}
                                </Link>

                                <span
                                    v-else
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        displayNumber(
                                            supplierInvoice,
                                        )
                                    }}
                                </span>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Created
                                    {{
                                        formatDate(
                                            supplierInvoice
                                                .created_at
                                                ?.slice(
                                                    0,
                                                    10,
                                                )
                                            ?? null,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        supplierInvoice
                                            .supplier_invoice_number
                                    }}
                                </p>

                                <p
                                    v-if="
                                        supplierInvoice
                                            .document_number
                                        === null
                                    "
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Internal number pending
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatDate(
                                            supplierInvoice
                                                .invoice_date,
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Due:
                                    {{
                                        formatDate(
                                            supplierInvoice
                                                .due_date,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <Link
                                    v-if="
                                        supplierInvoice
                                            .purchase_order_number
                                    "
                                    :href="
                                        route(
                                            'purchase-orders.show',
                                            supplierInvoice
                                                .purchase_order_id,
                                        )
                                    "
                                    class="text-sm font-medium text-brand-600 transition hover:text-brand-700 dark:text-brand-400"
                                >
                                    {{
                                        supplierInvoice
                                            .purchase_order_number
                                    }}
                                </Link>

                                <span
                                    v-else
                                    class="text-sm text-gray-500"
                                >
                                    —
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        supplierInvoice
                                            .supplier.name
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        supplierInvoice
                                            .supplier.code
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm text-gray-900 dark:text-white"
                                >
                                    {{
                                        supplierInvoice
                                            .branch.name
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        supplierInvoice
                                            .branch.code
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
                                        formatAmount(
                                            supplierInvoice
                                                .total_amount,
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        supplierInvoice
                                            .currency_code
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        matchStatusClasses[
                                            supplierInvoice
                                                .match_status
                                        ]
                                    "
                                >
                                    {{
                                        supplierInvoice
                                            .match_status_label
                                    }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        statusClasses[
                                            supplierInvoice
                                                .status
                                        ]
                                    "
                                >
                                    {{
                                        supplierInvoice
                                            .status_label
                                    }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <div
                                    class="flex justify-end gap-2"
                                >
                                    <Link
                                        v-if="
                                            supplierInvoice
                                                .can.view
                                        "
                                        :href="
                                            route(
                                                'supplier-invoices.show',
                                                supplierInvoice.id,
                                            )
                                        "
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-brand-600 transition hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-500/10"
                                    >
                                        View
                                    </Link>

                                    <Link
                                        v-if="
                                            supplierInvoice
                                                .can.update
                                        "
                                        :href="
                                            route(
                                                'supplier-invoices.edit',
                                                supplierInvoice.id,
                                            )
                                        "
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        Edit
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                props.supplierInvoices
                                    .data.length === 0
                            "
                        >
                            <td
                                colspan="10"
                                class="px-5 py-16 text-center"
                            >
                                <h3
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    No Supplier Invoices found
                                </h3>

                                <p
                                    class="mt-2 text-sm text-gray-500"
                                >
                                    Create an invoice from a
                                    Purchase Order with posted
                                    Goods Receipt quantities, or
                                    change the current filters.
                                </p>

                                <Link
                                    v-if="props.can.create"
                                    :href="
                                        route(
                                            'supplier-invoices.create',
                                        )
                                    "
                                    class="mt-5 inline-flex rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
                                >
                                    Create Supplier Invoice
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="
                    props.supplierInvoices.meta.total
                    > 0
                "
                class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p class="text-sm text-gray-500">
                    Showing
                    {{
                        props.supplierInvoices.meta
                            .from
                    }}
                    to
                    {{
                        props.supplierInvoices.meta
                            .to
                    }}
                    of
                    {{
                        props.supplierInvoices.meta
                            .total
                    }}
                </p>

                <div class="flex gap-2">
                    <button
                        type="button"
                        :disabled="
                            props.supplierInvoices.meta
                                .current_page <= 1
                        "
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            goToPage(
                                props.supplierInvoices
                                    .meta.current_page
                                - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <span
                        class="rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        {{
                            props.supplierInvoices.meta
                                .current_page
                        }}
                        /
                        {{
                            props.supplierInvoices.meta
                                .last_page
                        }}
                    </span>

                    <button
                        type="button"
                        :disabled="
                            props.supplierInvoices.meta
                                .current_page
                            >= props.supplierInvoices.meta
                                .last_page
                        "
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            goToPage(
                                props.supplierInvoices
                                    .meta.current_page
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