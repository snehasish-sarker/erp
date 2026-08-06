<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    SalesInvoiceEditProps,
} from '@/Types/sales-invoice';

import SalesInvoiceForm from './Partials/SalesInvoiceForm.vue';

defineOptions({
    layout: ErpLayout,
});

defineProps<SalesInvoiceEditProps>();
</script>

<template>
    <Head title="Edit Sales Invoice Draft" />

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

                    <Link
                        :href="
                            route(
                                'sales-invoices.show',
                                salesInvoice.id,
                            )
                        "
                        class="hover:text-brand-500"
                    >
                        Draft #{{ salesInvoice.id }}
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
                    Edit Sales Invoice Draft
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Revision
                    {{ salesInvoice.revision }}.
                    Update quantities before posting.
                </p>
            </div>

            <Link
                :href="
                    route(
                        'sales-invoices.show',
                        salesInvoice.id,
                    )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                View Invoice
            </Link>
        </div>

        <SalesInvoiceForm
            :sales-order="
                selectedSalesOrder
            "
            :sales-invoice="
                salesInvoice
            "
        />
    </div>
</template>