<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { BranchOption, RegisterRow, TreasuryAccountOption } from '@/Types/treasury';

defineOptions({ layout: ErpLayout });
const props = defineProps<{
    register: { rows: RegisterRow[]; opening_balance: string; closing_balance: string };
    filters: { account_id: number | null; branch_id: number | null; date_from: string; date_to: string; search: string };
    branches: BranchOption[];
    accounts: TreasuryAccountOption[];
}>();
const filters = reactive({ ...props.filters });
const apply = (): void => router.get(route('treasury.register'), filters, { preserveState: true, replace: true });
const reset = (): void => router.get(route('treasury.register'));
const amount = (value: string): string => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 6 }).format(Number(value));
</script>

<template>
<Head title="Treasury Transaction Register" />
<div class="space-y-6">
    <div><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Treasury Transaction Register</h1><p class="mt-1 text-sm text-gray-500">Posted cash and bank General Ledger movements.</p></div>
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <select v-model="filters.account_id" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700"><option :value="null">All accounts</option><option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.code }} — {{ a.name }}</option></select>
            <select v-model="filters.branch_id" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700"><option :value="null">All branches</option><option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option></select>
            <input v-model="filters.date_from" type="date" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700" />
            <input v-model="filters.date_to" :min="filters.date_from" type="date" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700" />
            <input v-model="filters.search" placeholder="Journal, source, reference" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700" />
        </div>
        <div class="mt-4 flex justify-end gap-2"><button class="rounded-lg border border-gray-300 px-4 py-2 text-sm dark:border-gray-700" type="button" @click="reset">Reset</button><button class="rounded-lg bg-brand-500 px-4 py-2 text-sm text-white" type="button" @click="apply">Apply</button></div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2"><div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"><p class="text-sm text-gray-500">Opening Balance</p><p class="mt-2 text-2xl font-semibold">{{ amount(register.opening_balance) }}</p></div><div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"><p class="text-sm text-gray-500">Closing Balance</p><p class="mt-2 text-2xl font-semibold">{{ amount(register.closing_balance) }}</p></div></div>
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900"><div class="overflow-x-auto"><table class="min-w-[1200px] divide-y divide-gray-200 dark:divide-gray-800"><thead class="bg-gray-50 dark:bg-white/[0.03]"><tr><th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Date / Journal</th><th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Account</th><th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Branch</th><th class="px-4 py-3 text-left text-xs uppercase text-gray-500">Description</th><th class="px-4 py-3 text-right text-xs uppercase text-gray-500">Debit</th><th class="px-4 py-3 text-right text-xs uppercase text-gray-500">Credit</th><th class="px-4 py-3 text-right text-xs uppercase text-gray-500">Running</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800"><tr v-for="row in register.rows" :key="row.id"><td class="px-4 py-3 text-sm"><b>{{ row.posting_date }}</b><br><span class="text-xs text-gray-500">{{ row.journal_number }} · {{ row.source_document_number ?? '—' }}</span></td><td class="px-4 py-3 text-sm">{{ row.account_code }} — {{ row.account_name }}</td><td class="px-4 py-3 text-sm">{{ row.branch_code }} — {{ row.branch_name }}</td><td class="px-4 py-3 text-sm">{{ row.description ?? row.reference ?? '—' }}</td><td class="px-4 py-3 text-right text-sm">{{ amount(row.base_debit_amount) }}</td><td class="px-4 py-3 text-right text-sm">{{ amount(row.base_credit_amount) }}</td><td class="px-4 py-3 text-right text-sm font-semibold">{{ amount(row.base_running_balance) }}</td></tr><tr v-if="register.rows.length === 0"><td colspan="7" class="px-4 py-12 text-center text-sm text-gray-500">No posted treasury movements match the filters.</td></tr></tbody></table></div></div>
</div>
</template>
