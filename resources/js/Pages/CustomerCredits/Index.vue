<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { CustomerCreditBalanceProps } from '@/Types/customer-settlement';

defineOptions({ layout: ErpLayout });
const props = defineProps<CustomerCreditBalanceProps>();

const filters = reactive({ ...props.filters });
const pages = computed(() => {
    const result: number[] = [];
    for (
        let page = Math.max(1, props.credits.meta.current_page - 2);
        page <= Math.min(props.credits.meta.last_page, props.credits.meta.current_page + 2);
        page += 1
    ) {
        result.push(page);
    }
    return result;
});

const visit = (page = 1): void => {
    const query: Record<string, string | number> = { page, per_page: filters.per_page };
    if (filters.search.trim()) query.search = filters.search.trim();
    if (filters.branch_id) query.branch_id = filters.branch_id;
    if (filters.customer_id) query.customer_id = filters.customer_id;
    if (filters.currency_code.trim()) query.currency_code = filters.currency_code.trim().toUpperCase();
    if (filters.item_type) query.item_type = filters.item_type;
    router.get(route('customer-credits.index'), query, { preserveState: true, preserveScroll: true, replace: true });
};

const reset = (): void => router.get(route('customer-credits.index'));
const amount = (value: string): string => new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 6 }).format(Number(value));
const date = (value: string | null): string => value ? new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(`${value}T00:00:00`)) : '—';
const label = (value: string): string => value.replace(/_/g, ' ').replace(/\b\w/g, (character) => character.toUpperCase());
</script>

<template>
    <Head title="Customer Credit Balances" />
    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Customer Credit Balances</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Review unallocated receipts, Credit Notes, and AR credit adjustments.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link v-if="can.apply" :href="route('customer-credit-applications.create')" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600">Apply Credit</Link>
                <Link v-if="can.refund" :href="route('customer-refunds.create')" class="rounded-lg border border-brand-300 px-4 py-2.5 text-sm font-medium text-brand-600 dark:border-brand-800 dark:text-brand-400">Create Refund</Link>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div v-for="item in summary" :key="item.currency_code" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ item.currency_code }} Available Credit</p>
                <p class="mt-2 text-xl font-semibold text-brand-600 dark:text-brand-400">{{ amount(item.outstanding_amount) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ item.item_count }} open items · Base {{ amount(item.base_outstanding_amount) }}</p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <input v-model="filters.search" type="search" placeholder="Document or customer" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white xl:col-span-2" />
                <select v-model="filters.branch_id" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white"><option :value="null">All branches</option><option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }} ({{ branch.code }})</option></select>
                <select v-model="filters.customer_id" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white"><option :value="null">All customers</option><option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }} ({{ customer.code }})</option></select>
                <input v-model="filters.currency_code" maxlength="3" placeholder="Currency" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm uppercase dark:border-gray-700 dark:text-white" />
                <select v-model="filters.item_type" class="h-11 rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white"><option value="">All credit types</option><option value="credit">Credit Note</option><option value="receipt">Customer Advance</option><option value="adjustment_credit">Credit Adjustment</option></select>
            </div>
            <div class="mt-4 flex justify-end gap-2"><button type="button" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm dark:border-gray-700" @click="reset">Reset</button><button type="button" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white" @click="visit(1)">Apply Filters</button></div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-[1050px] divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.03]"><tr><th class="px-5 py-3 text-left text-xs uppercase text-gray-500">Document</th><th class="px-5 py-3 text-left text-xs uppercase text-gray-500">Customer</th><th class="px-5 py-3 text-left text-xs uppercase text-gray-500">Branch</th><th class="px-5 py-3 text-left text-xs uppercase text-gray-500">Posting Date</th><th class="px-5 py-3 text-left text-xs uppercase text-gray-500">Type</th><th class="px-5 py-3 text-right text-xs uppercase text-gray-500">Original</th><th class="px-5 py-3 text-right text-xs uppercase text-gray-500">Applied</th><th class="px-5 py-3 text-right text-xs uppercase text-gray-500">Available</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-if="credits.data.length === 0"><td colspan="8" class="px-5 py-12 text-center text-sm text-gray-500">No available customer credits found.</td></tr>
                        <tr v-for="credit in credits.data" :key="credit.id">
                            <td class="px-5 py-4"><p class="font-medium text-gray-900 dark:text-white">{{ credit.document_number ?? `Open Item #${credit.id}` }}</p><p class="text-xs text-gray-500">{{ credit.currency_code }} · Rate {{ credit.exchange_rate }}</p></td>
                            <td class="px-5 py-4"><p class="text-sm font-medium text-gray-900 dark:text-white">{{ credit.customer?.name ?? '—' }}</p><p class="text-xs text-gray-500">{{ credit.customer?.code }}</p></td>
                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">{{ credit.branch?.name ?? '—' }}</td>
                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">{{ date(credit.posting_date) }}</td>
                            <td class="px-5 py-4"><span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">{{ label(credit.item_type) }}</span></td>
                            <td class="px-5 py-4 text-right text-sm">{{ amount(credit.original_amount) }}</td>
                            <td class="px-5 py-4 text-right text-sm">{{ amount(credit.allocated_amount) }}</td>
                            <td class="px-5 py-4 text-right text-sm font-semibold text-brand-600 dark:text-brand-400">{{ amount(credit.outstanding_amount) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500">Showing {{ credits.meta.from ?? 0 }}–{{ credits.meta.to ?? 0 }} of {{ credits.meta.total }}</p>
                <div v-if="credits.meta.last_page > 1" class="flex gap-1"><button :disabled="credits.meta.current_page <= 1" class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700" @click="visit(credits.meta.current_page - 1)">Previous</button><button v-for="page in pages" :key="page" :class="['rounded-lg px-3 py-2 text-sm', page === credits.meta.current_page ? 'bg-brand-500 text-white' : 'border border-gray-300 dark:border-gray-700']" @click="visit(page)">{{ page }}</button><button :disabled="credits.meta.current_page >= credits.meta.last_page" class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700" @click="visit(credits.meta.current_page + 1)">Next</button></div>
            </div>
        </div>
    </div>
</template>
