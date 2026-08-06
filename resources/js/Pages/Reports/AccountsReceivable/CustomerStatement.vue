<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import {
    computed,
    reactive,
} from 'vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    CustomerStatementFilters,
    CustomerStatementPageProps,
} from '@/Types/accounts-receivable-report';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<CustomerStatementPageProps>();
const { can } = useAuthorization();
const canExport = computed((): boolean => can('exports.create'));

const filters = reactive<CustomerStatementFilters>({
    ...props.filters,
});

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
        date_from: filters.date_from,
        date_to: filters.date_to,
        per_page: filters.per_page,
    };

    if (filters.customer_id !== null) {
        query.customer_id = filters.customer_id;
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
        route('reports.accounts-receivable.customer-statement'),
        queryParameters(),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const resetFilters = (): void => {
    const now = new Date();
    const local = new Date(
        now.getTime() - now.getTimezoneOffset() * 60_000,
    );
    const dateTo = local.toISOString().slice(0, 10);

    filters.customer_id = null;
    filters.branch_id = null;
    filters.currency_code = null;
    filters.date_from = `${dateTo.slice(0, 8)}01`;
    filters.date_to = dateTo;
    filters.per_page = 25;
    applyFilters();
};

const goToPage = (page: number): void => {
    if (props.report === null) {
        return;
    }

    const meta = props.report.entries.meta;

    if (page < 1 || page > meta.last_page || page === meta.current_page) {
        return;
    }

    router.get(
        route('reports.accounts-receivable.customer-statement'),
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
    if (props.report === null) {
        return [];
    }

    const current = props.report.entries.meta.current_page;
    const last = props.report.entries.meta.last_page;
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

const agingHref = computed((): string | null => {
    if (filters.customer_id === null) {
        return null;
    }

    return route(
        'reports.accounts-receivable.aging.customers.show',
        {
            customerId: filters.customer_id,
            branch_id: filters.branch_id ?? undefined,
            currency_code: filters.currency_code ?? undefined,
            as_of_date: filters.date_to,
        },
    );
});

const requestExport = (format: 'csv' | 'xlsx'): void => {
    if (props.report === null) {
        return;
    }

    router.post(
        route('exports.store'),
        {
            export_type: 'customer_statement',
            format,
            filters: queryParameters(),
        },
        {
            preserveScroll: true,
        },
    );
};

const printReport = (): void => {
    if (props.report === null) {
        return;
    }

    window.open(
        route(
            'reports.accounts-receivable.customer-statement.print',
            queryParameters(),
        ),
        '_blank',
        'noopener,noreferrer',
    );
};

const balanceClass = (value: string | number): string => {
    const amount = decimalValue(value);

    if (amount > 0.000001) {
        return 'text-error-600 dark:text-error-400';
    }

    if (amount < -0.000001) {
        return 'text-success-600 dark:text-success-400';
    }

    return 'text-gray-900 dark:text-white';
};
</script>

<template>
    <Head title="Customer Statement" />

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
                    <span class="text-gray-700 dark:text-gray-300">Customer Statement</span>
                </div>

                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Customer Statement
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Chronological customer subledger activity with transaction-currency
                    and base-currency running balances.
                </p>
            </div>

            <div v-if="report !== null" class="flex flex-wrap gap-2">
                <Link
                    v-if="agingHref !== null"
                    :href="agingHref"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                >
                    Customer Aging
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

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Customer</label>
                    <select
                        v-model="filters.customer_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">Select a customer</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }} ({{ customer.code }})
                        </option>
                    </select>
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
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Per Page</label>
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
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date From</label>
                    <input
                        v-model="filters.date_from"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Date To</label>
                    <input
                        v-model="filters.date_to"
                        :min="filters.date_from"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
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
                    :disabled="filters.customer_id === null"
                    type="button"
                    class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                    @click="applyFilters"
                >
                    Generate Statement
                </button>
            </div>
        </div>

        <div
            v-if="report === null"
            class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-16 text-center dark:border-gray-700 dark:bg-gray-900"
        >
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Select a customer
            </h2>
            <p class="mt-2 text-sm text-gray-500">
                Choose a customer and date range to generate the statement.
            </p>
        </div>

        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Opening Balance</p>
                    <p :class="['mt-2 text-xl font-semibold', balanceClass(report.summary.base.opening_balance)]">
                        {{ report.base_currency_code }} {{ formatAmount(report.summary.base.opening_balance) }}
                    </p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Period Debit</p>
                    <p class="mt-2 text-xl font-semibold text-error-600 dark:text-error-400">
                        {{ report.base_currency_code }} {{ formatAmount(report.summary.base.period_debit) }}
                    </p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Period Credit</p>
                    <p class="mt-2 text-xl font-semibold text-success-600 dark:text-success-400">
                        {{ report.base_currency_code }} {{ formatAmount(report.summary.base.period_credit) }}
                    </p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Closing Balance</p>
                    <p :class="['mt-2 text-xl font-semibold', balanceClass(report.summary.base.closing_balance)]">
                        {{ report.base_currency_code }} {{ formatAmount(report.summary.base.closing_balance) }}
                    </p>
                </div>
            </div>

            <div v-if="report.summary.currencies.length > 0" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="summary in report.summary.currencies"
                    :key="summary.currency_code"
                    class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
                >
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ summary.currency_code }}</h3>
                        <span :class="['text-sm font-semibold', balanceClass(summary.closing_balance)]">
                            {{ formatAmount(summary.closing_balance) }}
                        </span>
                    </div>
                    <dl class="mt-4 grid grid-cols-3 gap-3 text-xs">
                        <div>
                            <dt class="text-gray-500">Opening</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ formatAmount(summary.opening_balance) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Debit</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ formatAmount(summary.period_debit) }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500">Credit</dt>
                            <dd class="mt-1 font-medium text-gray-900 dark:text-white">{{ formatAmount(summary.period_credit) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 p-5 dark:border-gray-800 sm:p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ report.customer.name }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ report.customer.code }} · {{ formatDate(report.filters.date_from) }} to
                        {{ formatDate(report.filters.date_to) }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[1450px] divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-white/[0.03]">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Date / Reference</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Branch</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Currency</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Debit</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Credit</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Currency Balance</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Base Debit</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Base Credit</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Base Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-if="report.entries.data.length === 0">
                                <td colspan="10" class="px-5 py-14 text-center text-sm text-gray-500">
                                    No customer-ledger activity was found in this period.
                                </td>
                            </tr>
                            <tr v-for="entry in report.entries.data" :key="entry.id">
                                <td class="px-4 py-4">
                                    <p class="font-medium text-gray-900 dark:text-white">{{ formatDate(entry.posting_date) }}</p>
                                    <p class="text-xs text-gray-500">{{ entry.source_document_number ?? entry.reference }}</p>
                                    <p class="text-xs text-gray-500">{{ entry.journal_reference }}</p>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    <p>{{ entry.entry_type_label }}</p>
                                    <p v-if="entry.reversal_of" class="text-xs text-error-500">
                                        Reversal of {{ entry.reversal_of.source_document_number ?? entry.reversal_of.reference }}
                                    </p>
                                    <p v-if="entry.description" class="mt-1 max-w-sm text-xs text-gray-500">{{ entry.description }}</p>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    {{ entry.branch.name ?? '—' }}
                                    <p class="text-xs text-gray-500">{{ entry.branch.code ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300">
                                    {{ entry.currency_code }}
                                    <p class="text-xs text-gray-500">Rate {{ formatRate(entry.exchange_rate) }}</p>
                                </td>
                                <td class="px-4 py-4 text-right text-sm text-error-600 dark:text-error-400">{{ formatAmount(entry.debit_amount) }}</td>
                                <td class="px-4 py-4 text-right text-sm text-success-600 dark:text-success-400">{{ formatAmount(entry.credit_amount) }}</td>
                                <td :class="['px-4 py-4 text-right text-sm font-semibold', balanceClass(entry.currency_running_balance)]">
                                    {{ formatAmount(entry.currency_running_balance) }}
                                </td>
                                <td class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatAmount(entry.base_debit_amount) }}</td>
                                <td class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatAmount(entry.base_credit_amount) }}</td>
                                <td :class="['px-4 py-4 text-right text-sm font-semibold', balanceClass(entry.base_running_balance)]">
                                    {{ formatAmount(entry.base_running_balance) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-gray-500">
                        Showing {{ report.entries.meta.from ?? 0 }} to {{ report.entries.meta.to ?? 0 }}
                        of {{ report.entries.meta.total }} entries
                    </p>
                    <div v-if="report.entries.meta.last_page > 1" class="flex items-center gap-1">
                        <button
                            :disabled="report.entries.meta.current_page <= 1"
                            type="button"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                            @click="goToPage(report.entries.meta.current_page - 1)"
                        >
                            Previous
                        </button>
                        <button
                            v-for="page in visiblePages"
                            :key="page"
                            type="button"
                            :class="[
                                'min-w-10 rounded-lg px-3 py-2 text-sm',
                                page === report.entries.meta.current_page
                                    ? 'bg-brand-500 text-white'
                                    : 'border border-gray-300 dark:border-gray-700',
                            ]"
                            @click="goToPage(page)"
                        >
                            {{ page }}
                        </button>
                        <button
                            :disabled="report.entries.meta.current_page >= report.entries.meta.last_page"
                            type="button"
                            class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                            @click="goToPage(report.entries.meta.current_page + 1)"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
