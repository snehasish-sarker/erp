<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';
import { computed } from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    SupplierPaymentEditProps,
} from '@/Types/supplier-payment';

import SupplierPaymentForm from './Partials/SupplierPaymentForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props =
    defineProps<SupplierPaymentEditProps>();

const documentTitle = computed(
    (): string => {
        return props.supplierPayment
            .payment_number
            ?? `Draft #${props.supplierPayment.id}`;
    },
);

const statusLabel = computed(
    (): string => {
        return props.supplierPayment.status
            .replaceAll('_', ' ')
            .replace(
                /\b\w/g,
                (
                    character,
                ): string =>
                    character.toUpperCase(),
            );
    },
);

const hasAllocatedNumber = computed(
    (): boolean => {
        const paymentNumber =
            props.supplierPayment
                .payment_number;

        return typeof paymentNumber === 'string'
            && paymentNumber.trim() !== '';
    },
);
</script>

<template>
    <Head :title="`Edit ${documentTitle}`" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <div
                    class="mb-2 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    <Link
                        :href="route('supplier-payments.index')"
                        class="transition hover:text-brand-500"
                    >
                        Supplier Payments
                    </Link>

                    <span>/</span>

                    <Link
                        :href="
                            route(
                                'supplier-payments.show',
                                props.supplierPayment.id,
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
                        Edit Supplier Payment
                    </h1>

                    <span
                        class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                        {{ statusLabel }}
                    </span>
                </div>

                <p
                    class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400"
                >
                    Update the draft payment context, invoice
                    allocations, settlement reference, and
                    internal notes before submission.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('supplier-payments.index')"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back to List
                </Link>

                <Link
                    :href="
                        route(
                            'supplier-payments.show',
                            props.supplierPayment.id,
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    View Payment
                </Link>
            </div>
        </div>

        <section
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
                        Draft Supplier Payment
                    </h2>

                    <p
                        class="mt-1 text-sm text-amber-700 dark:text-amber-300"
                    >
                        Draft allocation rows are planning data
                        only. Editing this document does not
                        settle Supplier Invoices or create any
                        General Ledger entry.
                    </p>
                </div>
            </div>
        </section>

        <section
            v-if="hasAllocatedNumber"
            class="rounded-2xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-500/30 dark:bg-blue-500/10"
        >
            <h2
                class="text-sm font-semibold text-blue-900 dark:text-blue-200"
            >
                Document Number Already Allocated
            </h2>

            <p
                class="mt-2 text-sm text-blue-700 dark:text-blue-300"
            >
                This payment has already received a permanent
                document number. Its branch and payment date
                are locked to preserve the numbering sequence.
            </p>
        </section>

        <section
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Payment Number
                    </p>

                    <p
                        class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{ documentTitle }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Current Status
                    </p>

                    <p
                        class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{ statusLabel }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Revision
                    </p>

                    <p
                        class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{ props.supplierPayment.revision }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Invoice Allocations
                    </p>

                    <p
                        class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{
                            props.supplierPayment
                                .allocations.length
                        }}
                    </p>
                </div>
            </div>
        </section>

        <SupplierPaymentForm
            :supplier-payment="props.supplierPayment"
            :branches="props.branches"
            :suppliers="props.suppliers"
            :payment-accounts="props.paymentAccounts"
            :open-items="props.openItems"
            :payment-methods="props.paymentMethods"
            :defaults="props.defaults"
        />
    </div>
</template>