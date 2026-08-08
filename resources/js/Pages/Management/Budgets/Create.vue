<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import ManagementBudgetForm from './Partials/ManagementBudgetForm.vue';
import type { ManagementAccount, ManagementBranch, ManagementBudgetFormData, ManagementFiscalYear } from '@/Types/management';

defineOptions({ layout: ErpLayout });
const props = defineProps<{ branches: ManagementBranch[]; fiscal_years: ManagementFiscalYear[]; accounts: ManagementAccount[]; currency_code: string }>();
const form = useForm<ManagementBudgetFormData>({ branch_id: null, fiscal_year_id: null, name: '', notes: '', lines: [] });
const submit = (): void => form.post(route('management.budgets.store'));
</script>
<template><Head title="Create Management Budget" /><div class="space-y-5"><div><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Create Management Budget</h1><p class="text-sm text-gray-500">Build a branch-level monthly revenue and expense plan.</p></div><ManagementBudgetForm :form="form" :branches="props.branches" :fiscal-years="props.fiscal_years" :accounts="props.accounts" :currency-code="props.currency_code" submit-label="Create budget" @submit="submit" /></div></template>
