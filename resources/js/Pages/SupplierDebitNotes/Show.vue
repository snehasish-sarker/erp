<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    SupplierDebitNoteAllocationStatus,
    SupplierDebitNoteLine,
    SupplierDebitNoteShowProps,
    SupplierDebitNoteStatus,
} from '@/Types/supplier-debit-note';

defineOptions({ layout: ErpLayout });

const props = defineProps<SupplierDebitNoteShowProps>();

type DirectWorkflowAction =
    | 'submit'
    | 'return-to-draft'
    | 'approve'
    | 'post'
    | 'delete';

const actionInProgress =
    ref<DirectWorkflowAction | null>(null);

const showCancellationModal = ref(false);
const showReversalModal = ref(false);

const cancellationForm = useForm({
    cancellation_reason: '',
});

const localDate = (): string => {
    const currentDate = new Date();

    const localValue = new Date(
        currentDate.getTime()
        - currentDate.getTimezoneOffset()
            * 60_000,
    );

    return localValue
        .toISOString()
        .slice(0, 10);
};

const defaultReversalPostingDate =
    (): string => {
        const today = localDate();

        const originalPostingDate =
            props.supplierDebitNote
                .posting_date
            ?? '';

        return originalPostingDate !== ''
            && originalPostingDate > today
            ? originalPostingDate
            : today;
    };

const reversalForm = useForm({
    reversal_posting_date:
        defaultReversalPostingDate(),

    reversal_reason: '',
});

const documentTitle = computed(
    (): string => {
        return props.supplierDebitNote
            .debit_note_number
            ?? `Draft #${props.supplierDebitNote.id}`;
    },
);

const statusClasses: Record<
    SupplierDebitNoteStatus,
    string
> = {
    draft:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    submitted:
        'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',

    approved:
        'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',

    posted:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    reversed:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',

    cancelled:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const allocationStatusClasses: Record<
    SupplierDebitNoteAllocationStatus,
    string
> = {
    draft:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    reserved:
        'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',

    applied:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    reversed:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',

    cancelled:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const decimalValue = (
    value: string | number | null,
): number => {
    const parsed = Number.parseFloat(
        String(value ?? '0'),
    );

    return Number.isFinite(parsed)
        ? parsed
        : 0;
};

const formatQuantity = (
    value: string | number,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        },
    ).format(
        decimalValue(value),
    );
};

const formatAmount = (
    value: string | number,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(
        decimalValue(value),
    );
};

const formatRate = (
    value: string | number,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 8,
        },
    ).format(
        decimalValue(value),
    );
};

const formatDate = (
    value: string | null,
): string => {
    if (
        value === null
        || value === ''
    ) {
        return '—';
    }

    const parts = value.split('-');

    if (parts.length !== 3) {
        return value;
    }

    const year = Number(parts[0]);
    const month = Number(parts[1]);
    const day = Number(parts[2]);

    if (
        !Number.isInteger(year)
        || !Number.isInteger(month)
        || !Number.isInteger(day)
    ) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        },
    ).format(
        new Date(
            Date.UTC(
                year,
                month - 1,
                day,
            ),
        ),
    );
};

const formatDateTime = (
    value: string | null,
): string => {
    if (
        value === null
        || value === ''
    ) {
        return '—';
    }

    const date = new Date(value);

    if (
        Number.isNaN(
            date.getTime(),
        )
    ) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-GB',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        },
    ).format(date);
};

const titleCase = (
    value: string,
): string => {
    return value
        .replaceAll('_', ' ')
        .replace(
            /\b\w/g,
            (
                character,
            ): string =>
                character.toUpperCase(),
        );
};

const varianceClasses = (
    value: string,
): string => {
    const parsed =
        decimalValue(value);

    if (
        Math.abs(parsed)
        <= 0.000001
    ) {
        return 'text-gray-600 dark:text-gray-300';
    }

    return parsed > 0
        ? 'text-amber-600 dark:text-amber-400'
        : 'text-blue-600 dark:text-blue-400';
};

const hasPurchaseReturnVariance =
    computed(
        (): boolean => {
            return Math.abs(
                decimalValue(
                    props.supplierDebitNote
                        .purchase_return_cost_variance,
                ),
            ) > 0.000001;
        },
    );

const hasUnallocatedAmount =
    computed(
        (): boolean => {
            return Math.abs(
                decimalValue(
                    props.supplierDebitNote
                        .unallocated_amount,
                ),
            ) > 0.000001;
        },
    );

const lineHasSourceVariance = (
    line: SupplierDebitNoteLine,
): boolean => {
    return Math.abs(
        decimalValue(
            line.purchase_return_cost_variance,
        ),
    ) > 0.000001;
};

const runWorkflowAction = (
    action: Exclude<
        DirectWorkflowAction,
        'delete'
    >,
    confirmationMessage: string,
): void => {
    if (
        !window.confirm(
            confirmationMessage,
        )
    ) {
        return;
    }

    actionInProgress.value =
        action;

    router.post(
        route(
            `supplier-debit-notes.${action}`,
            props.supplierDebitNote.id,
        ),
        {},
        {
            preserveScroll: true,

            onFinish: () => {
                actionInProgress.value =
                    null;
            },
        },
    );
};

const submitSupplierDebitNote =
    (): void => {
        runWorkflowAction(
            'submit',
            'Submit this Supplier Debit Note? A permanent document number will be allocated.',
        );
    };

const returnSupplierDebitNoteToDraft =
    (): void => {
        runWorkflowAction(
            'return-to-draft',
            'Return this Supplier Debit Note to draft?',
        );
    };

const approveSupplierDebitNote =
    (): void => {
        runWorkflowAction(
            'approve',
            'Approve this Supplier Debit Note? Any linked Supplier Invoice amount will be reserved.',
        );
    };

const postSupplierDebitNote =
    (): void => {
        runWorkflowAction(
            'post',
            'Post this Supplier Debit Note to Accounts Payable?',
        );
    };

const deleteSupplierDebitNote =
    (): void => {
        const confirmed =
            window.confirm(
                'Delete this unnumbered Supplier Debit Note draft? This action cannot be undone.',
            );

        if (!confirmed) {
            return;
        }

        actionInProgress.value =
            'delete';

        router.delete(
            route(
                'supplier-debit-notes.destroy',
                props.supplierDebitNote.id,
            ),
            {
                preserveScroll: true,

                onFinish: () => {
                    actionInProgress.value =
                        null;
                },
            },
        );
    };

const openCancellationModal =
    (): void => {
        cancellationForm.reset();
        cancellationForm.clearErrors();

        showCancellationModal.value =
            true;
    };

const closeCancellationModal =
    (): void => {
        if (
            cancellationForm.processing
        ) {
            return;
        }

        cancellationForm.reset();
        cancellationForm.clearErrors();

        showCancellationModal.value =
            false;
    };

const cancelSupplierDebitNote =
    (): void => {
        cancellationForm.post(
            route(
                'supplier-debit-notes.cancel',
                props.supplierDebitNote.id,
            ),
            {
                preserveScroll: true,

                onSuccess: () => {
                    cancellationForm.reset();

                    showCancellationModal.value =
                        false;
                },
            },
        );
    };

const openReversalModal =
    (): void => {
        reversalForm.reset();
        reversalForm.clearErrors();

        reversalForm
            .reversal_posting_date =
                defaultReversalPostingDate();

        showReversalModal.value =
            true;
    };

const closeReversalModal =
    (): void => {
        if (
            reversalForm.processing
        ) {
            return;
        }

        reversalForm.reset();
        reversalForm.clearErrors();

        showReversalModal.value =
            false;
    };

const reverseSupplierDebitNote =
    (): void => {
        reversalForm.post(
            route(
                'supplier-debit-notes.reverse',
                props.supplierDebitNote.id,
            ),
            {
                preserveScroll: true,

                onSuccess: () => {
                    reversalForm.reset();

                    showReversalModal.value =
                        false;
                },
            },
        );
    };
</script>

<template>
    <Head :title="documentTitle" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
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

                    <span
                        class="text-gray-700 dark:text-gray-300"
                    >
                        {{ documentTitle }}
                    </span>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3"
                >
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        {{ documentTitle }}
                    </h1>

                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            statusClasses[
                                props.supplierDebitNote
                                    .status
                            ]
                        "
                    >
                        {{
                            props.supplierDebitNote
                                .status_label
                        }}
                    </span>
                </div>

                <p
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    Purchase Return:

                    <Link
                        :href="
                            route(
                                'purchase-returns.show',
                                props.supplierDebitNote
                                    .purchase_return_id,
                            )
                        "
                        class="font-medium text-amber-600 transition hover:text-amber-700 dark:text-amber-400"
                    >
                        {{
                            props.supplierDebitNote
                                .purchase_return_number
                            ?? `Return #${props.supplierDebitNote.purchase_return_id}`
                        }}
                    </Link>
                </p>

                <p
                    class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                >
                    Document revision
                    {{
                        props.supplierDebitNote
                            .revision
                    }}
                    · Source return revision
                    {{
                        props.supplierDebitNote
                            .source_purchase_return_revision
                    }}
                </p>
            </div>

            <div
                class="flex flex-wrap items-center gap-2"
            >
                <Link
                    :href="
                        route(
                            'supplier-debit-notes.index',
                        )
                    "
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back
                </Link>

                <Link
                    v-if="
                        props.supplierDebitNote
                            .can.update
                    "
                    :href="
                        route(
                            'supplier-debit-notes.edit',
                            props.supplierDebitNote.id,
                        )
                    "
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Edit
                </Link>

                <button
                    v-if="
                        props.supplierDebitNote
                            .can.delete
                    "
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/40 dark:bg-gray-900 dark:text-red-400 dark:hover:bg-red-500/10"
                    @click="
                        deleteSupplierDebitNote
                    "
                >
                    {{
                        actionInProgress
                        === 'delete'
                            ? 'Deleting...'
                            : 'Delete Draft'
                    }}
                </button>

                <button
                    v-if="
                        props.supplierDebitNote
                            .can.submit
                    "
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="
                        submitSupplierDebitNote
                    "
                >
                    {{
                        actionInProgress
                        === 'submit'
                            ? 'Submitting...'
                            : 'Submit'
                    }}
                </button>

                <button
                    v-if="
                        props.supplierDebitNote
                            .can.return_to_draft
                    "
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    @click="
                        returnSupplierDebitNoteToDraft
                    "
                >
                    {{
                        actionInProgress
                        === 'return-to-draft'
                            ? 'Returning...'
                            : 'Return to Draft'
                    }}
                </button>

                <button
                    v-if="
                        props.supplierDebitNote
                            .can.approve
                    "
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="
                        approveSupplierDebitNote
                    "
                >
                    {{
                        actionInProgress
                        === 'approve'
                            ? 'Approving...'
                            : 'Approve'
                    }}
                </button>

                <button
                    v-if="
                        props.supplierDebitNote
                            .can.cancel
                    "
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/40 dark:bg-gray-900 dark:text-red-400 dark:hover:bg-red-500/10"
                    @click="
                        openCancellationModal
                    "
                >
                    Cancel
                </button>

                <button
                    v-if="
                        props.supplierDebitNote
                            .can.post
                    "
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="
                        postSupplierDebitNote
                    "
                >
                    {{
                        actionInProgress
                        === 'post'
                            ? 'Posting...'
                            : 'Post Debit Note'
                    }}
                </button>

                <button
                    v-if="
                        props.supplierDebitNote
                            .can.reverse
                    "
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="
                        openReversalModal
                    "
                >
                    Reverse Debit Note
                </button>
            </div>
        </div>

        <section
            v-if="
                props.supplierDebitNote.status
                === 'approved'
            "
            class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-500/30 dark:bg-indigo-500/10"
        >
            <h2
                class="font-semibold text-indigo-900 dark:text-indigo-200"
            >
                Supplier Invoice Amount Reserved
            </h2>

            <p
                class="mt-2 text-sm text-indigo-700 dark:text-indigo-300"
            >
                The linked Supplier Invoice allocation is
                reserved. Posting requires a successful
                Accounts Payable and journal entry.
            </p>
        </section>

        <section
            v-if="
                props.supplierDebitNote.status
                === 'posted'
            "
            class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-500/30 dark:bg-emerald-500/10"
        >
            <h2
                class="font-semibold text-emerald-900 dark:text-emerald-200"
            >
                Supplier Debit Note Posted
            </h2>

            <p
                class="mt-2 text-sm text-emerald-700 dark:text-emerald-300"
            >
                The financial posting completed and the
                linked Supplier Invoice allocation was
                applied.
            </p>

            <p
                class="mt-3 break-all text-xs text-emerald-700 dark:text-emerald-400"
            >
                Accounting reference:

                <span class="font-semibold">
                    {{
                        props.supplierDebitNote
                            .accounting_posting_reference
                        ?? '—'
                    }}
                </span>
            </p>
        </section>

        <section
            v-if="
                props.supplierDebitNote.status
                === 'cancelled'
            "
            class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-500/30 dark:bg-red-500/10"
        >
            <h2
                class="font-semibold text-red-900 dark:text-red-200"
            >
                Supplier Debit Note Cancelled
            </h2>

            <p
                class="mt-2 whitespace-pre-line text-sm text-red-700 dark:text-red-300"
            >
                {{
                    props.supplierDebitNote
                        .cancellation_reason
                    ?? 'No cancellation reason was recorded.'
                }}
            </p>

            <p
                class="mt-3 text-xs text-red-600 dark:text-red-400"
            >
                Cancelled by
                {{
                    props.supplierDebitNote
                        .cancelled_by?.name
                    ?? 'Unknown user'
                }}
                on
                {{
                    formatDateTime(
                        props.supplierDebitNote
                            .cancelled_at,
                    )
                }}
            </p>
        </section>

        <section
            v-if="
                props.supplierDebitNote.status
                === 'reversed'
            "
            class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-500/30 dark:bg-red-500/10"
        >
            <h2
                class="font-semibold text-red-900 dark:text-red-200"
            >
                Supplier Debit Note Reversed
            </h2>

            <p
                class="mt-2 whitespace-pre-line text-sm text-red-700 dark:text-red-300"
            >
                {{
                    props.supplierDebitNote
                        .reversal_reason
                    ?? 'No reversal reason was recorded.'
                }}
            </p>

            <div
                class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-xs text-red-600 dark:text-red-400"
            >
                <span>
                    Reversal posting date:
                    {{
                        formatDate(
                            props.supplierDebitNote
                                .reversal_posting_date,
                        )
                    }}
                </span>

                <span>
                    Reversed by
                    {{
                        props.supplierDebitNote
                            .reversed_by?.name
                        ?? 'Unknown user'
                    }}
                    on
                    {{
                        formatDateTime(
                            props.supplierDebitNote
                                .reversed_at,
                        )
                    }}
                </span>
            </div>

            <p
                class="mt-3 break-all text-xs text-red-600 dark:text-red-400"
            >
                Accounting reversal reference:

                <span class="font-semibold">
                    {{
                        props.supplierDebitNote
                            .accounting_reversal_reference
                        ?? '—'
                    }}
                </span>
            </p>
        </section>

        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Debit Note Total
                </p>

                <p
                    class="mt-3 text-2xl font-bold text-gray-900 dark:text-white"
                >
                    {{
                        formatAmount(
                            props.supplierDebitNote
                                .total_amount,
                        )
                    }}
                </p>

                <p
                    class="mt-1 text-sm text-gray-500"
                >
                    {{
                        props.supplierDebitNote
                            .currency_code
                    }}
                </p>
            </section>

            <section
                class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-sm dark:border-emerald-500/30 dark:bg-emerald-500/10"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-400"
                >
                    Allocated Amount
                </p>

                <p
                    class="mt-3 text-2xl font-bold text-emerald-900 dark:text-emerald-200"
                >
                    {{
                        formatAmount(
                            props.supplierDebitNote
                                .allocated_amount,
                        )
                    }}
                </p>

                <p
                    class="mt-1 text-sm text-emerald-700 dark:text-emerald-400"
                >
                    Linked Supplier Invoice
                </p>
            </section>

            <section
                class="rounded-2xl border p-5 shadow-sm"
                :class="
                    hasUnallocatedAmount
                        ? 'border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10'
                        : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900'
                "
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide"
                    :class="
                        hasUnallocatedAmount
                            ? 'text-amber-700 dark:text-amber-400'
                            : 'text-gray-500'
                    "
                >
                    Unallocated Amount
                </p>

                <p
                    class="mt-3 text-2xl font-bold"
                    :class="
                        hasUnallocatedAmount
                            ? 'text-amber-900 dark:text-amber-200'
                            : 'text-gray-900 dark:text-white'
                    "
                >
                    {{
                        formatAmount(
                            props.supplierDebitNote
                                .unallocated_amount,
                        )
                    }}
                </p>

                <p
                    class="mt-1 text-sm"
                    :class="
                        hasUnallocatedAmount
                            ? 'text-amber-700 dark:text-amber-400'
                            : 'text-gray-500'
                    "
                >
                    Open supplier credit
                </p>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Return Cost Variance
                </p>

                <p
                    class="mt-3 text-2xl font-bold"
                    :class="
                        varianceClasses(
                            props.supplierDebitNote
                                .purchase_return_cost_variance,
                        )
                    "
                >
                    {{
                        formatAmount(
                            props.supplierDebitNote
                                .purchase_return_cost_variance,
                        )
                    }}
                </p>

                <p
                    class="mt-1 text-sm text-gray-500"
                >
                    Supplier value minus inventory value
                </p>
            </section>
        </div>

        <div
            class="grid grid-cols-1 gap-6 xl:grid-cols-3"
        >
            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm xl:col-span-2 dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Debit Note Information
                </h2>

                <dl
                    class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Debit Note Number
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                props.supplierDebitNote
                                    .debit_note_number
                                ?? 'Pending submission'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Debit Note Date
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    props.supplierDebitNote
                                        .debit_note_date,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Posting Date
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    props.supplierDebitNote
                                        .posting_date,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Supplier
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                props.supplierDebitNote
                                    .supplier.name
                            }}
                            ({{
                                props.supplierDebitNote
                                    .supplier.code
                            }})
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Branch
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                props.supplierDebitNote
                                    .branch.name
                            }}
                            ({{
                                props.supplierDebitNote
                                    .branch.code
                            }})
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Currency / Exchange Rate
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                props.supplierDebitNote
                                    .currency_code
                            }}
                            ·
                            {{
                                formatRate(
                                    props.supplierDebitNote
                                        .exchange_rate,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Supplier Reference
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                props.supplierDebitNote
                                    .supplier_reference
                                ?? '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Document Allocation
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                props.supplierDebitNote
                                    .document_number_allocation_id
                                ?? 'Not allocated'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Current Status
                        </dt>

                        <dd class="mt-1">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    statusClasses[
                                        props.supplierDebitNote
                                            .status
                                    ]
                                "
                            >
                                {{
                                    props.supplierDebitNote
                                        .status_label
                                }}
                            </span>
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Commercial Totals
                </h2>

                <div class="mt-5 space-y-4">
                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Gross Amount
                        </span>

                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    props.supplierDebitNote
                                        .gross_amount,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Discount
                        </span>

                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    props.supplierDebitNote
                                        .discount_amount,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Subtotal
                        </span>

                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    props.supplierDebitNote
                                        .subtotal,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Tax
                        </span>

                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    props.supplierDebitNote
                                        .tax_amount,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="border-t border-gray-200 pt-4 dark:border-gray-800"
                    >
                        <div
                            class="flex items-center justify-between gap-4"
                        >
                            <span
                                class="font-medium text-gray-900 dark:text-white"
                            >
                                Total Debit Note
                            </span>

                            <span
                                class="text-xl font-bold text-gray-900 dark:text-white"
                            >
                                {{
                                    formatAmount(
                                        props.supplierDebitNote
                                            .total_amount,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <h2
                class="text-lg font-semibold text-gray-900 dark:text-white"
            >
                Source Documents
            </h2>

            <div
                class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                <Link
                    :href="
                        route(
                            'purchase-returns.show',
                            props.supplierDebitNote
                                .purchase_return_id,
                        )
                    "
                    class="rounded-xl border border-amber-200 bg-amber-50 p-4 transition hover:border-amber-300 dark:border-amber-500/30 dark:bg-amber-500/10"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-400"
                    >
                        Purchase Return
                    </p>

                    <p
                        class="mt-2 text-sm font-semibold text-amber-900 dark:text-amber-200"
                    >
                        {{
                            props.supplierDebitNote
                                .purchase_return_number
                            ?? `Return #${props.supplierDebitNote.purchase_return_id}`
                        }}
                    </p>
                </Link>

                <Link
                    v-if="
                        props.supplierDebitNote
                            .supplier_invoice_id
                        !== null
                    "
                    :href="
                        route(
                            'supplier-invoices.show',
                            props.supplierDebitNote
                                .supplier_invoice_id,
                        )
                    "
                    class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 transition hover:border-indigo-300 dark:border-indigo-500/30 dark:bg-indigo-500/10"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-indigo-700 dark:text-indigo-400"
                    >
                        Supplier Invoice
                    </p>

                    <p
                        class="mt-2 text-sm font-semibold text-indigo-900 dark:text-indigo-200"
                    >
                        {{
                            props.supplierDebitNote
                                .supplier_invoice_number
                            ?? `Invoice #${props.supplierDebitNote.supplier_invoice_id}`
                        }}
                    </p>
                </Link>

                <div
                    v-else
                    class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950/40"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Supplier Invoice
                    </p>

                    <p
                        class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-300"
                    >
                        Unallocated supplier credit
                    </p>
                </div>

                <Link
                    :href="
                        route(
                            'purchase-orders.show',
                            props.supplierDebitNote
                                .purchase_order_id,
                        )
                    "
                    class="rounded-xl border border-blue-200 bg-blue-50 p-4 transition hover:border-blue-300 dark:border-blue-500/30 dark:bg-blue-500/10"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-blue-700 dark:text-blue-400"
                    >
                        Purchase Order
                    </p>

                    <p
                        class="mt-2 text-sm font-semibold text-blue-900 dark:text-blue-200"
                    >
                        {{
                            props.supplierDebitNote
                                .purchase_order_number
                            ?? `PO #${props.supplierDebitNote.purchase_order_id}`
                        }}
                    </p>
                </Link>

                <Link
                    :href="
                        route(
                            'goods-receipts.show',
                            props.supplierDebitNote
                                .goods_receipt_id,
                        )
                    "
                    class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 transition hover:border-emerald-300 dark:border-emerald-500/30 dark:bg-emerald-500/10"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-emerald-700 dark:text-emerald-400"
                    >
                        Goods Receipt
                    </p>

                    <p
                        class="mt-2 text-sm font-semibold text-emerald-900 dark:text-emerald-200"
                    >
                        {{
                            props.supplierDebitNote
                                .goods_receipt_number
                            ?? `GR #${props.supplierDebitNote.goods_receipt_id}`
                        }}
                    </p>
                </Link>
            </div>
        </section>

        <section
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Supplier Invoice Allocation
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Allocation status changes from draft
                        to reserved, applied, and reversed
                        through the financial workflow.
                    </p>
                </div>

                <div
                    class="text-left sm:text-right"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Allocated / Unallocated
                    </p>

                    <p
                        class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{
                            formatAmount(
                                props.supplierDebitNote
                                    .allocated_amount,
                            )
                        }}
                        /
                        {{
                            formatAmount(
                                props.supplierDebitNote
                                    .unallocated_amount,
                            )
                        }}
                    </p>
                </div>
            </div>

            <div
                v-if="
                    props.supplierDebitNote
                        .allocations.length === 0
                "
                class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10"
            >
                <p
                    class="text-sm font-medium text-amber-900 dark:text-amber-200"
                >
                    This Supplier Debit Note is not allocated
                    to a Supplier Invoice.
                </p>

                <p
                    class="mt-1 text-sm text-amber-700 dark:text-amber-300"
                >
                    The full amount remains an open supplier
                    credit for the future Accounts Payable
                    allocation workflow.
                </p>
            </div>

            <div
                v-else
                class="mt-5 overflow-x-auto"
            >
                <table class="w-full min-w-[1050px]">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-400"
                        >
                            <th class="px-4 py-3">
                                Supplier Invoice
                            </th>

                            <th
                                class="px-4 py-3 text-right"
                            >
                                Amount
                            </th>

                            <th class="px-4 py-3">
                                Status
                            </th>

                            <th class="px-4 py-3">
                                Reserved
                            </th>

                            <th class="px-4 py-3">
                                Applied
                            </th>

                            <th class="px-4 py-3">
                                Reversed / Cancelled
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="allocation in props.supplierDebitNote.allocations"
                            :key="allocation.id"
                            class="border-b border-gray-100 last:border-b-0 dark:border-gray-800"
                        >
                            <td class="px-4 py-4">
                                <Link
                                    :href="
                                        route(
                                            'supplier-invoices.show',
                                            allocation
                                                .supplier_invoice_id,
                                        )
                                    "
                                    class="text-sm font-medium text-indigo-600 transition hover:text-indigo-700 dark:text-indigo-400"
                                >
                                    {{
                                        allocation
                                            .document_number
                                        ?? allocation
                                            .supplier_invoice_number
                                    }}
                                </Link>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        allocation
                                            .supplier_invoice_number
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-4 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    formatAmount(
                                        allocation.amount,
                                    )
                                }}
                            </td>

                            <td class="px-4 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        allocationStatusClasses[
                                            allocation.status
                                        ]
                                    "
                                >
                                    {{
                                        titleCase(
                                            allocation.status,
                                        )
                                    }}
                                </span>
                            </td>

                            <td
                                class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatDateTime(
                                        allocation
                                            .reserved_at,
                                    )
                                }}
                            </td>

                            <td
                                class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatDateTime(
                                        allocation
                                            .applied_at,
                                    )
                                }}
                            </td>

                            <td
                                class="px-4 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <p>
                                    Reversed:
                                    {{
                                        formatDateTime(
                                            allocation
                                                .reversed_at,
                                        )
                                    }}
                                </p>

                                <p class="mt-1">
                                    Cancelled:
                                    {{
                                        formatDateTime(
                                            allocation
                                                .cancelled_at,
                                        )
                                    }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            v-if="hasPurchaseReturnVariance"
            class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-500/30 dark:bg-amber-500/10"
        >
            <h2
                class="font-semibold text-amber-900 dark:text-amber-200"
            >
                Purchase Return Inventory Cost Variance
            </h2>

            <p
                class="mt-2 text-sm text-amber-700 dark:text-amber-300"
            >
                The supplier commercial value differs from
                the inventory value removed at
                weighted-average cost.
            </p>

            <div
                class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3"
            >
                <div>
                    <p
                        class="text-xs uppercase text-amber-700 dark:text-amber-400"
                    >
                        Supplier Value
                    </p>

                    <p
                        class="mt-1 font-semibold text-amber-900 dark:text-amber-200"
                    >
                        {{
                            formatAmount(
                                props.supplierDebitNote
                                    .purchase_return_supplier_value,
                            )
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs uppercase text-amber-700 dark:text-amber-400"
                    >
                        Inventory Value
                    </p>

                    <p
                        class="mt-1 font-semibold text-amber-900 dark:text-amber-200"
                    >
                        {{
                            formatAmount(
                                props.supplierDebitNote
                                    .purchase_return_inventory_value,
                            )
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs uppercase text-amber-700 dark:text-amber-400"
                    >
                        Cost Variance
                    </p>

                    <p
                        class="mt-1 font-semibold"
                        :class="
                            varianceClasses(
                                props.supplierDebitNote
                                    .purchase_return_cost_variance,
                            )
                        "
                    >
                        {{
                            formatAmount(
                                props.supplierDebitNote
                                    .purchase_return_cost_variance,
                            )
                        }}
                    </p>
                </div>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="border-b border-gray-200 p-5 dark:border-gray-800"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Debit Note Lines
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Commercial values use the posted Purchase
                    Return quantities. Source inventory
                    values remain visible for reconciliation.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table
                    class="w-full min-w-[2050px]"
                >
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-400"
                        >
                            <th class="px-5 py-3.5">
                                Line
                            </th>

                            <th class="px-5 py-3.5">
                                Product
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Quantity
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Unit Price
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Gross
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Discount
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Subtotal
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Tax Rate
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Tax
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Total
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Return Supplier Value
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Return Inventory Value
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Return Variance
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <template
                            v-for="line in props.supplierDebitNote.lines"
                            :key="line.id"
                        >
                            <tr
                                class="border-b border-gray-100 align-top dark:border-gray-800"
                            >
                                <td
                                    class="px-5 py-4 text-sm text-gray-500"
                                >
                                    {{ line.line_number }}
                                </td>

                                <td class="px-5 py-4">
                                    <p
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{
                                            line.product_name
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        {{
                                            line.product_sku
                                        }}
                                        ·
                                        {{
                                            line.unit_code
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-400"
                                    >
                                        Purchase Return line
                                        #{{
                                            line.purchase_return_line_id
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            line.supplier_invoice_line_id
                                            !== null
                                        "
                                        class="mt-1 text-xs text-indigo-500"
                                    >
                                        Supplier Invoice line
                                        #{{
                                            line.supplier_invoice_line_id
                                        }}
                                    </p>
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatQuantity(
                                            line.return_quantity,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatAmount(
                                            line.unit_price,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatAmount(
                                            line.gross_amount,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatAmount(
                                            line.discount_amount,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatAmount(
                                            line.subtotal,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatQuantity(
                                            line.tax_rate,
                                        )
                                    }}%
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatAmount(
                                            line.tax_amount,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatAmount(
                                            line.total_amount,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right"
                                >
                                    <p
                                        class="text-sm font-semibold text-gray-900 dark:text-white"
                                    >
                                        {{
                                            formatAmount(
                                                line.purchase_return_supplier_total_cost,
                                            )
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        Unit:
                                        {{
                                            formatAmount(
                                                line.purchase_return_supplier_unit_cost,
                                            )
                                        }}
                                    </p>
                                </td>

                                <td
                                    class="px-5 py-4 text-right"
                                >
                                    <p
                                        class="text-sm font-semibold text-gray-900 dark:text-white"
                                    >
                                        {{
                                            formatAmount(
                                                line.purchase_return_inventory_total_cost,
                                            )
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        Unit:
                                        {{
                                            formatAmount(
                                                line.purchase_return_inventory_unit_cost,
                                            )
                                        }}
                                    </p>
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm font-semibold"
                                    :class="
                                        varianceClasses(
                                            line.purchase_return_cost_variance,
                                        )
                                    "
                                >
                                    {{
                                        formatAmount(
                                            line.purchase_return_cost_variance,
                                        )
                                    }}
                                </td>
                            </tr>

                            <tr
                                v-if="
                                    line.description !== null
                                    || line.notes !== null
                                    || lineHasSourceVariance(
                                        line,
                                    )
                                "
                                class="border-b border-gray-200 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-950/30"
                            >
                                <td
                                    colspan="13"
                                    class="px-5 py-4"
                                >
                                    <div
                                        class="grid grid-cols-1 gap-5 lg:grid-cols-3"
                                    >
                                        <div>
                                            <h3
                                                class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                            >
                                                Description
                                            </h3>

                                            <p
                                                class="mt-2 whitespace-pre-line text-sm text-gray-900 dark:text-white"
                                            >
                                                {{
                                                    line.description
                                                    ?? '—'
                                                }}
                                            </p>
                                        </div>

                                        <div>
                                            <h3
                                                class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                            >
                                                Line Notes
                                            </h3>

                                            <p
                                                class="mt-2 whitespace-pre-line text-sm text-gray-900 dark:text-white"
                                            >
                                                {{
                                                    line.notes
                                                    ?? '—'
                                                }}
                                            </p>
                                        </div>

                                        <div>
                                            <h3
                                                class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                            >
                                                Source Reconciliation
                                            </h3>

                                            <p
                                                v-if="
                                                    lineHasSourceVariance(
                                                        line,
                                                    )
                                                "
                                                class="mt-2 text-sm font-medium"
                                                :class="
                                                    varianceClasses(
                                                        line.purchase_return_cost_variance,
                                                    )
                                                "
                                            >
                                                This line contains a
                                                Purchase Return cost
                                                variance of
                                                {{
                                                    formatAmount(
                                                        line.purchase_return_cost_variance,
                                                    )
                                                }}.
                                            </p>

                                            <p
                                                v-else
                                                class="mt-2 text-sm text-gray-500"
                                            >
                                                No Purchase Return
                                                cost variance exists
                                                for this line.
                                            </p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr
                            v-if="
                                props.supplierDebitNote
                                    .lines.length === 0
                            "
                        >
                            <td
                                colspan="13"
                                class="px-5 py-14 text-center text-sm text-gray-500"
                            >
                                No Supplier Debit Note lines
                                were found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div
            class="grid grid-cols-1 gap-6 xl:grid-cols-2"
        >
            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Debit Note Reason
                </h2>

                <p
                    class="mt-4 whitespace-pre-line text-sm text-gray-900 dark:text-white"
                >
                    {{
                        props.supplierDebitNote
                            .reason
                    }}
                </p>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Internal Notes
                </h2>

                <p
                    class="mt-4 whitespace-pre-line text-sm text-gray-900 dark:text-white"
                >
                    {{
                        props.supplierDebitNote
                            .notes
                        ?? '—'
                    }}
                </p>
            </section>
        </div>

        <section
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <h2
                class="text-lg font-semibold text-gray-900 dark:text-white"
            >
                Workflow History
            </h2>

            <div
                class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <div
                    class="rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Created
                    </p>

                    <p
                        class="mt-2 text-sm font-medium text-gray-900 dark:text-white"
                    >
                        {{
                            props.supplierDebitNote
                                .created_by?.name
                            ?? 'Unknown user'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-gray-500"
                    >
                        {{
                            formatDateTime(
                                props.supplierDebitNote
                                    .created_at,
                            )
                        }}
                    </p>
                </div>

                <div
                    v-if="
                        props.supplierDebitNote
                            .submitted_at
                    "
                    class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-500/30 dark:bg-blue-500/10"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-blue-600 dark:text-blue-400"
                    >
                        Submitted
                    </p>

                    <p
                        class="mt-2 text-sm font-medium text-blue-900 dark:text-blue-200"
                    >
                        {{
                            props.supplierDebitNote
                                .submitted_by?.name
                            ?? 'Unknown user'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-blue-700 dark:text-blue-400"
                    >
                        {{
                            formatDateTime(
                                props.supplierDebitNote
                                    .submitted_at,
                            )
                        }}
                    </p>
                </div>

                <div
                    v-if="
                        props.supplierDebitNote
                            .approved_at
                    "
                    class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-500/30 dark:bg-indigo-500/10"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-indigo-600 dark:text-indigo-400"
                    >
                        Approved
                    </p>

                    <p
                        class="mt-2 text-sm font-medium text-indigo-900 dark:text-indigo-200"
                    >
                        {{
                            props.supplierDebitNote
                                .approved_by?.name
                            ?? 'Unknown user'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-indigo-700 dark:text-indigo-400"
                    >
                        {{
                            formatDateTime(
                                props.supplierDebitNote
                                    .approved_at,
                            )
                        }}
                    </p>
                </div>

                <div
                    v-if="
                        props.supplierDebitNote
                            .posted_at
                    "
                    class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/30 dark:bg-emerald-500/10"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-emerald-600 dark:text-emerald-400"
                    >
                        Posted
                    </p>

                    <p
                        class="mt-2 text-sm font-medium text-emerald-900 dark:text-emerald-200"
                    >
                        {{
                            props.supplierDebitNote
                                .posted_by?.name
                            ?? 'Unknown user'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-emerald-700 dark:text-emerald-400"
                    >
                        {{
                            formatDateTime(
                                props.supplierDebitNote
                                    .posted_at,
                            )
                        }}
                    </p>

                    <p
                        class="mt-2 break-all text-xs text-emerald-700 dark:text-emerald-400"
                    >
                        {{
                            props.supplierDebitNote
                                .accounting_posting_reference
                            ?? 'No accounting reference'
                        }}
                    </p>
                </div>

                <div
                    v-if="
                        props.supplierDebitNote
                            .cancelled_at
                    "
                    class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-500/30 dark:bg-red-500/10"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-red-600 dark:text-red-400"
                    >
                        Cancelled
                    </p>

                    <p
                        class="mt-2 text-sm font-medium text-red-900 dark:text-red-200"
                    >
                        {{
                            props.supplierDebitNote
                                .cancelled_by?.name
                            ?? 'Unknown user'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-red-700 dark:text-red-400"
                    >
                        {{
                            formatDateTime(
                                props.supplierDebitNote
                                    .cancelled_at,
                            )
                        }}
                    </p>
                </div>

                <div
                    v-if="
                        props.supplierDebitNote
                            .reversed_at
                    "
                    class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-500/30 dark:bg-red-500/10"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-red-600 dark:text-red-400"
                    >
                        Reversed
                    </p>

                    <p
                        class="mt-2 text-sm font-medium text-red-900 dark:text-red-200"
                    >
                        {{
                            props.supplierDebitNote
                                .reversed_by?.name
                            ?? 'Unknown user'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-red-700 dark:text-red-400"
                    >
                        {{
                            formatDateTime(
                                props.supplierDebitNote
                                    .reversed_at,
                            )
                        }}
                    </p>

                    <p
                        class="mt-2 break-all text-xs text-red-700 dark:text-red-400"
                    >
                        {{
                            props.supplierDebitNote
                                .accounting_reversal_reference
                            ?? 'No accounting reversal reference'
                        }}
                    </p>
                </div>
            </div>
        </section>
    </div>

    <div
        v-if="showCancellationModal"
        class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-950/60 p-4"
        @click.self="
            closeCancellationModal
        "
    >
        <div
            class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900"
        >
            <h2
                class="text-lg font-semibold text-gray-900 dark:text-white"
            >
                Cancel Supplier Debit Note
            </h2>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Cancelling an approved Debit Note releases
                its reserved Supplier Invoice amount. A
                cancelled Debit Note cannot be reopened.
            </p>

            <form
                class="mt-5"
                @submit.prevent="
                    cancelSupplierDebitNote
                "
            >
                <label
                    for="supplier-debit-note-cancellation-reason"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Cancellation Reason
                    <span class="text-red-500">
                        *
                    </span>
                </label>

                <textarea
                    id="supplier-debit-note-cancellation-reason"
                    v-model="
                        cancellationForm
                            .cancellation_reason
                    "
                    rows="5"
                    maxlength="500"
                    placeholder="Explain why this Supplier Debit Note is being cancelled"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                />

                <p
                    v-if="
                        cancellationForm.errors
                            .cancellation_reason
                    "
                    class="mt-1 text-sm text-red-600"
                >
                    {{
                        cancellationForm.errors
                            .cancellation_reason
                    }}
                </p>

                <div
                    class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                >
                    <button
                        type="button"
                        :disabled="
                            cancellationForm
                                .processing
                        "
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            closeCancellationModal
                        "
                    >
                        Keep Debit Note
                    </button>

                    <button
                        type="submit"
                        :disabled="
                            cancellationForm
                                .processing
                        "
                        class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{
                            cancellationForm
                                .processing
                                ? 'Cancelling...'
                                : 'Confirm Cancellation'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        v-if="showReversalModal"
        class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-950/60 p-4"
        @click.self="
            closeReversalModal
        "
    >
        <div
            class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900"
        >
            <h2
                class="text-lg font-semibold text-gray-900 dark:text-white"
            >
                Reverse Supplier Debit Note
            </h2>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Reversal requires a successful accounting
                reversal. The applied Supplier Invoice amount
                is restored only after accounting succeeds.
            </p>

            <form
                class="mt-5 space-y-5"
                @submit.prevent="
                    reverseSupplierDebitNote
                "
            >
                <div>
                    <label
                        for="supplier-debit-note-reversal-date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Reversal Posting Date
                        <span class="text-red-500">
                            *
                        </span>
                    </label>

                    <input
                        id="supplier-debit-note-reversal-date"
                        v-model="
                            reversalForm
                                .reversal_posting_date
                        "
                        type="date"
                        :min="
                            props.supplierDebitNote
                                .posting_date
                            ?? undefined
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="
                            reversalForm.errors
                                .reversal_posting_date
                        "
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            reversalForm.errors
                                .reversal_posting_date
                        }}
                    </p>
                </div>

                <div>
                    <label
                        for="supplier-debit-note-reversal-reason"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Reversal Reason
                        <span class="text-red-500">
                            *
                        </span>
                    </label>

                    <textarea
                        id="supplier-debit-note-reversal-reason"
                        v-model="
                            reversalForm
                                .reversal_reason
                        "
                        rows="5"
                        maxlength="500"
                        placeholder="Explain why this Supplier Debit Note is being reversed"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="
                            reversalForm.errors
                                .reversal_reason
                        "
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            reversalForm.errors
                                .reversal_reason
                        }}
                    </p>
                </div>

                <div
                    class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                >
                    <button
                        type="button"
                        :disabled="
                            reversalForm.processing
                        "
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            closeReversalModal
                        "
                    >
                        Keep Debit Note
                    </button>

                    <button
                        type="submit"
                        :disabled="
                            reversalForm.processing
                        "
                        class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{
                            reversalForm.processing
                                ? 'Reversing...'
                                : 'Confirm Reversal'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>