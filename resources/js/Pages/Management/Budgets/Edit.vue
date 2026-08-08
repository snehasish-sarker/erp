<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import ManagementBudgetForm from './Partials/ManagementBudgetForm.vue';
import type { ManagementAccount, ManagementBranch, ManagementBudgetFormData, ManagementFiscalYear } from '@/Types/management';

defineOptions({ layout: ErpLayout });
const props = defineProps<{ branches: ManagementBranch[]; fiscal_years: ManagementFiscalYear[]; accounts: ManagementAccount[]; currency_code: string; budget: ManagementBudgetFormData & { id: number } }>();
const form = useForm<ManagementBudgetFormData>({ branch_id: props.budget.branch_id, fiscal_year_id: props.budget.fiscal_year_id, name: props.budget.name, notes: props.budget.notes, lines: props.budget.lines.map((line) => ({ ...line })) });
const submit = (): void => form.put(route('management.budgets.update', props.budget.id));
</script>
<template><Head title="Edit Management Budget" /><div class="space-y-5"><div><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Edit Management Budget</h1><p class="text-sm text-gray-500">Changes are permitted only while the budget is in draft.</p></div><ManagementBudgetForm :form="form" :branches="props.branches" :fiscal-years="props.fiscal_years" :accounts="props.accounts" :currency-code="props.currency_code" submit-label="Save changes" @submit="submit" /></div></template>
