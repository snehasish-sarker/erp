<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import { ref } from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    CustomerCreditNoteCreateProps,
} from '@/Types/customer-credit-note';

import CustomerCreditNoteForm from './Partials/CustomerCreditNoteForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<CustomerCreditNoteCreateProps>();

const selectedInvoiceId = ref<number | null>(
    props.selectedSalesInvoice?.id ?? null,
);

const loadInvoice = (): void => {
    if (selectedInvoiceId.value === null) {
        return;
    }

    router.get(
        route('sales-returns.create'),
        {
            sales_invoice_id: selectedInvoiceId.value,
        },
        {
            preserveState: false,
            preserveScroll: true,
            replace: true,
        },
    );
};
</script>

<template>
    <Head title="Create Customer Credit Note" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="mb-2 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <Link
                        :href="route('sales-returns.index')"
                        class="hover:text-brand-500"
                    >
                        Sales Returns
                    </Link>

                    <span>/</span>
                    <span class="text-gray-700 dark:text-gray-300">Create</span>
                </div>

                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Create Customer Credit Note
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Credit invoice quantities or values and optionally restore returned stock.
                </p>
            </div>

            <Link
                :href="route('sales-returns.index')"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Back to Sales Returns
            </Link>
        </div>

        <div
            v-if="selectedSalesInvoice === null"
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
        >
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Select a Posted Sales Invoice
            </h2>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Only posted invoices with remaining creditable quantity or value are shown.
            </p>

            <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                <select
                    v-model="selectedInvoiceId"
                    class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                >
                    <option :value="null">Select a Sales Invoice</option>

                    <option
                        v-for="invoice in salesInvoices"
                        :key="invoice.id"
                        :value="invoice.id"
                    >
                        {{ invoice.invoice_number ?? `Invoice #${invoice.id}` }}
                        — {{ invoice.customer_name }}
                        — {{ invoice.currency_code }} {{ invoice.total_amount }}
                    </option>
                </select>

                <button
                    :disabled="selectedInvoiceId === null"
                    type="button"
                    class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="loadInvoice"
                >
                    Continue
                </button>
            </div>

            <p
                v-if="salesInvoices.length === 0"
                class="mt-5 text-sm text-warning-600 dark:text-warning-400"
            >
                No posted Sales Invoice currently has a remaining creditable balance.
            </p>
        </div>

        <CustomerCreditNoteForm
            v-else
            :sales-invoice="selectedSalesInvoice"
            :defaults="defaults"
        />
    </div>
</template>
