<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import FinancialStatementFilters from '@/Components/Accounting/FinancialStatementFilters.vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    FinancialBranchOption,
    TrialBalanceReport,
} from '@/Types/financial-control';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    report: TrialBalanceReport;
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
    <Head title="Trial Balance" />

    <div class="space-y-5">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                Trial Balance
            </h1>
            <p class="text-sm text-gray-500">
                Opening, period movement, and closing balances in
                {{ report.currency_code }}.
            </p>
        </div>

        <FinancialStatementFilters
            route-name="reports.financial-statements.trial-balance"
            export-type="trial_balance"
            :filters="report.filters"
            :branches="branches"
        />

        <div
            class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3 text-left">Account</th>
                        <th
                            v-for="label in [
                                'Opening Dr',
                                'Opening Cr',
                                'Period Dr',
                                'Period Cr',
                                'Closing Dr',
                                'Closing Cr',
                            ]"
                            :key="label"
                            class="px-4 py-3 text-right"
                        >
                            {{ label }}
                        </th>
                    </tr>
                </thead>

                <tbody>
                    <tr
                        v-for="row in report.rows"
                        :key="row.account_id"
                        class="border-t border-gray-100 dark:border-gray-800"
                    >
                        <td class="px-4 py-3">
                            <strong>{{ row.code }}</strong> — {{ row.name }}
                        </td>
                        <td class="px-4 py-3 text-right">{{ amount(row.opening_debit) }}</td>
                        <td class="px-4 py-3 text-right">{{ amount(row.opening_credit) }}</td>
                        <td class="px-4 py-3 text-right">{{ amount(row.period_debit) }}</td>
                        <td class="px-4 py-3 text-right">{{ amount(row.period_credit) }}</td>
                        <td class="px-4 py-3 text-right">{{ amount(row.closing_debit) }}</td>
                        <td class="px-4 py-3 text-right">{{ amount(row.closing_credit) }}</td>
                    </tr>

                    <tr v-if="report.rows.length === 0">
                        <td colspan="7" class="px-4 py-10 text-center text-gray-500">
                            No posted ledger activity was found for the selected period.
                        </td>
                    </tr>
                </tbody>

                <tfoot class="border-t-2 border-gray-200 font-semibold dark:border-gray-700">
                    <tr>
                        <td class="px-4 py-3">Totals</td>
                        <td class="px-4 py-3 text-right">{{ amount(report.totals.opening_debit) }}</td>
                        <td class="px-4 py-3 text-right">{{ amount(report.totals.opening_credit) }}</td>
                        <td class="px-4 py-3 text-right">{{ amount(report.totals.period_debit) }}</td>
                        <td class="px-4 py-3 text-right">{{ amount(report.totals.period_credit) }}</td>
                        <td class="px-4 py-3 text-right">{{ amount(report.totals.closing_debit) }}</td>
                        <td class="px-4 py-3 text-right">{{ amount(report.totals.closing_credit) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div
            class="grid gap-3 sm:grid-cols-3"
        >
            <div
                v-for="item in [
                    ['Opening difference', report.totals.opening_difference],
                    ['Period difference', report.totals.period_difference],
                    ['Closing difference', report.totals.closing_difference],
                ]"
                :key="String(item[0])"
                class="rounded-xl border p-4 text-sm dark:border-gray-800"
            >
                <p class="text-gray-500">{{ item[0] }}</p>
                <p
                    class="mt-1 font-semibold"
                    :class="Number(item[1]) === 0 ? 'text-success-600' : 'text-error-600'"
                >
                    {{ amount(String(item[1])) }}
                </p>
            </div>
        </div>
    </div>
</template>
