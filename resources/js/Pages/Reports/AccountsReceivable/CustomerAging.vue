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
    AccountsReceivableAgingFilters,
    CustomerAgingOpenItem,
    CustomerAgingPageProps,
} from '@/Types/accounts-receivable-report';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<CustomerAgingPageProps>();
const { can } = useAuthorization();
const canExport = computed((): boolean => can('exports.create'));

const filters = reactive<AccountsReceivableAgingFilters>({
    ...props.report.filters,
    customer_id: props.report.customer.id,
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

const formatRate = (value: string | number): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 8,
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

    if (filters.currency_code !== null) {
        query.currency_code = filters.currency_code;
    }

    return query;
};

const applyFilters = (): void => {
    router.get(
        route(
            'reports.accounts-receivable.aging.customers.show',
            props.report.customer.id,
        ),
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
    filters.search = '';
    filters.branch_id = null;
    filters.currency_code = null;
    filters.per_page = 25;
    applyFilters();
};

const goToPage = (page: number): void => {
    const meta = props.report.items.meta;

    if (page < 1 || page > meta.last_page || page === meta.current_page) {
        return;
    }

    router.get(
        route(
            'reports.accounts-receivable.aging.customers.show',
            props.report.customer.id,
        ),
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
    const current = props.report.items.meta.current_page;
    const last = props.report.items.meta.last_page;
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
            export_type: 'customer_aging_detail',
            format,
            filters: {
                customer_id: props.report.customer.id,
                ...queryParameters(),
            },
        },
        {
            preserveScroll: true,
        },
    );
};

const printReport = (): void => {
    window.open(
        route(
            'reports.accounts-receivable.aging.customers.print',
            {
                customerId: props.report.customer.id,
                ...queryParameters(),
            },
        ),
        '_blank',
        'noopener,noreferrer',
    );
};

const statementHref = computed((): string => {
    return route(
        'reports.accounts-receivable.customer-statement',
        {
            customer_id: props.report.customer.id,
            branch_id: filters.branch_id ?? undefined,
            currency_code: filters.currency_code ?? undefined,
            date_to: filters.as_of_date,
        },
    );
});

const bucketClass = (item: CustomerAgingOpenItem): string => {
    if (item.balance_side === 'credit') {
        return 'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-300';
    }

    if (item.bucket_key === 'current') {
        return 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
    }

    if (
        item.bucket_key === 'days_1_30'
        || item.bucket_key === 'days_31_60'
    ) {
        return 'bg-warning-50 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300';
    }

    return 'bg-error-50 text-error-700 dark:bg-error-900/30 dark:text-error-300';
};
</script>

<template>
    <Head :title="`${report.customer.name} Aging`" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <Link
                        :href="route('reports.accounts-receivable.aging')"
                        class="hover:text-brand-500"
                    >
                        Accounts Receivable Aging
                    </Link>
                    <span>/</span>
                    <span class="text-gray-700 dark:text-gray-300">
                        {{ report.customer.name }}
                    </span>
                </div>

                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    {{ report.customer.name }} Aging
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ report.customer.code }} · {{ report.customer.customer_type }} ·
                    as of {{ formatDate(report.filters.as_of_date) }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="statementHref"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                >
                    Customer Statement
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
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Gross Receivable</p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ report.base_currency_code }} {{ formatAmount(report.summary.total_receivable) }}
                </p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Unapplied Credit</p>
                <p class="mt-2 text-xl font-semibold text-success-600 dark:text-success-400">
                    {{ report.base_currency_code }} {{ formatAmount(report.summary.unapplied_credit) }}
                </p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Net Outstanding</p>
                <p class="mt-2 text-xl font-semibold text-brand-600 dark:text-brand-400">
                    {{ report.base_currency_code }} {{ formatAmount(report.summary.net_outstanding) }}
                </p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Over 90 Days</p>
                <p class="mt-2 text-xl font-semibold text-error-600 dark:text-error-400">
                    {{ report.base_currency_code }}
                    {{ formatAmount(decimalValue(report.summary.days_91_120) + decimalValue(report.summary.days_over_120)) }}
                </p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Credit Limit</p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ decimalValue(report.customer.credit_limit ?? '0') > 0
                        ? `${report.base_currency_code} ${formatAmount(report.customer.credit_limit ?? '0')}`
                        : 'Unlimited' }}
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Document Search
                    </label>
                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Invoice, receipt, journal, or reference"
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
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Currency</label>
                    <select
                        v-model="filters.currency_code"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">All currencies</option>
                        <option v-for="currency in currencies" :key="currency" :value="currency">
                            {{ currency }}
                        </option>
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
                <table class="min-w-[1400px] divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.03]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Document</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Type</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Branch</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Dates</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Currency</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Original</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Allocated</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Outstanding</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Base Outstanding</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Aging</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-if="report.items.data.length === 0">
                            <td colspan="10" class="px-5 py-14 text-center text-sm text-gray-500">
                                No open items found for this customer.
                            </td>
                        </tr>
                        <tr v-for="item in report.items.data" :key="item.id">
                            <td class="px-4 py-4">
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ item.document_number ?? item.reference }}
                                </p>
                                <p class="text-xs text-gray-500">{{ item.journal_reference }}</p>
                                <p v-if="item.description" class="mt-1 max-w-xs text-xs text-gray-500">
                                    {{ item.description }}
                                </p>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                <p>{{ item.item_type_label }}</p>
                                <p class="text-xs text-gray-500">{{ item.entry_type_label }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ item.branch.name }}
                                <p class="text-xs text-gray-500">{{ item.branch.code }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                <p>Document: {{ formatDate(item.document_date) }}</p>
                                <p>Due: {{ formatDate(item.due_date) }}</p>
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ item.currency_code }}
                                <p class="text-xs text-gray-500">Rate {{ formatRate(item.exchange_rate) }}</p>
                            </td>
                            <td class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                {{ formatAmount(item.original_amount) }}
                            </td>
                            <td class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                {{ formatAmount(item.historical_allocated_amount) }}
                            </td>
                            <td
                                :class="[
                                    'px-4 py-4 text-right text-sm font-semibold',
                                    item.balance_side === 'credit'
                                        ? 'text-success-600 dark:text-success-400'
                                        : 'text-gray-900 dark:text-white',
                                ]"
                            >
                                {{ formatAmount(item.outstanding_amount) }}
                            </td>
                            <td class="px-4 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                {{ formatAmount(item.base_outstanding_amount) }}
                            </td>
                            <td class="px-4 py-4">
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                        bucketClass(item),
                                    ]"
                                >
                                    {{ item.bucket_label }}
                                </span>
                                <p v-if="item.days_overdue !== null" class="mt-1 text-xs text-gray-500">
                                    {{ item.days_overdue > 0 ? `${item.days_overdue} days overdue` : 'Not overdue' }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500">
                    Showing {{ report.items.meta.from ?? 0 }} to {{ report.items.meta.to ?? 0 }}
                    of {{ report.items.meta.total }} open items
                </p>
                <div v-if="report.items.meta.last_page > 1" class="flex items-center gap-1">
                    <button
                        :disabled="report.items.meta.current_page <= 1"
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                        @click="goToPage(report.items.meta.current_page - 1)"
                    >
                        Previous
                    </button>
                    <button
                        v-for="page in visiblePages"
                        :key="page"
                        type="button"
                        :class="[
                            'min-w-10 rounded-lg px-3 py-2 text-sm',
                            page === report.items.meta.current_page
                                ? 'bg-brand-500 text-white'
                                : 'border border-gray-300 dark:border-gray-700',
                        ]"
                        @click="goToPage(page)"
                    >
                        {{ page }}
                    </button>
                    <button
                        :disabled="report.items.meta.current_page >= report.items.meta.last_page"
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                        @click="goToPage(report.items.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
