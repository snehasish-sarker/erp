<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import FinancialStatementFilters from '@/Components/Accounting/FinancialStatementFilters.vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    CashFlowReport,
    FinancialBranchOption,
} from '@/Types/financial-control';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    report: CashFlowReport;
    branches: FinancialBranchOption[];
}>();

const amount = (value: string): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    }).format(Number(value));
};

const label = (value: string): string => {
    return value.replaceAll('_', ' ');
};
</script>

<template>
    <Head title="Cash Flow Statement" />

    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                Cash Flow Statement
            </h1>
            <p class="text-sm text-gray-500">
                Direct and indirect cash-flow views in {{ report.currency_code }}.
            </p>
        </div>

        <FinancialStatementFilters
            route-name="reports.financial-statements.cash-flow"
            export-type="cash_flow"
            :filters="report.filters"
            :branches="branches"
            show-method
        />

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <template v-if="report.method === 'direct'">
                <section
                    v-for="(rows, section) in report.direct.sections"
                    :key="section"
                    class="mb-5"
                >
                    <h2 class="mb-2 text-lg font-semibold capitalize">
                        {{ section }} activities
                    </h2>
                    <div
                        v-for="row in rows"
                        :key="row.label"
                        class="flex justify-between gap-4 border-b border-gray-100 py-2 text-sm dark:border-gray-800"
                    >
                        <span>{{ row.label }}</span>
                        <span>{{ amount(row.amount) }}</span>
                    </div>
                    <p v-if="rows.length === 0" class="py-2 text-sm text-gray-400">
                        No cash movement.
                    </p>
                </section>

                <div
                    v-for="(value, key) in report.direct.totals"
                    :key="key"
                    class="flex justify-between gap-4 py-1 font-medium"
                >
                    <span class="capitalize">{{ label(String(key)) }}</span>
                    <span>{{ amount(value) }}</span>
                </div>
            </template>

            <template v-else>
                <div
                    v-for="row in report.indirect.rows"
                    :key="row.label"
                    class="flex justify-between gap-4 border-b border-gray-100 py-2 text-sm dark:border-gray-800"
                >
                    <span>{{ row.label }}</span>
                    <span>{{ amount(row.amount) }}</span>
                </div>

                <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                    <div
                        v-for="(value, key) in report.indirect.totals"
                        :key="key"
                        class="flex justify-between gap-4 py-1 font-medium"
                    >
                        <span class="capitalize">{{ label(String(key)) }}</span>
                        <span>{{ amount(value) }}</span>
                    </div>
                </div>
            </template>
        </div>
    </div>
</template>
