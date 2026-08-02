<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    PurchaseOrderEditProps,
} from '@/Types/purchase-order';

import PurchaseOrderForm from './Partials/PurchaseOrderForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<PurchaseOrderEditProps>();
</script>

<template>
    <Head
        :title="
            purchaseOrder.document_number
                ? `Edit ${purchaseOrder.document_number}`
                : 'Edit Purchase Order'
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
                        :href="route('purchase-orders.index')"
                        class="transition hover:text-brand-500"
                    >
                        Purchase Orders
                    </Link>

                    <span>/</span>

                    <Link
                        :href="
                            route(
                                'purchase-orders.show',
                                purchaseOrder.id,
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        {{
                            purchaseOrder.document_number
                                ?? `Draft #${purchaseOrder.id}`
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
                        Edit Purchase Order
                    </h1>

                    <span
                        class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium capitalize text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        {{
                            purchaseOrder.status.replace(
                                /_/g,
                                ' ',
                            )
                        }}
                    </span>
                </div>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Update this draft Purchase Order before
                    submitting it for approval.
                </p>

                <p
                    class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                >
                    Revision {{ purchaseOrder.revision }}
                </p>
            </div>

            <Link
                :href="
                    route(
                        'purchase-orders.show',
                        purchaseOrder.id,
                    )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                View Purchase Order
            </Link>
        </div>

        <PurchaseOrderForm
            :purchase-order="props.purchaseOrder"
            :branch-options="props.branchOptions"
            :warehouse-options="props.warehouseOptions"
            :supplier-options="props.supplierOptions"
            :product-options="props.productOptions"
            :defaults="props.defaults"
        />
    </div>
</template>