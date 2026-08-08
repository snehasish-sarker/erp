<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import FinancialStatementFilters from '@/Components/Accounting/FinancialStatementFilters.vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    BalanceSheetReport,
    FinancialBranchOption,
} from '@/Types/financial-control';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    report: BalanceSheetReport;
    branches: FinancialBranchOption[];
}>();

const amount = (value: string): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    }).format(Number(value));
};
</script>

<template>
    <Head title="Balance Sheet" />

    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                Balance Sheet
            </h1>
            <p class="text-sm text-gray-500">
                Assets, liabilities, and equity in {{ report.currency_code }}.
            </p>
        </div>

        <FinancialStatementFilters
            route-name="reports.financial-statements.balance-sheet"
            export-type="balance_sheet"
            :filters="report.filters"
            :branches="branches"
            as-of-only
        />

        <div class="grid gap-5 xl:grid-cols-3">
            <section
                v-for="(rows, section) in report.sections"
                :key="section"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2 class="mb-3 text-lg font-semibold capitalize text-gray-900 dark:text-white">
                    {{ section }}
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
                    No balance.
                </p>

                <div class="mt-3 flex justify-between gap-4 font-bold">
                    <span>Total {{ section }}</span>
                    <span>{{ amount(report.totals[String(section)]) }}</span>
                </div>
            </section>
        </div>

        <div
            class="rounded-xl border p-4 font-semibold"
            :class="Number(report.totals.difference) === 0
                ? 'border-success-300 bg-success-50 text-success-800 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-300'
                : 'border-error-300 bg-error-50 text-error-800 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300'"
        >
            Accounting equation difference:
            {{ amount(report.totals.difference) }}
        </div>
    </div>
</template>
