<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { ManagementBranch, ManagementFiscalYear, PaginatedManagementBudgets } from '@/Types/management';

defineOptions({ layout: ErpLayout });
const props = defineProps<{
    budgets: PaginatedManagementBudgets;
    filters: { search: string; branch_id: number | null; fiscal_year_id: number | null; status: string; per_page: number };
    branches: ManagementBranch[];
    fiscalYears: ManagementFiscalYear[];
    statuses: Array<{ value: string; label: string }>;
    can: { create: boolean };
}>();
const filters = reactive({ ...props.filters });
const apply = (page = 1): void => router.get(route('management.budgets.index'), { ...filters, page }, { preserveState: true, preserveScroll: true, replace: true });
const reset = (): void => { filters.search = ''; filters.branch_id = null; filters.fiscal_year_id = null; filters.status = ''; filters.per_page = 25; apply(); };
</script>
<template>
    <Head title="Management Budgets" />
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Management Budgets</h1><p class="text-sm text-gray-500">Branch-level annual plans by revenue and expense account.</p></div><Link v-if="can.create" :href="route('management.budgets.create')" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white">Create budget</Link></div>
        <div class="grid gap-3 rounded-xl border border-gray-200 p-4 md:grid-cols-5 dark:border-gray-800">
            <input v-model="filters.search" type="search" placeholder="Search budget" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900" @keyup.enter="apply()" />
            <select v-model="filters.branch_id" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"><option :value="null">All branches</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.code }} — {{ branch.name }}</option></select>
            <select v-model="filters.fiscal_year_id" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"><option :value="null">All fiscal years</option><option v-for="year in fiscalYears" :key="year.id" :value="year.id">{{ year.name }}</option></select>
            <select v-model="filters.status" class="rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900"><option value="">All statuses</option><option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option></select>
            <div class="flex gap-2"><button class="rounded-lg bg-gray-900 px-3 py-2 text-sm text-white dark:bg-white dark:text-gray-900" @click="apply()">Apply</button><button class="rounded-lg border px-3 py-2 text-sm" @click="reset">Reset</button></div>
        </div>
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
            <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="p-3">Budget</th><th class="p-3">Branch</th><th class="p-3">Fiscal year</th><th class="p-3">Status</th><th class="p-3 text-right">Actions</th></tr></thead><tbody><tr v-for="budget in budgets.data" :key="budget.id" class="border-t border-gray-100 dark:border-gray-800"><td class="p-3 font-medium text-gray-900 dark:text-white">{{ budget.name }}</td><td class="p-3">{{ budget.branch?.code }} — {{ budget.branch?.name }}</td><td class="p-3">{{ budget.fiscal_year?.name }}</td><td class="p-3"><span class="rounded-full px-2 py-1 text-xs" :class="budget.status === 'approved' ? 'bg-success-50 text-success-700 dark:bg-success-500/15' : 'bg-warning-50 text-warning-700 dark:bg-warning-500/15'">{{ budget.status_label }}</span></td><td class="p-3 text-right"><Link :href="route('management.budgets.show', budget.id)" class="text-brand-600">View</Link><Link v-if="budget.can.update" :href="route('management.budgets.edit', budget.id)" class="ml-3 text-gray-600 dark:text-gray-300">Edit</Link></td></tr><tr v-if="budgets.data.length === 0"><td colspan="5" class="p-8 text-center text-gray-500">No management budgets found.</td></tr></tbody></table></div>
            <div class="flex items-center justify-between border-t border-gray-200 p-3 text-sm dark:border-gray-800"><span>{{ budgets.meta.from ?? 0 }}–{{ budgets.meta.to ?? 0 }} of {{ budgets.meta.total }}</span><div class="flex gap-2"><button :disabled="budgets.meta.current_page <= 1" class="rounded border px-3 py-1 disabled:opacity-40" @click="apply(budgets.meta.current_page - 1)">Previous</button><button :disabled="budgets.meta.current_page >= budgets.meta.last_page" class="rounded border px-3 py-1 disabled:opacity-40" @click="apply(budgets.meta.current_page + 1)">Next</button></div></div>
        </div>
    </div>
</template>
