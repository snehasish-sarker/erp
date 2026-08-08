<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import type {
    BalanceSheetReport,
    CashFlowReport,
    FinancialCompany,
    FinancialStatementReport,
    ProfitAndLossReport,
    TrialBalanceReport,
} from '@/Types/financial-control';

const props = defineProps<{
    report: FinancialStatementReport;
    company: FinancialCompany;
}>();

const trialBalance = computed((): TrialBalanceReport | null => {
    return props.report.statement === 'trial_balance' ? props.report : null;
});

const profitAndLoss = computed((): ProfitAndLossReport | null => {
    return props.report.statement === 'profit_and_loss' ? props.report : null;
});

const balanceSheet = computed((): BalanceSheetReport | null => {
    return props.report.statement === 'balance_sheet' ? props.report : null;
});

const cashFlow = computed((): CashFlowReport | null => {
    return props.report.statement === 'cash_flow' ? props.report : null;
});

const amount = (value: string | number | null | undefined): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    }).format(Number(value ?? 0));
};

const title = computed((): string => props.report.title);

const periodLabel = computed((): string => {
    const filters = props.report.filters;

    if (filters.as_of_date) {
        return `As of ${filters.as_of_date}`;
    }

    if (filters.date_from && filters.date_to) {
        return `${filters.date_from} to ${filters.date_to}`;
    }

    return '';
});

const printPage = (): void => {
    window.print();
};

const displayLabel = (value: string): string => {
    return value
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter: string): string => letter.toUpperCase());
};
</script>

<template>
    <Head :title="title" />

    <main class="mx-auto max-w-[1100px] bg-white p-8 text-gray-950">
        <header class="mb-6 flex justify-between gap-8 border-b pb-4">
            <div>
                <h1 class="text-2xl font-bold">{{ company.name }}</h1>
                <p v-if="company.address" class="text-sm">{{ company.address }}</p>
                <p class="text-sm">
                    <span v-if="company.email">{{ company.email }}</span>
                    <span v-if="company.email && company.phone"> · </span>
                    <span v-if="company.phone">{{ company.phone }}</span>
                </p>
            </div>

            <div class="text-right">
                <h2 class="text-xl font-semibold">{{ title }}</h2>
                <p v-if="periodLabel" class="text-sm">{{ periodLabel }}</p>
                <p class="text-sm">Currency: {{ report.currency_code }}</p>
                <button
                    type="button"
                    class="mt-3 rounded border px-3 py-1 text-sm print:hidden"
                    @click="printPage"
                >
                    Print
                </button>
            </div>
        </header>

        <template v-if="trialBalance">
            <table class="w-full border-collapse text-xs">
                <thead>
                    <tr>
                        <th class="border p-2 text-left">Account</th>
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
                            class="border p-2 text-right"
                        >
                            {{ label }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in trialBalance.rows" :key="row.account_id">
                        <td class="border p-2">{{ row.code }} — {{ row.name }}</td>
                        <td class="border p-2 text-right">{{ amount(row.opening_debit) }}</td>
                        <td class="border p-2 text-right">{{ amount(row.opening_credit) }}</td>
                        <td class="border p-2 text-right">{{ amount(row.period_debit) }}</td>
                        <td class="border p-2 text-right">{{ amount(row.period_credit) }}</td>
                        <td class="border p-2 text-right">{{ amount(row.closing_debit) }}</td>
                        <td class="border p-2 text-right">{{ amount(row.closing_credit) }}</td>
                    </tr>
                </tbody>
                <tfoot class="font-semibold">
                    <tr>
                        <td class="border p-2">Totals</td>
                        <td class="border p-2 text-right">{{ amount(trialBalance.totals.opening_debit) }}</td>
                        <td class="border p-2 text-right">{{ amount(trialBalance.totals.opening_credit) }}</td>
                        <td class="border p-2 text-right">{{ amount(trialBalance.totals.period_debit) }}</td>
                        <td class="border p-2 text-right">{{ amount(trialBalance.totals.period_credit) }}</td>
                        <td class="border p-2 text-right">{{ amount(trialBalance.totals.closing_debit) }}</td>
                        <td class="border p-2 text-right">{{ amount(trialBalance.totals.closing_credit) }}</td>
                    </tr>
                </tfoot>
            </table>
        </template>

        <template v-else-if="cashFlow">
            <template v-if="cashFlow.method === 'direct'">
                <section
                    v-for="(rows, section) in cashFlow.direct.sections"
                    :key="section"
                    class="mb-5"
                >
                    <h3 class="mb-2 font-semibold capitalize">{{ section }} activities</h3>
                    <div
                        v-for="row in rows"
                        :key="row.label"
                        class="flex justify-between border-b py-1 text-sm"
                    >
                        <span>{{ row.label }}</span>
                        <span>{{ amount(row.amount) }}</span>
                    </div>
                </section>
                <div class="mt-4 border-t pt-3">
                    <div
                        v-for="(value, key) in cashFlow.direct.totals"
                        :key="key"
                        class="flex justify-between py-1 text-sm font-semibold"
                    >
                        <span>{{ displayLabel(String(key)) }}</span>
                        <span>{{ amount(value) }}</span>
                    </div>
                </div>
            </template>

            <template v-else>
                <div
                    v-for="row in cashFlow.indirect.rows"
                    :key="row.label"
                    class="flex justify-between border-b py-1 text-sm"
                >
                    <span>{{ row.label }}</span>
                    <span>{{ amount(row.amount) }}</span>
                </div>
                <div class="mt-4 border-t pt-3">
                    <div
                        v-for="(value, key) in cashFlow.indirect.totals"
                        :key="key"
                        class="flex justify-between py-1 text-sm font-semibold"
                    >
                        <span>{{ displayLabel(String(key)) }}</span>
                        <span>{{ amount(value) }}</span>
                    </div>
                </div>
            </template>
        </template>

        <template v-else-if="profitAndLoss">
            <section
                v-for="(rows, section) in profitAndLoss.sections"
                :key="section"
                class="mb-5"
            >
                <h3 class="mb-2 text-lg font-semibold">
                    {{ displayLabel(String(section)) }}
                </h3>
                <div
                    v-for="row in rows"
                    :key="`${section}-${row.code}`"
                    class="flex justify-between border-b py-1 text-sm"
                >
                    <span>{{ row.code }} — {{ row.name }}</span>
                    <span>{{ amount(row.amount) }}</span>
                </div>
            </section>
            <div class="mt-5 border-t pt-3">
                <div
                    v-for="(value, key) in profitAndLoss.totals"
                    :key="key"
                    class="flex justify-between py-1 font-semibold"
                >
                    <span>{{ displayLabel(String(key)) }}</span>
                    <span>{{ amount(value) }}</span>
                </div>
            </div>
        </template>

        <template v-else-if="balanceSheet">
            <section
                v-for="(rows, section) in balanceSheet.sections"
                :key="section"
                class="mb-5"
            >
                <h3 class="mb-2 text-lg font-semibold capitalize">{{ section }}</h3>
                <div
                    v-for="row in rows"
                    :key="`${section}-${row.code}`"
                    class="flex justify-between border-b py-1 text-sm"
                >
                    <span>{{ row.code }} — {{ row.name }}</span>
                    <span>{{ amount(row.amount) }}</span>
                </div>
            </section>
            <div class="mt-5 border-t pt-3">
                <div
                    v-for="(value, key) in balanceSheet.totals"
                    :key="key"
                    class="flex justify-between py-1 font-semibold"
                >
                    <span>{{ displayLabel(String(key)) }}</span>
                    <span>{{ amount(value) }}</span>
                </div>
            </div>
        </template>

        <footer class="mt-8 border-t pt-3 text-xs text-gray-500">
            Generated {{ report.generated_at }} · {{ company.code }}
        </footer>
    </main>
</template>

<style>
@media print {
    @page {
        size: A4;
        margin: 12mm;
    }

    body {
        background: #fff;
    }
}
</style>
