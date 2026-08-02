<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';
import { computed } from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    PurchaseReturnEditProps,
} from '@/Types/purchase-return';

import PurchaseReturnForm from './Partials/PurchaseReturnForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<PurchaseReturnEditProps>();

const documentTitle = computed(
    (): string => {
        return props.purchaseReturn
            .return_number
            ?? `Draft #${props.purchaseReturn.id}`;
    },
);
</script>

<template>
    <Head
        :title="`Edit ${documentTitle}`"
    />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <div
                    class="mb-2 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    <Link
                        :href="
                            route(
                                'purchase-returns.index',
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        Purchase Returns
                    </Link>

                    <span>/</span>

                    <Link
                        :href="
                            route(
                                'purchase-returns.show',
                                props.purchaseReturn.id,
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

                <div
                    class="flex flex-wrap items-center gap-3"
                >
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        Edit Purchase Return
                    </h1>

                    <span
                        class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium capitalize text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        {{
                            props.purchaseReturn.status
                        }}
                    </span>
                </div>

                <p
                    class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400"
                >
                    Update the draft return quantities,
                    supplier reference, and return reasons
                    before submitting it for approval.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="
                        route(
                            'purchase-returns.index',
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back to List
                </Link>

                <Link
                    :href="
                        route(
                            'purchase-returns.show',
                            props.purchaseReturn.id,
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    View Purchase Return
                </Link>
            </div>
        </div>

        <div
            class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-500/30 dark:bg-amber-500/10"
        >
            <div class="flex items-start gap-3">
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-sm font-semibold text-amber-700 dark:bg-amber-500/20 dark:text-amber-300"
                >
                    !
                </div>

                <div>
                    <h2
                        class="text-sm font-semibold text-amber-900 dark:text-amber-200"
                    >
                        Draft Purchase Return
                    </h2>

                    <p
                        class="mt-1 text-sm text-amber-700 dark:text-amber-300"
                    >
                        Editing recalculates supplier values
                        using the original Goods Receipt
                        commercial costs. No stock or return
                        quantity is reserved until approval.
                    </p>
                </div>
            </div>
        </div>

        <div
            v-if="
                props.purchaseReturn
                    .return_number
            "
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-3"
            >
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Purchase Return Number
                    </p>

                    <p
                        class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{
                            props.purchaseReturn
                                .return_number
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Source Goods Receipt
                    </p>

                    <p
                        class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        #{{
                            props.purchaseReturn
                                .goods_receipt_id
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Current Status
                    </p>

                    <p
                        class="mt-1 text-sm font-semibold capitalize text-gray-900 dark:text-white"
                    >
                        {{
                            props.purchaseReturn
                                .status
                        }}
                    </p>
                </div>
            </div>
        </div>

        <PurchaseReturnForm
            :purchase-return="
                props.purchaseReturn
            "
            :goods-receipts="
                props.goodsReceipts
            "
            :selected-goods-receipt-id="
                props.selectedGoodsReceiptId
            "
            :defaults="props.defaults"
        />
    </div>
</template>