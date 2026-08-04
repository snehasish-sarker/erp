<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    SupplierPaymentCreateProps,
} from '@/Types/supplier-payment';

import SupplierPaymentForm from './Partials/SupplierPaymentForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props =
    defineProps<SupplierPaymentCreateProps>();
</script>

<template>
    <Head title="Create Supplier Payment" />

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

                    <span
                        class="text-gray-700 dark:text-gray-300"
                    >
                        Create
                    </span>
                </div>

                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Create Supplier Payment
                </h1>

                <p
                    class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400"
                >
                    Record a cash, bank, cheque, mobile
                    financial service, or other supplier
                    payment and allocate it across one or more
                    posted Supplier Invoices.
                </p>
            </div>

            <Link
                :href="route('supplier-payments.index')"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Back to Supplier Payments
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
                            Select Payment Context
                        </h2>

                        <p
                            class="mt-1 text-sm text-blue-700 dark:text-blue-300"
                        >
                            Choose the branch, supplier,
                            payment method, settlement account,
                            currency, and business dates.
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
                            Allocate Supplier Invoices
                        </h2>

                        <p
                            class="mt-1 text-sm text-indigo-700 dark:text-indigo-300"
                        >
                            Apply partial or full amounts to
                            multiple posted Supplier Invoice
                            open items without overallocating.
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
                            Preserve Unallocated Advance
                        </h2>

                        <p
                            class="mt-1 text-sm text-emerald-700 dark:text-emerald-300"
                        >
                            Any amount not assigned to invoices
                            remains available as a supplier
                            advance after financial posting.
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <section
            class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-500/30 dark:bg-amber-500/10"
        >
            <h2
                class="text-sm font-semibold text-amber-900 dark:text-amber-200"
            >
                Draft Creation
            </h2>

            <p
                class="mt-2 text-sm text-amber-700 dark:text-amber-300"
            >
                Saving creates a draft only. Supplier balances,
                invoice outstanding amounts, cash or bank
                balances, and the General Ledger remain
                unchanged until the approved payment is posted.
            </p>
        </section>

        <SupplierPaymentForm
            :branches="props.branches"
            :suppliers="props.suppliers"
            :payment-accounts="props.paymentAccounts"
            :open-items="props.openItems"
            :payment-methods="props.paymentMethods"
            :defaults="props.defaults"
        />
    </div>
</template>