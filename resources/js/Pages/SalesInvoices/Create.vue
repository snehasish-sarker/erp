<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import { ref } from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    SalesInvoiceCreateProps,
} from '@/Types/sales-invoice';

import SalesInvoiceForm from './Partials/SalesInvoiceForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props =
    defineProps<SalesInvoiceCreateProps>();

const selectedOrderId =
    ref<number | null>(
        props.selectedSalesOrder?.id
        ?? null,
    );

const loadOrder = (): void => {
    if (
        selectedOrderId.value
        === null
    ) {
        return;
    }

    router.get(
        route('sales-invoices.create'),
        {
            sales_order_id:
                selectedOrderId.value,
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
    <Head title="Create Sales Invoice" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div
                    class="mb-2 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    <Link
                        :href="
                            route(
                                'sales-invoices.index',
                            )
                        "
                        class="hover:text-brand-500"
                    >
                        Sales Invoices
                    </Link>

                    <span>/</span>

                    <span
                        class="text-gray-700 dark:text-gray-300"
                    >
                        Create
                    </span>
                </div>

                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Create Sales Invoice
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Invoice posted customer dispatch
                    quantities from one Sales Order.
                </p>
            </div>

            <Link
                :href="
                    route(
                        'sales-invoices.index',
                    )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Back to Sales Invoices
            </Link>
        </div>

        <div
            v-if="
                selectedSalesOrder
                    === null
            "
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
        >
            <h2
                class="text-lg font-semibold text-gray-900 dark:text-white"
            >
                Select a Sales Order
            </h2>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Only orders with posted dispatch
                quantity that has not been fully
                invoiced are shown.
            </p>

            <div
                class="mt-5 flex flex-col gap-3 sm:flex-row"
            >
                <select
                    v-model="selectedOrderId"
                    class="h-11 min-w-0 flex-1 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                >
                    <option :value="null">
                        Select a Sales Order
                    </option>

                    <option
                        v-for="order in salesOrders"
                        :key="order.id"
                        :value="order.id"
                    >
                        {{
                            order.document_number
                            ?? `Sales Order #${order.id}`
                        }}
                        —
                        {{ order.customer_name }}
                        ({{ order.customer_code }})
                    </option>
                </select>

                <button
                    :disabled="
                        selectedOrderId === null
                    "
                    type="button"
                    class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="loadOrder"
                >
                    Continue
                </button>
            </div>

            <p
                v-if="
                    salesOrders.length === 0
                "
                class="mt-5 text-sm text-warning-600 dark:text-warning-400"
            >
                No Sales Order is currently
                ready for invoicing.
            </p>
        </div>

        <SalesInvoiceForm
            v-else
            :sales-order="
                selectedSalesOrder
            "
            :defaults="defaults"
        />
    </div>
</template>