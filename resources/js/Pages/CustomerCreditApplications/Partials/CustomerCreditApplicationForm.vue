<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import type {
    CustomerCreditApplicationFormData,
    CustomerCreditApplicationFormProps,
    OpenCustomerItem,
} from '@/Types/customer-settlement';

const props = defineProps<CustomerCreditApplicationFormProps>();
const existing = props.document;

const form = useForm<CustomerCreditApplicationFormData>({
    branch_id: existing?.branch_id ?? props.selection.branch_id,
    customer_id: existing?.customer_id ?? props.selection.customer_id,
    application_date: existing?.application_date ?? props.defaults.application_date,
    posting_date: existing?.posting_date ?? props.defaults.posting_date,
    currency_code: existing?.currency_code ?? props.selection.currency_code,
    reason: existing?.reason ?? '',
    notes: existing?.notes ?? '',
    lines: existing?.lines?.length
        ? existing.lines.map((line) => ({ ...line }))
        : [{ receivable_open_item_id: null, credit_open_item_id: null, amount: '0.000000' }],
});

const isEditing = computed(() => existing?.id !== undefined);
const selectionReady = computed(() => form.branch_id !== null && form.customer_id !== null && form.currency_code.trim().length === 3);
const number = (value: string): number => Number.isFinite(Number.parseFloat(value)) ? Number.parseFloat(value) : 0;
const money = (value: number | string): string => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 6 }).format(typeof value === 'number' ? value : number(value));
const total = computed(() => form.lines.reduce((sum, line) => sum + Math.max(number(line.amount), 0), 0));

const label = (item: OpenCustomerItem): string => `${item.document_number ?? `Open Item #${item.id}`} · ${item.currency_code} ${money(item.outstanding_amount)}`;
const selectedItem = (items: OpenCustomerItem[], id: number | null): OpenCustomerItem | undefined => items.find((item) => item.id === id);
const maximum = (index: number): number => {
    const line = form.lines[index];
    if (!line) return 0;
    const receivable = selectedItem(props.receivables, line.receivable_open_item_id);
    const credit = selectedItem(props.credits, line.credit_open_item_id);
    return Math.min(number(receivable?.outstanding_amount ?? '0'), number(credit?.outstanding_amount ?? '0'));
};

const fillMaximum = (index: number): void => {
    const line = form.lines[index];
    if (line) line.amount = maximum(index).toFixed(6);
};
const addLine = (): void => form.lines.push({ receivable_open_item_id: null, credit_open_item_id: null, amount: '0.000000' });
const removeLine = (index: number): void => { if (form.lines.length > 1) form.lines.splice(index, 1); };

const loadItems = (): void => {
    if (!selectionReady.value || isEditing.value) return;
    router.get(route('customer-credit-applications.create'), {
        branch_id: form.branch_id,
        customer_id: form.customer_id,
        currency_code: form.currency_code.toUpperCase(),
    }, { preserveScroll: true, replace: true });
};

const submit = (): void => {
    if (existing?.id) {
        form.put(route('customer-credit-applications.update', existing.id), { preserveScroll: true });
    } else {
        form.post(route('customer-credit-applications.store'), { preserveScroll: true });
    }
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Application Information</h2>
            <p class="mt-1 text-sm text-gray-500">Select one customer, branch, and currency. All applied items must match them.</p>
            <div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <div><label class="mb-1.5 block text-sm font-medium">Branch</label><select v-model="form.branch_id" :disabled="isEditing" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white"><option :value="null">Select branch</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }} ({{ branch.code }})</option></select><p v-if="form.errors.branch_id" class="mt-1 text-xs text-error-500">{{ form.errors.branch_id }}</p></div>
                <div><label class="mb-1.5 block text-sm font-medium">Customer</label><select v-model="form.customer_id" :disabled="isEditing" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white"><option :value="null">Select customer</option><option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }} ({{ customer.code }})</option></select><p v-if="form.errors.customer_id" class="mt-1 text-xs text-error-500">{{ form.errors.customer_id }}</p></div>
                <div><label class="mb-1.5 block text-sm font-medium">Currency</label><div class="flex gap-2"><input v-model="form.currency_code" :readonly="isEditing" maxlength="3" class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 uppercase dark:border-gray-700 dark:text-white" /><button v-if="!isEditing" :disabled="!selectionReady" type="button" class="rounded-lg border border-brand-300 px-3 text-sm font-medium text-brand-600 disabled:opacity-50" @click="loadItems">Load Items</button></div><p v-if="form.errors.currency_code" class="mt-1 text-xs text-error-500">{{ form.errors.currency_code }}</p></div>
                <div><label class="mb-1.5 block text-sm font-medium">Application Date</label><input v-model="form.application_date" type="date" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white" /></div>
                <div><label class="mb-1.5 block text-sm font-medium">Posting Date</label><input v-model="form.posting_date" type="date" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white" /></div>
                <div class="md:col-span-2 xl:col-span-1"><label class="mb-1.5 block text-sm font-medium">Reason</label><input v-model="form.reason" maxlength="500" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 dark:border-gray-700 dark:text-white" placeholder="Reason for applying the credit" /><p v-if="form.errors.reason" class="mt-1 text-xs text-error-500">{{ form.errors.reason }}</p></div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="flex items-center justify-between border-b border-gray-200 p-5 dark:border-gray-800 sm:p-6"><div><h2 class="text-lg font-semibold text-gray-900 dark:text-white">Credit Applications</h2><p class="mt-1 text-sm text-gray-500">One credit may be distributed across multiple invoices by using multiple lines.</p></div><button type="button" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white" @click="addLine">Add Line</button></div>
            <p v-if="form.errors.lines" class="px-5 pt-4 text-sm text-error-500">{{ form.errors.lines }}</p>
            <div class="overflow-x-auto"><table class="min-w-[1050px] divide-y divide-gray-200 dark:divide-gray-800"><thead class="bg-gray-50 dark:bg-white/[0.03]"><tr><th class="px-5 py-3 text-left text-xs uppercase text-gray-500">Receivable</th><th class="px-5 py-3 text-left text-xs uppercase text-gray-500">Customer Credit</th><th class="px-5 py-3 text-left text-xs uppercase text-gray-500">Amount</th><th class="px-5 py-3 text-right text-xs uppercase text-gray-500">Action</th></tr></thead><tbody class="divide-y divide-gray-100 dark:divide-gray-800"><tr v-for="(line, index) in form.lines" :key="index"><td class="px-5 py-4"><select v-model="line.receivable_open_item_id" class="h-11 w-full min-w-72 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white"><option :value="null">Select invoice/debit item</option><option v-for="item in receivables" :key="item.id" :value="item.id">{{ label(item) }}</option></select></td><td class="px-5 py-4"><select v-model="line.credit_open_item_id" class="h-11 w-full min-w-72 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white"><option :value="null">Select customer credit</option><option v-for="item in credits" :key="item.id" :value="item.id">{{ label(item) }}</option></select></td><td class="px-5 py-4"><div class="flex min-w-56 gap-2"><input v-model="line.amount" min="0.000001" step="0.000001" type="number" class="h-11 w-36 rounded-lg border border-gray-300 bg-transparent px-3 text-right dark:border-gray-700 dark:text-white" /><button type="button" class="rounded-lg border border-gray-300 px-3 text-xs dark:border-gray-700" @click="fillMaximum(index)">Max</button></div><p class="mt-1 text-xs text-gray-500">Available pair: {{ money(maximum(index)) }}</p></td><td class="px-5 py-4 text-right"><button :disabled="form.lines.length === 1" type="button" class="text-sm font-medium text-error-500 disabled:opacity-30" @click="removeLine(index)">Remove</button></td></tr></tbody></table></div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 sm:p-6"><label class="mb-1.5 block text-sm font-medium">Notes</label><textarea v-model="form.notes" rows="5" maxlength="4000" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 dark:border-gray-700 dark:text-white" /></div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 sm:p-6"><p class="text-sm text-gray-500">Total Applied</p><p class="mt-2 text-2xl font-semibold text-brand-600 dark:text-brand-400">{{ form.currency_code }} {{ money(total) }}</p><p class="mt-3 text-xs text-gray-500">Final base-currency amounts and realized FX differences are calculated during posting from each open item’s carrying value.</p></div>
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><Link :href="existing?.id ? route('customer-credit-applications.show', existing.id) : route('customer-credit-applications.index')" class="rounded-lg border border-gray-300 px-5 py-2.5 text-center text-sm font-medium dark:border-gray-700">Cancel</Link><button :disabled="form.processing || total <= 0" type="submit" class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white disabled:opacity-60">{{ form.processing ? 'Saving...' : isEditing ? 'Update Draft' : 'Create Draft' }}</button></div>
    </form>
</template>
