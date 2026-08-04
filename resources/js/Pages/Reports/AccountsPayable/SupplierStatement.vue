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
    SupplierStatementFilters,
    SupplierStatementPageProps,
} from '@/Types/accounts-payable-report';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<SupplierStatementPageProps>();

const { can } = useAuthorization();

const canExport = computed(
    (): boolean => can('exports.create'),
);

const filters = reactive<SupplierStatementFilters>({
    ...props.filters,
});

const selectedSupplier = computed(
    () => props.suppliers.find(
        (supplier) => supplier.id === filters.supplier_id,
    ) ?? null,
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

const queryParameters = (): Record<
    string,
    string | number
> => {
    const query: Record<
        string,
        string | number
    > = {
        date_from: filters.date_from,
        date_to: filters.date_to,
        per_page: filters.per_page,
    };

    if (filters.supplier_id !== null) {
        query.supplier_id = filters.supplier_id;
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
        route('reports.accounts-payable.supplier-statement'),
        queryParameters(),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const resetFilters = (): void => {
    const today = new Date();

    const localToday = new Date(
        today.getTime()
        - today.getTimezoneOffset() * 60_000,
    );

    const dateTo = localToday
        .toISOString()
        .slice(0, 10);

    const dateFrom = `${dateTo.slice(0, 8)}01`;

    filters.supplier_id = null;
    filters.branch_id = null;
    filters.currency_code = null;
    filters.date_from = dateFrom;
    filters.date_to = dateTo;
    filters.per_page = 25;

    applyFilters();
};

const goToPage = (
    page: number,
): void => {
    if (props.report === null) {
        return;
    }

    const meta = props.report.entries.meta;

    if (
        page < 1
        || page > meta.last_page
        || page === meta.current_page
    ) {
        return;
    }

    router.get(
        route('reports.accounts-payable.supplier-statement'),
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

const agingHref = computed(
    (): string | null => {
        if (filters.supplier_id === null) {
            return null;
        }

        return route(
            'reports.accounts-payable.aging.suppliers.show',
            {
                supplierId: filters.supplier_id,
                branch_id:
                    filters.branch_id
                    ?? undefined,
                currency_code:
                    filters.currency_code
                    ?? undefined,
                as_of_date: filters.date_to,
            },
        );
    },
);

const requestExport = (): void => {
    if (props.report === null) {
        return;
    }

    router.post(
        route('exports.store'),
        {
            export_type:
                'supplier_statement',
            format: 'csv',
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
            'reports.accounts-payable.supplier-statement.print',
            queryParameters(),
        ),
        '_blank',
        'noopener,noreferrer',
    );
};
</script>

<template>
    <Head title="Supplier Statement" />

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
                        Accounts Payable Reports
                    </Link>

                    <span>/</span>

                    <span
                        class="
                            text-gray-700
                            dark:text-gray-300
                        "
                    >
                        Supplier Statement
                    </span>
                </div>

                <h1
                    class="
                        text-2xl font-semibold
                        text-gray-900
                        dark:text-white
                    "
                >
                    Supplier Statement
                </h1>

                <p
                    class="
                        mt-1 max-w-3xl text-sm
                        text-gray-500
                        dark:text-gray-400
                    "
                >
                    Review opening balance, period
                    activity, closing balance, and
                    pagination-safe running balances
                    for a supplier.
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
                    AP Aging
                </Link>

                <Link
                    v-if="agingHref !== null"
                    :href="agingHref"
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
                    Supplier Aging
                </Link>

                <button
                    v-if="
                        props.report !== null
                        && canExport
                    "
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
                    v-if="props.report !== null"
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
            <form
                class="
                    grid grid-cols-1 gap-4
                    md:grid-cols-2
                    xl:grid-cols-6
                "
                @submit.prevent="applyFilters"
            >
                <div class="md:col-span-2">
                    <label
                        for="supplier-statement-supplier"
                        class="
                            mb-1.5 block text-sm
                            font-medium text-gray-700
                            dark:text-gray-300
                        "
                    >
                        Supplier
                        <span class="text-red-500">
                            *
                        </span>
                    </label>

                    <select
                        id="supplier-statement-supplier"
                        v-model="filters.supplier_id"
                        required
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
                        <option :value="null">
                            Select supplier
                        </option>

                        <option
                            v-for="supplier in props.suppliers"
                            :key="supplier.id"
                            :value="supplier.id"
                        >
                            {{ supplier.code }} —
                            {{ supplier.name
                            }}{{ supplier.deleted
                                ? ' (Deleted)'
                                : ''
                            }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier-statement-branch"
                        class="
                            mb-1.5 block text-sm
                            font-medium text-gray-700
                            dark:text-gray-300
                        "
                    >
                        Branch
                    </label>

                    <select
                        id="supplier-statement-branch"
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
                        for="supplier-statement-currency"
                        class="
                            mb-1.5 block text-sm
                            font-medium text-gray-700
                            dark:text-gray-300
                        "
                    >
                        Currency
                    </label>

                    <select
                        id="supplier-statement-currency"
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

                <div>
                    <label
                        for="supplier-statement-from"
                        class="
                            mb-1.5 block text-sm
                            font-medium text-gray-700
                            dark:text-gray-300
                        "
                    >
                        Date From
                    </label>

                    <input
                        id="supplier-statement-from"
                        v-model="filters.date_from"
                        type="date"
                        required
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
                        for="supplier-statement-to"
                        class="
                            mb-1.5 block text-sm
                            font-medium text-gray-700
                            dark:text-gray-300
                        "
                    >
                        Date To
                    </label>

                    <input
                        id="supplier-statement-to"
                        v-model="filters.date_to"
                        type="date"
                        :min="filters.date_from"
                        required
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

                <div
                    class="
                        flex flex-wrap items-end
                        justify-between gap-3
                        border-t border-gray-200
                        pt-5 md:col-span-2
                        xl:col-span-6
                        dark:border-gray-800
                    "
                >
                    <div class="flex items-center gap-3">
                        <label
                            for="supplier-statement-per-page"
                            class="text-sm text-gray-500"
                        >
                            Rows per page
                        </label>

                        <select
                            id="supplier-statement-per-page"
                            v-model="filters.per_page"
                            class="
                                rounded-lg border
                                border-gray-300 bg-white
                                px-3 py-2 text-sm
                                dark:border-gray-700
                                dark:bg-gray-950
                                dark:text-white
                            "
                        >
                            <option :value="10">10</option>
                            <option :value="15">15</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            type="button"
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
                            @click="resetFilters"
                        >
                            Reset
                        </button>

                        <button
                            type="submit"
                            :disabled="
                                filters.supplier_id
                                === null
                            "
                            class="
                                rounded-lg bg-brand-500
                                px-5 py-2.5 text-sm
                                font-semibold text-white
                                hover:bg-brand-600
                                disabled:cursor-not-allowed
                                disabled:opacity-50
                            "
                        >
                            Generate Statement
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <section
            v-if="props.report === null"
            class="
                rounded-2xl border
                border-dashed border-gray-300
                bg-white px-6 py-16
                text-center shadow-sm
                dark:border-gray-700
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
                Select a Supplier
            </h2>

            <p
                class="
                    mx-auto mt-2 max-w-xl
                    text-sm text-gray-500
                    dark:text-gray-400
                "
            >
                Choose a supplier and date range to
                generate a statement. Positive balances
                represent amounts payable; negative
                balances represent supplier credits
                or advances.
            </p>
        </section>

        <template v-else>
            <section
                class="
                    rounded-2xl border
                    border-gray-200 bg-white
                    p-5 shadow-sm
                    dark:border-gray-800
                    dark:bg-gray-900
                "
            >
                <div
                    class="
                        flex flex-col gap-4
                        lg:flex-row
                        lg:items-start
                        lg:justify-between
                    "
                >
                    <div>
                        <p
                            class="
                                text-xs font-medium
                                uppercase tracking-wide
                                text-gray-500
                            "
                        >
                            Statement For
                        </p>

                        <h2
                            class="
                                mt-2 text-xl font-semibold
                                text-gray-900
                                dark:text-white
                            "
                        >
                            {{
                                props.report.supplier.code
                            }}
                            —
                            {{
                                props.report.supplier.name
                            }}
                        </h2>

                        <p
                            class="
                                mt-1 text-sm
                                text-gray-500
                                dark:text-gray-400
                            "
                        >
                            {{
                                formatDate(
                                    props.report.filters
                                        .date_from,
                                )
                            }}
                            to
                            {{
                                formatDate(
                                    props.report.filters
                                        .date_to,
                                )
                            }}
                        </p>
                    </div>

                    <div
                        class="
                            text-sm text-gray-500
                            dark:text-gray-400
                        "
                    >
                        <p
                            v-if="
                                props.report.supplier.email
                            "
                        >
                            {{
                                props.report.supplier.email
                            }}
                        </p>

                        <p
                            v-if="
                                props.report.supplier.phone
                            "
                            class="mt-1"
                        >
                            {{
                                props.report.supplier.phone
                            }}
                        </p>

                        <p class="mt-1">
                            Payment terms:
                            {{
                                props.report.supplier
                                    .payment_terms_days
                            }}
                            day(s)
                        </p>
                    </div>
                </div>
            </section>

            <div
                class="
                    grid grid-cols-1 gap-4
                    sm:grid-cols-2
                    xl:grid-cols-4
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
                        Opening Balance
                    </p>

                    <p
                        class="mt-3 text-2xl font-bold"
                        :class="
                            balanceClass(
                                props.report.summary.base
                                    .opening_balance,
                            )
                        "
                    >
                        {{
                            formatAmount(
                                props.report.summary.base
                                    .opening_balance,
                            )
                        }}
                    </p>

                    <p class="mt-1 text-sm text-gray-500">
                        {{
                            props.report
                                .base_currency_code
                        }}
                    </p>
                </section>

                <section
                    class="
                        rounded-2xl border
                        border-blue-200 bg-blue-50
                        p-5 shadow-sm
                        dark:border-blue-500/30
                        dark:bg-blue-500/10
                    "
                >
                    <p
                        class="
                            text-xs font-medium uppercase
                            tracking-wide text-blue-700
                            dark:text-blue-300
                        "
                    >
                        Period Debit
                    </p>

                    <p
                        class="
                            mt-3 text-2xl font-bold
                            text-blue-900
                            dark:text-blue-200
                        "
                    >
                        {{
                            formatAmount(
                                props.report.summary.base
                                    .period_debit,
                            )
                        }}
                    </p>

                    <p
                        class="
                            mt-1 text-sm text-blue-700
                            dark:text-blue-400
                        "
                    >
                        Payments and supplier credits
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
                        Period Credit
                    </p>

                    <p
                        class="
                            mt-3 text-2xl font-bold
                            text-red-900
                            dark:text-red-200
                        "
                    >
                        {{
                            formatAmount(
                                props.report.summary.base
                                    .period_credit,
                            )
                        }}
                    </p>

                    <p
                        class="
                            mt-1 text-sm text-red-700
                            dark:text-red-400
                        "
                    >
                        Invoices and payable increases
                    </p>
                </section>

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
                        Closing Balance
                    </p>

                    <p
                        class="mt-3 text-2xl font-bold"
                        :class="
                            balanceClass(
                                props.report.summary.base
                                    .closing_balance,
                            )
                        "
                    >
                        {{
                            formatAmount(
                                props.report.summary.base
                                    .closing_balance,
                            )
                        }}
                    </p>

                    <p class="mt-1 text-sm text-gray-500">
                        Credit less debit
                    </p>
                </section>
            </div>

            <section
                v-if="
                    props.report.summary.currencies
                        .length > 0
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
                    Currency Summary
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
                            in props.report.summary
                                .currencies
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
                                font-semibold
                                text-gray-900
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
                                    Opening
                                </dt>

                                <dd
                                    :class="
                                        balanceClass(
                                            currency
                                                .opening_balance,
                                        )
                                    "
                                >
                                    {{
                                        formatAmount(
                                            currency
                                                .opening_balance,
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
                                    Debit
                                </dt>

                                <dd
                                    class="
                                        text-blue-700
                                        dark:text-blue-300
                                    "
                                >
                                    {{
                                        formatAmount(
                                            currency
                                                .period_debit,
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
                                    Credit
                                </dt>

                                <dd
                                    class="
                                        text-red-700
                                        dark:text-red-300
                                    "
                                >
                                    {{
                                        formatAmount(
                                            currency
                                                .period_credit,
                                        )
                                    }}
                                </dd>
                            </div>

                            <div
                                class="
                                    flex justify-between
                                    gap-4 border-t
                                    border-gray-200 pt-2
                                    dark:border-gray-800
                                "
                            >
                                <dt
                                    class="
                                        font-medium
                                        text-gray-700
                                        dark:text-gray-300
                                    "
                                >
                                    Closing
                                </dt>

                                <dd
                                    class="font-semibold"
                                    :class="
                                        balanceClass(
                                            currency
                                                .closing_balance,
                                        )
                                    "
                                >
                                    {{
                                        formatAmount(
                                            currency
                                                .closing_balance,
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
                        Statement Activity
                    </h2>

                    <p
                        class="
                            mt-1 text-sm
                            text-gray-500
                            dark:text-gray-400
                        "
                    >
                        Running balances include opening
                        activity and all earlier rows on
                        prior pages.
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
                                    Date / Reference
                                </th>

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
                                    Branch / Description
                                </th>

                                <th
                                    class="
                                        px-5 py-3 text-right
                                        text-xs font-semibold
                                        uppercase tracking-wide
                                        text-gray-500
                                    "
                                >
                                    Debit
                                </th>

                                <th
                                    class="
                                        px-5 py-3 text-right
                                        text-xs font-semibold
                                        uppercase tracking-wide
                                        text-gray-500
                                    "
                                >
                                    Credit
                                </th>

                                <th
                                    class="
                                        px-5 py-3 text-right
                                        text-xs font-semibold
                                        uppercase tracking-wide
                                        text-gray-500
                                    "
                                >
                                    Currency Balance
                                </th>

                                <th
                                    class="
                                        px-5 py-3 text-right
                                        text-xs font-semibold
                                        uppercase tracking-wide
                                        text-gray-500
                                    "
                                >
                                    Base Balance
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
                                    entry
                                    in props.report.entries
                                        .data
                                "
                                :key="entry.id"
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
                                            formatDate(
                                                entry
                                                    .posting_date,
                                            )
                                        }}
                                    </p>

                                    <p
                                        class="
                                            mt-1 text-xs
                                            text-gray-500
                                            dark:text-gray-400
                                        "
                                    >
                                        {{ entry.reference }}
                                    </p>

                                    <p
                                        class="
                                            mt-1 break-all
                                            text-xs text-gray-400
                                        "
                                    >
                                        {{
                                            entry
                                                .journal_reference
                                        }}
                                    </p>
                                </td>

                                <td class="px-5 py-4">
                                    <p
                                        class="
                                            text-sm font-semibold
                                            text-gray-900
                                            dark:text-white
                                        "
                                    >
                                        {{
                                            entry
                                                .source_document_number
                                            ?? `Source #${entry.source_id}`
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
                                            entry
                                                .entry_type_label
                                        }}
                                    </p>

                                    <p
                                        v-if="entry.reversal_of"
                                        class="
                                            mt-1 text-xs
                                            text-red-600
                                            dark:text-red-400
                                        "
                                    >
                                        Reverses
                                        {{
                                            entry.reversal_of
                                                .source_document_number
                                            ?? entry.reversal_of
                                                .reference
                                        }}
                                    </p>
                                </td>

                                <td class="px-5 py-4">
                                    <p
                                        class="
                                            text-sm
                                            text-gray-900
                                            dark:text-white
                                        "
                                    >
                                        {{
                                            entry.branch.code
                                            ?? '—'
                                        }}
                                        —
                                        {{
                                            entry.branch.name
                                            ?? 'Unknown branch'
                                        }}
                                    </p>

                                    <p
                                        class="
                                            mt-1 max-w-sm
                                            whitespace-pre-line
                                            text-xs
                                            text-gray-500
                                            dark:text-gray-400
                                        "
                                    >
                                        {{ entry.description }}
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
                                                entry
                                                    .exchange_rate,
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
                                            text-sm font-medium
                                            text-blue-700
                                            dark:text-blue-300
                                        "
                                    >
                                        {{
                                            formatAmount(
                                                entry
                                                    .debit_amount,
                                            )
                                        }}
                                    </p>

                                    <p
                                        class="
                                            mt-1 text-xs
                                            text-gray-500
                                        "
                                    >
                                        Base
                                        {{
                                            formatAmount(
                                                entry
                                                    .base_debit_amount,
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
                                            text-sm font-medium
                                            text-red-700
                                            dark:text-red-300
                                        "
                                    >
                                        {{
                                            formatAmount(
                                                entry
                                                    .credit_amount,
                                            )
                                        }}
                                    </p>

                                    <p
                                        class="
                                            mt-1 text-xs
                                            text-gray-500
                                        "
                                    >
                                        Base
                                        {{
                                            formatAmount(
                                                entry
                                                    .base_credit_amount,
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
                                            text-sm font-semibold
                                        "
                                        :class="
                                            balanceClass(
                                                entry
                                                    .currency_running_balance,
                                            )
                                        "
                                    >
                                        {{
                                            formatAmount(
                                                entry
                                                    .currency_running_balance,
                                            )
                                        }}
                                    </p>

                                    <p
                                        class="
                                            mt-1 text-xs
                                            text-gray-500
                                        "
                                    >
                                        {{
                                            entry.currency_code
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
                                            text-sm font-semibold
                                        "
                                        :class="
                                            balanceClass(
                                                entry
                                                    .base_running_balance,
                                            )
                                        "
                                    >
                                        {{
                                            formatAmount(
                                                entry
                                                    .base_running_balance,
                                            )
                                        }}
                                    </p>

                                    <p
                                        class="
                                            mt-1 text-xs
                                            text-gray-500
                                        "
                                    >
                                        {{
                                            props.report
                                                .base_currency_code
                                        }}
                                    </p>
                                </td>
                            </tr>

                            <tr
                                v-if="
                                    props.report.entries.data
                                        .length === 0
                                "
                            >
                                <td
                                    colspan="7"
                                    class="
                                        px-5 py-14
                                        text-center text-sm
                                        text-gray-500
                                        dark:text-gray-400
                                    "
                                >
                                    No Supplier Ledger entries
                                    fall within the selected
                                    statement period.
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
                            props.report.entries.meta.from
                            ?? 0
                        }}
                        to
                        {{
                            props.report.entries.meta.to
                            ?? 0
                        }}
                        of
                        {{
                            props.report.entries.meta.total
                        }}
                        entries
                    </p>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            :disabled="
                                props.report.entries.meta
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
                                    props.report.entries.meta
                                        .current_page - 1,
                                )
                            "
                        >
                            Previous
                        </button>

                        <button
                            type="button"
                            :disabled="
                                props.report.entries.meta
                                    .current_page
                                >= props.report.entries.meta
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
                                    props.report.entries.meta
                                        .current_page + 1,
                                )
                            "
                        >
                            Next
                        </button>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>