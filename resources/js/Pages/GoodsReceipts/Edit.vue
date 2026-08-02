<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    GoodsReceiptEditProps,
} from '@/Types/goods-receipt';

import GoodsReceiptForm from './Partials/GoodsReceiptForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<GoodsReceiptEditProps>();
</script>

<template>
    <Head
        :title="
            goodsReceipt.receipt_number
                ? `Edit ${goodsReceipt.receipt_number}`
                : `Edit Goods Receipt #${goodsReceipt.id}`
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

                    <Link
                        :href="
                            route(
                                'goods-receipts.show',
                                goodsReceipt.id,
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        {{
                            goodsReceipt.receipt_number
                            ?? `Draft #${goodsReceipt.id}`
                        }}
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
                    Edit Goods Receipt
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Update this draft before posting it to
                    inventory.
                </p>
            </div>

            <Link
                :href="
                    route(
                        'goods-receipts.show',
                        goodsReceipt.id,
                    )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                View Goods Receipt
            </Link>
        </div>

        <GoodsReceiptForm
            :goods-receipt="props.goodsReceipt"
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