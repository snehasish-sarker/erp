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
    AccountsReceivableAgingBucketKey,
    AccountsReceivableAgingFilters,
    AccountsReceivableAgingPageProps,
    AccountsReceivableAgingSort,
} from '@/Types/accounts-receivable-report';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<AccountsReceivableAgingPageProps>();
const { can } = useAuthorization();
const canExport = computed((): boolean => can('exports.create'));

const filters = reactive<AccountsReceivableAgingFilters>({
    ...props.report.filters,
});

let searchTimer: ReturnType<typeof setTimeout> | null = null;

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

const formatDate = (value: string): string => {
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
        route('reports.accounts-receivable.aging'),
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
    filters.sort = 'net_outstanding';
    filters.direction = 'desc';
    filters.per_page = 25;
    applyFilters();
};

const sortBy = (column: AccountsReceivableAgingSort): void => {
    if (filters.sort === column) {
        filters.direction = filters.direction === 'asc' ? 'desc' : 'asc';
    } else {
        filters.sort = column;
        filters.direction = 'desc';
    }

    applyFilters();
};

const sortIndicator = (column: AccountsReceivableAgingSort): string => {
    if (filters.sort !== column) {
        return '';
    }

    return filters.direction === 'asc' ? '↑' : '↓';
};

const goToPage = (page: number): void => {
    const meta = props.report.customers.meta;

    if (page < 1 || page > meta.last_page || page === meta.current_page) {
        return;
    }

    router.get(
        route('reports.accounts-receivable.aging'),
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
    const current = props.report.customers.meta.current_page;
    const last = props.report.customers.meta.last_page;
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
            export_type: 'accounts_receivable_aging',
            format,
            filters: queryParameters(),
        },
        {
            preserveScroll: true,
        },
    );
};

const printReport = (): void => {
    window.open(
        route(
            'reports.accounts-receivable.aging.print',
            queryParameters(),
        ),
        '_blank',
        'noopener,noreferrer',
    );
};

const customerAgingHref = (customerId: number): string => {
    return route(
        'reports.accounts-receivable.aging.customers.show',
        {
            customerId,
            ...queryParameters(),
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

const reconciliationClass = (value: string): string => {
    return Math.abs(decimalValue(value)) <= 0.000001
        ? 'text-success-600 dark:text-success-400'
        : 'text-error-600 dark:text-error-400';
};

const bucketKeys: AccountsReceivableAgingBucketKey[] = [
    'current',
    'days_1_30',
    'days_31_60',
    'days_61_90',
    'days_91_120',
    'days_over_120',
];

const bucketLabel = (
    bucket: AccountsReceivableAgingBucketKey,
): string => {
    return props.report.buckets.find((item) => item.value === bucket)?.label
        ?? bucket;
};
</script>

<template>
    <Head title="Accounts Receivable Aging" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Accounts Receivable Aging
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Customer receivables, unapplied credits, overdue exposure,
                    and customer-ledger reconciliation as of
                    {{ formatDate(report.filters.as_of_date) }}.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-if="canExport"
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    @click="requestExport('csv')"
                >
                    Export CSV
                </button>

                <button
                    v-if="canExport"
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    @click="requestExport('xlsx')"
                >
                    Export Excel
                </button>

                <button
                    type="button"
                    class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
                    @click="printReport"
                >
                    Print Report
                </button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                    Gross Receivable
                </p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ report.base_currency_code }}
                    {{ formatAmount(report.dashboard.total_receivable) }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                    Unapplied Credits
                </p>
                <p class="mt-2 text-xl font-semibold text-success-600 dark:text-success-400">
                    {{ report.base_currency_code }}
                    {{ formatAmount(report.dashboard.unapplied_credit) }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                    Net Receivable
                </p>
                <p class="mt-2 text-xl font-semibold text-brand-600 dark:text-brand-400">
                    {{ report.base_currency_code }}
                    {{ formatAmount(report.dashboard.net_receivable) }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                    Overdue
                </p>
                <p class="mt-2 text-xl font-semibold text-error-600 dark:text-error-400">
                    {{ report.base_currency_code }}
                    {{ formatAmount(report.dashboard.overdue_receivable) }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ report.dashboard.overdue_ratio }}% of gross receivable
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                    Customers
                </p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ report.dashboard.customer_count }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                    Open Invoices
                </p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ report.dashboard.open_invoice_count }}
                </p>
            </div>
        </div>

        <div
            v-if="Math.abs(decimalValue(report.totals.difference ?? '0')) > 0.000001"
            class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-900/50 dark:bg-error-900/20 dark:text-error-300"
        >
            Customer-ledger and open-item balances differ by
            {{ report.base_currency_code }}
            {{ formatAmount(report.totals.difference ?? '0') }}.
            Review the customer rows highlighted in red.
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Search Customer
                    </label>
                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Customer name or code"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        As of Date
                    </label>
                    <input
                        v-model="filters.as_of_date"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Branch
                    </label>
                    <select
                        v-model="filters.branch_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">All branches</option>
                        <option
                            v-for="branch in branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }} ({{ branch.code }})
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Customer
                    </label>
                    <select
                        v-model="filters.customer_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">All customers</option>
                        <option
                            v-for="customer in customers"
                            :key="customer.id"
                            :value="customer.id"
                        >
                            {{ customer.name }} ({{ customer.code }})
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Currency
                    </label>
                    <select
                        v-model="filters.currency_code"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">All currencies</option>
                        <option
                            v-for="currency in currencies"
                            :key="currency"
                            :value="currency"
                        >
                            {{ currency }}
                        </option>
                    </select>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap justify-end gap-3">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    @click="resetFilters"
                >
                    Reset
                </button>
                <button
                    type="button"
                    class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
                    @click="applyFilters"
                >
                    Apply Filters
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-[1650px] divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.03]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('customer_name')">
                                    Customer {{ sortIndicator('customer_name') }}
                                </button>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('total_receivable')">
                                    Receivable {{ sortIndicator('total_receivable') }}
                                </button>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('unapplied_credit')">
                                    Credits {{ sortIndicator('unapplied_credit') }}
                                </button>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('net_outstanding')">
                                    Net {{ sortIndicator('net_outstanding') }}
                                </button>
                            </th>
                            <th
                                v-for="bucket in bucketKeys"
                                :key="bucket"
                                class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button type="button" @click="sortBy(bucket)">
                                    {{ bucketLabel(bucket) }} {{ sortIndicator(bucket) }}
                                </button>
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Ledger
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Difference
                            </th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-if="report.customers.data.length === 0">
                            <td colspan="14" class="px-5 py-14 text-center text-sm text-gray-500">
                                No Accounts Receivable balances were found.
                            </td>
                        </tr>

                        <tr
                            v-for="row in report.customers.data"
                            :key="row.customer.id"
                            class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]"
                        >
                            <td class="px-4 py-4">
                                <Link
                                    :href="customerAgingHref(row.customer.id)"
                                    class="font-medium text-brand-600 dark:text-brand-400"
                                >
                                    {{ row.customer.name }}
                                </Link>
                                <p class="text-xs text-gray-500">
                                    {{ row.customer.code }} · {{ row.customer.customer_type }} ·
                                    {{ row.open_invoice_count }} invoices
                                </p>
                            </td>
                            <td class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                {{ formatAmount(row.total_receivable) }}
                            </td>
                            <td class="px-4 py-4 text-right text-sm text-success-600 dark:text-success-400">
                                {{ formatAmount(row.unapplied_credit) }}
                            </td>
                            <td class="px-4 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                {{ formatAmount(row.net_outstanding) }}
                            </td>
                            <td
                                v-for="bucket in bucketKeys"
                                :key="bucket"
                                class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ formatAmount(row.buckets[bucket]) }}
                            </td>
                            <td class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                {{ formatAmount(row.ledger_balance) }}
                            </td>
                            <td
                                :class="[
                                    'px-4 py-4 text-right text-sm font-semibold',
                                    reconciliationClass(row.difference),
                                ]"
                            >
                                {{ formatAmount(row.difference) }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                <div class="inline-flex gap-2">
                                    <Link
                                        :href="customerAgingHref(row.customer.id)"
                                        class="text-sm font-medium text-brand-600 dark:text-brand-400"
                                    >
                                        Aging
                                    </Link>
                                    <Link
                                        :href="customerStatementHref(row.customer.id)"
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        Statement
                                    </Link>
                                </div>
                            </td>
                        </tr>
                    </tbody>

                    <tfoot class="bg-gray-50 font-semibold dark:bg-white/[0.03]">
                        <tr>
                            <td class="px-4 py-4 text-sm text-gray-900 dark:text-white">
                                Total
                            </td>
                            <td class="px-4 py-4 text-right text-sm">{{ formatAmount(report.totals.total_receivable) }}</td>
                            <td class="px-4 py-4 text-right text-sm">{{ formatAmount(report.totals.unapplied_credit) }}</td>
                            <td class="px-4 py-4 text-right text-sm">{{ formatAmount(report.totals.net_outstanding) }}</td>
                            <td
                                v-for="bucket in bucketKeys"
                                :key="bucket"
                                class="px-4 py-4 text-right text-sm"
                            >
                                {{ formatAmount(report.totals[bucket]) }}
                            </td>
                            <td class="px-4 py-4 text-right text-sm">{{ formatAmount(report.totals.ledger_balance ?? '0') }}</td>
                            <td
                                :class="[
                                    'px-4 py-4 text-right text-sm',
                                    reconciliationClass(report.totals.difference ?? '0'),
                                ]"
                            >
                                {{ formatAmount(report.totals.difference ?? '0') }}
                            </td>
                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500">
                    Showing {{ report.customers.meta.from ?? 0 }} to
                    {{ report.customers.meta.to ?? 0 }} of
                    {{ report.customers.meta.total }} customers
                </p>

                <div v-if="report.customers.meta.last_page > 1" class="flex items-center gap-1">
                    <button
                        :disabled="report.customers.meta.current_page <= 1"
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                        @click="goToPage(report.customers.meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        v-for="page in visiblePages"
                        :key="page"
                        type="button"
                        :class="[
                            'min-w-10 rounded-lg px-3 py-2 text-sm',
                            page === report.customers.meta.current_page
                                ? 'bg-brand-500 text-white'
                                : 'border border-gray-300 dark:border-gray-700',
                        ]"
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </button>
                    <button
                        :disabled="report.customers.meta.current_page >= report.customers.meta.last_page"
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                        @click="goToPage(report.customers.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
