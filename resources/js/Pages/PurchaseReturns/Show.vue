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
    PurchaseReturnLine,
    PurchaseReturnShowProps,
    PurchaseReturnStatus,
} from '@/Types/purchase-return';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<PurchaseReturnShowProps>();

type DirectWorkflowAction =
    | 'submit'
    | 'return-to-draft'
    | 'approve'
    | 'post'
    | 'delete';

const actionInProgress =
    ref<DirectWorkflowAction | null>(
        null,
    );

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
            props.purchaseReturn.posting_date
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
    (): string => {
        return props.purchaseReturn
            .return_number
            ?? `Draft #${props.purchaseReturn.id}`;
    },
);

const statusClasses: Record<
    PurchaseReturnStatus,
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
    const parsed = decimalValue(value);

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        },
    ).format(parsed);
};

const formatAmount = (
    value: string | number,
): string => {
    const parsed = decimalValue(value);

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(parsed);
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

const hasCostVariance = computed(
    (): boolean => {
        return Math.abs(
            decimalValue(
                props.purchaseReturn
                    .total_cost_variance,
            ),
        ) > 0.000001;
    },
);

const inventoryValueAvailable = computed(
    (): boolean => {
        return [
            'posted',
            'reversed',
        ].includes(
            props.purchaseReturn.status,
        );
    },
);

const varianceClasses = (
    value: string,
): string => {
    const parsed = decimalValue(value);

    if (Math.abs(parsed) <= 0.000001) {
        return 'text-gray-600 dark:text-gray-300';
    }

    return parsed > 0
        ? 'text-amber-600 dark:text-amber-400'
        : 'text-blue-600 dark:text-blue-400';
};

const lineHasVariance = (
    line: PurchaseReturnLine,
): boolean => {
    return Math.abs(
        decimalValue(
            line.cost_variance_amount,
        ),
    ) > 0.000001;
};

const movementLabel = (
    movementType: string,
): string => {
    if (
        movementType
        === 'purchase_return'
    ) {
        return 'Purchase Return';
    }

    if (
        movementType
        === 'purchase_return_reversal'
    ) {
        return 'Purchase Return Reversal';
    }

    return movementType
        .replaceAll('_', ' ')
        .replace(
            /\b\w/g,
            (
                character,
            ): string =>
                character.toUpperCase(),
        );
};

const runWorkflowAction = (
    action: Exclude<
        DirectWorkflowAction,
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
            `purchase-returns.${action}`,
            props.purchaseReturn.id,
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

const submitPurchaseReturn = (): void => {
    runWorkflowAction(
        'submit',
        'Submit this Purchase Return for approval? A permanent return number will be allocated and the return will become read-only unless it is returned to draft.',
    );
};

const returnPurchaseReturnToDraft =
    (): void => {
        runWorkflowAction(
            'return-to-draft',
            'Return this Purchase Return to draft? It will become editable again.',
        );
    };

const approvePurchaseReturn = (): void => {
    runWorkflowAction(
        'approve',
        'Approve this Purchase Return? The return quantities will be reserved against the source Goods Receipt until the return is posted or cancelled.',
    );
};

const postPurchaseReturn = (): void => {
    runWorkflowAction(
        'post',
        'Post this Purchase Return to inventory? Stock will be removed using the current weighted-average inventory cost, and the document will become read-only.',
    );
};

const deletePurchaseReturn = (): void => {
    const confirmed = window.confirm(
        'Delete this Purchase Return draft? Only an unnumbered, never-submitted draft can be deleted. This action cannot be undone.',
    );

    if (!confirmed) {
        return;
    }

    actionInProgress.value = 'delete';

    router.delete(
        route(
            'purchase-returns.destroy',
            props.purchaseReturn.id,
        ),
        {
            preserveScroll: true,

            onFinish: () => {
                actionInProgress.value = null;
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

const cancelPurchaseReturn = (): void => {
    cancellationForm.post(
        route(
            'purchase-returns.cancel',
            props.purchaseReturn.id,
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

const reversePurchaseReturn = (): void => {
    reversalForm.post(
        route(
            'purchase-returns.reverse',
            props.purchaseReturn.id,
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
                                purchaseReturn.status
                            ]
                        "
                    >
                        {{
                            purchaseReturn
                                .status_label
                        }}
                    </span>
                </div>

                <p
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    Source Goods Receipt:
                    <Link
                        :href="
                            route(
                                'goods-receipts.show',
                                purchaseReturn
                                    .goods_receipt_id,
                            )
                        "
                        class="font-medium text-brand-600 transition hover:text-brand-700 dark:text-brand-400"
                    >
                        {{
                            purchaseReturn
                                .goods_receipt_number
                            ?? `Goods Receipt #${purchaseReturn.goods_receipt_id}`
                        }}
                    </Link>
                </p>

                <p
                    class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                >
                    Revision
                    {{ purchaseReturn.revision }}
                </p>
            </div>

            <div
                class="flex flex-wrap items-center gap-2"
            >
                <Link
                    :href="
                        route(
                            'purchase-returns.index',
                        )
                    "
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back
                </Link>

                <Link
                    v-if="purchaseReturn.can.update"
                    :href="
                        route(
                            'purchase-returns.edit',
                            purchaseReturn.id,
                        )
                    "
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Edit
                </Link>

                <Link
    v-if="
        purchaseReturn
            .can_create_supplier_debit_note
    "
    :href="
        route(
            'supplier-debit-notes.create',
            {
                purchase_return_id:
                    purchaseReturn.id,
            },
        )
    "
    class="rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-violet-700"
>
    Create Supplier Debit Note
</Link>

<Link
    v-else-if="
        purchaseReturn
            .supplier_debit_note
        !== null
        && purchaseReturn
            .can_view_supplier_debit_notes
    "
    :href="
        route(
            'supplier-debit-notes.show',
            purchaseReturn
                .supplier_debit_note.id,
        )
    "
    class="rounded-lg border border-violet-300 bg-white px-4 py-2.5 text-sm font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:bg-gray-900 dark:text-violet-400 dark:hover:bg-violet-500/10"
>
    View Supplier Debit Note
</Link>

                <button
                    v-if="purchaseReturn.can.delete"
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/40 dark:bg-gray-900 dark:text-red-400 dark:hover:bg-red-500/10"
                    @click="deletePurchaseReturn"
                >
                    {{
                        actionInProgress
                            === 'delete'
                            ? 'Deleting...'
                            : 'Delete Draft'
                    }}
                </button>

                <button
                    v-if="purchaseReturn.can.submit"
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="submitPurchaseReturn"
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
                        purchaseReturn.can
                            .return_to_draft
                    "
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    @click="
                        returnPurchaseReturnToDraft
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
                    v-if="purchaseReturn.can.approve"
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="approvePurchaseReturn"
                >
                    {{
                        actionInProgress
                            === 'approve'
                            ? 'Approving...'
                            : 'Approve'
                    }}
                </button>

                <button
                    v-if="purchaseReturn.can.cancel"
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/40 dark:bg-gray-900 dark:text-red-400 dark:hover:bg-red-500/10"
                    @click="openCancellationModal"
                >
                    Cancel
                </button>

                <button
                    v-if="purchaseReturn.can.post"
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="postPurchaseReturn"
                >
                    {{
                        actionInProgress
                            === 'post'
                            ? 'Posting...'
                            : 'Post Return'
                    }}
                </button>

                <button
                    v-if="purchaseReturn.can.reverse"
                    type="button"
                    :disabled="
                        actionInProgress !== null
                    "
                    class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                    @click="openReversalModal"
                >
                    Reverse Return
                </button>
            </div>
        </div>

        <section
            v-if="
                purchaseReturn.status
                === 'approved'
            "
            class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-500/30 dark:bg-indigo-500/10"
        >
            <h2
                class="font-semibold text-indigo-900 dark:text-indigo-200"
            >
                Return Quantities Reserved
            </h2>

            <p
                class="mt-2 text-sm text-indigo-700 dark:text-indigo-300"
            >
                The approved quantities are reserved against
                the source Goods Receipt. They cannot be used
                by another Purchase Return while this return
                remains approved.
            </p>
        </section>

        <section
            v-if="
                purchaseReturn.status
                === 'cancelled'
            "
            class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-500/30 dark:bg-red-500/10"
        >
            <h2
                class="font-semibold text-red-900 dark:text-red-200"
            >
                Purchase Return Cancelled
            </h2>

            <p
                class="mt-2 whitespace-pre-line text-sm text-red-700 dark:text-red-300"
            >
                {{
                    purchaseReturn
                        .cancellation_reason
                    ?? 'No cancellation reason was recorded.'
                }}
            </p>

            <p
                class="mt-3 text-xs text-red-600 dark:text-red-400"
            >
                Cancelled by
                {{
                    purchaseReturn
                        .cancelled_by?.name
                    ?? 'Unknown user'
                }}
                on
                {{
                    formatDateTime(
                        purchaseReturn
                            .cancelled_at,
                    )
                }}
            </p>
        </section>

        <section
            v-if="
                purchaseReturn.status
                === 'reversed'
            "
            class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-500/30 dark:bg-red-500/10"
        >
            <h2
                class="font-semibold text-red-900 dark:text-red-200"
            >
                Purchase Return Reversed
            </h2>

            <p
                class="mt-2 whitespace-pre-line text-sm text-red-700 dark:text-red-300"
            >
                {{
                    purchaseReturn
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
                            purchaseReturn
                                .reversal_posting_date,
                        )
                    }}
                </span>

                <span>
                    Reversed by
                    {{
                        purchaseReturn
                            .reversed_by?.name
                        ?? 'Unknown user'
                    }}
                    on
                    {{
                        formatDateTime(
                            purchaseReturn
                                .reversed_at,
                        )
                    }}
                </span>
            </div>
        </section>

        <div
            class="grid grid-cols-1 gap-6 xl:grid-cols-3"
        >
            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm xl:col-span-2 dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Return Information
                </h2>

                <dl
                    class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Purchase Return Number
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                purchaseReturn
                                    .return_number
                                ?? 'Pending submission'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Return Date
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    purchaseReturn
                                        .return_date,
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
                                    purchaseReturn
                                        .posting_date,
                                )
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
                                        purchaseReturn
                                            .purchase_order_id,
                                    )
                                "
                                class="text-sm font-medium text-brand-600 transition hover:text-brand-700 dark:text-brand-400"
                            >
                                {{
                                    purchaseReturn
                                        .purchase_order_number
                                    ?? `Purchase Order #${purchaseReturn.purchase_order_id}`
                                }}
                            </Link>
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Goods Receipt
                        </dt>

                        <dd class="mt-1">
                            <Link
                                :href="
                                    route(
                                        'goods-receipts.show',
                                        purchaseReturn
                                            .goods_receipt_id,
                                    )
                                "
                                class="text-sm font-medium text-brand-600 transition hover:text-brand-700 dark:text-brand-400"
                            >
                                {{
                                    purchaseReturn
                                        .goods_receipt_number
                                    ?? `Goods Receipt #${purchaseReturn.goods_receipt_id}`
                                }}
                            </Link>
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Supplier Invoice
                        </dt>

                        <dd class="mt-1">
                            <Link
                                v-if="
                                    purchaseReturn
                                        .supplier_invoice_id
                                    !== null
                                "
                                :href="
                                    route(
                                        'supplier-invoices.show',
                                        purchaseReturn
                                            .supplier_invoice_id,
                                    )
                                "
                                class="text-sm font-medium text-indigo-600 transition hover:text-indigo-700 dark:text-indigo-400"
                            >
                                {{
                                    purchaseReturn
                                        .supplier_invoice_number
                                    ?? `Supplier Invoice #${purchaseReturn.supplier_invoice_id}`
                                }}
                            </Link>

                            <span
                                v-else
                                class="text-sm text-gray-500"
                            >
                                Not linked
                            </span>
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
                                purchaseReturn
                                    .supplier.name
                            }}
                            ({{
                                purchaseReturn
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
                                purchaseReturn
                                    .branch.name
                            }}
                            ({{
                                purchaseReturn
                                    .branch.code
                            }})
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Warehouse
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            <template
                                v-if="
                                    purchaseReturn
                                        .warehouse
                                "
                            >
                                {{
                                    purchaseReturn
                                        .warehouse
                                        ?.name
                                }}
                                ({{
                                    purchaseReturn
                                        .warehouse
                                        ?.code
                                }})
                            </template>

                            <template v-else>
                                No warehouse
                            </template>
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
                                purchaseReturn
                                    .supplier_reference
                                ?? '—'
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
                                purchaseReturn
                                    .revision
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
                                purchaseReturn
                                    .document_number_allocation_id
                                ?? 'Not allocated'
                            }}
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
                    Return Totals
                </h2>

                <div class="mt-5 space-y-4">
                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Return Quantity
                        </span>

                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                formatQuantity(
                                    purchaseReturn
                                        .total_return_quantity,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <div>
                            <p class="text-gray-500">
                                Supplier Value
                            </p>

                            <p
                                class="mt-1 text-xs text-gray-400"
                            >
                                Original receipt cost
                            </p>
                        </div>

                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    purchaseReturn
                                        .total_supplier_value,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <div>
                            <p class="text-gray-500">
                                Inventory Value
                            </p>

                            <p
                                class="mt-1 text-xs text-gray-400"
                            >
                                Weighted-average cost
                            </p>
                        </div>

                        <span
                            class="font-semibold text-gray-900 dark:text-white"
                        >
                            <template
                                v-if="
                                    inventoryValueAvailable
                                "
                            >
                                {{
                                    formatAmount(
                                        purchaseReturn
                                            .total_inventory_value,
                                    )
                                }}
                            </template>

                            <template v-else>
                                Pending posting
                            </template>
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
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    Cost Variance
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Supplier value minus
                                    inventory value
                                </p>
                            </div>

                            <p
                                v-if="
                                    inventoryValueAvailable
                                "
                                class="text-xl font-bold"
                                :class="
                                    varianceClasses(
                                        purchaseReturn
                                            .total_cost_variance,
                                    )
                                "
                            >
                                {{
                                    formatAmount(
                                        purchaseReturn
                                            .total_cost_variance,
                                    )
                                }}
                            </p>

                            <p
                                v-else
                                class="text-sm font-medium text-gray-500"
                            >
                                Pending posting
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section
            v-if="
                inventoryValueAvailable
                && hasCostVariance
            "
            class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-500/30 dark:bg-amber-500/10"
        >
            <h2
                class="font-semibold text-amber-900 dark:text-amber-200"
            >
                Purchase Return Cost Variance
            </h2>

            <p
                class="mt-2 text-sm text-amber-700 dark:text-amber-300"
            >
                The supplier commercial value differs from
                the inventory value removed using the current
                weighted-average cost. This difference is
                retained for the future supplier debit-note
                and accounting workflow.
            </p>

            <p
                class="mt-3 text-lg font-bold"
                :class="
                    varianceClasses(
                        purchaseReturn
                            .total_cost_variance,
                    )
                "
            >
                {{
                    formatAmount(
                        purchaseReturn
                            .total_cost_variance,
                    )
                }}
            </p>
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
                    Return Lines
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Quantities are returned against their
                    original Goods Receipt lines. Supplier
                    value uses the receipt cost, while
                    inventory value is assigned during
                    posting.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table
                    class="w-full min-w-[1800px]"
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
                                Accepted
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Previously Returned
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Previously Reserved
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Returnable Snapshot
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Return Quantity
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Supplier Unit Cost
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Supplier Value
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Inventory Unit Cost
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Inventory Value
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Variance
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <template
                            v-for="line in purchaseReturn.lines"
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
                                        ·
                                        {{
                                            line.product_type
                                        }}
                                    </p>
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatQuantity(
                                            line.accepted_quantity_snapshot,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatQuantity(
                                            line.previously_returned_quantity_snapshot,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatQuantity(
                                            line.previously_reserved_quantity_snapshot,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatQuantity(
                                            line.returnable_quantity_snapshot,
                                        )
                                    }}
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
                                            line.supplier_unit_cost,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatAmount(
                                            line.supplier_total_cost,
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    <template
                                        v-if="
                                            inventoryValueAvailable
                                        "
                                    >
                                        {{
                                            formatAmount(
                                                line.inventory_unit_cost,
                                            )
                                        }}
                                    </template>

                                    <template v-else>
                                        —
                                    </template>
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    <template
                                        v-if="
                                            inventoryValueAvailable
                                        "
                                    >
                                        {{
                                            formatAmount(
                                                line.inventory_total_cost,
                                            )
                                        }}
                                    </template>

                                    <template v-else>
                                        —
                                    </template>
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm font-semibold"
                                    :class="
                                        inventoryValueAvailable
                                            ? varianceClasses(
                                                line.cost_variance_amount,
                                            )
                                            : 'text-gray-400'
                                    "
                                >
                                    <template
                                        v-if="
                                            inventoryValueAvailable
                                        "
                                    >
                                        {{
                                            formatAmount(
                                                line.cost_variance_amount,
                                            )
                                        }}
                                    </template>

                                    <template v-else>
                                        —
                                    </template>
                                </td>
                            </tr>

                            <tr
                                class="border-b border-gray-200 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-950/30"
                            >
                                <td
                                    colspan="12"
                                    class="px-5 py-4"
                                >
                                    <div
                                        class="grid grid-cols-1 gap-5 lg:grid-cols-3"
                                    >
                                        <div>
                                            <h3
                                                class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                            >
                                                Batch and Serial Snapshot
                                            </h3>

                                            <p
                                                class="mt-2 text-sm text-gray-900 dark:text-white"
                                            >
                                                Batch:
                                                {{
                                                    line.batch_number
                                                    ?? '—'
                                                }}
                                            </p>

                                            <p
                                                class="mt-1 text-sm text-gray-900 dark:text-white"
                                            >
                                                Serial count:
                                                {{
                                                    line.serial_numbers
                                                        .length
                                                }}
                                            </p>

                                            <div
                                                v-if="
                                                    line.serial_numbers
                                                        .length
                                                    > 0
                                                "
                                                class="mt-3 flex flex-wrap gap-2"
                                            >
                                                <span
                                                    v-for="serialNumber in line.serial_numbers"
                                                    :key="serialNumber"
                                                    class="rounded-md bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                                >
                                                    {{
                                                        serialNumber
                                                    }}
                                                </span>
                                            </div>
                                        </div>

                                        <div>
                                            <h3
                                                class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                            >
                                                Line Return Reason
                                            </h3>

                                            <p
                                                class="mt-2 whitespace-pre-line text-sm text-gray-900 dark:text-white"
                                            >
                                                {{
                                                    line.return_reason
                                                    ?? purchaseReturn
                                                        .return_reason
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

                                            <p
                                                v-if="
                                                    inventoryValueAvailable
                                                    && lineHasVariance(
                                                        line,
                                                    )
                                                "
                                                class="mt-3 text-xs font-medium"
                                                :class="
                                                    varianceClasses(
                                                        line.cost_variance_amount,
                                                    )
                                                "
                                            >
                                                This line
                                                contains an
                                                inventory cost
                                                variance.
                                            </p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>

                        <tr
                            v-if="
                                purchaseReturn.lines
                                    .length === 0
                            "
                        >
                            <td
                                colspan="12"
                                class="px-5 py-14 text-center text-sm text-gray-500"
                            >
                                No Purchase Return lines were
                                found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            v-if="
                purchaseReturn.stock_ledger_entries
                    .length > 0
            "
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="border-b border-gray-200 p-5 dark:border-gray-800"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Inventory Ledger Entries
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Immutable stock movements created when
                    the Purchase Return was posted or
                    reversed.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table
                    class="w-full min-w-[1450px]"
                >
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-400"
                        >
                            <th class="px-5 py-3.5">
                                Date
                            </th>

                            <th class="px-5 py-3.5">
                                Movement
                            </th>

                            <th class="px-5 py-3.5">
                                Product
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Quantity In
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Quantity Out
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Unit Cost
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Total Cost
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Balance Quantity
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Balance Value
                            </th>

                            <th class="px-5 py-3.5">
                                Posted By
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="entry in purchaseReturn.stock_ledger_entries"
                            :key="entry.id"
                            class="border-b border-gray-100 last:border-b-0 dark:border-gray-800"
                        >
                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatDateTime(
                                        entry.occurred_at,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        entry.movement_type
                                        === 'purchase_return'
                                            ? 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400'
                                            : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400'
                                    "
                                >
                                    {{
                                        movementLabel(
                                            entry.movement_type,
                                        )
                                    }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        entry.product_name
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        entry.product_sku
                                    }}
                                    ·
                                    {{ entry.unit_code }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-emerald-600 dark:text-emerald-400"
                            >
                                {{
                                    formatQuantity(
                                        entry.quantity_in,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-red-600 dark:text-red-400"
                            >
                                {{
                                    formatQuantity(
                                        entry.quantity_out,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatAmount(
                                        entry.unit_cost,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    formatAmount(
                                        entry.total_cost,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatQuantity(
                                        entry.balance_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatAmount(
                                        entry.balance_value,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    entry.created_by
                                        ?.name
                                    ?? 'Unknown user'
                                }}
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
                    Return Reason
                </h2>

                <p
                    class="mt-4 whitespace-pre-line text-sm text-gray-900 dark:text-white"
                >
                    {{
                        purchaseReturn
                            .return_reason
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
                        purchaseReturn.notes
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
                            purchaseReturn
                                .created_by?.name
                            ?? 'Unknown user'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-gray-500"
                    >
                        {{
                            formatDateTime(
                                purchaseReturn
                                    .created_at,
                            )
                        }}
                    </p>
                </div>

                <div
                    v-if="
                        purchaseReturn.submitted_at
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
                            purchaseReturn
                                .submitted_by?.name
                            ?? 'Unknown user'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-blue-700 dark:text-blue-400"
                    >
                        {{
                            formatDateTime(
                                purchaseReturn
                                    .submitted_at,
                            )
                        }}
                    </p>
                </div>

                <div
                    v-if="
                        purchaseReturn.approved_at
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
                            purchaseReturn
                                .approved_by?.name
                            ?? 'Unknown user'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-indigo-700 dark:text-indigo-400"
                    >
                        {{
                            formatDateTime(
                                purchaseReturn
                                    .approved_at,
                            )
                        }}
                    </p>
                </div>

                <div
                    v-if="purchaseReturn.posted_at"
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
                            purchaseReturn
                                .posted_by?.name
                            ?? 'Unknown user'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-emerald-700 dark:text-emerald-400"
                    >
                        {{
                            formatDateTime(
                                purchaseReturn
                                    .posted_at,
                            )
                        }}
                    </p>
                </div>

                <div
                    v-if="
                        purchaseReturn.cancelled_at
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
                            purchaseReturn
                                .cancelled_by?.name
                            ?? 'Unknown user'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-red-700 dark:text-red-400"
                    >
                        {{
                            formatDateTime(
                                purchaseReturn
                                    .cancelled_at,
                            )
                        }}
                    </p>
                </div>

                <div
                    v-if="
                        purchaseReturn.reversed_at
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
                            purchaseReturn
                                .reversed_by?.name
                            ?? 'Unknown user'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-red-700 dark:text-red-400"
                    >
                        {{
                            formatDateTime(
                                purchaseReturn
                                    .reversed_at,
                            )
                        }}
                    </p>
                </div>
            </div>
        </section>
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
                Cancel Purchase Return
            </h2>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Cancelling an approved return releases its
                reserved Goods Receipt quantities. A
                cancelled return cannot be reopened.
            </p>

            <form
                class="mt-5"
                @submit.prevent="
                    cancelPurchaseReturn
                "
            >
                <label
                    for="purchase-return-cancellation-reason"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Cancellation Reason
                    <span class="text-red-500">
                        *
                    </span>
                </label>

                <textarea
                    id="purchase-return-cancellation-reason"
                    v-model="
                        cancellationForm
                            .cancellation_reason
                    "
                    rows="5"
                    maxlength="500"
                    placeholder="Explain why this Purchase Return is being cancelled"
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
                        Keep Purchase Return
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
        @click.self="closeReversalModal"
    >
        <div
            class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900"
        >
            <h2
                class="text-lg font-semibold text-gray-900 dark:text-white"
            >
                Reverse Purchase Return
            </h2>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Reversal restores the exact inventory
                quantity and value removed by the original
                Purchase Return posting. The reversal is
                blocked when a later stock movement exists
                for any affected product.
            </p>

            <form
                class="mt-5 space-y-5"
                @submit.prevent="
                    reversePurchaseReturn
                "
            >
                <div>
                    <label
                        for="purchase-return-reversal-date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Reversal Posting Date
                        <span class="text-red-500">
                            *
                        </span>
                    </label>

                    <input
                        id="purchase-return-reversal-date"
                        v-model="
                            reversalForm
                                .reversal_posting_date
                        "
                        type="date"
                        :min="
                            purchaseReturn
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
                        for="purchase-return-reversal-reason"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Reversal Reason
                        <span class="text-red-500">
                            *
                        </span>
                    </label>

                    <textarea
                        id="purchase-return-reversal-reason"
                        v-model="
                            reversalForm
                                .reversal_reason
                        "
                        rows="5"
                        maxlength="500"
                        placeholder="Explain why this Purchase Return is being reversed"
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
                        @click="closeReversalModal"
                    >
                        Keep Purchase Return
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