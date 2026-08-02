<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    SupplierInvoiceCreateProps,
} from '@/Types/supplier-invoice';

import SupplierInvoiceForm from './Partials/SupplierInvoiceForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<SupplierInvoiceCreateProps>();
</script>

<template>
    <Head title="Create Supplier Invoice" />

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
                                'supplier-invoices.index',
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        Supplier Invoices
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
                    Create Supplier Invoice
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Record a supplier invoice and match it
                    against its Purchase Order and posted
                    Goods Receipts.
                </p>
            </div>

            <Link
                :href="
                    route(
                        'supplier-invoices.index',
                    )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Back to Supplier Invoices
            </Link>
        </div>

        <div
            class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-500/30 dark:bg-blue-500/10"
        >
            <div class="flex items-start gap-3">
                <div
                    class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700 dark:bg-blue-500/20 dark:text-blue-300"
                >
                    3
                </div>

                <div>
                    <h2
                        class="text-sm font-semibold text-blue-900 dark:text-blue-200"
                    >
                        Three-Way Matching
                    </h2>

                    <p
                        class="mt-1 text-sm text-blue-700 dark:text-blue-300"
                    >
                        The invoice will be checked against
                        Purchase Order quantities and prices,
                        along with accepted quantities from
                        posted Goods Receipts.
                    </p>
                </div>
            </div>
        </div>

        <SupplierInvoiceForm
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