<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { CustomerArAdjustmentFormData, CustomerArAdjustmentFormProps } from '@/Types/customer-settlement';
const props = defineProps<CustomerArAdjustmentFormProps>();
const existing = props.document;
const form = useForm<CustomerArAdjustmentFormData>({
    branch_id: existing?.branch_id ?? null,
    customer_id: existing?.customer_id ?? null,
    offset_account_id: existing?.offset_account_id ?? null,
    adjustment_date: existing?.adjustment_date ?? props.defaults.adjustment_date,
    posting_date: existing?.posting_date ?? props.defaults.posting_date,
    currency_code: existing?.currency_code ?? props.defaults.currency_code,
    exchange_rate: existing?.exchange_rate ?? props.defaults.exchange_rate,
    direction: existing?.direction ?? 'debit',
    amount: existing?.amount ?? '0.000000',
    reason: existing?.reason ?? '',
    notes: existing?.notes ?? '',
});
const isEditing = computed(() => existing?.id !== undefined);
const baseAmount = computed(() => Math.max(Number(form.amount) || 0, 0) * Math.max(Number(form.exchange_rate) || 0, 0));
const money = (value: number): string => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 6 }).format(value);
const submit = (): void => { if (existing?.id) form.put(route('customer-ar-adjustments.update', existing.id), { preserveScroll: true }); else form.post(route('customer-ar-adjustments.store'), { preserveScroll: true }); };
</script>
<template>
<form class="space-y-6" @submit.prevent="submit">
<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 sm:p-6"><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Adjustment Information</h2><p class="mt-1 text-sm text-gray-500">Debit adjustments increase customer receivables. Credit adjustments create customer credit.</p><div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
<div><label class="mb-1.5 block text-sm font-medium">Branch</label><select v-model="form.branch_id" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white"><option :value="null">Select branch</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }} ({{ branch.code }})</option></select><p v-if="form.errors.branch_id" class="mt-1 text-xs text-error-500">{{ form.errors.branch_id }}</p></div>
<div><label class="mb-1.5 block text-sm font-medium">Customer</label><select v-model="form.customer_id" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white"><option :value="null">Select customer</option><option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }} ({{ customer.code }})</option></select></div>
<div><label class="mb-1.5 block text-sm font-medium">Offset Account</label><select v-model="form.offset_account_id" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white"><option :value="null">Select offset account</option><option v-for="account in accounts" :key="account.id" :value="account.id">{{ account.code }} — {{ account.name }}</option></select></div>
<div><label class="mb-1.5 block text-sm font-medium">Direction</label><select v-model="form.direction" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white"><option v-for="direction in directions" :key="direction.value" :value="direction.value">{{ direction.label }}</option></select></div>
<div><label class="mb-1.5 block text-sm font-medium">Adjustment Date</label><input v-model="form.adjustment_date" type="date" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white" /></div>
<div><label class="mb-1.5 block text-sm font-medium">Posting Date</label><input v-model="form.posting_date" type="date" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white" /></div>
<div><label class="mb-1.5 block text-sm font-medium">Currency</label><input v-model="form.currency_code" maxlength="3" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 uppercase dark:border-gray-700 dark:text-white" /></div>
<div><label class="mb-1.5 block text-sm font-medium">Exchange Rate</label><input v-model="form.exchange_rate" min="0.00000001" step="0.00000001" type="number" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right dark:border-gray-700 dark:text-white" /></div>
<div><label class="mb-1.5 block text-sm font-medium">Amount</label><input v-model="form.amount" min="0.000001" step="0.000001" type="number" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right dark:border-gray-700 dark:text-white" /></div>
<div class="md:col-span-2 xl:col-span-3"><label class="mb-1.5 block text-sm font-medium">Reason</label><input v-model="form.reason" maxlength="500" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white" placeholder="Business reason and supporting reference" /></div>
<div class="md:col-span-2 xl:col-span-3"><label class="mb-1.5 block text-sm font-medium">Notes</label><textarea v-model="form.notes" rows="5" maxlength="4000" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 dark:border-gray-700 dark:text-white" /></div>
</div></div>
<div :class="['rounded-2xl border p-5', form.direction === 'debit' ? 'border-warning-200 bg-warning-50 dark:border-warning-900/50 dark:bg-warning-900/20' : 'border-success-200 bg-success-50 dark:border-success-900/50 dark:bg-success-900/20']"><p class="text-sm font-medium">{{ form.direction === 'debit' ? 'Debit Adjustment' : 'Credit Adjustment' }}</p><p class="mt-1 text-sm">{{ form.direction === 'debit' ? 'Customer receivable will increase.' : 'An available customer credit will be created.' }}</p><p class="mt-3 text-xl font-semibold">{{ form.currency_code }} {{ money(Number(form.amount) || 0) }} · Base {{ money(baseAmount) }}</p></div>
<div v-if="form.hasErrors" class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700">Correct the validation errors before saving.</div>
<div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><Link :href="existing?.id ? route('customer-ar-adjustments.show', existing.id) : route('customer-ar-adjustments.index')" class="rounded-lg border border-gray-300 px-5 py-2.5 text-center text-sm dark:border-gray-700">Cancel</Link><button :disabled="form.processing || (Number(form.amount) || 0) <= 0" class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white disabled:opacity-60">{{ form.processing ? 'Saving...' : isEditing ? 'Update Adjustment Draft' : 'Create Adjustment Draft' }}</button></div>
</form>
</template>
