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
    SalesInvoiceFilters,
    SalesInvoiceIndexProps,
    SalesInvoiceSort,
    SalesInvoiceStatus,
    SalesInvoiceSummary,
} from '@/Types/sales-invoice';

defineOptions({
    layout: ErpLayout,
});

const props =
    defineProps<SalesInvoiceIndexProps>();

const filters =
    reactive<SalesInvoiceFilters>({
        search:
            props.filters.search ?? '',

        branch_id:
            props.filters.branch_id
            ?? null,

        customer_id:
            props.filters.customer_id
            ?? null,

        status:
            props.filters.status ?? '',

        posting_date_from:
            props.filters
                .posting_date_from
            ?? '',

        posting_date_to:
            props.filters
                .posting_date_to
            ?? '',

        sort:
            props.filters.sort
            ?? 'created_at',

        direction:
            props.filters.direction
            ?? 'desc',

        per_page:
            props.filters.per_page
            ?? 15,
    });

let searchTimer:
    ReturnType<typeof setTimeout>
    | null = null;

const hasFilters = computed(
    (): boolean =>
        filters.search.trim() !== ''
        || filters.branch_id !== null
        || filters.customer_id !== null
        || filters.status !== ''
        || filters.posting_date_from
            !== ''
        || filters.posting_date_to
            !== '',
);

const query = (
    page?: number,
): Record<string, string | number> => {
    const result: Record<
        string,
        string | number
    > = {
        sort: filters.sort,
        direction: filters.direction,
        per_page: filters.per_page,
    };

    if (page !== undefined) {
        result.page = page;
    }

    if (filters.search.trim() !== '') {
        result.search =
            filters.search.trim();
    }

    if (filters.branch_id !== null) {
        result.branch_id =
            filters.branch_id;
    }

    if (
        filters.customer_id !== null
    ) {
        result.customer_id =
            filters.customer_id;
    }

    if (filters.status !== '') {
        result.status = filters.status;
    }

    if (
        filters.posting_date_from
        !== ''
    ) {
        result.posting_date_from =
            filters.posting_date_from;
    }

    if (
        filters.posting_date_to
        !== ''
    ) {
        result.posting_date_to =
            filters.posting_date_to;
    }

    return result;
};

const visit = (
    page = 1,
): void => {
    router.get(
        route('sales-invoices.index'),
        query(page),
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
            () => visit(1),
            400,
        );
    },
);

onBeforeUnmount(() => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
});

const reset = (): void => {
    router.get(
        route('sales-invoices.index'),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const sortBy = (
    column: SalesInvoiceSort,
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

    visit(1);
};

const indicator = (
    column: SalesInvoiceSort,
): string => {
    if (filters.sort !== column) {
        return '';
    }

    return filters.direction === 'asc'
        ? '↑'
        : '↓';
};

const pages = computed(
    (): number[] => {
        const current =
            props.salesInvoices
                .meta
                .current_page;

        const last =
            props.salesInvoices
                .meta
                .last_page;

        const result: number[] = [];

        for (
            let page = Math.max(
                1,
                current - 2,
            );
            page <= Math.min(
                last,
                current + 2,
            );
            page += 1
        ) {
            result.push(page);
        }

        return result;
    },
);

const formatDate = (
    value: string | null,
): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(
        `${value}T00:00:00`,
    );

    if (
        Number.isNaN(
            date.getTime(),
        )
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
    ).format(date);
};

const formatAmount = (
    value: string,
): string => {
    const amount =
        Number.parseFloat(value);

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

const statusClass = (
    status: SalesInvoiceStatus,
): string => {
    if (status === 'posted') {
        return 'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-300';
    }

    if (status === 'reversed') {
        return 'bg-error-50 text-error-700 dark:bg-error-900/30 dark:text-error-300';
    }

    return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
};

const remove = (
    invoice: SalesInvoiceSummary,
): void => {
    if (
        !window.confirm(
            `Delete Sales Invoice draft #${invoice.id}?`,
        )
    ) {
        return;
    }

    router.delete(
        route(
            'sales-invoices.destroy',
            invoice.id,
        ),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Sales Invoices" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Sales Invoices
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Manage dispatch-based customer invoices
                    and Accounts Receivable posting.
                </p>
            </div>

            <Link
                v-if="can.create"
                :href="
                    route(
                        'sales-invoices.create',
                    )
                "
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
            >
                Create Sales Invoice
            </Link>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
        >
            <div
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-6"
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
                        placeholder="Invoice, Sales Order, customer, or code"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
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
                        Per Page
                    </label>

                    <select
                        v-model="filters.per_page"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
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

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Posting From
                    </label>

                    <input
                        v-model="
                            filters.posting_date_from
                        "
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Posting To
                    </label>

                    <input
                        v-model="
                            filters.posting_date_to
                        "
                        :min="
                            filters.posting_date_from
                        "
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>
            </div>

            <div
                class="mt-5 flex justify-end gap-3"
            >
                <button
                    v-if="hasFilters"
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    @click="reset"
                >
                    Reset
                </button>

                <button
                    type="button"
                    class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
                    @click="visit(1)"
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
                    class="min-w-[1050px] divide-y divide-gray-200 dark:divide-gray-800"
                >
                    <thead
                        class="bg-gray-50 dark:bg-white/[0.03]"
                    >
                        <tr>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    @click="
                                        sortBy(
                                            'invoice_number',
                                        )
                                    "
                                >
                                    Invoice
                                    {{
                                        indicator(
                                            'invoice_number',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Sales Order
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    @click="
                                        sortBy(
                                            'customer_name',
                                        )
                                    "
                                >
                                    Customer
                                    {{
                                        indicator(
                                            'customer_name',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    @click="
                                        sortBy(
                                            'invoice_date',
                                        )
                                    "
                                >
                                    Invoice Date
                                    {{
                                        indicator(
                                            'invoice_date',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    @click="
                                        sortBy(
                                            'due_date',
                                        )
                                    "
                                >
                                    Due Date
                                    {{
                                        indicator(
                                            'due_date',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    @click="
                                        sortBy(
                                            'total_amount',
                                        )
                                    "
                                >
                                    Total
                                    {{
                                        indicator(
                                            'total_amount',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    @click="
                                        sortBy(
                                            'status',
                                        )
                                    "
                                >
                                    Status
                                    {{
                                        indicator(
                                            'status',
                                        )
                                    }}
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
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-if="
                                salesInvoices.data.length
                                    === 0
                            "
                        >
                            <td
                                colspan="8"
                                class="px-5 py-14 text-center text-sm text-gray-500"
                            >
                                No Sales Invoices found.
                            </td>
                        </tr>

                        <tr
                            v-for="invoice in salesInvoices.data"
                            :key="invoice.id"
                            class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]"
                        >
                            <td class="px-5 py-4">
                                <Link
                                    :href="
                                        route(
                                            'sales-invoices.show',
                                            invoice.id,
                                        )
                                    "
                                    class="font-medium text-brand-600 dark:text-brand-400"
                                >
                                    {{
                                        invoice.invoice_number
                                        ?? `Draft #${invoice.id}`
                                    }}
                                </Link>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    invoice.sales_order_number
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        invoice.customer_name
                                    }}
                                </p>

                                <p
                                    class="text-xs text-gray-500"
                                >
                                    {{
                                        invoice.customer_code
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatDate(
                                        invoice.invoice_date,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatDate(
                                        invoice.due_date,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    invoice.currency_code
                                }}
                                {{
                                    formatAmount(
                                        invoice.total_amount,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                        statusClass(
                                            invoice.status,
                                        ),
                                    ]"
                                >
                                    {{
                                        invoice.status_label
                                    }}
                                </span>
                            </td>

                            <td
                                class="px-5 py-4 text-right"
                            >
                                <div
                                    class="inline-flex gap-2"
                                >
                                    <Link
                                        v-if="
                                            invoice.can.view
                                        "
                                        :href="
                                            route(
                                                'sales-invoices.show',
                                                invoice.id,
                                            )
                                        "
                                        class="text-sm font-medium text-brand-600 dark:text-brand-400"
                                    >
                                        View
                                    </Link>

                                    <Link
                                        v-if="
                                            invoice.can.update
                                        "
                                        :href="
                                            route(
                                                'sales-invoices.edit',
                                                invoice.id,
                                            )
                                        "
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="
                                            invoice.can.delete
                                        "
                                        type="button"
                                        class="text-sm font-medium text-error-500"
                                        @click="
                                            remove(invoice)
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
                <p class="text-sm text-gray-500">
                    Showing
                    {{
                        salesInvoices.meta.from
                        ?? 0
                    }}
                    to
                    {{
                        salesInvoices.meta.to
                        ?? 0
                    }}
                    of
                    {{
                        salesInvoices.meta.total
                    }}
                </p>

                <div
                    v-if="
                        salesInvoices.meta.last_page
                            > 1
                    "
                    class="flex items-center gap-1"
                >
                    <button
                        :disabled="
                            salesInvoices.meta.current_page
                                <= 1
                        "
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                        @click="
                            visit(
                                salesInvoices.meta.current_page
                                    - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <button
                        v-for="page in pages"
                        :key="page"
                        type="button"
                        :class="[
                            'min-w-10 rounded-lg px-3 py-2 text-sm',
                            page
                                === salesInvoices.meta.current_page
                                ? 'bg-brand-500 text-white'
                                : 'border border-gray-300 dark:border-gray-700',
                        ]"
                        @click="visit(page)"
                    >
                        {{ page }}
                    </button>

                    <button
                        :disabled="
                            salesInvoices.meta.current_page
                                >= salesInvoices.meta.last_page
                        "
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                        @click="
                            visit(
                                salesInvoices.meta.current_page
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