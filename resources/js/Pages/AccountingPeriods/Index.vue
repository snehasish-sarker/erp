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
import type { ComputedRef } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import type {
    FiscalYearFilters,
    FiscalYearPagination,
    FiscalYearRecord,
    FiscalYearStatus,
    FiscalYearStatusOption,
} from '@/Types/accounting-period';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    fiscalYears: FiscalYearPagination;
    filters: FiscalYearFilters;
    statusOptions: FiscalYearStatusOption[];
}>();

const { can } = useAuthorization();

const filterForm = reactive<FiscalYearFilters>({
    search: props.filters.search,
    status: props.filters.status,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const hasActiveFilters: ComputedRef<boolean> =
    computed(
        (): boolean =>
            filterForm.search !== ''
            || filterForm.status !== '',
    );

const navigate = (page = 1): void => {
    router.get(
        '/erp/accounting-periods',
        {
            search: filterForm.search,
            status: filterForm.status,
            sort: filterForm.sort,
            direction: filterForm.direction,
            per_page: filterForm.per_page,
            page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const applyFilters = (): void => {
    navigate();
};

const resetFilters = (): void => {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.sort = 'start_date';
    filterForm.direction = 'desc';
    filterForm.per_page = 25;

    navigate();
};

const sortBy = (
    column: FiscalYearFilters['sort'],
): void => {
    if (filterForm.sort === column) {
        filterForm.direction =
            filterForm.direction === 'asc'
                ? 'desc'
                : 'asc';
    } else {
        filterForm.sort = column;
        filterForm.direction = 'asc';
    }

    navigate();
};

const sortIndicator = (
    column: FiscalYearFilters['sort'],
): string => {
    if (filterForm.sort !== column) {
        return '';
    }

    return filterForm.direction === 'asc'
        ? '↑'
        : '↓';
};

const statusLabel = (
    status: FiscalYearStatus,
): string =>
    props.statusOptions.find(
        (
            option: FiscalYearStatusOption,
        ): boolean =>
            option.value === status,
    )?.label ?? status;

const statusBadgeClass = (
    status: FiscalYearStatus,
): string => {
    const classes: Record<
        FiscalYearStatus,
        string
    > = {
        active: 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400',
        closed: 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-400',
    };

    return classes[status];
};

const formatDate = (
    value: string,
): string =>
    new Intl.DateTimeFormat(
        'en-US',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            timeZone: 'UTC',
        },
    ).format(
        new Date(`${value}T00:00:00Z`),
    );

const periodSummary = (
    fiscalYear: FiscalYearRecord,
): string =>
    `${fiscalYear.closed_periods_count} closed · `
    + `${fiscalYear.open_periods_count} open`;
</script>

<template>
    <Head title="Accounting Periods" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    Accounting Periods
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Manage fiscal calendars and control
                    which periods accept financial and
                    inventory postings.
                </p>
            </div>

            <Link
                v-if="
                    can(
                        'accounting_periods.generate',
                    )
                "
                href="/erp/accounting-periods/create"
                class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
            >
                Generate fiscal year
            </Link>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <form
                class="grid gap-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:grid-cols-2 sm:p-6 lg:grid-cols-4"
                @submit.prevent="applyFilters"
            >
                <div class="sm:col-span-2">
                    <label
                        for="fiscal-year-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search
                    </label>

                    <input
                        id="fiscal-year-search"
                        v-model="filterForm.search"
                        type="search"
                        placeholder="Fiscal year name or code"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="fiscal-year-status"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                    </label>

                    <select
                        id="fiscal-year-status"
                        v-model="filterForm.status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="
                                option in statusOptions
                            "
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="fiscal-year-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>

                    <select
                        id="fiscal-year-per-page"
                        v-model.number="
                            filterForm.per_page
                        "
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>

                <div
                    class="flex items-end gap-3 sm:col-span-2 lg:col-span-4"
                >
                    <button
                        type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white transition hover:bg-brand-600"
                    >
                        Apply filters
                    </button>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]"
                    >
                        <tr>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                <button
                                    type="button"
                                    @click="sortBy('name')"
                                >
                                    Fiscal year
                                    {{
                                        sortIndicator(
                                            'name',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                <button
                                    type="button"
                                    @click="sortBy('code')"
                                >
                                    Code
                                    {{
                                        sortIndicator(
                                            'code',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                <button
                                    type="button"
                                    @click="
                                        sortBy(
                                            'start_date',
                                        )
                                    "
                                >
                                    Date range
                                    {{
                                        sortIndicator(
                                            'start_date',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Period progress
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                <button
                                    type="button"
                                    @click="sortBy('status')"
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
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="
                                fiscalYear
                                in fiscalYears.data
                            "
                            :key="fiscalYear.id"
                        >
                            <td class="px-5 py-4 align-top">
                                <div
                                    class="flex items-center gap-2"
                                >
                                    <p
                                        class="font-medium text-gray-800 dark:text-white/90"
                                    >
                                        {{
                                            fiscalYear.name
                                        }}
                                    </p>

                                    <span
                                        v-if="
                                            fiscalYear
                                                .is_current
                                        "
                                        class="inline-flex rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700 dark:bg-brand-500/15 dark:text-brand-300"
                                    >
                                        Current
                                    </span>
                                </div>
                            </td>

                            <td
                                class="px-5 py-4 align-top font-mono text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ fiscalYear.code }}
                            </td>

                            <td
                                class="px-5 py-4 align-top text-sm text-gray-700 dark:text-gray-300"
                            >
                                <p>
                                    {{
                                        formatDate(
                                            fiscalYear
                                                .start_date,
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    to
                                    {{
                                        formatDate(
                                            fiscalYear
                                                .end_date,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <p
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        periodSummary(
                                            fiscalYear,
                                        )
                                    }}
                                </p>

                                <div
                                    class="mt-2 h-1.5 w-32 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10"
                                >
                                    <div
                                        class="h-full rounded-full bg-brand-500"
                                        :style="{
                                            width:
                                                fiscalYear.periods_count
                                                === 0
                                                    ? '0%'
                                                    : `${(
                                                        fiscalYear.closed_periods_count
                                                        / fiscalYear.periods_count
                                                    ) * 100}%`,
                                        }"
                                    />
                                </div>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        statusBadgeClass(
                                            fiscalYear
                                                .status,
                                        )
                                    "
                                >
                                    {{
                                        statusLabel(
                                            fiscalYear
                                                .status,
                                        )
                                    }}
                                </span>
                            </td>

                            <td class="px-5 py-4 align-top">
                                <div
                                    class="flex justify-end"
                                >
                                    <Link
                                        :href="`/erp/accounting-periods/${fiscalYear.id}`"
                                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                    >
                                        View periods
                                    </Link>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                fiscalYears.data.length
                                === 0
                            "
                        >
                            <td
                                colspan="6"
                                class="px-5 py-12 text-center"
                            >
                                <p
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    No fiscal years found.
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Generate a fiscal year
                                    or adjust the current
                                    filters.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Showing
                    {{
                        fiscalYears.meta.from ?? 0
                    }}–{{
                        fiscalYears.meta.to ?? 0
                    }}
                    of
                    {{ fiscalYears.meta.total }}
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            fiscalYears.meta
                                .current_page <= 1
                        "
                        @click="
                            navigate(
                                fiscalYears.meta
                                    .current_page - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <span
                        class="px-2 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Page
                        {{
                            fiscalYears.meta
                                .current_page
                        }}
                        of
                        {{
                            fiscalYears.meta
                                .last_page
                        }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            fiscalYears.meta
                                .current_page
                            >= fiscalYears.meta
                                .last_page
                        "
                        @click="
                            navigate(
                                fiscalYears.meta
                                    .current_page + 1,
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