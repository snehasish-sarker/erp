<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';
import { computed } from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    SupplierInvoiceEditProps,
} from '@/Types/supplier-invoice';

import SupplierInvoiceForm from './Partials/SupplierInvoiceForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<SupplierInvoiceEditProps>();

const documentTitle = computed(
    (): string => {
        return props.supplierInvoice
            .supplier_invoice_number
            || `Supplier Invoice #${props.supplierInvoice.id}`;
    },
);
</script>

<template>
    <Head
        :title="`Edit ${documentTitle}`"
    />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div
                    class="mb-2 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    <Link
                        :href="
                            route(
                                'supplier-invoices.index',
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        Supplier Invoices
                    </Link>

                    <span>/</span>

                    <Link
                        :href="
                            route(
                                'supplier-invoices.show',
                                props.supplierInvoice.id,
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        {{ documentTitle }}
                    </Link>

                    <span>/</span>

                    <span
                        class="text-gray-700 dark:text-gray-300"
                    >
                        Edit
                    </span>
                </div>

                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Edit Supplier Invoice
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Update the draft invoice and its
                    three-way matching allocations before
                    validation.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="
                        route(
                            'supplier-invoices.index',
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back to List
                </Link>

                <Link
                    :href="
                        route(
                            'supplier-invoices.show',
                            props.supplierInvoice.id,
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    View Supplier Invoice
                </Link>
            </div>
        </div>

        <div
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10"
        >
            <h2
                class="text-sm font-semibold text-amber-900 dark:text-amber-200"
            >
                Draft invoice
            </h2>

            <p
                class="mt-1 text-sm text-amber-700 dark:text-amber-300"
            >
                Editing recalculates invoice totals and
                three-way matching results. Quantities are
                not reserved until the invoice is validated.
            </p>
        </div>

        <SupplierInvoiceForm
            :supplier-invoice="
                props.supplierInvoice
            "
            :branches="props.branches"
            :purchase-orders="
                props.purchaseOrders
            "
            :selected-purchase-order-id="
                props.selectedPurchaseOrderId
            "
            :match-statuses="
                props.matchStatuses
            "
            :defaults="props.defaults"
        />
    </div>
</template>