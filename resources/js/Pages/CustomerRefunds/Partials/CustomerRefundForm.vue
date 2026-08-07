<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import type { CustomerRefundFormData, CustomerRefundFormProps, OpenCustomerItem } from '@/Types/customer-settlement';

const props = defineProps<CustomerRefundFormProps>();
const existing = props.document;
const form = useForm<CustomerRefundFormData>({
    branch_id: existing?.branch_id ?? props.selection.branch_id,
    customer_id: existing?.customer_id ?? props.selection.customer_id,
    refund_account_id: existing?.refund_account_id ?? null,
    refund_date: existing?.refund_date ?? props.defaults.refund_date,
    posting_date: existing?.posting_date ?? props.defaults.posting_date,
    currency_code: existing?.currency_code ?? props.selection.currency_code,
    exchange_rate: existing?.exchange_rate ?? props.defaults.exchange_rate,
    refund_method: existing?.refund_method ?? 'bank_transfer',
    refund_reference: existing?.refund_reference ?? '',
    cheque_number: existing?.cheque_number ?? '',
    cheque_date: existing?.cheque_date ?? '',
    reason: existing?.reason ?? '',
    notes: existing?.notes ?? '',
    allocations: existing?.allocations?.length ? existing.allocations.map((line) => ({ ...line })) : [{ credit_open_item_id: null, amount: '0.000000' }],
});

const isEditing = computed(() => existing?.id !== undefined);
const number = (value: string): number => Number.isFinite(Number.parseFloat(value)) ? Number.parseFloat(value) : 0;
const money = (value: string | number): string => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 6 }).format(typeof value === 'number' ? value : number(value));
const total = computed(() => form.allocations.reduce((sum, line) => sum + Math.max(number(line.amount), 0), 0));
const requiresCheque = computed(() => form.refund_method === 'cheque');
const requiredControl = computed(() => form.refund_method === 'cash' ? 'cash' : 'bank');
const filteredAccounts = computed(() => props.accounts.filter((account) => account.control_type === requiredControl.value));
const selectionReady = computed(() => form.branch_id !== null && form.customer_id !== null && form.currency_code.trim().length === 3);
const credit = (id: number | null): OpenCustomerItem | undefined => props.credits.find((item) => item.id === id);
const creditLabel = (item: OpenCustomerItem): string => `${item.document_number ?? `Open Item #${item.id}`} · ${item.item_type.replace(/_/g, ' ')} · ${item.currency_code} ${money(item.outstanding_amount)}`;

watch(() => form.refund_method, () => {
    if (!filteredAccounts.value.some((account) => account.id === form.refund_account_id)) {
        form.refund_account_id = filteredAccounts.value[0]?.id ?? null;
    }
    if (!requiresCheque.value) {
        form.cheque_number = '';
        form.cheque_date = '';
    }
});

const loadCredits = (): void => {
    if (!selectionReady.value || isEditing.value) return;
    router.get(route('customer-refunds.create'), { branch_id: form.branch_id, customer_id: form.customer_id, currency_code: form.currency_code.toUpperCase() }, { preserveScroll: true, replace: true });
};
const setMaximum = (index: number): void => { const line = form.allocations[index]; const item = line ? credit(line.credit_open_item_id) : undefined; if (line && item) line.amount = item.outstanding_amount; };
const addLine = (): void => form.allocations.push({ credit_open_item_id: null, amount: '0.000000' });
const removeLine = (index: number): void => { if (form.allocations.length > 1) form.allocations.splice(index, 1); };
const submit = (): void => { if (existing?.id) form.put(route('customer-refunds.update', existing.id), { preserveScroll: true }); else form.post(route('customer-refunds.store'), { preserveScroll: true }); };
</script>

<template>
<form class="space-y-6" @submit.prevent="submit">
    <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 sm:p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Refund Information</h2>
        <p class="mt-1 text-sm text-gray-500">Refund unused customer credits through an approved cash or bank account.</p>
        <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            <div><label class="mb-1.5 block text-sm font-medium">Branch</label><select v-model="form.branch_id" :disabled="isEditing" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white"><option :value="null">Select branch</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }} ({{ branch.code }})</option></select></div>
            <div><label class="mb-1.5 block text-sm font-medium">Customer</label><select v-model="form.customer_id" :disabled="isEditing" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white"><option :value="null">Select customer</option><option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }} ({{ customer.code }})</option></select></div>
            <div><label class="mb-1.5 block text-sm font-medium">Currency</label><div class="flex gap-2"><input v-model="form.currency_code" :readonly="isEditing" maxlength="3" class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 uppercase dark:border-gray-700 dark:text-white" /><button v-if="!isEditing" :disabled="!selectionReady" type="button" class="rounded-lg border border-brand-300 px-3 text-sm font-medium text-brand-600 disabled:opacity-50" @click="loadCredits">Load Credits</button></div></div>
            <div><label class="mb-1.5 block text-sm font-medium">Refund Date</label><input v-model="form.refund_date" type="date" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white" /></div>
            <div><label class="mb-1.5 block text-sm font-medium">Posting Date</label><input v-model="form.posting_date" type="date" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white" /></div>
            <div><label class="mb-1.5 block text-sm font-medium">Exchange Rate</label><input v-model="form.exchange_rate" min="0.00000001" step="0.00000001" type="number" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right dark:border-gray-700 dark:text-white" /></div>
            <div><label class="mb-1.5 block text-sm font-medium">Refund Method</label><select v-model="form.refund_method" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white"><option v-for="method in methods" :key="method.value" :value="method.value">{{ method.label }}</option></select></div>
            <div><label class="mb-1.5 block text-sm font-medium">Cash / Bank Account</label><select v-model="form.refund_account_id" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white"><option :value="null">Select account</option><option v-for="account in filteredAccounts" :key="account.id" :value="account.id">{{ account.code }} — {{ account.name }}</option></select></div>
            <div><label class="mb-1.5 block text-sm font-medium">External Reference</label><input v-model="form.refund_reference" maxlength="160" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white" /></div>
            <template v-if="requiresCheque"><div><label class="mb-1.5 block text-sm font-medium">Cheque Number</label><input v-model="form.cheque_number" maxlength="100" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white" /></div><div><label class="mb-1.5 block text-sm font-medium">Cheque Date</label><input v-model="form.cheque_date" type="date" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white" /></div></template>
            <div class="md:col-span-2 xl:col-span-3"><label class="mb-1.5 block text-sm font-medium">Reason</label><input v-model="form.reason" maxlength="500" placeholder="Reason for returning the customer credit" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white" /></div>
        </div>
        <p v-if="form.hasErrors" class="mt-4 text-sm text-error-500">Correct the highlighted validation errors before saving.</p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 p-5 dark:border-gray-800"><div><h2 class="text-lg font-semibold">Refunded Credits</h2><p class="mt-1 text-sm text-gray-500">A refund can consume multiple available credits from the same customer and currency.</p></div><button type="button" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white" @click="addLine">Add Credit</button></div>
        <div class="overflow-x-auto"><table class="min-w-[850px] divide-y divide-gray-200 dark:divide-gray-800"><thead class="bg-gray-50 dark:bg-white/[0.03]"><tr><th class="px-5 py-3 text-left text-xs uppercase text-gray-500">Customer Credit</th><th class="px-5 py-3 text-left text-xs uppercase text-gray-500">Refund Amount</th><th class="px-5 py-3 text-right text-xs uppercase text-gray-500">Action</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800"><tr v-for="(line, index) in form.allocations" :key="index"><td class="px-5 py-4"><select v-model="line.credit_open_item_id" class="h-11 w-full min-w-96 rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white"><option :value="null">Select available credit</option><option v-for="item in credits" :key="item.id" :value="item.id">{{ creditLabel(item) }}</option></select></td><td class="px-5 py-4"><div class="flex gap-2"><input v-model="line.amount" min="0.000001" step="0.000001" type="number" class="h-11 w-44 rounded-lg border border-gray-300 bg-transparent px-3 text-right dark:border-gray-700 dark:text-white" /><button type="button" class="rounded-lg border border-gray-300 px-3 text-xs dark:border-gray-700" @click="setMaximum(index)">Full</button></div><p v-if="credit(line.credit_open_item_id)" class="mt-1 text-xs text-gray-500">Available {{ money(credit(line.credit_open_item_id)?.outstanding_amount ?? '0') }}</p></td><td class="px-5 py-4 text-right"><button :disabled="form.allocations.length === 1" type="button" class="text-sm text-error-500 disabled:opacity-30" @click="removeLine(index)">Remove</button></td></tr></tbody></table></div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]"><div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"><label class="mb-1.5 block text-sm font-medium">Notes</label><textarea v-model="form.notes" rows="5" maxlength="4000" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 dark:border-gray-700 dark:text-white" /></div><div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"><p class="text-sm text-gray-500">Refund Total</p><p class="mt-2 text-2xl font-semibold text-brand-600">{{ form.currency_code }} {{ money(total) }}</p><p class="mt-3 text-xs text-gray-500">Cash base value is calculated from the refund exchange rate. Credit carrying values are retained for realized FX recognition.</p></div></div>
    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><Link :href="existing?.id ? route('customer-refunds.show', existing.id) : route('customer-refunds.index')" class="rounded-lg border border-gray-300 px-5 py-2.5 text-center text-sm dark:border-gray-700">Cancel</Link><button :disabled="form.processing || total <= 0" class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white disabled:opacity-60">{{ form.processing ? 'Saving...' : isEditing ? 'Update Refund Draft' : 'Create Refund Draft' }}</button></div>
</form>
</template>
