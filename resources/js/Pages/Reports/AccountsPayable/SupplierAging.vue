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
    AccountsPayableAgingBucketKey,
    AccountsPayableAgingFilters,
    SupplierAgingOpenItem,
    SupplierAgingPageProps,
} from '@/Types/accounts-payable-report';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<SupplierAgingPageProps>();

const { can } = useAuthorization();

const canExport = computed(
    (): boolean => can('exports.create'),
);

const filters = reactive<AccountsPayableAgingFilters>({
    ...props.report.filters,
    supplier_id: props.report.supplier.id,
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

const formatRate = (
    value: string | number,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 8,
        },
    ).format(decimalValue(value));
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

    if (filters.currency_code !== null) {
        query.currency_code = filters.currency_code;
    }

    return query;
};

const applyFilters = (): void => {
    router.get(
        route(
            'reports.accounts-payable.aging.suppliers.show',
            props.report.supplier.id,
        ),
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
    filters.currency_code = null;
    filters.per_page = 25;

    applyFilters();
};

const goToPage = (
    page: number,
): void => {
    const meta = props.report.items.meta;

    if (
        page < 1
        || page > meta.last_page
        || page === meta.current_page
    ) {
        return;
    }

    router.get(
        route(
            'reports.accounts-payable.aging.suppliers.show',
            props.report.supplier.id,
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

const bucketClasses = (
    item: SupplierAgingOpenItem,
): string => {
    if (item.balance_side === 'credit') {
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300';
    }

    if (item.bucket_key === 'current') {
        return 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300';
    }

    if (
        item.bucket_key === 'days_1_30'
        || item.bucket_key === 'days_31_60'
    ) {
        return 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300';
    }

    return 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300';
};

const statementHref = computed(
    (): string => route(
        'reports.accounts-payable.supplier-statement',
        {
            supplier_id: props.report.supplier.id,
            branch_id: filters.branch_id ?? undefined,
            currency_code: filters.currency_code ?? undefined,
            date_to: filters.as_of_date,
        },
    ),
);

const requestExport = (): void => {
    router.post(
        route('exports.store'),
        {
            export_type:
                'supplier_aging_detail',
            format: 'csv',
            filters: {
                supplier_id:
                    props.report.supplier.id,
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
            'reports.accounts-payable.aging.suppliers.print',
            {
                supplierId:
                    props.report.supplier.id,
                ...queryParameters(),
            },
        ),
        '_blank',
        'noopener,noreferrer',
    );
};
</script>

<template>
    <Head :title="`${props.report.supplier.name} Aging`" />

    <div class="space-y-6">
        <div
            class="
                flex flex-col gap-4
                lg:flex-row
                lg:items-start
                lg:justify-between
            "
        >
            <div>
                <div
                    class="
                        mb-2 flex flex-wrap
                        items-center gap-2
                        text-sm text-gray-500
                        dark:text-gray-400
                    "
                >
                    <Link
                        :href="
                            route(
                                'reports.accounts-payable.aging',
                            )
                        "
                        class="hover:text-brand-500"
                    >
                        Accounts Payable Aging
                    </Link>

                    <span>/</span>

                    <span
                        class="
                            text-gray-700
                            dark:text-gray-300
                        "
                    >
                        {{ props.report.supplier.code }}
                    </span>
                </div>

                <h1
                    class="
                        text-2xl font-semibold
                        text-gray-900
                        dark:text-white
                    "
                >
                    {{ props.report.supplier.name }}
                </h1>

                <p
                    class="
                        mt-1 text-sm text-gray-500
                        dark:text-gray-400
                    "
                >
                    Supplier aging detail as of
                    {{
                        formatDate(
                            props.report.filters
                                .as_of_date,
                        )
                    }}
                    · Payment terms
                    {{
                        props.report.supplier
                            .payment_terms_days
                    }}
                    day(s)
                </p>
            </div>

            <div class="flex flex-wrap gap-2 print:hidden">
                <Link
                    :href="
                        route(
                            'reports.accounts-payable.aging',
                        )
                    "
                    class="
                        rounded-lg border
                        border-gray-300 bg-white
                        px-4 py-2.5 text-sm
                        font-medium text-gray-700
                        hover:bg-gray-50
                        dark:border-gray-700
                        dark:bg-gray-900
                        dark:text-gray-300
                    "
                >
                    Back to Aging
                </Link>

                <Link
                    :href="statementHref"
                    class="
                        rounded-lg border
                        border-brand-300
                        bg-brand-50 px-4 py-2.5
                        text-sm font-medium
                        text-brand-700
                        hover:bg-brand-100
                        dark:border-brand-500/40
                        dark:bg-brand-500/10
                        dark:text-brand-300
                    "
                >
                    Supplier Statement
                </Link>

                <button
                    v-if="canExport"
                    type="button"
                    class="
                        rounded-lg border
                        border-brand-300
                        bg-brand-50 px-4 py-2.5
                        text-sm font-medium
                        text-brand-700
                        hover:bg-brand-100
                        dark:border-brand-500/40
                        dark:bg-brand-500/10
                        dark:text-brand-300
                    "
                    @click="requestExport"
                >
                    Export CSV
                </button>

                <button
                    type="button"
                    class="
                        rounded-lg bg-brand-500
                        px-4 py-2.5 text-sm
                        font-semibold text-white
                        hover:bg-brand-600
                    "
                    @click="printReport"
                >
                    Print / Save PDF
                </button>
            </div>
        </div>

        <section
            class="
                rounded-2xl border
                border-gray-200 bg-white
                p-5 shadow-sm print:hidden
                dark:border-gray-800
                dark:bg-gray-900
            "
        >
            <div
                class="
                    grid grid-cols-1 gap-4
                    md:grid-cols-2
                    xl:grid-cols-5
                "
            >
                <div class="md:col-span-2">
                    <label
                        for="supplier-aging-search"
                        class="
                            mb-1.5 block text-sm
                            font-medium text-gray-700
                            dark:text-gray-300
                        "
                    >
                        Search Documents
                    </label>

                    <input
                        id="supplier-aging-search"
                        v-model="filters.search"
                        type="search"
                        placeholder="Document, ledger, or journal reference"
                        class="
                            w-full rounded-lg border
                            border-gray-300 bg-white
                            px-3 py-2.5 text-sm
                            text-gray-900 outline-none
                            focus:border-brand-500
                            focus:ring-2
                            focus:ring-brand-500/20
                            dark:border-gray-700
                            dark:bg-gray-950
                            dark:text-white
                        "
                    >
                </div>

                <div>
                    <label
                        for="supplier-aging-date"
                        class="
                            mb-1.5 block text-sm
                            font-medium text-gray-700
                            dark:text-gray-300
                        "
                    >
                        As-of Date
                    </label>

                    <input
                        id="supplier-aging-date"
                        v-model="filters.as_of_date"
                        type="date"
                        class="
                            w-full rounded-lg border
                            border-gray-300 bg-white
                            px-3 py-2.5 text-sm
                            text-gray-900 outline-none
                            focus:border-brand-500
                            focus:ring-2
                            focus:ring-brand-500/20
                            dark:border-gray-700
                            dark:bg-gray-950
                            dark:text-white
                        "
                        @change="applyFilters"
                    >
                </div>

                <div>
                    <label
                        for="supplier-aging-branch"
                        class="
                            mb-1.5 block text-sm
                            font-medium text-gray-700
                            dark:text-gray-300
                        "
                    >
                        Branch
                    </label>

                    <select
                        id="supplier-aging-branch"
                        v-model="filters.branch_id"
                        class="
                            w-full rounded-lg border
                            border-gray-300 bg-white
                            px-3 py-2.5 text-sm
                            text-gray-900 outline-none
                            focus:border-brand-500
                            focus:ring-2
                            focus:ring-brand-500/20
                            dark:border-gray-700
                            dark:bg-gray-950
                            dark:text-white
                        "
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
                            {{ branch.code }} —
                            {{ branch.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier-aging-currency"
                        class="
                            mb-1.5 block text-sm
                            font-medium text-gray-700
                            dark:text-gray-300
                        "
                    >
                        Currency
                    </label>

                    <select
                        id="supplier-aging-currency"
                        v-model="filters.currency_code"
                        class="
                            w-full rounded-lg border
                            border-gray-300 bg-white
                            px-3 py-2.5 text-sm
                            text-gray-900 outline-none
                            focus:border-brand-500
                            focus:ring-2
                            focus:ring-brand-500/20
                            dark:border-gray-700
                            dark:bg-gray-950
                            dark:text-white
                        "
                        @change="applyFilters"
                    >
                        <option :value="null">
                            All currencies
                        </option>

                        <option
                            v-for="
                                currency
                                in props.currencies
                            "
                            :key="currency"
                            :value="currency"
                        >
                            {{ currency }}
                        </option>
                    </select>
                </div>
            </div>

            <div
                class="
                    mt-5 flex flex-wrap
                    items-center justify-between
                    gap-3 border-t
                    border-gray-200 pt-5
                    dark:border-gray-800
                "
            >
                <div class="flex items-center gap-3">
                    <label
                        for="supplier-aging-per-page"
                        class="text-sm text-gray-500"
                    >
                        Rows per page
                    </label>

                    <select
                        id="supplier-aging-per-page"
                        v-model="filters.per_page"
                        class="
                            rounded-lg border
                            border-gray-300 bg-white
                            px-3 py-2 text-sm
                            dark:border-gray-700
                            dark:bg-gray-950
                            dark:text-white
                        "
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
                    type="button"
                    class="
                        rounded-lg border
                        border-gray-300 bg-white
                        px-4 py-2 text-sm
                        font-medium text-gray-700
                        hover:bg-gray-50
                        dark:border-gray-700
                        dark:bg-gray-900
                        dark:text-gray-300
                    "
                    @click="resetFilters"
                >
                    Reset Filters
                </button>
            </div>
        </section>

        <div
            class="
                grid grid-cols-1 gap-4
                md:grid-cols-3
            "
        >
            <section
                class="
                    rounded-2xl border
                    border-gray-200 bg-white
                    p-5 shadow-sm
                    dark:border-gray-800
                    dark:bg-gray-900
                "
            >
                <p
                    class="
                        text-xs font-medium uppercase
                        tracking-wide text-gray-500
                    "
                >
                    Gross Payables
                </p>

                <p
                    class="
                        mt-3 text-2xl font-bold
                        text-gray-900
                        dark:text-white
                    "
                >
                    {{
                        formatAmount(
                            props.report.summary
                                .total_payable,
                        )
                    }}
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    {{ props.report.base_currency_code }}
                </p>
            </section>

            <section
                class="
                    rounded-2xl border
                    border-emerald-200
                    bg-emerald-50 p-5
                    shadow-sm
                    dark:border-emerald-500/30
                    dark:bg-emerald-500/10
                "
            >
                <p
                    class="
                        text-xs font-medium uppercase
                        tracking-wide text-emerald-700
                        dark:text-emerald-300
                    "
                >
                    Unapplied Credits
                </p>

                <p
                    class="
                        mt-3 text-2xl font-bold
                        text-emerald-900
                        dark:text-emerald-200
                    "
                >
                    {{
                        formatAmount(
                            props.report.summary
                                .unapplied_credit,
                        )
                    }}
                </p>
            </section>

            <section
                class="
                    rounded-2xl border
                    border-red-200 bg-red-50
                    p-5 shadow-sm
                    dark:border-red-500/30
                    dark:bg-red-500/10
                "
            >
                <p
                    class="
                        text-xs font-medium uppercase
                        tracking-wide text-red-700
                        dark:text-red-300
                    "
                >
                    Net Outstanding
                </p>

                <p
                    class="mt-3 text-2xl font-bold"
                    :class="
                        balanceClass(
                            props.report.summary
                                .net_outstanding,
                        )
                    "
                >
                    {{
                        formatAmount(
                            props.report.summary
                                .net_outstanding,
                        )
                    }}
                </p>
            </section>
        </div>

        <div
            class="
                grid grid-cols-1 gap-4
                sm:grid-cols-2
                xl:grid-cols-6
            "
        >
            <section
                v-for="bucketKey in bucketKeys"
                :key="bucketKey"
                class="
                    rounded-2xl border
                    border-gray-200 bg-white
                    p-4 shadow-sm
                    dark:border-gray-800
                    dark:bg-gray-900
                "
            >
                <p
                    class="
                        text-xs font-medium uppercase
                        tracking-wide text-gray-500
                    "
                >
                    {{ bucketLabels[bucketKey] }}
                </p>

                <p
                    class="
                        mt-2 text-lg font-bold
                        text-gray-900
                        dark:text-white
                    "
                >
                    {{
                        formatAmount(
                            props.report.summary[
                                bucketKey
                            ],
                        )
                    }}
                </p>
            </section>
        </div>

        <section
            v-if="
                props.report.currencies.length > 0
            "
            class="
                rounded-2xl border
                border-gray-200 bg-white
                p-5 shadow-sm
                dark:border-gray-800
                dark:bg-gray-900
            "
        >
            <h2
                class="
                    text-lg font-semibold
                    text-gray-900
                    dark:text-white
                "
            >
                Currency Breakdown
            </h2>

            <div
                class="
                    mt-4 grid grid-cols-1
                    gap-4 md:grid-cols-2
                    xl:grid-cols-3
                "
            >
                <div
                    v-for="
                        currency
                        in props.report.currencies
                    "
                    :key="currency.currency_code"
                    class="
                        rounded-xl border
                        border-gray-200
                        bg-gray-50 p-4
                        dark:border-gray-800
                        dark:bg-gray-950
                    "
                >
                    <p
                        class="
                            font-semibold text-gray-900
                            dark:text-white
                        "
                    >
                        {{ currency.currency_code }}
                    </p>

                    <dl class="mt-3 space-y-2 text-sm">
                        <div
                            class="
                                flex justify-between
                                gap-4
                            "
                        >
                            <dt class="text-gray-500">
                                Payable
                            </dt>

                            <dd
                                class="
                                    font-medium
                                    text-gray-900
                                    dark:text-white
                                "
                            >
                                {{
                                    formatAmount(
                                        currency
                                            .total_payable,
                                    )
                                }}
                            </dd>
                        </div>

                        <div
                            class="
                                flex justify-between
                                gap-4
                            "
                        >
                            <dt class="text-gray-500">
                                Credits
                            </dt>

                            <dd
                                class="
                                    font-medium
                                    text-emerald-700
                                    dark:text-emerald-300
                                "
                            >
                                {{
                                    formatAmount(
                                        currency
                                            .unapplied_credit,
                                    )
                                }}
                            </dd>
                        </div>

                        <div
                            class="
                                flex justify-between
                                gap-4
                            "
                        >
                            <dt class="text-gray-500">
                                Net
                            </dt>

                            <dd
                                class="font-semibold"
                                :class="
                                    balanceClass(
                                        currency
                                            .net_outstanding,
                                    )
                                "
                            >
                                {{
                                    formatAmount(
                                        currency
                                            .net_outstanding,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <section
            class="
                overflow-hidden rounded-2xl
                border border-gray-200
                bg-white shadow-sm
                dark:border-gray-800
                dark:bg-gray-900
            "
        >
            <div
                class="
                    border-b border-gray-200
                    px-5 py-4
                    dark:border-gray-800
                "
            >
                <h2
                    class="
                        text-lg font-semibold
                        text-gray-900
                        dark:text-white
                    "
                >
                    Open Items as of
                    {{
                        formatDate(
                            props.report.filters
                                .as_of_date,
                        )
                    }}
                </h2>

                <p
                    class="
                        mt-1 text-sm text-gray-500
                        dark:text-gray-400
                    "
                >
                    Payable items are aged by due date;
                    supplier credits remain separate from
                    overdue buckets.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table
                    class="
                        min-w-full divide-y
                        divide-gray-200
                        dark:divide-gray-800
                    "
                >
                    <thead
                        class="
                            bg-gray-50
                            dark:bg-gray-950
                        "
                    >
                        <tr>
                            <th
                                class="
                                    px-5 py-3 text-left
                                    text-xs font-semibold
                                    uppercase tracking-wide
                                    text-gray-500
                                "
                            >
                                Document
                            </th>

                            <th
                                class="
                                    px-5 py-3 text-left
                                    text-xs font-semibold
                                    uppercase tracking-wide
                                    text-gray-500
                                "
                            >
                                Branch / Type
                            </th>

                            <th
                                class="
                                    px-5 py-3 text-left
                                    text-xs font-semibold
                                    uppercase tracking-wide
                                    text-gray-500
                                "
                            >
                                Dates
                            </th>

                            <th
                                class="
                                    px-5 py-3 text-right
                                    text-xs font-semibold
                                    uppercase tracking-wide
                                    text-gray-500
                                "
                            >
                                Original / Allocated
                            </th>

                            <th
                                class="
                                    px-5 py-3 text-right
                                    text-xs font-semibold
                                    uppercase tracking-wide
                                    text-gray-500
                                "
                            >
                                Outstanding
                            </th>

                            <th
                                class="
                                    px-5 py-3 text-left
                                    text-xs font-semibold
                                    uppercase tracking-wide
                                    text-gray-500
                                "
                            >
                                Aging
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="
                            divide-y divide-gray-100
                            bg-white
                            dark:divide-gray-800
                            dark:bg-gray-900
                        "
                    >
                        <tr
                            v-for="
                                item
                                in props.report.items.data
                            "
                            :key="item.id"
                            class="
                                align-top
                                hover:bg-gray-50
                                dark:hover:bg-gray-800/50
                            "
                        >
                            <td class="px-5 py-4">
                                <p
                                    class="
                                        text-sm font-semibold
                                        text-gray-900
                                        dark:text-white
                                    "
                                >
                                    {{
                                        item.document_number
                                        ?? `Open Item #${item.id}`
                                    }}
                                </p>

                                <p
                                    class="
                                        mt-1 text-xs
                                        text-gray-500
                                        dark:text-gray-400
                                    "
                                >
                                    {{
                                        item.entry_type_label
                                    }}
                                </p>

                                <p
                                    class="
                                        mt-1 text-xs
                                        text-gray-400
                                    "
                                >
                                    Rate
                                    {{
                                        formatRate(
                                            item.exchange_rate,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="
                                        text-sm text-gray-900
                                        dark:text-white
                                    "
                                >
                                    {{ item.branch.code }}
                                    —
                                    {{ item.branch.name }}
                                </p>

                                <p
                                    class="
                                        mt-1 text-xs
                                        text-gray-500
                                        dark:text-gray-400
                                    "
                                >
                                    {{ item.item_type_label }}
                                </p>
                            </td>

                            <td
                                class="
                                    px-5 py-4 text-sm
                                    text-gray-700
                                    dark:text-gray-300
                                "
                            >
                                <p>
                                    Document:
                                    {{
                                        formatDate(
                                            item.document_date,
                                        )
                                    }}
                                </p>

                                <p class="mt-1">
                                    Posting:
                                    {{
                                        formatDate(
                                            item.posting_date,
                                        )
                                    }}
                                </p>

                                <p class="mt-1">
                                    Due:
                                    {{
                                        formatDate(
                                            item.due_date,
                                        )
                                    }}
                                </p>
                            </td>

                            <td
                                class="
                                    px-5 py-4 text-right
                                "
                            >
                                <p
                                    class="
                                        text-sm text-gray-900
                                        dark:text-white
                                    "
                                >
                                    {{
                                        formatAmount(
                                            item.original_amount,
                                        )
                                    }}
                                    {{ item.currency_code }}
                                </p>

                                <p
                                    class="
                                        mt-1 text-xs
                                        text-gray-500
                                        dark:text-gray-400
                                    "
                                >
                                    Allocated:
                                    {{
                                        formatAmount(
                                            item
                                                .historical_allocated_amount,
                                        )
                                    }}
                                </p>
                            </td>

                            <td
                                class="
                                    px-5 py-4 text-right
                                "
                            >
                                <p
                                    class="text-sm font-semibold"
                                    :class="
                                        item.balance_side
                                        === 'credit'
                                            ? 'text-emerald-700 dark:text-emerald-300'
                                            : 'text-gray-900 dark:text-white'
                                    "
                                >
                                    {{
                                        formatAmount(
                                            item
                                                .outstanding_amount,
                                        )
                                    }}
                                    {{ item.currency_code }}
                                </p>

                                <p
                                    class="
                                        mt-1 text-xs
                                        text-gray-500
                                        dark:text-gray-400
                                    "
                                >
                                    Base:
                                    {{
                                        formatAmount(
                                            item
                                                .base_outstanding_amount,
                                        )
                                    }}
                                    {{
                                        props.report
                                            .base_currency_code
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="
                                        inline-flex rounded-full
                                        px-2.5 py-1
                                        text-xs font-semibold
                                    "
                                    :class="
                                        bucketClasses(item)
                                    "
                                >
                                    {{ item.bucket_label }}
                                </span>

                                <p
                                    v-if="
                                        item.days_overdue
                                        !== null
                                    "
                                    class="
                                        mt-2 text-xs
                                        text-gray-500
                                        dark:text-gray-400
                                    "
                                >
                                    {{
                                        item.days_overdue
                                        <= 0
                                            ? 'Not overdue'
                                            : `${item.days_overdue} day(s) overdue`
                                    }}
                                </p>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                props.report.items.data
                                    .length === 0
                            "
                        >
                            <td
                                colspan="6"
                                class="
                                    px-5 py-14 text-center
                                    text-sm text-gray-500
                                    dark:text-gray-400
                                "
                            >
                                No outstanding items match
                                the selected filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="
                    flex flex-col gap-4
                    border-t border-gray-200
                    px-5 py-4
                    sm:flex-row
                    sm:items-center
                    sm:justify-between
                    print:hidden
                    dark:border-gray-800
                "
            >
                <p
                    class="
                        text-sm text-gray-500
                        dark:text-gray-400
                    "
                >
                    Showing
                    {{
                        props.report.items.meta.from
                        ?? 0
                    }}
                    to
                    {{
                        props.report.items.meta.to
                        ?? 0
                    }}
                    of
                    {{ props.report.items.meta.total }}
                    items
                </p>

                <div class="flex gap-2">
                    <button
                        type="button"
                        :disabled="
                            props.report.items.meta
                                .current_page <= 1
                        "
                        class="
                            rounded-lg border
                            border-gray-300 bg-white
                            px-3 py-2 text-sm
                            text-gray-700
                            disabled:opacity-50
                            dark:border-gray-700
                            dark:bg-gray-900
                            dark:text-gray-300
                        "
                        @click="
                            goToPage(
                                props.report.items.meta
                                    .current_page - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <button
                        type="button"
                        :disabled="
                            props.report.items.meta
                                .current_page
                            >= props.report.items.meta
                                .last_page
                        "
                        class="
                            rounded-lg border
                            border-gray-300 bg-white
                            px-3 py-2 text-sm
                            text-gray-700
                            disabled:opacity-50
                            dark:border-gray-700
                            dark:bg-gray-900
                            dark:text-gray-300
                        "
                        @click="
                            goToPage(
                                props.report.items.meta
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