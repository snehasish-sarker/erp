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
    CustomerReceiptFilters,
    CustomerReceiptIndexProps,
    CustomerReceiptSort,
    CustomerReceiptStatus,
    CustomerReceiptSummary,
} from '@/Types/customer-receipt';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<CustomerReceiptIndexProps>();

const filters = reactive<CustomerReceiptFilters>({
    search: props.filters.search ?? '',
    branch_id: props.filters.branch_id ?? null,
    customer_id: props.filters.customer_id ?? null,
    receipt_account_id:
        props.filters.receipt_account_id ?? null,
    status: props.filters.status ?? '',
    receipt_method:
        props.filters.receipt_method ?? '',
    receipt_date_from:
        props.filters.receipt_date_from ?? '',
    receipt_date_to:
        props.filters.receipt_date_to ?? '',
    sort: props.filters.sort ?? 'created_at',
    direction: props.filters.direction ?? 'desc',
    per_page: props.filters.per_page ?? 15,
});

const hasActiveFilters = computed(
    (): boolean => {
        return filters.search.trim() !== ''
            || filters.branch_id !== null
            || filters.customer_id !== null
            || filters.receipt_account_id !== null
            || filters.status !== ''
            || filters.receipt_method !== ''
            || filters.receipt_date_from !== ''
            || filters.receipt_date_to !== '';
    },
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

    if (filters.customer_id !== null) {
        query.customer_id = filters.customer_id;
    }

    if (filters.receipt_account_id !== null) {
        query.receipt_account_id =
            filters.receipt_account_id;
    }

    if (filters.status !== '') {
        query.status = filters.status;
    }

    if (filters.receipt_method !== '') {
        query.receipt_method = filters.receipt_method;
    }

    if (filters.receipt_date_from !== '') {
        query.receipt_date_from =
            filters.receipt_date_from;
    }

    if (filters.receipt_date_to !== '') {
        query.receipt_date_to =
            filters.receipt_date_to;
    }

    return query;
};

const applyFilters = (): void => {
    router.get(
        route('customer-receipts.index'),
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

let suppressSearchWatch = false;

watch(
    () => filters.search,
    (): void => {
        if (suppressSearchWatch) {
            return;
        }

        if (searchTimer !== null) {
            clearTimeout(searchTimer);
        }

        searchTimer = setTimeout(
            applyFilters,
            400,
        );
    },
    {
        flush: 'sync',
    },
);

onBeforeUnmount((): void => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
});

const resetFilters = (): void => {
    suppressSearchWatch = true;

    if (searchTimer !== null) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }

    filters.search = '';
    filters.branch_id = null;
    filters.customer_id = null;
    filters.receipt_account_id = null;
    filters.status = '';
    filters.receipt_method = '';
    filters.receipt_date_from = '';
    filters.receipt_date_to = '';
    filters.sort = 'created_at';
    filters.direction = 'desc';
    filters.per_page = 15;

    applyFilters();

    suppressSearchWatch = false;
};

const toggleSort = (
    sort: CustomerReceiptSort,
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
    sort: CustomerReceiptSort,
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
    const meta = props.customerReceipts.meta;

    if (
        page < 1
        || page > meta.last_page
        || page === meta.current_page
    ) {
        return;
    }

    router.get(
        route('customer-receipts.index'),
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

const paginationItems = computed<PaginationItem[]>(
    (): PaginationItem[] => {
        const current =
            props.customerReceipts.meta.current_page;

        const last =
            props.customerReceipts.meta.last_page;

        if (last <= 7) {
            return Array.from(
                {
                    length: last,
                },
                (
                    _,
                    index,
                ): number => index + 1,
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
    },
);

const statusClasses: Record<
    CustomerReceiptStatus,
    string
> = {
    draft:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    submitted:
        'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',

    approved:
        'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',

    posted:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    reversed:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',

    cancelled:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const displayNumber = (
    customerReceipt: CustomerReceiptSummary,
): string => {
    return customerReceipt.receipt_number
        ?? `Draft #${customerReceipt.id}`;
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

const formatDateTime = (
    value: string | null,
): string => {
    if (value === null || value === '') {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        },
    ).format(date);
};

const decimalValue = (
    value: string | number | null,
): number => {
    const parsed = Number.parseFloat(
        String(value ?? '0'),
    );

    return Number.isFinite(parsed)
        ? parsed
        : 0;
};

const formatAmount = (
    value: string | number,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(decimalValue(value));
};

const unallocatedClasses = (
    customerReceipt: CustomerReceiptSummary,
): string => {
    return Math.abs(
        decimalValue(
            customerReceipt.unallocated_amount,
        ),
    ) > 0.000001
        ? 'text-amber-600 dark:text-amber-400'
        : 'text-gray-500 dark:text-gray-400';
};

const deleteCustomerReceipt = (
    customerReceipt: CustomerReceiptSummary,
): void => {
    const confirmed = window.confirm(
        `Delete ${displayNumber(
            customerReceipt,
        )}? Only an unnumbered, never-submitted draft can be permanently deleted.`,
    );

    if (!confirmed) {
        return;
    }

    router.delete(
        route(
            'customer-receipts.destroy',
            customerReceipt.id,
        ),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Customer Receipts" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Customer Receipts
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Manage customer settlements, invoice
                    allocations, unallocated advances, and
                    related General Ledger postings.
                </p>
            </div>

            <Link
                v-if="props.can.create"
                :href="route('customer-receipts.create')"
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600"
            >
                Create Customer Receipt
            </Link>
        </div>

        <section
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                <div class="md:col-span-2">
                    <label
                        for="customer-receipt-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Search
                    </label>

                    <input
                        id="customer-receipt-search"
                        v-model="filters.search"
                        type="search"
                        placeholder="Receipt number, customer, reference, cheque, or account"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        for="customer-receipt-branch-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Branch
                    </label>

                    <select
                        id="customer-receipt-branch-filter"
                        v-model="filters.branch_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="null">
                            All branches
                        </option>

                        <option
                            v-for="branch in props.branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.code }} — {{ branch.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="customer-receipt-customer-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Customer
                    </label>

                    <select
                        id="customer-receipt-customer-filter"
                        v-model="filters.customer_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="null">
                            All customers
                        </option>

                        <option
                            v-for="customer in props.customers"
                            :key="customer.id"
                            :value="customer.id"
                        >
                            {{ customer.code }} — {{ customer.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="customer-receipt-account-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Receipt Account
                    </label>

                    <select
                        id="customer-receipt-account-filter"
                        v-model="filters.receipt_account_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="null">
                            All accounts
                        </option>

                        <option
                            v-for="account in props.receiptAccounts"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.code }} — {{ account.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="customer-receipt-method-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Receipt Method
                    </label>

                    <select
                        id="customer-receipt-method-filter"
                        v-model="filters.receipt_method"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option value="">
                            All methods
                        </option>

                        <option
                            v-for="method in props.receiptMethods"
                            :key="method.value"
                            :value="method.value"
                        >
                            {{ method.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="customer-receipt-status-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Status
                    </label>

                    <select
                        id="customer-receipt-status-filter"
                        v-model="filters.status"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
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
                        for="customer-receipt-date-from"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Receipt Date From
                    </label>

                    <input
                        id="customer-receipt-date-from"
                        v-model="filters.receipt_date_from"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label
                        for="customer-receipt-date-to"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Receipt Date To
                    </label>

                    <input
                        id="customer-receipt-date-to"
                        v-model="filters.receipt_date_to"
                        type="date"
                        :min="filters.receipt_date_from || undefined"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>
            </div>

            <div
                class="mt-5 flex flex-col gap-3 border-t border-gray-200 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <div class="flex items-center gap-3">
                    <label
                        for="customer-receipt-per-page"
                        class="text-sm text-gray-600 dark:text-gray-400"
                    >
                        Rows per page
                    </label>

                    <select
                        id="customer-receipt-per-page"
                        v-model="filters.per_page"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>

                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    @click="resetFilters"
                >
                    Reset Filters
                </button>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-gray-200 dark:divide-gray-800"
                >
                    <thead
                        class="bg-gray-50 dark:bg-gray-950"
                    >
                        <tr>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 transition hover:text-gray-900 dark:hover:text-white"
                                    @click="toggleSort('receipt_number')"
                                >
                                    Receipt
                                    <span>{{ sortIndicator('receipt_number') }}</span>
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Customer / Branch
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Method / Account
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 transition hover:text-gray-900 dark:hover:text-white"
                                    @click="toggleSort('receipt_date')"
                                >
                                    Dates
                                    <span>{{ sortIndicator('receipt_date') }}</span>
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 transition hover:text-gray-900 dark:hover:text-white"
                                    @click="toggleSort('total_amount')"
                                >
                                    Amounts
                                    <span>{{ sortIndicator('total_amount') }}</span>
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 transition hover:text-gray-900 dark:hover:text-white"
                                    @click="toggleSort('status')"
                                >
                                    Status
                                    <span>{{ sortIndicator('status') }}</span>
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900"
                    >
                        <tr
                            v-for="customerReceipt in props.customerReceipts.data"
                            :key="customerReceipt.id"
                            class="transition hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        >
                            <td class="px-5 py-4 align-top">
                                <Link
                                    :href="route('customer-receipts.show', customerReceipt.id)"
                                    class="text-sm font-semibold text-brand-600 transition hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                                >
                                    {{ displayNumber(customerReceipt) }}
                                </Link>

                                <p
                                    v-if="customerReceipt.receipt_reference"
                                    class="mt-1 max-w-56 truncate text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Ref: {{ customerReceipt.receipt_reference }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-400"
                                >
                                    Created {{ formatDateTime(customerReceipt.created_at) }}
                                </p>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <p
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ customerReceipt.customer.name }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ customerReceipt.customer.code }}
                                </p>

                                <p
                                    class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ customerReceipt.branch.code ?? '—' }} —
                                    {{ customerReceipt.branch.name ?? 'Unknown branch' }}
                                </p>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{ customerReceipt.receipt_method_label }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ customerReceipt.receipt_account.code }} —
                                    {{ customerReceipt.receipt_account.name }}
                                </p>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <p
                                    class="text-sm text-gray-900 dark:text-white"
                                >
                                    Receipt: {{ formatDate(customerReceipt.receipt_date) }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Posting: {{ formatDate(customerReceipt.posting_date) }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-right align-top">
                                <p
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ formatAmount(customerReceipt.total_amount) }}
                                    {{ customerReceipt.currency_code }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-emerald-600 dark:text-emerald-400"
                                >
                                    Allocated:
                                    {{ formatAmount(customerReceipt.allocated_amount) }}
                                </p>

                                <p
                                    class="mt-1 text-xs"
                                    :class="unallocatedClasses(customerReceipt)"
                                >
                                    Unallocated:
                                    {{ formatAmount(customerReceipt.unallocated_amount) }}
                                </p>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="statusClasses[customerReceipt.status]"
                                >
                                    {{ customerReceipt.status_label }}
                                </span>
                            </td>

                            <td class="px-5 py-4 text-right align-top">
                                <div
                                    class="flex flex-wrap justify-end gap-2"
                                >
                                    <Link
                                        v-if="customerReceipt.can.view"
                                        :href="route('customer-receipts.show', customerReceipt.id)"
                                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        View
                                    </Link>

                                    <Link
                                        v-if="customerReceipt.can.update"
                                        :href="route('customer-receipts.edit', customerReceipt.id)"
                                        class="rounded-lg border border-brand-300 bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-100 dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-300 dark:hover:bg-brand-500/20"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="customerReceipt.can.delete"
                                        type="button"
                                        class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20"
                                        @click="deleteCustomerReceipt(customerReceipt)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="props.customerReceipts.data.length === 0"
                        >
                            <td
                                colspan="7"
                                class="px-5 py-16 text-center"
                            >
                                <p
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    No Customer Receipts found.
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Adjust the filters or create a new draft receipt.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Showing
                    {{ props.customerReceipts.meta.from ?? 0 }}
                    to
                    {{ props.customerReceipts.meta.to ?? 0 }}
                    of
                    {{ props.customerReceipts.meta.total }}
                    Customer Receipts
                </p>

                <div
                    v-if="props.customerReceipts.meta.last_page > 1"
                    class="flex flex-wrap items-center gap-1"
                >
                    <button
                        type="button"
                        :disabled="props.customerReceipts.meta.current_page <= 1"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="goToPage(props.customerReceipts.meta.current_page - 1)"
                    >
                        Previous
                    </button>

                    <template
                        v-for="item in paginationItems"
                        :key="item"
                    >
                        <span
                            v-if="typeof item !== 'number'"
                            class="px-2 py-2 text-sm text-gray-400"
                        >
                            …
                        </span>

                        <button
                            v-else
                            type="button"
                            class="h-9 min-w-9 rounded-lg border px-3 text-sm font-medium transition"
                            :class="
                                item === props.customerReceipts.meta.current_page
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
                        :disabled="props.customerReceipts.meta.current_page >= props.customerReceipts.meta.last_page"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="goToPage(props.customerReceipts.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>