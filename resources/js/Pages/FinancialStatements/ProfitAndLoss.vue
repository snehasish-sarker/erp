<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import FinancialStatementFilters from '@/Components/Accounting/FinancialStatementFilters.vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    FinancialBranchOption,
    ProfitAndLossReport,
} from '@/Types/financial-control';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    report: ProfitAndLossReport;
    branches: FinancialBranchOption[];
}>();

const amount = (value: string): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    }).format(Number(value));
};

const label = (value: string): string => {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter: string): string => letter.toUpperCase());
};
</script>

<template>
    <Head title="Profit and Loss" />

    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                Profit and Loss Statement
            </h1>
            <p class="text-sm text-gray-500">
                Income and expenses in {{ report.currency_code }}.
            </p>
        </div>

        <FinancialStatementFilters
            route-name="reports.financial-statements.profit-and-loss"
            export-type="profit_and_loss"
            :filters="report.filters"
            :branches="branches"
            show-comparison
        />

        <div
            v-if="report.comparison && report.comparison_range && report.comparison_variance"
            class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <h2 class="mb-3 font-semibold text-gray-900 dark:text-white">
                Comparison summary
            </h2>
            <p class="mb-4 text-sm text-gray-500">
                {{ report.comparison_range.date_from }} to
                {{ report.comparison_range.date_to }}
            </p>
            <table class="min-w-full text-sm">
                <thead>
                    <tr>
                        <th class="py-2 text-left">Measure</th>
                        <th class="py-2 text-right">Current</th>
                        <th class="py-2 text-right">Comparison</th>
                        <th class="py-2 text-right">Variance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="key in [
                            'revenue',
                            'gross_profit',
                            'operating_profit',
                            'profit_before_tax',
                            'net_profit',
                        ]"
                        :key="key"
                        class="border-t border-gray-100 dark:border-gray-800"
                    >
                        <td class="py-2">{{ label(key) }}</td>
                        <td class="py-2 text-right">{{ amount(report.totals[key]) }}</td>
                        <td class="py-2 text-right">{{ amount(report.comparison.totals[key]) }}</td>
                        <td class="py-2 text-right font-medium">
                            {{ amount(report.comparison_variance[key]) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <section
                v-for="(rows, section) in report.sections"
                :key="section"
                class="mb-5"
            >
                <h2 class="mb-2 font-semibold text-gray-900 dark:text-white">
                    {{ label(String(section)) }}
                </h2>

                <div
                    v-for="row in rows"
                    :key="`${section}-${row.code}`"
                    class="flex justify-between gap-4 border-b border-gray-100 py-2 text-sm dark:border-gray-800"
                >
                    <span>{{ row.code }} — {{ row.name }}</span>
                    <span class="shrink-0">{{ amount(row.amount) }}</span>
                </div>

                <p v-if="rows.length === 0" class="py-2 text-sm text-gray-400">
                    No activity.
                </p>
            </section>

            <div class="mt-5 space-y-2 border-t border-gray-300 pt-4 dark:border-gray-700">
                <div
                    v-for="(value, key) in report.totals"
                    :key="key"
                    class="flex justify-between gap-4"
                    :class="key === 'net_profit' ? 'text-lg font-bold' : 'font-medium'"
                >
                    <span>{{ label(String(key)) }}</span>
                    <span>{{ amount(value) }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
