<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    FinancialBranchOption,
    FinancialControlDashboard,
} from '@/Types/financial-control';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    dashboard: FinancialControlDashboard;
    branches: FinancialBranchOption[];
    filters: {
        branch_id: number | null;
    };
}>();

const amount = (value: string | number): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    }).format(Number(value));
};

interface MetricCard {
    label: string;
    value: string | number;
    count: boolean;
    suffix: string;
}

const metricCards = computed((): MetricCard[] => [
    {
        label: 'Net Profit',
        value: props.dashboard.metrics.net_profit,
        count: false,
        suffix: '',
    },
    {
        label: 'Total Assets',
        value: props.dashboard.metrics.total_assets,
        count: false,
        suffix: '',
    },
    {
        label: 'Cash and Bank',
        value: props.dashboard.metrics.cash_and_bank,
        count: false,
        suffix: '',
    },
    {
        label: 'Working Capital',
        value: props.dashboard.metrics.working_capital,
        count: false,
        suffix: '',
    },
    {
        label: 'Current Ratio',
        value: props.dashboard.metrics.current_ratio ?? '0',
        count: true,
        suffix: props.dashboard.metrics.current_ratio === null ? 'N/A' : 'x',
    },
    {
        label: 'Control Difference',
        value: props.dashboard.metrics.reconciliation_difference,
        count: false,
        suffix: '',
    },
    {
        label: 'Unreconciled Banks',
        value: props.dashboard.metrics.unreconciled_bank_accounts,
        count: true,
        suffix: '',
    },
    {
        label: 'Unposted Journals',
        value: props.dashboard.metrics.unposted_journals,
        count: true,
        suffix: '',
    },
]);

const reportLinks = [
    {
        label: 'Trial Balance',
        routeName: 'reports.financial-statements.trial-balance',
    },
    {
        label: 'Profit and Loss',
        routeName: 'reports.financial-statements.profit-and-loss',
    },
    {
        label: 'Balance Sheet',
        routeName: 'reports.financial-statements.balance-sheet',
    },
    {
        label: 'Cash Flow',
        routeName: 'reports.financial-statements.cash-flow',
    },
    {
        label: 'Financial Reconciliations',
        routeName: 'financial-control.reconciliations',
    },
];

const changeBranch = (event: Event): void => {
    const value = (event.target as HTMLSelectElement).value;

    router.get(
        route('financial-control.index'),
        value === '' ? {} : { branch_id: Number(value) },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};
</script>

<template>
    <Head title="Financial Control" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Financial Control Dashboard
                </h1>
                <p class="text-sm text-gray-500">
                    Financial statements, reconciliations, liquidity, and period-close readiness
                    as of {{ dashboard.as_of_date }}.
                </p>
            </div>

            <select
                :value="filters.branch_id ?? ''"
                class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                @change="changeBranch"
            >
                <option value="">All accessible branches</option>
                <option
                    v-for="branch in branches"
                    :key="branch.id"
                    :value="branch.id"
                >
                    {{ branch.code }} — {{ branch.name }}
                </option>
            </select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-8">
            <div
                v-for="item in metricCards"
                :key="item.label"
                class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p class="text-xs uppercase tracking-wide text-gray-500">
                    {{ item.label }}
                </p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                    <template v-if="item.count">
                        <span v-if="item.suffix === 'N/A'">N/A</span>
                        <span v-else>{{ item.value }}{{ item.suffix ?? '' }}</span>
                    </template>
                    <template v-else>
                        {{ dashboard.currency_code }} {{ amount(item.value) }}
                    </template>
                </p>
            </div>
        </div>

        <div
            class="rounded-2xl border p-4"
            :class="dashboard.reconciliation.summary.status === 'reconciled'
                ? 'border-success-300 bg-success-50 text-success-800 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-300'
                : 'border-warning-300 bg-warning-50 text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300'"
        >
            <p class="font-semibold">
                Financial control status:
                {{ dashboard.reconciliation.summary.status.replaceAll('_', ' ') }}
            </p>
            <p class="mt-1 text-sm">
                Absolute difference:
                {{ dashboard.currency_code }}
                {{ amount(dashboard.reconciliation.summary.total_absolute_difference) }} ·
                Unreconciled banks:
                {{ dashboard.reconciliation.summary.unreconciled_bank_accounts }}
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Link
                v-for="link in reportLinks"
                :key="link.routeName"
                :href="route(link.routeName)"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-brand-400 dark:border-gray-800 dark:bg-gray-900"
            >
                <h2 class="font-semibold text-gray-900 dark:text-white">
                    {{ link.label }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">Open report</p>
            </Link>

            <Link
                v-if="dashboard.period"
                :href="route('financial-control.period-close.show', dashboard.period.id)"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-brand-400 dark:border-gray-800 dark:bg-gray-900"
            >
                <h2 class="font-semibold text-gray-900 dark:text-white">
                    Period Close — {{ dashboard.period.code }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    {{ dashboard.period.start_date }} to {{ dashboard.period.end_date }} ·
                    Status: {{ dashboard.period.status }}
                </p>
            </Link>
        </div>
    </div>
</template>
