<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    GoodsReceiptCreateProps,
} from '@/Types/goods-receipt';

import GoodsReceiptForm from './Partials/GoodsReceiptForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<GoodsReceiptCreateProps>();
</script>

<template>
    <Head title="Create Goods Receipt" />

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
                                'goods-receipts.index',
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        Goods Receipts
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
                    Create Goods Receipt
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Record a delivery against an approved
                    Purchase Order.
                </p>
            </div>

            <Link
                :href="
                    route(
                        'goods-receipts.index',
                    )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Back to Goods Receipts
            </Link>
        </div>

        <GoodsReceiptForm
            :purchase-order-options="
                props.purchaseOrderOptions
            "
            :inspection-status-options="
                props.inspectionStatusOptions
            "
            :defaults="props.defaults"
        />
    </div>
</template>