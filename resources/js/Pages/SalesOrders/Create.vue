<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    SalesOrderCreateProps,
} from '@/Types/sales-order';

import SalesOrderForm from './Partials/SalesOrderForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<SalesOrderCreateProps>();
</script>

<template>
    <Head title="Create Sales Order" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div
                    class="mb-2 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    <Link
                        :href="route('sales-orders.index')"
                        class="transition hover:text-brand-500"
                    >
                        Sales Orders
                    </Link>

                    <span>/</span>

                    <span class="text-gray-700 dark:text-gray-300">
                        Create
                    </span>
                </div>

                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Create Sales Order
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Prepare a branch-controlled customer order
                    and save it as a draft.
                </p>
            </div>

            <Link
                :href="route('sales-orders.index')"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Back to Sales Orders
            </Link>
        </div>

        <SalesOrderForm
            :branches="props.branches"
            :warehouses="props.warehouses"
            :customers="props.customers"
            :products="props.products"
            :defaults="props.defaults"
            :can="props.can"
        />
    </div>
</template>