<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    CustomerDispatchEditProps,
} from '@/Types/dispatch';

import CustomerDispatchForm from './Partials/CustomerDispatchForm.vue';

defineOptions({
    layout: ErpLayout,
});

defineProps<CustomerDispatchEditProps>();
</script>

<template>
    <Head title="Edit Dispatch Draft" />

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
                                'dispatches.index',
                            )
                        "
                        class="hover:text-brand-500"
                    >
                        Dispatches
                    </Link>

                    <span>/</span>

                    <Link
                        :href="
                            route(
                                'dispatches.show',
                                dispatch.id,
                            )
                        "
                        class="hover:text-brand-500"
                    >
                        Draft #{{ dispatch.id }}
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
                    Edit Dispatch Draft
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Update quantities and delivery details
                    before posting.
                </p>
            </div>

            <Link
                :href="
                    route(
                        'dispatches.show',
                        dispatch.id,
                    )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                View Dispatch
            </Link>
        </div>

        <CustomerDispatchForm
            :sales-order="
                selectedSalesOrder
            "
            :dispatch="dispatch"
        />
    </div>
</template>