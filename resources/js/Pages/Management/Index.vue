<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { ManagementBranch, ManagementDashboard } from '@/Types/management';

defineOptions({ layout: ErpLayout });
const props = defineProps<{ dashboard: ManagementDashboard; branches: ManagementBranch[] }>();
const { can } = useAuthorization();
const filters = reactive({ ...props.dashboard.filters });
const money = (value: string): string => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value));
const percent = (value: string): string => `${Number(value).toFixed(2)}%`;
const apply = (): void => router.get(route('management.index'), { date_from: filters.date_from, date_to: filters.date_to, branch_id: filters.branch_id }, { preserveState: true, replace: true });
</script>
<template>
    <Head title="Executive Management Dashboard" />
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Executive Management Dashboard</h1><p class="text-sm text-gray-500">Posted-ledger KPIs and operating profitability in {{ dashboard.currency_code }}.</p></div><div class="flex flex-wrap gap-2"><Link v-if="can('management_budgets.view')" :href="route('management.budgets.index')" class="rounded-lg border px-3 py-2 text-sm">Budgets</Link><Link v-if="can('management_report_schedules.view')" :href="route('management.schedules.index')" class="rounded-lg border px-3 py-2 text-sm">Scheduled reports</Link></div></div>
        <div class="grid gap-3 rounded-xl border p-4 sm:grid-cols-4 dark:border-gray-800"><input v-model="filters.date_from" type="date" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900" /><input v-model="filters.date_to" type="date" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900" /><select v-model="filters.branch_id" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"><option :value="null">All accessible branches</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.code }} — {{ branch.name }}</option></select><button class="rounded-lg bg-gray-900 px-3 py-2 text-sm text-white dark:bg-white dark:text-gray-900" @click="apply">Refresh</button></div>
        <div class="grid gap-4 md:grid-cols-3 xl:grid-cols-6"><div v-for="item in [
            ['Revenue', dashboard.kpis.revenue, dashboard.kpis.revenue_change_percent],
            ['Gross profit', dashboard.kpis.gross_profit, dashboard.kpis.gross_margin_percent],
            ['Net profit', dashboard.kpis.net_profit, dashboard.kpis.net_margin_percent],
            ['Closing cash', dashboard.kpis.closing_cash, null],
            ['Total assets', dashboard.kpis.total_assets, null],
            ['Liabilities', dashboard.kpis.total_liabilities, null],
        ]" :key="String(item[0])" class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">{{ item[0] }}</p><p class="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{{ money(String(item[1])) }}</p><p v-if="item[2] !== null" class="mt-1 text-xs text-gray-500">{{ percent(String(item[2])) }}</p></div></div>
        <div class="grid gap-5 xl:grid-cols-2"><div class="rounded-xl border dark:border-gray-800"><div class="flex items-center justify-between border-b p-4 dark:border-gray-800"><h2 class="font-semibold">Branch profitability</h2><Link :href="route('management.reports.branch-profitability', dashboard.filters)" class="text-sm text-brand-600">Full report</Link></div><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="text-left text-xs uppercase text-gray-500"><th class="p-3">Branch</th><th class="p-3 text-right">Revenue</th><th class="p-3 text-right">Profit</th><th class="p-3 text-right">Margin</th></tr></thead><tbody><tr v-for="row in dashboard.branch_profitability" :key="row.branch_id" class="border-t dark:border-gray-800"><td class="p-3">{{ row.branch_code }} — {{ row.branch_name }}</td><td class="p-3 text-right">{{ money(row.revenue) }}</td><td class="p-3 text-right">{{ money(row.profit) }}</td><td class="p-3 text-right">{{ percent(row.margin_percent) }}</td></tr></tbody></table></div></div><div class="rounded-xl border dark:border-gray-800"><div class="flex items-center justify-between border-b p-4 dark:border-gray-800"><h2 class="font-semibold">Gross margin trend</h2><Link :href="route('management.reports.gross-margin', dashboard.filters)" class="text-sm text-brand-600">Full report</Link></div><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="text-left text-xs uppercase text-gray-500"><th class="p-3">Period</th><th class="p-3 text-right">Revenue</th><th class="p-3 text-right">Gross profit</th><th class="p-3 text-right">Margin</th></tr></thead><tbody><tr v-for="row in dashboard.gross_margin_trend" :key="row.period" class="border-t dark:border-gray-800"><td class="p-3">{{ row.period }}</td><td class="p-3 text-right">{{ money(row.revenue) }}</td><td class="p-3 text-right">{{ money(row.gross_profit) }}</td><td class="p-3 text-right">{{ percent(row.margin_percent) }}</td></tr></tbody></table></div></div></div>
    </div>
</template>
