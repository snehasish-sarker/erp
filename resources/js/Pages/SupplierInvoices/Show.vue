<script setup lang="ts">
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/vue3';
import {
    computed,
    ref,
} from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    SupplierInvoiceLine,
    SupplierInvoiceMatchStatus,
    SupplierInvoiceShowProps,
    SupplierInvoiceStatus,
} from '@/Types/supplier-invoice';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<SupplierInvoiceShowProps>();

type WorkflowAction =
    | 'validate'
    | 'return-to-draft'
    | 'approve'
    | 'post'
    | 'delete';

const actionInProgress = ref<WorkflowAction | null>(
    null,
);

const showDisputeModal = ref(false);
const showCancellationModal = ref(false);
const showReversalModal = ref(false);

const disputeForm = useForm({
    dispute_reason: '',
});

const cancellationForm = useForm({
    cancellation_reason: '',
});

const localDate = (): string => {
    const date = new Date();

    const localTime = new Date(
        date.getTime()
        - date.getTimezoneOffset() * 60_000,
    );

    return localTime
        .toISOString()
        .slice(0, 10);
};

const defaultReversalPostingDate = (): string => {
    const today = localDate();

    const originalPostingDate =
        props.supplierInvoice.posting_date
        ?? '';

    if (
        originalPostingDate !== ''
        && originalPostingDate > today
    ) {
        return originalPostingDate;
    }

    return today;
};

const reversalForm = useForm({
    reversal_posting_date:
        defaultReversalPostingDate(),

    reversal_reason: '',
});

const documentTitle = computed(
    (): string =>
        props.supplierInvoice.document_number
        ?? `Draft #${props.supplierInvoice.id}`,
);

const statusClasses: Record<
    SupplierInvoiceStatus,
    string
> = {
    draft:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    validated:
        'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',

    approved:
        'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',

    posted:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    disputed:
        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',

    reversed:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',

    cancelled:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const matchStatusClasses: Record<
    SupplierInvoiceMatchStatus,
    string
> = {
    unmatched:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    matched:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    variance:
        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',

    blocked:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const formatDate = (
    value: string | null,
): string => {
    if (value === null || value === '') {
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
    if (value === null || value === '') {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
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

const formatAmount = (
    value: string,
): string => {
    const parsed = Number.parseFloat(value);

    if (!Number.isFinite(parsed)) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(parsed);
};

const formatQuantity = (
    value: string,
): string => {
    const parsed = Number.parseFloat(value);

    if (!Number.isFinite(parsed)) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        },
    ).format(parsed);
};

const decimalValue = (
    value: string,
): number => {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed)
        ? parsed
        : 0;
};

const isZero = (
    value: string,
): boolean => {
    return Math.abs(
        decimalValue(value),
    ) <= 0.000001;
};

const hasHeaderVariance = computed(
    (): boolean => {
        const invoice =
            props.supplierInvoice;

        return !isZero(
            invoice.quantity_variance,
        )
            || !isZero(
                invoice.price_variance_amount,
            )
            || !isZero(
                invoice.discount_variance_amount,
            )
            || !isZero(
                invoice.tax_variance_amount,
            )
            || !isZero(
                invoice.total_variance_amount,
            );
    },
);

const hasLineVariance = (
    line: SupplierInvoiceLine,
): boolean => {
    return line.match_status === 'variance'
        || !isZero(line.quantity_variance)
        || !isZero(
            line.price_variance_amount,
        )
        || !isZero(
            line.discount_variance_amount,
        )
        || !isZero(
            line.tax_variance_amount,
        )
        || !isZero(
            line.total_variance_amount,
        );
};

const runWorkflowAction = (
    action: Exclude<
        WorkflowAction,
        'delete'
    >,
    confirmationMessage: string,
): void => {
    if (!window.confirm(confirmationMessage)) {
        return;
    }

    actionInProgress.value = action;

    router.post(
        route(
            `supplier-invoices.${action}`,
            props.supplierInvoice.id,
        ),
        {},
        {
            preserveScroll: true,

            onFinish: () => {
                actionInProgress.value = null;
            },
        },
    );
};

const validateSupplierInvoice = (): void => {
    runWorkflowAction(
        'validate',
        'Validate this Supplier Invoice? Goods Receipt quantities will be reserved and a permanent internal document number will be allocated.',
    );
};

const returnSupplierInvoiceToDraft = (): void => {
    runWorkflowAction(
        'return-to-draft',
        'Return this Supplier Invoice to draft? Its Goods Receipt quantity reservations will be released and the invoice will become editable.',
    );
};

const approveSupplierInvoice = (): void => {
    runWorkflowAction(
        'approve',
        'Approve this Supplier Invoice? The approved invoice will become eligible for Accounts Payable posting.',
    );
};

const postSupplierInvoice = (): void => {
    runWorkflowAction(
        'post',
        'Post this Supplier Invoice to Accounts Payable? Posting requires the Accounts Payable and GRNI accounting integration to be configured.',
    );
};

const deleteSupplierInvoice = (): void => {
    const confirmed = window.confirm(
        'Delete this draft Supplier Invoice? This action permanently removes the draft and cannot be undone.',
    );

    if (!confirmed) {
        return;
    }

    actionInProgress.value = 'delete';

    router.delete(
        route(
            'supplier-invoices.destroy',
            props.supplierInvoice.id,
        ),
        {
            preserveScroll: true,

            onFinish: () => {
                actionInProgress.value = null;
            },
        },
    );
};

const openDisputeModal = (): void => {
    disputeForm.reset();
    disputeForm.clearErrors();

    showDisputeModal.value = true;
};

const closeDisputeModal = (): void => {
    if (disputeForm.processing) {
        return;
    }

    disputeForm.reset();
    disputeForm.clearErrors();

    showDisputeModal.value = false;
};

const disputeSupplierInvoice = (): void => {
    disputeForm.post(
        route(
            'supplier-invoices.dispute',
            props.supplierInvoice.id,
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                disputeForm.reset();
                showDisputeModal.value = false;
            },
        },
    );
};

const openCancellationModal = (): void => {
    cancellationForm.reset();
    cancellationForm.clearErrors();

    showCancellationModal.value = true;
};

const closeCancellationModal = (): void => {
    if (cancellationForm.processing) {
        return;
    }

    cancellationForm.reset();
    cancellationForm.clearErrors();

    showCancellationModal.value = false;
};

const cancelSupplierInvoice = (): void => {
    cancellationForm.post(
        route(
            'supplier-invoices.cancel',
            props.supplierInvoice.id,
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                cancellationForm.reset();
                showCancellationModal.value = false;
            },
        },
    );
};

const openReversalModal = (): void => {
    reversalForm.reset();
    reversalForm.clearErrors();

    reversalForm.reversal_posting_date =
        defaultReversalPostingDate();

    showReversalModal.value = true;
};

const closeReversalModal = (): void => {
    if (reversalForm.processing) {
        return;
    }

    reversalForm.reset();
    reversalForm.clearErrors();

    showReversalModal.value = false;
};

const reverseSupplierInvoice = (): void => {
    reversalForm.post(
        route(
            'supplier-invoices.reverse',
            props.supplierInvoice.id,
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                reversalForm.reset();
                showReversalModal.value = false;
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
                                'supplier-invoices.index',
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        Supplier Invoices
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
                                supplierInvoice.status
                            ]
                        "
                    >
                        {{
                            supplierInvoice.status_label
                        }}
                    </span>

                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            matchStatusClasses[
                                supplierInvoice.match_status
                            ]
                        "
                    >
                        {{
                            supplierInvoice
                                .match_status_label
                        }}
                    </span>
                </div>

                <p
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    Supplier Invoice:
                    <span
                        class="font-medium text-gray-700 dark:text-gray-300"
                    >
                        {{
                            supplierInvoice
                                .supplier_invoice_number
                        }}
                    </span>
                </p>

                <p
                    class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                >
                    Revision
                    {{ supplierInvoice.revision }}
                </p>
            </div>

            <div
                class="flex flex-wrap items-center gap-2"
            >
                <Link
                    :href="
                        route(
                            'supplier-invoices.index',
                        )
                    "
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back
                </Link>

                <Link
                    v-if="supplierInvoice.can.update"
                    :href="
                        route(
                            'supplier-invoices.edit',
                            supplierInvoice.id,
                        )
                    "
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Edit
                </Link>

                <Link
    v-if="
        supplierInvoice
            .can_view_purchase_returns
    "
    :href="
        route(
            'purchase-returns.index',
            {
                supplier_invoice_id:
                    supplierInvoice.id,
            },
        )
    "
    class="rounded-lg border border-amber-300 bg-white px-4 py-2.5 text-sm font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-500/40 dark:bg-gray-900 dark:text-amber-400 dark:hover:bg-amber-500/10"
>
    Purchase Returns
</Link>

<Link
    v-if="
        supplierInvoice
            .can_view_supplier_debit_notes
    "
    :href="
        route(
            'supplier-debit-notes.index',
            {
                supplier_invoice_id:
                    supplierInvoice.id,
            },
        )
    "
    class="rounded-lg border border-violet-300 bg-white px-4 py-2.5 text-sm font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:bg-gray-900 dark:text-violet-400 dark:hover:bg-violet-500/10"
>
    Supplier Debit Notes
</Link>

                <button
                    v-if="supplierInvoice.can.delete"
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/40 dark:bg-gray-900 dark:text-red-400 dark:hover:bg-red-500/10"
                    @click="deleteSupplierInvoice"
                >
                    {{
                        actionInProgress === 'delete'
                            ? 'Deleting...'
                            : 'Delete Draft'
                    }}
                </button>

                <button
                    v-if="
                        supplierInvoice.can.validate
                    "
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="
                        validateSupplierInvoice
                    "
                >
                    {{
                        actionInProgress
                            === 'validate'
                            ? 'Validating...'
                            : 'Validate'
                    }}
                </button>

                <button
                    v-if="
                        supplierInvoice.can
                            .return_to_draft
                    "
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    @click="
                        returnSupplierInvoiceToDraft
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
                    v-if="supplierInvoice.can.approve"
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="
                        approveSupplierInvoice
                    "
                >
                    {{
                        actionInProgress === 'approve'
                            ? 'Approving...'
                            : 'Approve'
                    }}
                </button>

                <button
                    v-if="supplierInvoice.can.dispute"
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="openDisputeModal"
                >
                    Dispute
                </button>

                <button
                    v-if="supplierInvoice.can.cancel"
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
                    v-if="supplierInvoice.can.post"
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="postSupplierInvoice"
                >
                    {{
                        actionInProgress === 'post'
                            ? 'Posting...'
                            : 'Post Invoice'
                    }}
                </button>

                <button
                    v-if="supplierInvoice.can.reverse"
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="openReversalModal"
                >
                    Reverse Invoice
                </button>
            </div>
        </div>

        <div
            v-if="
                supplierInvoice.status
                === 'disputed'
            "
            class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-500/30 dark:bg-amber-500/10"
        >
            <h2
                class="font-semibold text-amber-800 dark:text-amber-300"
            >
                Supplier Invoice Disputed
            </h2>

            <p
                class="mt-2 whitespace-pre-line text-sm text-amber-700 dark:text-amber-400"
            >
                {{
                    supplierInvoice.dispute_reason
                    ?? 'No dispute reason was recorded.'
                }}
            </p>

            <p
                class="mt-3 text-xs text-amber-600 dark:text-amber-400"
            >
                Disputed by
                {{
                    supplierInvoice
                        .disputed_by?.name
                    ?? 'Unknown user'
                }}
                on
                {{
                    formatDateTime(
                        supplierInvoice
                            .disputed_at,
                    )
                }}
            </p>
        </div>

        <div
            v-if="
                supplierInvoice.status
                === 'cancelled'
            "
            class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-500/30 dark:bg-red-500/10"
        >
            <h2
                class="font-semibold text-red-800 dark:text-red-300"
            >
                Supplier Invoice Cancelled
            </h2>

            <p
                class="mt-2 whitespace-pre-line text-sm text-red-700 dark:text-red-400"
            >
                {{
                    supplierInvoice
                        .cancellation_reason
                    ?? 'No cancellation reason was recorded.'
                }}
            </p>

            <p
                class="mt-3 text-xs text-red-600 dark:text-red-400"
            >
                Cancelled by
                {{
                    supplierInvoice
                        .cancelled_by?.name
                    ?? 'Unknown user'
                }}
                on
                {{
                    formatDateTime(
                        supplierInvoice
                            .cancelled_at,
                    )
                }}
            </p>
        </div>

        <div
            v-if="
                supplierInvoice.status
                === 'reversed'
            "
            class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-500/30 dark:bg-red-500/10"
        >
            <h2
                class="font-semibold text-red-800 dark:text-red-300"
            >
                Supplier Invoice Reversed
            </h2>

            <p
                class="mt-2 whitespace-pre-line text-sm text-red-700 dark:text-red-400"
            >
                {{
                    supplierInvoice.reversal_reason
                    ?? 'No reversal reason was recorded.'
                }}
            </p>

            <p
                class="mt-3 text-xs text-red-600 dark:text-red-400"
            >
                Reversed by
                {{
                    supplierInvoice
                        .reversed_by?.name
                    ?? 'Unknown user'
                }}
                on
                {{
                    formatDateTime(
                        supplierInvoice
                            .reversed_at,
                    )
                }}
            </p>
        </div>

        <div
            v-if="
                supplierInvoice.status
                    === 'approved'
                && supplierInvoice
                    .accounting_posting_reference
                    === null
            "
            class="rounded-2xl border border-blue-200 bg-blue-50 p-5 dark:border-blue-500/30 dark:bg-blue-500/10"
        >
            <h2
                class="font-semibold text-blue-800 dark:text-blue-300"
            >
                Accounting Posting Required
            </h2>

            <p
                class="mt-2 text-sm text-blue-700 dark:text-blue-400"
            >
                Posting requires the Accounts Payable and
                Goods Received Not Invoiced journal
                integration. The backend safely blocks
                posting when that integration is not yet
                configured.
            </p>
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
                    Invoice Information
                </h2>

                <dl
                    class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2"
                >
                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Internal Number
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                supplierInvoice
                                    .document_number
                                ?? 'Pending validation'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Supplier Invoice Number
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                supplierInvoice
                                    .supplier_invoice_number
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Purchase Order
                        </dt>

                        <dd class="mt-1">
                            <Link
                                :href="
                                    route(
                                        'purchase-orders.show',
                                        supplierInvoice
                                            .purchase_order_id,
                                    )
                                "
                                class="text-sm font-medium text-brand-600 transition hover:text-brand-700 dark:text-brand-400"
                            >
                                {{
                                    supplierInvoice
                                        .purchase_order_number
                                    ?? `Purchase Order #${supplierInvoice.purchase_order_id}`
                                }}
                            </Link>
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
                                supplierInvoice
                                    .supplier.name
                            }}
                            ({{
                                supplierInvoice
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
                                supplierInvoice
                                    .branch.name
                            }}
                            ({{
                                supplierInvoice
                                    .branch.code
                            }})
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Invoice Date
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    supplierInvoice
                                        .invoice_date,
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
                                    supplierInvoice
                                        .posting_date,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Due Date
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    supplierInvoice
                                        .due_date,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Currency
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                supplierInvoice
                                    .currency_code
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Exchange Rate
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    supplierInvoice
                                        .exchange_rate,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Revision
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                supplierInvoice
                                    .revision
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Quantity Reservation
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            <template
                                v-if="
                                    supplierInvoice
                                        .matching_reserved_at
                                "
                            >
                                Reserved on
                                {{
                                    formatDateTime(
                                        supplierInvoice
                                            .matching_reserved_at,
                                    )
                                }}
                            </template>

                            <template v-else>
                                Not reserved
                            </template>
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Invoice Totals
                </h2>

                <div class="space-y-4">
                    <div
                        class="flex justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Gross Subtotal
                        </span>

                        <span
                            class="text-right font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    supplierInvoice
                                        .subtotal_amount,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Discount
                        </span>

                        <span
                            class="text-right font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    supplierInvoice
                                        .discount_amount,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Tax
                        </span>

                        <span
                            class="text-right font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    supplierInvoice
                                        .tax_amount,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Other Charges
                        </span>

                        <span
                            class="text-right font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    supplierInvoice
                                        .other_charges,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Rounding Adjustment
                        </span>

                        <span
                            class="text-right font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    supplierInvoice
                                        .rounding_adjustment,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="border-t border-gray-200 pt-4 dark:border-gray-800"
                    >
                        <div
                            class="flex items-end justify-between gap-4"
                        >
                            <div>
                                <p
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    Invoice Total
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        supplierInvoice
                                            .currency_code
                                    }}
                                </p>
                            </div>

                            <p
                                class="text-right text-xl font-bold text-gray-900 dark:text-white"
                            >
                                {{
                                    formatAmount(
                                        supplierInvoice
                                            .total_amount,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section
            v-if="
                hasHeaderVariance
                || supplierInvoice.match_status
                    !== 'matched'
            "
            class="rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm dark:border-amber-500/30 dark:bg-amber-500/10"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-semibold text-amber-900 dark:text-amber-200"
                    >
                        Matching Variance Summary
                    </h2>

                    <p
                        class="mt-1 text-sm text-amber-700 dark:text-amber-300"
                    >
                        Review quantity, price, discount,
                        and tax differences before approval.
                    </p>
                </div>

                <span
                    class="inline-flex self-start rounded-full px-2.5 py-1 text-xs font-medium"
                    :class="
                        matchStatusClasses[
                            supplierInvoice.match_status
                        ]
                    "
                >
                    {{
                        supplierInvoice
                            .match_status_label
                    }}
                </span>
            </div>

            <dl
                class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5"
            >
                <div
                    class="rounded-xl bg-white/80 p-4 dark:bg-gray-900/60"
                >
                    <dt
                        class="text-xs uppercase text-gray-500"
                    >
                        Quantity Variance
                    </dt>

                    <dd
                        class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{
                            formatQuantity(
                                supplierInvoice
                                    .quantity_variance,
                            )
                        }}
                    </dd>
                </div>

                <div
                    class="rounded-xl bg-white/80 p-4 dark:bg-gray-900/60"
                >
                    <dt
                        class="text-xs uppercase text-gray-500"
                    >
                        Price Variance
                    </dt>

                    <dd
                        class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{
                            formatAmount(
                                supplierInvoice
                                    .price_variance_amount,
                            )
                        }}
                    </dd>
                </div>

                <div
                    class="rounded-xl bg-white/80 p-4 dark:bg-gray-900/60"
                >
                    <dt
                        class="text-xs uppercase text-gray-500"
                    >
                        Discount Variance
                    </dt>

                    <dd
                        class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{
                            formatAmount(
                                supplierInvoice
                                    .discount_variance_amount,
                            )
                        }}
                    </dd>
                </div>

                <div
                    class="rounded-xl bg-white/80 p-4 dark:bg-gray-900/60"
                >
                    <dt
                        class="text-xs uppercase text-gray-500"
                    >
                        Tax Variance
                    </dt>

                    <dd
                        class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{
                            formatAmount(
                                supplierInvoice
                                    .tax_variance_amount,
                            )
                        }}
                    </dd>
                </div>

                <div
                    class="rounded-xl bg-white/80 p-4 dark:bg-gray-900/60"
                >
                    <dt
                        class="text-xs uppercase text-gray-500"
                    >
                        Total Variance
                    </dt>

                    <dd
                        class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{
                            formatAmount(
                                supplierInvoice
                                    .total_variance_amount,
                            )
                        }}
                    </dd>
                </div>
            </dl>
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
                    Three-Way Matching Lines
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Purchase Order quantities and prices are
                    compared with accepted Goods Receipt
                    quantities and supplier invoice values.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1900px]">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-400"
                        >
                            <th class="px-5 py-3.5">
                                Product
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                PO Qty
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Received
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Previously Invoiced
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Available
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Invoice Qty
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Matched Qty
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                PO Price
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Invoice Price
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Discount
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

                            <th class="px-5 py-3.5">
                                Match Status
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <template
                            v-for="line in supplierInvoice.lines"
                            :key="line.id"
                        >
                            <tr
                                class="border-b border-gray-100 align-top dark:border-gray-800"
                            >
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
                                        ·
                                        {{
                                            line.product_type
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            line.description
                                        "
                                        class="mt-2 max-w-xs whitespace-pre-line text-xs text-gray-500"
                                    >
                                        {{
                                            line.description
                                        }}
                                    </p>
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm"
                                >
                                    {{
                                        formatQuantity(
                                            line.ordered_quantity_snapshot,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm"
                                >
                                    {{
                                        formatQuantity(
                                            line.received_quantity_snapshot,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm"
                                >
                                    {{
                                        formatQuantity(
                                            line.previously_invoiced_quantity_snapshot,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm"
                                >
                                    {{
                                        formatQuantity(
                                            line.available_to_invoice_quantity_snapshot,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatQuantity(
                                            line.invoiced_quantity,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm font-medium text-emerald-600"
                                >
                                    {{
                                        formatQuantity(
                                            line.matched_quantity,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm"
                                >
                                    {{
                                        formatAmount(
                                            line.purchase_order_unit_price_snapshot,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm"
                                >
                                    {{
                                        formatAmount(
                                            line.invoice_unit_price,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm"
                                >
                                    {{
                                        formatAmount(
                                            line.discount_amount,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm"
                                >
                                    <p>
                                        {{
                                            formatAmount(
                                                line.tax_amount,
                                            )
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        {{
                                            formatQuantity(
                                                line.tax_rate,
                                            )
                                        }}%
                                    </p>
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatAmount(
                                            line.line_total,
                                        )
                                    }}
                                </td>

                                <td class="px-5 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            matchStatusClasses[
                                                line.match_status
                                            ]
                                        "
                                    >
                                        {{
                                            line.match_status_label
                                        }}
                                    </span>
                                </td>
                            </tr>

                            <tr
                                class="border-b border-gray-200 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-950/30"
                            >
                                <td
                                    colspan="13"
                                    class="px-5 py-4"
                                >
                                    <div
                                        class="grid grid-cols-1 gap-5 xl:grid-cols-3"
                                    >
                                        <div
                                            class="xl:col-span-2"
                                        >
                                            <h3
                                                class="text-sm font-semibold text-gray-900 dark:text-white"
                                            >
                                                Goods Receipt
                                                Allocations
                                            </h3>

                                            <div
                                                v-if="
                                                    line.matches
                                                        .length
                                                    > 0
                                                "
                                                class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2"
                                            >
                                                <div
                                                    v-for="match in line.matches"
                                                    :key="match.id"
                                                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
                                                >
                                                    <div
                                                        class="flex flex-wrap items-start justify-between gap-3"
                                                    >
                                                        <div>
                                                            <p
                                                                class="text-sm font-medium text-gray-900 dark:text-white"
                                                            >
                                                                {{
                                                                    match.receipt_number
                                                                    ?? `Goods Receipt #${match.goods_receipt_id}`
                                                                }}
                                                            </p>

                                                            <p
                                                                class="mt-1 text-xs text-gray-500"
                                                            >
                                                                {{
                                                                    formatDate(
                                                                        match.receipt_date,
                                                                    )
                                                                }}
                                                            </p>
                                                        </div>

                                                        <Link
                                                            :href="
                                                                route(
                                                                    'goods-receipts.show',
                                                                    match.goods_receipt_id,
                                                                )
                                                            "
                                                            class="text-xs font-medium text-brand-600 transition hover:text-brand-700 dark:text-brand-400"
                                                        >
                                                            View Receipt
                                                        </Link>
                                                    </div>

                                                    <dl
                                                        class="mt-4 grid grid-cols-2 gap-3 text-xs"
                                                    >
                                                        <div>
                                                            <dt
                                                                class="text-gray-500"
                                                            >
                                                                Matched
                                                            </dt>

                                                            <dd
                                                                class="mt-1 font-medium text-gray-900 dark:text-white"
                                                            >
                                                                {{
                                                                    formatQuantity(
                                                                        match.matched_quantity,
                                                                    )
                                                                }}
                                                            </dd>
                                                        </div>

                                                        <div>
                                                            <dt
                                                                class="text-gray-500"
                                                            >
                                                                Accepted
                                                            </dt>

                                                            <dd
                                                                class="mt-1 font-medium text-gray-900 dark:text-white"
                                                            >
                                                                {{
                                                                    formatQuantity(
                                                                        match.receipt_accepted_quantity_snapshot,
                                                                    )
                                                                }}
                                                            </dd>
                                                        </div>

                                                        <div>
                                                            <dt
                                                                class="text-gray-500"
                                                            >
                                                                Previously Invoiced
                                                            </dt>

                                                            <dd
                                                                class="mt-1 font-medium text-gray-900 dark:text-white"
                                                            >
                                                                {{
                                                                    formatQuantity(
                                                                        match.previously_invoiced_quantity_snapshot,
                                                                    )
                                                                }}
                                                            </dd>
                                                        </div>

                                                        <div>
                                                            <dt
                                                                class="text-gray-500"
                                                            >
                                                                Available
                                                            </dt>

                                                            <dd
                                                                class="mt-1 font-medium text-gray-900 dark:text-white"
                                                            >
                                                                {{
                                                                    formatQuantity(
                                                                        match.available_quantity_snapshot,
                                                                    )
                                                                }}
                                                            </dd>
                                                        </div>

                                                        <div>
                                                            <dt
                                                                class="text-gray-500"
                                                            >
                                                                Price Variance
                                                            </dt>

                                                            <dd
                                                                class="mt-1 font-medium text-gray-900 dark:text-white"
                                                            >
                                                                {{
                                                                    formatAmount(
                                                                        match.price_variance_amount,
                                                                    )
                                                                }}
                                                            </dd>
                                                        </div>

                                                        <div>
                                                            <dt
                                                                class="text-gray-500"
                                                            >
                                                                Matched Amount
                                                            </dt>

                                                            <dd
                                                                class="mt-1 font-medium text-gray-900 dark:text-white"
                                                            >
                                                                {{
                                                                    formatAmount(
                                                                        match.matched_amount,
                                                                    )
                                                                }}
                                                            </dd>
                                                        </div>
                                                    </dl>
                                                </div>
                                            </div>

                                            <p
                                                v-else
                                                class="mt-3 text-sm text-gray-500"
                                            >
                                                No Goods Receipt
                                                allocations were
                                                recorded.
                                            </p>
                                        </div>

                                        <div>
                                            <h3
                                                class="text-sm font-semibold text-gray-900 dark:text-white"
                                            >
                                                Variance Details
                                            </h3>

                                            <dl
                                                class="mt-3 space-y-3 rounded-xl border border-gray-200 bg-white p-4 text-sm dark:border-gray-800 dark:bg-gray-900"
                                            >
                                                <div
                                                    class="flex justify-between gap-3"
                                                >
                                                    <dt
                                                        class="text-gray-500"
                                                    >
                                                        Quantity
                                                    </dt>

                                                    <dd
                                                        class="font-medium text-gray-900 dark:text-white"
                                                    >
                                                        {{
                                                            formatQuantity(
                                                                line.quantity_variance,
                                                            )
                                                        }}
                                                    </dd>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-3"
                                                >
                                                    <dt
                                                        class="text-gray-500"
                                                    >
                                                        Price
                                                    </dt>

                                                    <dd
                                                        class="font-medium text-gray-900 dark:text-white"
                                                    >
                                                        {{
                                                            formatAmount(
                                                                line.price_variance_amount,
                                                            )
                                                        }}
                                                    </dd>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-3"
                                                >
                                                    <dt
                                                        class="text-gray-500"
                                                    >
                                                        Discount
                                                    </dt>

                                                    <dd
                                                        class="font-medium text-gray-900 dark:text-white"
                                                    >
                                                        {{
                                                            formatAmount(
                                                                line.discount_variance_amount,
                                                            )
                                                        }}
                                                    </dd>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-3"
                                                >
                                                    <dt
                                                        class="text-gray-500"
                                                    >
                                                        Tax
                                                    </dt>

                                                    <dd
                                                        class="font-medium text-gray-900 dark:text-white"
                                                    >
                                                        {{
                                                            formatAmount(
                                                                line.tax_variance_amount,
                                                            )
                                                        }}
                                                    </dd>
                                                </div>

                                                <div
                                                    class="flex justify-between gap-3 border-t border-gray-200 pt-3 dark:border-gray-800"
                                                >
                                                    <dt
                                                        class="font-medium text-gray-700 dark:text-gray-300"
                                                    >
                                                        Total
                                                    </dt>

                                                    <dd
                                                        class="font-semibold text-gray-900 dark:text-white"
                                                    >
                                                        {{
                                                            formatAmount(
                                                                line.total_variance_amount,
                                                            )
                                                        }}
                                                    </dd>
                                                </div>
                                            </dl>

                                            <div
                                                v-if="
                                                    hasLineVariance(
                                                        line,
                                                    )
                                                "
                                                class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10"
                                            >
                                                <p
                                                    class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300"
                                                >
                                                    Variance Reason
                                                </p>

                                                <p
                                                    class="mt-2 whitespace-pre-line text-sm text-amber-800 dark:text-amber-200"
                                                >
                                                    {{
                                                        line.variance_reason
                                                        ?? 'No variance reason was recorded.'
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
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
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Notes
                </h2>

                <div class="space-y-5">
                    <div>
                        <h3
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Matching Notes
                        </h3>

                        <p
                            class="mt-2 whitespace-pre-line text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                supplierInvoice
                                    .matching_notes
                                ?? '—'
                            }}
                        </p>
                    </div>

                    <div
                        class="border-t border-gray-200 pt-5 dark:border-gray-800"
                    >
                        <h3
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Internal Notes
                        </h3>

                        <p
                            class="mt-2 whitespace-pre-line text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                supplierInvoice.notes
                                ?? '—'
                            }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Accounting References
                </h2>

                <dl class="space-y-5">
                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Posting Reference
                        </dt>

                        <dd
                            class="mt-1 break-words text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                supplierInvoice
                                    .accounting_posting_reference
                                ?? 'Not posted'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Reversal Posting Date
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    supplierInvoice
                                        .reversal_posting_date,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Reversal Reference
                        </dt>

                        <dd
                            class="mt-1 break-words text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                supplierInvoice
                                    .accounting_reversal_reference
                                ?? 'Not reversed'
                            }}
                        </dd>
                    </div>
                </dl>
            </section>
        </div>

        <section
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <h2
                class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
            >
                Workflow History
            </h2>

            <div class="space-y-4">
                <p
                    class="text-sm text-gray-600 dark:text-gray-300"
                >
                    Created by
                    {{
                        supplierInvoice
                            .created_by?.name
                        ?? 'Unknown user'
                    }}
                    on
                    {{
                        formatDateTime(
                            supplierInvoice
                                .created_at,
                        )
                    }}
                </p>

                <p
                    v-if="
                        supplierInvoice
                            .validated_at
                    "
                    class="text-sm text-blue-700 dark:text-blue-400"
                >
                    Validated by
                    {{
                        supplierInvoice
                            .validated_by?.name
                        ?? 'Unknown user'
                    }}
                    on
                    {{
                        formatDateTime(
                            supplierInvoice
                                .validated_at,
                        )
                    }}
                </p>

                <p
                    v-if="
                        supplierInvoice
                            .approved_at
                    "
                    class="text-sm text-indigo-700 dark:text-indigo-400"
                >
                    Approved by
                    {{
                        supplierInvoice
                            .approved_by?.name
                        ?? 'Unknown user'
                    }}
                    on
                    {{
                        formatDateTime(
                            supplierInvoice
                                .approved_at,
                        )
                    }}
                </p>

                <p
                    v-if="
                        supplierInvoice
                            .disputed_at
                    "
                    class="text-sm text-amber-700 dark:text-amber-400"
                >
                    Disputed by
                    {{
                        supplierInvoice
                            .disputed_by?.name
                        ?? 'Unknown user'
                    }}
                    on
                    {{
                        formatDateTime(
                            supplierInvoice
                                .disputed_at,
                        )
                    }}
                </p>

                <p
                    v-if="supplierInvoice.posted_at"
                    class="text-sm text-emerald-700 dark:text-emerald-400"
                >
                    Posted by
                    {{
                        supplierInvoice
                            .posted_by?.name
                        ?? 'Unknown user'
                    }}
                    on
                    {{
                        formatDateTime(
                            supplierInvoice
                                .posted_at,
                        )
                    }}
                </p>

                <p
                    v-if="
                        supplierInvoice
                            .reversed_at
                    "
                    class="text-sm text-red-700 dark:text-red-400"
                >
                    Reversed by
                    {{
                        supplierInvoice
                            .reversed_by?.name
                        ?? 'Unknown user'
                    }}
                    on
                    {{
                        formatDateTime(
                            supplierInvoice
                                .reversed_at,
                        )
                    }}
                </p>

                <p
                    v-if="
                        supplierInvoice
                            .cancelled_at
                    "
                    class="text-sm text-red-700 dark:text-red-400"
                >
                    Cancelled by
                    {{
                        supplierInvoice
                            .cancelled_by?.name
                        ?? 'Unknown user'
                    }}
                    on
                    {{
                        formatDateTime(
                            supplierInvoice
                                .cancelled_at,
                        )
                    }}
                </p>
            </div>
        </section>
    </div>

    <div
        v-if="showDisputeModal"
        class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-950/60 p-4"
        @click.self="closeDisputeModal"
    >
        <div
            class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900"
        >
            <h2
                class="text-lg font-semibold text-gray-900 dark:text-white"
            >
                Dispute Supplier Invoice
            </h2>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Disputing the invoice releases its Goods
                Receipt quantity reservations. The invoice
                must be corrected and validated again.
            </p>

            <form
                class="mt-5"
                @submit.prevent="
                    disputeSupplierInvoice
                "
            >
                <label
                    for="dispute_reason"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Dispute Reason
                    <span class="text-red-500">
                        *
                    </span>
                </label>

                <textarea
                    id="dispute_reason"
                    v-model="
                        disputeForm.dispute_reason
                    "
                    rows="5"
                    maxlength="500"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                />

                <p
                    v-if="
                        disputeForm.errors
                            .dispute_reason
                    "
                    class="mt-1 text-sm text-red-600"
                >
                    {{
                        disputeForm.errors
                            .dispute_reason
                    }}
                </p>

                <div
                    class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                >
                    <button
                        type="button"
                        :disabled="
                            disputeForm.processing
                        "
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="closeDisputeModal"
                    >
                        Keep Current Status
                    </button>

                    <button
                        type="submit"
                        :disabled="
                            disputeForm.processing
                        "
                        class="rounded-lg bg-amber-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-amber-600 disabled:opacity-60"
                    >
                        {{
                            disputeForm.processing
                                ? 'Disputing...'
                                : 'Confirm Dispute'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div
        v-if="showCancellationModal"
        class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-950/60 p-4"
        @click.self="closeCancellationModal"
    >
        <div
            class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900"
        >
            <h2
                class="text-lg font-semibold text-gray-900 dark:text-white"
            >
                Cancel Supplier Invoice
            </h2>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Cancellation releases reserved Goods
                Receipt quantities and permanently closes
                this invoice workflow.
            </p>

            <form
                class="mt-5"
                @submit.prevent="
                    cancelSupplierInvoice
                "
            >
                <label
                    for="cancellation_reason"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Cancellation Reason
                    <span class="text-red-500">
                        *
                    </span>
                </label>

                <textarea
                    id="cancellation_reason"
                    v-model="
                        cancellationForm
                            .cancellation_reason
                    "
                    rows="5"
                    maxlength="500"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
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
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            closeCancellationModal
                        "
                    >
                        Keep Invoice
                    </button>

                    <button
                        type="submit"
                        :disabled="
                            cancellationForm
                                .processing
                        "
                        class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 disabled:opacity-60"
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
        @click.self="closeReversalModal"
    >
        <div
            class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900"
        >
            <h2
                class="text-lg font-semibold text-gray-900 dark:text-white"
            >
                Reverse Supplier Invoice
            </h2>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Reversal requires an open accounting
                period and a configured Accounts Payable
                reversal integration. Reserved Goods
                Receipt quantities are released only after
                successful accounting reversal.
            </p>

            <form
                class="mt-5 space-y-5"
                @submit.prevent="
                    reverseSupplierInvoice
                "
            >
                <div>
                    <label
                        for="reversal_posting_date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Reversal Posting Date
                        <span class="text-red-500">
                            *
                        </span>
                    </label>

                    <input
                        id="reversal_posting_date"
                        v-model="
                            reversalForm
                                .reversal_posting_date
                        "
                        type="date"
                        :min="
                            supplierInvoice
                                .posting_date
                            ?? undefined
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
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
                        for="reversal_reason"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Reversal Reason
                        <span class="text-red-500">
                            *
                        </span>
                    </label>

                    <textarea
                        id="reversal_reason"
                        v-model="
                            reversalForm
                                .reversal_reason
                        "
                        rows="5"
                        maxlength="500"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
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
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="closeReversalModal"
                    >
                        Keep Invoice
                    </button>

                    <button
                        type="submit"
                        :disabled="
                            reversalForm.processing
                        "
                        class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 disabled:opacity-60"
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