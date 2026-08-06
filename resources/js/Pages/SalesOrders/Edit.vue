<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    SalesOrderEditProps,
} from '@/Types/sales-order';

import SalesOrderForm from './Partials/SalesOrderForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<SalesOrderEditProps>();
</script>

<template>
    <Head
        :title="
            salesOrder.document_number
                ? `Edit ${salesOrder.document_number}`
                : 'Edit Sales Order'
        "
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
                        :href="route('sales-orders.index')"
                        class="transition hover:text-brand-500"
                    >
                        Sales Orders
                    </Link>

                    <span>/</span>

                    <Link
                        :href="
                            route(
                                'sales-orders.show',
                                salesOrder.id,
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        {{
                            salesOrder.document_number
                                ?? `Draft #${salesOrder.id}`
                        }}
                    </Link>

                    <span>/</span>

                    <span
                        class="text-gray-700 dark:text-gray-300"
                    >
                        Edit
                    </span>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3"
                >
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        Edit Sales Order
                    </h1>

                    <span
                        class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium capitalize text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        {{
                            salesOrder.status.replace(
                                /_/g,
                                ' ',
                            )
                        }}
                    </span>
                </div>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Update this draft before submitting it for
                    approval.
                </p>

                <p
                    class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                >
                    Revision {{ salesOrder.revision }}
                </p>
            </div>

            <Link
                :href="
                    route(
                        'sales-orders.show',
                        salesOrder.id,
                    )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                View Sales Order
            </Link>
        </div>

        <SalesOrderForm
            :sales-order="props.salesOrder"
            :branches="props.branches"
            :warehouses="props.warehouses"
            :customers="props.customers"
            :products="props.products"
            :defaults="props.defaults"
            :can="props.can"
        />
    </div>
</template>