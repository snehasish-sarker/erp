<script setup lang="ts">
import {
    Head,
    Link,
} from '@inertiajs/vue3';
import { computed } from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    SupplierDebitNoteEditProps,
} from '@/Types/supplier-debit-note';

import SupplierDebitNoteForm from './Partials/SupplierDebitNoteForm.vue';

defineOptions({
    layout: ErpLayout,
});

const props =
    defineProps<SupplierDebitNoteEditProps>();

const documentTitle = computed(
    (): string => {
        return props.supplierDebitNote
            .debit_note_number
            ?? `Draft #${props.supplierDebitNote.id}`;
    },
);

const statusLabel = computed(
    (): string => {
        return props.supplierDebitNote.status
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
        const number =
            props.supplierDebitNote
                .debit_note_number;

        return typeof number === 'string'
            && number.trim() !== '';
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
                                'supplier-debit-notes.index',
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        Supplier Debit Notes
                    </Link>

                    <span>/</span>

                    <Link
                        :href="
                            route(
                                'supplier-debit-notes.show',
                                props.supplierDebitNote.id,
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
                        Edit Supplier Debit Note
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
                    Update the commercial values, Supplier
                    Invoice allocation, reference, reason,
                    and line details before submission.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="
                        route(
                            'supplier-debit-notes.index',
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back to List
                </Link>

                <Link
                    :href="
                        route(
                            'supplier-debit-notes.show',
                            props.supplierDebitNote.id,
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    View Debit Note
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
                        Draft Supplier Debit Note
                    </h2>

                    <p
                        class="mt-1 text-sm text-amber-700 dark:text-amber-300"
                    >
                        The Purchase Return quantities remain
                        fixed because they must exactly match
                        the posted inventory return. You can
                        update the commercial price,
                        discount, tax, and invoice allocation.
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
                This Debit Note has already received a
                permanent document number. Its source
                Purchase Return and Debit Note date cannot be
                changed.
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
                        Debit Note Number
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
                        Purchase Return
                    </p>

                    <p
                        class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        #{{
                            props.supplierDebitNote
                                .purchase_return_id
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
                        class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{ statusLabel }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Supplier Invoice Allocation
                    </p>

                    <p
                        class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        <template
                            v-if="
                                props.supplierDebitNote
                                    .supplier_invoice_id
                                !== null
                            "
                        >
                            Invoice #{{
                                props.supplierDebitNote
                                    .supplier_invoice_id
                            }}
                        </template>

                        <template v-else>
                            Unallocated
                        </template>
                    </p>
                </div>
            </div>
        </section>

        <SupplierDebitNoteForm
            :supplier-debit-note="
                props.supplierDebitNote
            "
            :purchase-returns="
                props.purchaseReturns
            "
            :selected-purchase-return-id="
                props.selectedPurchaseReturnId
            "
            :defaults="props.defaults"
        />
    </div>
</template>