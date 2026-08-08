<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { BudgetVsActualReport, ManagementBranch, ManagementContext } from '@/Types/management';

defineOptions({ layout: ErpLayout });
type GenericRow = Record<string, string | number | null>;
const props = defineProps<{
    reportType: string;
    title: string;
    report: GenericRow[] | BudgetVsActualReport | null;
    filters: ManagementContext;
    branches: ManagementBranch[];
    budgets: Array<{ id: number; name: string; branch_name: string | null; fiscal_year_name: string | null }>;
    exportType: string;
}>();
const { can } = useAuthorization();
const filters = reactive({ ...props.filters });
const isBudget = computed(() => props.reportType === 'budget_vs_actual');
const rows = computed<GenericRow[]>(() => {
    if (props.report === null) return [];
    if (isBudget.value && !Array.isArray(props.report)) return props.report.rows as unknown as GenericRow[];
    return Array.isArray(props.report) ? props.report : [];
});
const columns = computed(() => {
    const map: Record<string, Array<{ key: string; label: string; numeric?: boolean }>> = {
        branch_profitability: [{ key: 'branch_code', label: 'Code' }, { key: 'branch_name', label: 'Branch' }, { key: 'revenue', label: 'Revenue', numeric: true }, { key: 'expenses', label: 'Expenses', numeric: true }, { key: 'profit', label: 'Profit', numeric: true }, { key: 'margin_percent', label: 'Margin %', numeric: true }],
        product_profitability: [{ key: 'product_sku', label: 'SKU' }, { key: 'product_name', label: 'Product' }, { key: 'revenue', label: 'Revenue', numeric: true }, { key: 'cost', label: 'Cost', numeric: true }, { key: 'gross_profit', label: 'Gross Profit', numeric: true }, { key: 'margin_percent', label: 'Margin %', numeric: true }],
        customer_profitability: [{ key: 'customer_code', label: 'Code' }, { key: 'customer_name', label: 'Customer' }, { key: 'revenue', label: 'Revenue', numeric: true }, { key: 'cost', label: 'Cost', numeric: true }, { key: 'gross_profit', label: 'Gross Profit', numeric: true }, { key: 'margin_percent', label: 'Margin %', numeric: true }],
        supplier_spend: [{ key: 'supplier_code', label: 'Code' }, { key: 'supplier_name', label: 'Supplier' }, { key: 'gross_spend', label: 'Gross Spend', numeric: true }, { key: 'debit_notes', label: 'Debit Notes', numeric: true }, { key: 'net_spend', label: 'Net Spend', numeric: true }],
        gross_margin: [{ key: 'period', label: 'Period' }, { key: 'revenue', label: 'Revenue', numeric: true }, { key: 'cost', label: 'Cost', numeric: true }, { key: 'gross_profit', label: 'Gross Profit', numeric: true }, { key: 'margin_percent', label: 'Margin %', numeric: true }],
        budget_vs_actual: [{ key: 'account_code', label: 'Code' }, { key: 'account_name', label: 'Account' }, { key: 'account_type', label: 'Type' }, { key: 'month_number', label: 'Month' }, { key: 'budget_amount', label: 'Budget', numeric: true }, { key: 'actual_amount', label: 'Actual', numeric: true }, { key: 'variance_amount', label: 'Favourable Variance', numeric: true }, { key: 'variance_percent', label: 'Variance %', numeric: true }],
    };
    return map[props.reportType] ?? [];
});
const routeName = computed(() => `management.reports.${props.reportType.replaceAll('_', '-')}`);
const query = (): Record<string, string | number> => {
    const result: Record<string, string | number> = { limit: filters.limit };
    if (!isBudget.value) { result.date_from = filters.date_from; result.date_to = filters.date_to; if (filters.branch_id !== null) result.branch_id = filters.branch_id; }
    if (isBudget.value && filters.budget_id !== null) result.budget_id = filters.budget_id;
    return result;
};
const apply = (): void => router.get(route(routeName.value), query(), { preserveState: true, replace: true });
const exportReport = (format: 'csv' | 'xlsx'): void => router.post(route('exports.store'), { export_type: props.exportType, format, filters: query() }, { preserveScroll: true });
const display = (value: string | number | null, numeric = false): string => {
    if (value === null) return '—';
    if (!numeric) return String(value);
    return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 6 }).format(Number(value));
};
</script>
<template>
    <Head :title="title" />
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">{{ title }}</h1><p class="text-sm text-gray-500">Management analysis based on posted operational and General Ledger data.</p></div><div v-if="can('exports.create')" class="flex gap-2"><button class="rounded-lg border px-3 py-2 text-sm" @click="exportReport('csv')">CSV</button><button class="rounded-lg border px-3 py-2 text-sm" @click="exportReport('xlsx')">Excel</button></div></div>
        <div class="grid gap-3 rounded-xl border p-4 md:grid-cols-5 dark:border-gray-800">
            <template v-if="!isBudget"><input v-model="filters.date_from" type="date" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900" /><input v-model="filters.date_to" type="date" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900" /><select v-model="filters.branch_id" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"><option :value="null">All accessible branches</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.code }} — {{ branch.name }}</option></select><input v-model="filters.limit" type="number" min="10" max="500" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900" /></template>
            <template v-else><select v-model="filters.budget_id" class="md:col-span-3 rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"><option :value="null">Select approved budget</option><option v-for="budget in budgets" :key="budget.id" :value="budget.id">{{ budget.branch_name }} — {{ budget.name }} ({{ budget.fiscal_year_name }})</option></select></template>
            <button class="rounded-lg bg-gray-900 px-3 py-2 text-sm text-white dark:bg-white dark:text-gray-900" @click="apply">Apply</button>
        </div>
        <div v-if="isBudget && report !== null && !Array.isArray(report)" class="grid gap-4 sm:grid-cols-3"><div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Budget</p><p class="text-xl font-semibold">{{ display(report.totals.budget, true) }}</p></div><div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Actual</p><p class="text-xl font-semibold">{{ display(report.totals.actual, true) }}</p></div><div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Difference</p><p class="text-xl font-semibold">{{ display(report.totals.difference, true) }}</p></div></div>
        <div class="overflow-hidden rounded-xl border dark:border-gray-800"><div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th v-for="column in columns" :key="column.key" class="p-3" :class="column.numeric ? 'text-right' : ''">{{ column.label }}</th></tr></thead><tbody><tr v-for="(row, index) in rows" :key="index" class="border-t dark:border-gray-800"><td v-for="column in columns" :key="column.key" class="p-3" :class="column.numeric ? 'text-right tabular-nums' : ''">{{ display(row[column.key] ?? null, column.numeric) }}</td></tr><tr v-if="rows.length === 0"><td :colspan="columns.length || 1" class="p-8 text-center text-gray-500">No report data is available for the selected filters.</td></tr></tbody></table></div></div>
    </div>
</template>
