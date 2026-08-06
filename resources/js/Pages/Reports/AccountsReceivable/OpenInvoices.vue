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

import { useAuthorization } from '@/Composables/useAuthorization';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    OpenInvoiceFilters,
    OpenInvoicePageProps,
    OpenInvoiceSort,
} from '@/Types/accounts-receivable-report';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<OpenInvoicePageProps>();
const { can } = useAuthorization();
const canExport = computed((): boolean => can('exports.create'));

const filters = reactive<OpenInvoiceFilters>({
    ...props.report.filters,
});

let searchTimer: ReturnType<typeof setTimeout> | null = null;

const isOverdue = computed((): boolean => props.report.mode === 'overdue');
const pageTitle = computed((): string => isOverdue.value
    ? 'Overdue Customer Invoices'
    : 'Open Customer Invoices');

const routeName = computed((): string => isOverdue.value
    ? 'reports.accounts-receivable.overdue-invoices'
    : 'reports.accounts-receivable.open-invoices');

const printRouteName = computed((): string => isOverdue.value
    ? 'reports.accounts-receivable.overdue-invoices.print'
    : 'reports.accounts-receivable.open-invoices.print');

const decimalValue = (
    value: string | number | null | undefined,
): number => {
    const parsed = Number.parseFloat(String(value ?? '0'));

    return Number.isFinite(parsed) ? parsed : 0;
};

const formatAmount = (value: string | number): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    }).format(decimalValue(value));
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    const [year, month, day] = value.split('-').map(Number);

    if (!year || !month || !day) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(Date.UTC(year, month - 1, day)));
};

const queryParameters = (): Record<string, string | number> => {
    const query: Record<string, string | number> = {
        as_of_date: filters.as_of_date,
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

    if (filters.currency_code !== null) {
        query.currency_code = filters.currency_code;
    }

    return query;
};

const applyFilters = (): void => {
    router.get(
        route(routeName.value),
        queryParameters(),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

watch(
    () => filters.search,
    (): void => {
        if (searchTimer !== null) {
            clearTimeout(searchTimer);
        }

        searchTimer = setTimeout(applyFilters, 400);
    },
);

onBeforeUnmount((): void => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
});

const resetFilters = (): void => {
    filters.branch_id = null;
    filters.customer_id = null;
    filters.currency_code = null;
    filters.search = '';
    filters.sort = 'due_date';
    filters.direction = 'asc';
    filters.per_page = 25;
    applyFilters();
};

const sortBy = (sort: OpenInvoiceSort): void => {
    if (filters.sort === sort) {
        filters.direction = filters.direction === 'asc' ? 'desc' : 'asc';
    } else {
        filters.sort = sort;
        filters.direction = sort === 'due_date' ? 'asc' : 'desc';
    }

    applyFilters();
};

const sortIndicator = (sort: OpenInvoiceSort): string => {
    if (filters.sort !== sort) {
        return '';
    }

    return filters.direction === 'asc' ? '↑' : '↓';
};

const goToPage = (page: number): void => {
    const meta = props.report.invoices.meta;

    if (page < 1 || page > meta.last_page || page === meta.current_page) {
        return;
    }

    router.get(
        route(routeName.value),
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

const visiblePages = computed((): number[] => {
    const current = props.report.invoices.meta.current_page;
    const last = props.report.invoices.meta.last_page;
    const pages: number[] = [];

    for (
        let page = Math.max(1, current - 2);
        page <= Math.min(last, current + 2);
        page += 1
    ) {
        pages.push(page);
    }

    return pages;
});

const requestExport = (format: 'csv' | 'xlsx'): void => {
    router.post(
        route('exports.store'),
        {
            export_type: 'accounts_receivable_open_invoices',
            format,
            filters: {
                ...queryParameters(),
                overdue_only: isOverdue.value,
            },
        },
        {
            preserveScroll: true,
        },
    );
};

const printReport = (): void => {
    window.open(
        route(printRouteName.value, queryParameters()),
        '_blank',
        'noopener,noreferrer',
    );
};

const customerAgingHref = (customerId: number): string => {
    return route(
        'reports.accounts-receivable.aging.customers.show',
        {
            customerId,
            branch_id: filters.branch_id ?? undefined,
            currency_code: filters.currency_code ?? undefined,
            as_of_date: filters.as_of_date,
        },
    );
};

const customerStatementHref = (customerId: number): string => {
    return route(
        'reports.accounts-receivable.customer-statement',
        {
            customer_id: customerId,
            branch_id: filters.branch_id ?? undefined,
            currency_code: filters.currency_code ?? undefined,
            date_to: filters.as_of_date,
        },
    );
};
</script>

<template>
    <Head :title="pageTitle" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="mb-2 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <Link
                        :href="route('reports.accounts-receivable.aging')"
                        class="hover:text-brand-500"
                    >
                        Accounts Receivable Reports
                    </Link>
                    <span>/</span>
                    <span class="text-gray-700 dark:text-gray-300">{{ pageTitle }}</span>
                </div>

                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ pageTitle }}
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Invoice balances reconstructed as of {{ formatDate(report.filters.as_of_date) }}.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route(isOverdue
                        ? 'reports.accounts-receivable.open-invoices'
                        : 'reports.accounts-receivable.overdue-invoices')"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                >
                    {{ isOverdue ? 'View All Open' : 'View Overdue Only' }}
                </Link>
                <button
                    v-if="canExport"
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    @click="requestExport('csv')"
                >
                    CSV
                </button>
                <button
                    v-if="canExport"
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    @click="requestExport('xlsx')"
                >
                    Excel
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
                    @click="printReport"
                >
                    Print
                </button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Invoices</p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">{{ report.summary.invoice_count }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Customers</p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">{{ report.summary.customer_count }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Original Base Amount</p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ report.base_currency_code }} {{ formatAmount(report.summary.base_original_amount) }}
                </p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Outstanding Base</p>
                <p class="mt-2 text-xl font-semibold text-brand-600 dark:text-brand-400">
                    {{ report.base_currency_code }} {{ formatAmount(report.summary.base_outstanding_amount) }}
                </p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Overdue Base</p>
                <p class="mt-2 text-xl font-semibold text-error-600 dark:text-error-400">
                    {{ report.base_currency_code }} {{ formatAmount(report.summary.overdue_base_amount) }}
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Invoice, customer, journal, or reference"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">As of Date</label>
                    <input
                        v-model="filters.as_of_date"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Branch</label>
                    <select
                        v-model="filters.branch_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">All branches</option>
                        <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                            {{ branch.name }} ({{ branch.code }})
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Customer</label>
                    <select
                        v-model="filters.customer_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">All customers</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }} ({{ customer.code }})
                        </option>
                    </select>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Currency</label>
                    <select
                        v-model="filters.currency_code"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">All currencies</option>
                        <option v-for="currency in currencies" :key="currency" :value="currency">{{ currency }}</option>
                    </select>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    @click="resetFilters"
                >
                    Reset
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white"
                    @click="applyFilters"
                >
                    Apply
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-[1450px] divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.03]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('document_number')">Invoice {{ sortIndicator('document_number') }}</button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('customer_name')">Customer {{ sortIndicator('customer_name') }}</button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Branch</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('document_date')">Invoice Date {{ sortIndicator('document_date') }}</button>
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('due_date')">Due Date {{ sortIndicator('due_date') }}</button>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('original_amount')">Original {{ sortIndicator('original_amount') }}</button>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Allocated</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('outstanding_amount')">Outstanding {{ sortIndicator('outstanding_amount') }}</button>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Base Outstanding</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('days_overdue')">Aging {{ sortIndicator('days_overdue') }}</button>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-if="report.invoices.data.length === 0">
                            <td colspan="11" class="px-5 py-14 text-center text-sm text-gray-500">
                                No {{ isOverdue ? 'overdue' : 'open' }} customer invoices were found.
                            </td>
                        </tr>
                        <tr v-for="invoice in report.invoices.data" :key="invoice.id">
                            <td class="px-4 py-4">
                                <p class="font-medium text-gray-900 dark:text-white">{{ invoice.document_number ?? invoice.reference }}</p>
                                <p class="text-xs text-gray-500">{{ invoice.journal_reference }}</p>
                            </td>
                            <td class="px-4 py-4">
                                <Link
                                    :href="customerAgingHref(invoice.customer.id)"
                                    class="font-medium text-brand-600 dark:text-brand-400"
                                >
                                    {{ invoice.customer.name }}
                                </Link>
                                <p class="text-xs text-gray-500">{{ invoice.customer.code }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ invoice.branch.name }}
                                <p class="text-xs text-gray-500">{{ invoice.branch.code }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">{{ formatDate(invoice.document_date) }}</td>
                            <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">{{ formatDate(invoice.due_date) }}</td>
                            <td class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                {{ invoice.currency_code }} {{ formatAmount(invoice.original_amount) }}
                            </td>
                            <td class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatAmount(invoice.historical_allocated_amount) }}</td>
                            <td class="px-4 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ formatAmount(invoice.outstanding_amount) }}</td>
                            <td class="px-4 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ formatAmount(invoice.base_outstanding_amount) }}</td>
                            <td class="px-4 py-4">
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                        (invoice.days_overdue ?? 0) > 60
                                            ? 'bg-error-50 text-error-700 dark:bg-error-900/30 dark:text-error-300'
                                            : (invoice.days_overdue ?? 0) > 0
                                                ? 'bg-warning-50 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300'
                                                : 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
                                    ]"
                                >
                                    {{ invoice.bucket_label }}
                                </span>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ (invoice.days_overdue ?? 0) > 0 ? `${invoice.days_overdue} days` : 'Current' }}
                                </p>
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="inline-flex gap-2">
                                    <Link
                                        :href="customerAgingHref(invoice.customer.id)"
                                        class="text-sm font-medium text-brand-600 dark:text-brand-400"
                                    >
                                        Aging
                                    </Link>
                                    <Link
                                        :href="customerStatementHref(invoice.customer.id)"
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        Statement
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500">
                    Showing {{ report.invoices.meta.from ?? 0 }} to {{ report.invoices.meta.to ?? 0 }}
                    of {{ report.invoices.meta.total }} invoices
                </p>
                <div v-if="report.invoices.meta.last_page > 1" class="flex items-center gap-1">
                    <button
                        :disabled="report.invoices.meta.current_page <= 1"
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                        @click="goToPage(report.invoices.meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        v-for="page in visiblePages"
                        :key="page"
                        type="button"
                        :class="[
                            'min-w-10 rounded-lg px-3 py-2 text-sm',
                            page === report.invoices.meta.current_page
                                ? 'bg-brand-500 text-white'
                                : 'border border-gray-300 dark:border-gray-700',
                        ]"
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </button>
                    <button
                        :disabled="report.invoices.meta.current_page >= report.invoices.meta.last_page"
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                        @click="goToPage(report.invoices.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
