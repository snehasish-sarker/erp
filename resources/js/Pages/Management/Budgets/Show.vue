<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { ManagementBudgetDetail } from '@/Types/management';

defineOptions({ layout: ErpLayout });
const props = defineProps<{ budget: ManagementBudgetDetail }>();
const money = (value: string): string => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 6 }).format(Number(value));
const month = (value: number): string => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][value - 1] ?? String(value);
const approve = (): void => { if (window.confirm('Approve this management budget and freeze editing?')) router.post(route('management.budgets.approve', props.budget.id)); };
const reopen = (): void => { if (window.confirm('Reopen this approved management budget for editing?')) router.post(route('management.budgets.reopen', props.budget.id)); };
const remove = (): void => { if (window.confirm('Delete this draft management budget?')) router.delete(route('management.budgets.destroy', props.budget.id)); };
</script>
<template>
    <Head :title="budget.name" />
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-sm text-gray-500">{{ budget.branch?.code }} · {{ budget.fiscal_year?.name }}</p><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ budget.name }}</h1><p class="text-sm text-gray-500">{{ budget.currency_code }} · {{ budget.status_label }}</p></div><div class="flex flex-wrap gap-2"><Link v-if="budget.can.update" :href="route('management.budgets.edit', budget.id)" class="rounded-lg border px-3 py-2 text-sm">Edit</Link><button v-if="budget.can.approve" class="rounded-lg bg-success-600 px-3 py-2 text-sm text-white" @click="approve">Approve</button><button v-if="budget.can.reopen" class="rounded-lg bg-warning-500 px-3 py-2 text-sm text-white" @click="reopen">Reopen</button><button v-if="budget.can.delete" class="rounded-lg bg-error-600 px-3 py-2 text-sm text-white" @click="remove">Delete</button></div></div>
        <div class="grid gap-4 sm:grid-cols-3"><div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Total planned</p><p class="mt-1 text-xl font-semibold">{{ budget.currency_code }} {{ money(budget.total_amount) }}</p></div><div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Lines</p><p class="mt-1 text-xl font-semibold">{{ budget.lines.length }}</p></div><div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Approved by</p><p class="mt-1 font-semibold">{{ budget.approved_by?.name ?? 'Not approved' }}</p></div></div>
        <div v-if="budget.notes" class="rounded-xl border p-4 text-sm dark:border-gray-800">{{ budget.notes }}</div>
        <div class="overflow-hidden rounded-xl border dark:border-gray-800"><table class="min-w-full text-sm"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="p-3">Account</th><th class="p-3">Type</th><th class="p-3">Month</th><th class="p-3 text-right">Amount</th><th class="p-3">Notes</th></tr></thead><tbody><tr v-for="line in budget.lines" :key="line.id" class="border-t dark:border-gray-800"><td class="p-3">{{ line.account_code }} — {{ line.account_name }}</td><td class="p-3 capitalize">{{ line.account_type }}</td><td class="p-3">{{ month(line.month_number) }}</td><td class="p-3 text-right">{{ money(line.amount) }}</td><td class="p-3 text-gray-500">{{ line.notes ?? '—' }}</td></tr></tbody></table></div>
    </div>
</template>
