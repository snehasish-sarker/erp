<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { TreasuryAccountCard } from '@/Types/treasury';

defineOptions({ layout: ErpLayout });

defineProps<{
    accounts: TreasuryAccountCard[];
    metrics: { draft_transfers: number; draft_adjustments: number; unreconciled_statements: number; draft_reconciliations: number };
    can: { view_register: boolean; create_transfer: boolean; create_adjustment: boolean; import_statement: boolean; create_reconciliation: boolean };
}>();

const amount = (value: string): string => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 6 }).format(Number(value));
</script>

<template>
    <Head title="Treasury" />
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Treasury</h1><p class="mt-1 text-sm text-gray-500">Cash, bank, transfers, statement imports, and reconciliation.</p></div>
            <div class="flex flex-wrap gap-2">
                <Link v-if="can.view_register" :href="route('treasury.register')" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium dark:border-gray-700">Transaction Register</Link>
                <Link v-if="can.create_transfer" :href="route('treasury-transfers.create')" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white">New Transfer</Link>
                <Link v-if="can.import_statement" :href="route('bank-statements.create')" class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-medium text-white dark:bg-white dark:text-gray-900">Import Statement</Link>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="item in [
                ['Transfers Awaiting Action', metrics.draft_transfers, route('treasury-transfers.index')],
                ['Adjustments Awaiting Action', metrics.draft_adjustments, route('treasury-adjustments.index')],
                ['Unreconciled Statements', metrics.unreconciled_statements, route('bank-statements.index')],
                ['Open Reconciliations', metrics.draft_reconciliations, route('bank-reconciliations.index')],
            ]" :key="String(item[0])" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm text-gray-500">{{ item[0] }}</p><p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">{{ item[1] }}</p><Link :href="String(item[2])" class="mt-3 inline-block text-sm font-medium text-brand-600">Open</Link>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-5 dark:border-gray-800"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Cash and Bank Accounts</h2></div>
            <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="account in accounts" :key="account.id" class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-gray-900 dark:text-white">{{ account.code }} — {{ account.name }}</p><p class="mt-1 text-xs uppercase tracking-wide text-gray-500">{{ account.control_type }}</p></div><span v-if="account.unmatched_statement_lines > 0" class="rounded-full bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-700">{{ account.unmatched_statement_lines }} unmatched</span></div>
                    <p class="mt-5 text-2xl font-semibold text-gray-900 dark:text-white">{{ amount(account.base_balance) }}</p>
                    <Link :href="route('treasury.register', { account_id: account.id })" class="mt-3 inline-block text-sm font-medium text-brand-600">View register</Link>
                </div>
                <p v-if="accounts.length === 0" class="text-sm text-gray-500">No active cash or bank accounts are configured.</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3">
            <Link v-if="can.create_adjustment" :href="route('treasury-adjustments.create')" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium dark:border-gray-700">New Bank Adjustment</Link>
            <Link v-if="can.create_reconciliation" :href="route('bank-reconciliations.create')" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium dark:border-gray-700">Start Reconciliation</Link>
        </div>
    </div>
</template>
