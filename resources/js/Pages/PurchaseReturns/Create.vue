<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    PurchaseReturnCreateProps,
} from '@/Types/purchase-return';

import PurchaseReturnForm from './Partials/PurchaseReturnForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<PurchaseReturnCreateProps>();
</script>

<template>
    <Head title="Create Purchase Return" />

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

                    <span
                        class="text-gray-700 dark:text-gray-300"
                    >
                        Create
                    </span>
                </div>

                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Create Purchase Return
                </h1>

                <p
                    class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400"
                >
                    Create a supplier return against a
                    posted Goods Receipt and select the
                    accepted quantities that are being sent
                    back.
                </p>
            </div>

            <Link
                :href="
                    route(
                        'purchase-returns.index',
                    )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Back to Purchase Returns
            </Link>
        </div>

        <div
            class="grid grid-cols-1 gap-4 lg:grid-cols-3"
        >
            <section
                class="rounded-2xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-500/30 dark:bg-blue-500/10"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700 dark:bg-blue-500/20 dark:text-blue-300"
                    >
                        1
                    </div>

                    <div>
                        <h2
                            class="text-sm font-semibold text-blue-900 dark:text-blue-200"
                        >
                            Select Goods Receipt
                        </h2>

                        <p
                            class="mt-1 text-sm text-blue-700 dark:text-blue-300"
                        >
                            Only posted receipt lines with
                            remaining returnable quantity
                            are available.
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-500/30 dark:bg-indigo-500/10"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300"
                    >
                        2
                    </div>

                    <div>
                        <h2
                            class="text-sm font-semibold text-indigo-900 dark:text-indigo-200"
                        >
                            Enter Return Quantities
                        </h2>

                        <p
                            class="mt-1 text-sm text-indigo-700 dark:text-indigo-300"
                        >
                            Quantities cannot exceed the
                            accepted amount remaining after
                            earlier reservations and returns.
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-500/30 dark:bg-emerald-500/10"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-semibold text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300"
                    >
                        3
                    </div>

                    <div>
                        <h2
                            class="text-sm font-semibold text-emerald-900 dark:text-emerald-200"
                        >
                            Submit for Approval
                        </h2>

                        <p
                            class="mt-1 text-sm text-emerald-700 dark:text-emerald-300"
                        >
                            Saving creates a draft. Inventory
                            is not reduced until approval and
                            posting are completed.
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <PurchaseReturnForm
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