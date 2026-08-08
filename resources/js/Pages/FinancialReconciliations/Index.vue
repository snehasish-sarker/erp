<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import { useAuthorization } from '@/Composables/useAuthorization';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    FinancialBranchOption,
    FinancialReconciliationReport,
    ReconciliationLine,
} from '@/Types/financial-control';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    report: FinancialReconciliationReport;
    branches: FinancialBranchOption[];
}>();

const { can } = useAuthorization();
const canExport = computed((): boolean => can('exports.create'));

const filters = reactive({
    as_of_date: props.report.as_of_date,
    branch_id: props.report.branch_id,
});

const amount = (value: string): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    }).format(Number(value));
};

const queryParameters = (): Record<string, string | number> => {
    const query: Record<string, string | number> = {
        as_of_date: filters.as_of_date,
    };

    if (filters.branch_id !== null) {
        query.branch_id = filters.branch_id;
    }

    return query;
};

const controls = computed((): Array<{ label: string; data: ReconciliationLine }> => [
    {
        label: 'Accounts Receivable',
        data: props.report.accounts_receivable,
    },
    {
        label: 'Accounts Payable',
        data: props.report.accounts_payable,
    },
    {
        label: 'Inventory',
        data: props.report.inventory,
    },
    {
        label: 'Treasury Clearing',
        data: {
            general_ledger: props.report.treasury_clearing.ledger_balance,
            subledger: '0.000000',
            difference: props.report.treasury_clearing.difference,
            status: props.report.treasury_clearing.status,
        },
    },
]);

const apply = (): void => {
    router.get(
        route('financial-control.reconciliations'),
        queryParameters(),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const requestExport = (format: 'csv' | 'xlsx'): void => {
    router.post(
        route('exports.store'),
        {
            export_type: 'financial_reconciliations',
            format,
            filters: queryParameters(),
        },
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Financial Reconciliations" />

    <div class="space-y-5">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Financial Reconciliations
                </h1>
                <p class="text-sm text-gray-500">
                    Control accounts compared with operational subledgers as of
                    {{ report.as_of_date }}.
                </p>
            </div>

            <div
                class="rounded-full px-3 py-1 text-sm font-medium"
                :class="report.summary.status === 'reconciled'
                    ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-300'
                    : 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-300'"
            >
                {{ report.summary.status.replaceAll('_', ' ') }}
            </div>
        </div>

        <div
            class="flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
        >
            <label class="text-sm">
                <span class="mb-1 block text-gray-500">As of</span>
                <input
                    v-model="filters.as_of_date"
                    type="date"
                    class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                />
            </label>

            <label class="text-sm">
                <span class="mb-1 block text-gray-500">Branch</span>
                <select
                    v-model="filters.branch_id"
                    class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-950"
                >
                    <option :value="null">All accessible branches</option>
                    <option
                        v-for="branch in branches"
                        :key="branch.id"
                        :value="branch.id"
                    >
                        {{ branch.code }} — {{ branch.name }}
                    </option>
                </select>
            </label>

            <button
                type="button"
                class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white"
                @click="apply"
            >
                Apply
            </button>

            <template v-if="canExport">
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium dark:border-gray-700"
                    @click="requestExport('csv')"
                >
                    CSV
                </button>
                <button
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium dark:border-gray-700"
                    @click="requestExport('xlsx')"
                >
                    Excel
                </button>
            </template>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="item in controls"
                :key="item.label"
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="flex justify-between gap-3">
                    <h2 class="font-semibold text-gray-900 dark:text-white">
                        {{ item.label }}
                    </h2>
                    <span
                        class="rounded-full px-2 py-1 text-xs"
                        :class="item.data.status === 'reconciled'
                            ? 'bg-success-50 text-success-700'
                            : 'bg-error-50 text-error-700'"
                    >
                        {{ item.data.status }}
                    </span>
                </div>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt>General Ledger</dt>
                        <dd>{{ amount(item.data.general_ledger) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt>Subledger</dt>
                        <dd>{{ amount(item.data.subledger) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 font-semibold">
                        <dt>Difference</dt>
                        <dd>{{ amount(item.data.difference) }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                <p class="text-sm text-gray-500">Total absolute difference</p>
                <p class="mt-1 text-xl font-semibold">
                    {{ report.currency_code }}
                    {{ amount(report.summary.total_absolute_difference) }}
                </p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                <p class="text-sm text-gray-500">Unreconciled bank accounts</p>
                <p class="mt-1 text-xl font-semibold">
                    {{ report.summary.unreconciled_bank_accounts }}
                </p>
            </div>
        </div>

        <div
            class="overflow-x-auto rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"
        >
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3 text-left">Bank / Branch</th>
                        <th class="px-4 py-3 text-right">Book balance</th>
                        <th class="px-4 py-3 text-left">Last reconciliation</th>
                        <th class="px-4 py-3 text-right">Difference</th>
                        <th class="px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="bank in report.bank_accounts"
                        :key="`${bank.account_id}-${bank.branch_id}`"
                        class="border-t border-gray-100 dark:border-gray-800"
                    >
                        <td class="px-4 py-3">
                            {{ bank.account_code }} — {{ bank.account_name }} /
                            {{ bank.branch_code }} — {{ bank.branch_name }}
                        </td>
                        <td class="px-4 py-3 text-right">{{ amount(bank.book_balance) }}</td>
                        <td class="px-4 py-3">
                            <span>{{ bank.last_reconciliation_date ?? 'Not reconciled' }}</span>
                            <span v-if="bank.last_reconciliation_number" class="block text-xs text-gray-500">
                                {{ bank.last_reconciliation_number }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            {{ amount(bank.difference_since_reconciliation) }}
                        </td>
                        <td class="px-4 py-3">{{ bank.status }}</td>
                    </tr>
                    <tr v-if="report.bank_accounts.length === 0">
                        <td colspan="5" class="px-4 py-10 text-center text-gray-500">
                            No active bank balances were found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
