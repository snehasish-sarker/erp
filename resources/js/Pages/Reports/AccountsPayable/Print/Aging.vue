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
    AccountsPayableAgingBucketKey,
    AccountsPayableAgingFilters,
    AccountsPayableAgingPageProps,
    AccountsPayableAgingSort,
    AccountsPayableAgingSupplierRow,
} from '@/Types/accounts-payable-report';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<AccountsPayableAgingPageProps>();

const filters = reactive<AccountsPayableAgingFilters>({
    ...props.report.filters,
});

const bucketKeys: AccountsPayableAgingBucketKey[] = [
    'current',
    'days_1_30',
    'days_31_60',
    'days_61_90',
    'days_91_120',
    'days_over_120',
];

const bucketLabels = computed(
    (): Record<AccountsPayableAgingBucketKey, string> => {
        const labels = {} as Record<
            AccountsPayableAgingBucketKey,
            string
        >;

        for (const bucket of props.report.buckets) {
            labels[bucket.value] = bucket.label;
        }

        return labels;
    },
);

const hasActiveFilters = computed(
    (): boolean => {
        return filters.search.trim() !== ''
            || filters.branch_id !== null
            || filters.supplier_id !== null
            || filters.currency_code !== null;
    },
);

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

const formatDate = (
    value: string,
): string => {
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
            Date.UTC(
                Number(parts[0]),
                Number(parts[1]) - 1,
                Number(parts[2]),
            ),
        ),
    );
};

const queryParameters = (): Record<
    string,
    string | number
> => {
    const query: Record<
        string,
        string | number
    > = {
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

    if (filters.supplier_id !== null) {
        query.supplier_id = filters.supplier_id;
    }

    if (filters.currency_code !== null) {
        query.currency_code = filters.currency_code;
    }

    return query;
};

const applyFilters = (): void => {
    router.get(
        route('reports.accounts-payable.aging'),
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
    (): void => {
        if (searchTimer !== null) {
            clearTimeout(searchTimer);
        }

        searchTimer = setTimeout(
            applyFilters,
            400,
        );
    },
);

onBeforeUnmount((): void => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
});

const resetFilters = (): void => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }

    filters.search = '';
    filters.branch_id = null;
    filters.supplier_id = null;
    filters.currency_code = null;
    filters.sort = 'net_outstanding';
    filters.direction = 'desc';
    filters.per_page = 25;

    applyFilters();
};

const toggleSort = (
    sort: AccountsPayableAgingSort,
): void => {
    if (filters.sort === sort) {
        filters.direction = filters.direction === 'asc'
            ? 'desc'
            : 'asc';
    } else {
        filters.sort = sort;
        filters.direction = sort === 'supplier_name'
            ? 'asc'
            : 'desc';
    }

    applyFilters();
};

const sortIndicator = (
    sort: AccountsPayableAgingSort,
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
    const meta = props.report.suppliers.meta;

    if (
        page < 1
        || page > meta.last_page
        || page === meta.current_page
    ) {
        return;
    }

    router.get(
        route('reports.accounts-payable.aging'),
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

type PaginationItem = number | 'ellipsis';

const paginationItems = computed<PaginationItem[]>(
    (): PaginationItem[] => {
        const current =
            props.report.suppliers.meta.current_page;

        const last =
            props.report.suppliers.meta.last_page;

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
            items.push('ellipsis');
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
            items.push('ellipsis');
        }

        items.push(last);

        return items;
    },
);

const balanceClass = (
    value: string | number,
): string => {
    const amount = decimalValue(value);

    if (amount > 0.000001) {
        return 'text-red-700 dark:text-red-300';
    }

    if (amount < -0.000001) {
        return 'text-emerald-700 dark:text-emerald-300';
    }

    return 'text-gray-900 dark:text-white';
};

const supplierAgingHref = (
    supplier: AccountsPayableAgingSupplierRow,
): string => {
    return route(
        'reports.accounts-payable.aging.suppliers.show',
        {
            supplierId: supplier.supplier.id,
            ...queryParameters(),
        },
    );
};

const supplierStatementHref = (
    supplier: AccountsPayableAgingSupplierRow,
): string => {
    return route(
        'reports.accounts-payable.supplier-statement',
        {
            supplier_id: supplier.supplier.id,
            branch_id:
                filters.branch_id ?? undefined,
            currency_code:
                filters.currency_code ?? undefined,
            date_to: filters.as_of_date,
        },
    );
};

const printReport = (): void => {
    window.print();
};
</script>

<template>
    <Head title="Accounts Payable Aging" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Accounts Payable Aging
                </h1>

                <p
                    class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400"
                >
                    Review supplier payables, unapplied
                    credits, and overdue exposure reconstructed
                    as of a selected historical date.
                </p>
            </div>

            <div class="flex flex-wrap gap-2 print:hidden">
                <Link
                    :href="route('reports.accounts-payable.supplier-statement')"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Supplier Statement
                </Link>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600"
                    @click="printReport"
                >
                    Print Report
                </button>
            </div>
        </div>

        <section
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm print:hidden dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                <div class="md:col-span-2">
                    <label
                        for="ap-aging-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Search
                    </label>

                    <input
                        id="ap-aging-search"
                        v-model="filters.search"
                        type="search"
                        placeholder="Supplier, code, document, ledger, or journal reference"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                </div>

                <div>
                    <label
                        for="ap-aging-as-of-date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        As-of Date
                    </label>

                    <input
                        id="ap-aging-as-of-date"
                        v-model="filters.as_of_date"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                </div>

                <div>
                    <label
                        for="ap-aging-branch"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Branch
                    </label>

                    <select
                        id="ap-aging-branch"
                        v-model="filters.branch_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="null">
                            All accessible branches
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
                        for="ap-aging-supplier"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier
                    </label>

                    <select
                        id="ap-aging-supplier"
                        v-model="filters.supplier_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="null">
                            All suppliers
                        </option>

                        <option
                            v-for="supplier in props.suppliers"
                            :key="supplier.id"
                            :value="supplier.id"
                        >
                            {{ supplier.code }} —
                            {{ supplier.name }}
                            {{
                                supplier.deleted
                                    ? ' (Deleted)'
                                    : ''
                            }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="ap-aging-currency"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Document Currency
                    </label>

                    <select
                        id="ap-aging-currency"
                        v-model="filters.currency_code"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="null">
                            All currencies
                        </option>

                        <option
                            v-for="currency in props.currencies"
                            :key="currency"
                            :value="currency"
                        >
                            {{ currency }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="ap-aging-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Rows per Page
                    </label>

                    <select
                        id="ap-aging-per-page"
                        v-model="filters.per_page"
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
            </div>

            <div
                class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-5 dark:border-gray-800"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Amounts in the summary and aging buckets
                    are shown in
                    {{ props.report.base_currency_code }}.
                </p>

                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    @click="resetFilters"
                >
                    Reset Filters
                </button>
            </div>
        </section>

        <section
            class="rounded-2xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-500/30 dark:bg-blue-500/10"
        >
            <p
                class="text-xs font-medium uppercase tracking-wide text-blue-700 dark:text-blue-300"
            >
                Historical reconstruction
            </p>

            <p
                class="mt-2 text-sm text-blue-800 dark:text-blue-200"
            >
                This report includes only allocations and
                reversals effective on or before
                {{
                    formatDate(
                        props.report.filters.as_of_date,
                    )
                }}.
                Current open-item status is not used as a
                substitute for historical balances.
            </p>
        </section>

        <div
            class="grid grid-cols-1 gap-4 md:grid-cols-3"
        >
            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Gross Payables
                </p>

                <p
                    class="mt-3 text-2xl font-bold text-gray-900 dark:text-white"
                >
                    {{
                        formatAmount(
                            props.report.totals.total_payable,
                        )
                    }}
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    {{ props.report.base_currency_code }}
                </p>
            </section>

            <section
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-300"
                >
                    Unapplied Supplier Credits
                </p>

                <p
                    class="mt-3 text-2xl font-bold text-emerald-900 dark:text-emerald-200"
                >
                    {{
                        formatAmount(
                            props.report.totals
                                .unapplied_credit,
                        )
                    }}
                </p>

                <p
                    class="mt-1 text-sm text-emerald-700 dark:text-emerald-400"
                >
                    Payments, returns, and debit-note credits
                </p>
            </section>

            <section
                class="rounded-2xl border border-red-200 bg-red-50 p-5 shadow-sm dark:border-red-500/30 dark:bg-red-500/10"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-red-700 dark:text-red-300"
                >
                    Net Accounts Payable
                </p>

                <p
                    class="mt-3 text-2xl font-bold"
                    :class="
                        balanceClass(
                            props.report.totals
                                .net_outstanding,
                        )
                    "
                >
                    {{
                        formatAmount(
                            props.report.totals
                                .net_outstanding,
                        )
                    }}
                </p>

                <p
                    class="mt-1 text-sm text-red-700 dark:text-red-400"
                >
                    Gross payables less unapplied credits
                </p>
            </section>
        </div>

        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6"
        >
            <section
                v-for="bucketKey in bucketKeys"
                :key="bucketKey"
                class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    {{ bucketLabels[bucketKey] }}
                </p>

                <p
                    class="mt-2 text-lg font-bold text-gray-900 dark:text-white"
                >
                    {{
                        formatAmount(
                            props.report.totals[bucketKey],
                        )
                    }}
                </p>
            </section>
        </div>

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
                                    class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
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

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
                                    @click="
                                        toggleSort(
                                            'total_payable',
                                        )
                                    "
                                >
                                    Payable
                                    {{
                                        sortIndicator(
                                            'total_payable',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
                                    @click="
                                        toggleSort(
                                            'unapplied_credit',
                                        )
                                    "
                                >
                                    Credits
                                    {{
                                        sortIndicator(
                                            'unapplied_credit',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
                                    @click="
                                        toggleSort(
                                            'net_outstanding',
                                        )
                                    "
                                >
                                    Net
                                    {{
                                        sortIndicator(
                                            'net_outstanding',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                v-for="bucketKey in bucketKeys"
                                :key="bucketKey"
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 hover:text-gray-900 dark:hover:text-white"
                                    @click="toggleSort(bucketKey)"
                                >
                                    {{ bucketLabels[bucketKey] }}
                                    {{
                                        sortIndicator(
                                            bucketKey,
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
                        class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900"
                    >
                        <tr
                            v-for="supplier in props.report.suppliers.data"
                            :key="supplier.supplier.id"
                            class="align-top transition hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        >
                            <td class="px-5 py-4">
                                <Link
                                    :href="
                                        supplierAgingHref(
                                            supplier,
                                        )
                                    "
                                    class="text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                                >
                                    {{ supplier.supplier.code }}
                                    —
                                    {{ supplier.supplier.name }}
                                </Link>

                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    {{ supplier.supplier.status }}
                                </p>

                                <div
                                    v-if="
                                        supplier.currencies.length
                                        > 0
                                    "
                                    class="mt-3 space-y-1"
                                >
                                    <p
                                        v-for="currency in supplier.currencies"
                                        :key="currency.currency_code"
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            currency.currency_code
                                        }}
                                        net:
                                        {{
                                            formatAmount(
                                                currency
                                                    .net_outstanding,
                                            )
                                        }}
                                    </p>
                                </div>
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    formatAmount(
                                        supplier.total_payable,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-semibold text-emerald-700 dark:text-emerald-300"
                            >
                                {{
                                    formatAmount(
                                        supplier
                                            .unapplied_credit,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-bold"
                                :class="
                                    balanceClass(
                                        supplier
                                            .net_outstanding,
                                    )
                                "
                            >
                                {{
                                    formatAmount(
                                        supplier
                                            .net_outstanding,
                                    )
                                }}
                            </td>

                            <td
                                v-for="bucketKey in bucketKeys"
                                :key="bucketKey"
                                class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatAmount(
                                        supplier.buckets[
                                            bucketKey
                                        ],
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right"
                            >
                                <div
                                    class="flex flex-wrap justify-end gap-2 print:hidden"
                                >
                                    <Link
                                        :href="
                                            supplierAgingHref(
                                                supplier,
                                            )
                                        "
                                        class="rounded-lg border border-brand-300 bg-brand-50 px-3 py-1.5 text-xs font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-300"
                                    >
                                        Aging Detail
                                    </Link>

                                    <Link
                                        :href="
                                            supplierStatementHref(
                                                supplier,
                                            )
                                        "
                                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    >
                                        Statement
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                props.report.suppliers.data
                                    .length === 0
                            "
                        >
                            <td
                                colspan="11"
                                class="px-5 py-16 text-center"
                            >
                                <p
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    No Accounts Payable balances
                                    found.
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Adjust the date or filters to
                                    broaden the report.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between print:hidden dark:border-gray-800"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Showing
                    {{
                        props.report.suppliers.meta.from
                        ?? 0
                    }}
                    to
                    {{
                        props.report.suppliers.meta.to
                        ?? 0
                    }}
                    of
                    {{
                        props.report.suppliers.meta.total
                    }}
                    suppliers
                </p>

                <div
                    v-if="
                        props.report.suppliers.meta
                            .last_page > 1
                    "
                    class="flex flex-wrap items-center gap-1"
                >
                    <button
                        type="button"
                        :disabled="
                            props.report.suppliers.meta
                                .current_page <= 1
                        "
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        @click="
                            goToPage(
                                props.report.suppliers.meta
                                    .current_page - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <template
                        v-for="(
                            item,
                            index
                        ) in paginationItems"
                        :key="`${item}-${index}`"
                    >
                        <span
                            v-if="item === 'ellipsis'"
                            class="px-2 text-sm text-gray-400"
                        >
                            …
                        </span>

                        <button
                            v-else
                            type="button"
                            class="h-9 min-w-9 rounded-lg border px-3 text-sm font-medium"
                            :class="
                                item
                                    === props.report
                                        .suppliers.meta
                                        .current_page
                                    ? 'border-brand-500 bg-brand-500 text-white'
                                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300'
                            "
                            @click="goToPage(item)"
                        >
                            {{ item }}
                        </button>
                    </template>

                    <button
                        type="button"
                        :disabled="
                            props.report.suppliers.meta
                                .current_page
                            >= props.report.suppliers.meta
                                .last_page
                        "
                        class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        @click="
                            goToPage(
                                props.report.suppliers.meta
                                    .current_page + 1,
                            )
                        "
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>