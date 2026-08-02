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
    PurchaseOrderLine,
    PurchaseOrderShowProps,
    PurchaseOrderStatus,
} from '@/Types/purchase-order';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<PurchaseOrderShowProps>();

const actionInProgress = ref<string | null>(
    null,
);

const showCancellationModal = ref(false);

const cancellationForm = useForm({
    cancellation_reason: '',
});

const documentTitle = computed((): string => {
    return props.purchaseOrder.document_number
        ?? `Draft #${props.purchaseOrder.id}`;
});

const statusClasses: Record<
    PurchaseOrderStatus,
    string
> = {
    draft:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    submitted:
        'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',

    approved:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    partially_received:
        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400',

    received:
        'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-400',

    closed:
        'bg-purple-50 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400',

    cancelled:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const statusClass = (
    status: PurchaseOrderStatus,
): string => {
    return statusClasses[status];
};

const formatDate = (
    value: string | null,
): string => {
    if (value === null || value === '') {
        return '—';
    }

    const dateParts = value.split('-');

    if (dateParts.length !== 3) {
        return value;
    }

    const year = Number(dateParts[0]);
    const month = Number(dateParts[1]);
    const day = Number(dateParts[2]);

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
            year,
            month - 1,
            day,
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

const receivedPercentage = (
    line: PurchaseOrderLine,
): number => {
    const ordered = Number.parseFloat(
        line.ordered_quantity,
    );

    const received = Number.parseFloat(
        line.received_quantity,
    );

    if (
        !Number.isFinite(ordered)
        || !Number.isFinite(received)
        || ordered <= 0
    ) {
        return 0;
    }

    return Math.min(
        Math.max(
            (received / ordered) * 100,
            0,
        ),
        100,
    );
};

const hasWorkflowActions = computed(
    (): boolean => {
        const permissions =
            props.purchaseOrder.can;

        return permissions.submit
            || permissions.return_to_draft
            || permissions.approve
            || permissions.cancel;
    },
);

const runWorkflowAction = (
    action: 'submit'
        | 'return-to-draft'
        | 'approve',
    confirmationMessage: string,
): void => {
    if (!window.confirm(confirmationMessage)) {
        return;
    }

    actionInProgress.value = action;

    router.post(
        route(
            `purchase-orders.${action}`,
            props.purchaseOrder.id,
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

const submitPurchaseOrder = (): void => {
    runWorkflowAction(
        'submit',
        'Submit this Purchase Order for approval? It will receive a permanent document number and can no longer be edited unless it is returned to draft.',
    );
};

const returnPurchaseOrderToDraft = (): void => {
    runWorkflowAction(
        'return-to-draft',
        'Return this Purchase Order to draft? It will become editable again.',
    );
};

const approvePurchaseOrder = (): void => {
    runWorkflowAction(
        'approve',
        'Approve this Purchase Order? The approved document will become available for receiving.',
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

    showCancellationModal.value = false;

    cancellationForm.reset();
    cancellationForm.clearErrors();
};

const cancelPurchaseOrder = (): void => {
    cancellationForm.post(
        route(
            'purchase-orders.cancel',
            props.purchaseOrder.id,
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                showCancellationModal.value = false;
                cancellationForm.reset();
            },
        },
    );
};

const deletePurchaseOrder = (): void => {
    const confirmed = window.confirm(
        `Delete ${documentTitle.value}? This draft will be removed from the active Purchase Order list.`,
    );

    if (!confirmed) {
        return;
    }

    actionInProgress.value = 'delete';

    router.delete(
        route(
            'purchase-orders.destroy',
            props.purchaseOrder.id,
        ),
        {
            preserveScroll: true,

            onFinish: () => {
                actionInProgress.value = null;
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
                                'purchase-orders.index',
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        Purchase Orders
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
                            statusClass(
                                purchaseOrder.status,
                            )
                        "
                    >
                        {{
                            purchaseOrder.status_label
                        }}
                    </span>
                </div>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Supplier:
                    <span
                        class="font-medium text-gray-700 dark:text-gray-300"
                    >
                        {{
                            purchaseOrder.supplier_name
                        }}
                        ({{
                            purchaseOrder.supplier_code
                        }})
                    </span>
                </p>

                <p
                    class="mt-1 text-xs text-gray-400 dark:text-gray-500"
                >
                    Revision
                    {{ purchaseOrder.revision }}
                </p>
            </div>

            <div
                class="flex flex-wrap items-center gap-2"
            >
                <Link
                    :href="
                        route(
                            'purchase-orders.index',
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back
                </Link>

                <Link
                    v-if="purchaseOrder.can.update"
                    :href="
                        route(
                            'purchase-orders.edit',
                            purchaseOrder.id,
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Edit
                </Link>

                <Link
    v-if="purchaseOrder.can.receive_goods"
    :href="
        route(
            'goods-receipts.create',
            {
                purchase_order_id:
                    purchaseOrder.id,
            },
        )
    "
    class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
>
    Receive Goods
</Link>

<Link
    v-if="
        purchaseOrder
            .can_create_supplier_invoice
    "
    :href="
        route(
            'supplier-invoices.create',
            {
                purchase_order_id:
                    purchaseOrder.id,
            },
        )
    "
    class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700"
>
    Create Supplier Invoice
</Link>

<Link
    v-if="
        purchaseOrder
            .can_view_purchase_returns
    "
    :href="
        route(
            'purchase-returns.index',
            {
                purchase_order_id:
                    purchaseOrder.id,
            },
        )
    "
    class="inline-flex items-center justify-center rounded-lg border border-amber-300 bg-white px-4 py-2.5 text-sm font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-500/40 dark:bg-gray-900 dark:text-amber-400 dark:hover:bg-amber-500/10"
>
    Purchase Returns
</Link>

<Link
    v-if="
        purchaseOrder
            .can_view_supplier_debit_notes
    "
    :href="
        route(
            'supplier-debit-notes.index',
            {
                purchase_order_id:
                    purchaseOrder.id,
            },
        )
    "
    class="inline-flex items-center justify-center rounded-lg border border-violet-300 bg-white px-4 py-2.5 text-sm font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:bg-gray-900 dark:text-violet-400 dark:hover:bg-violet-500/10"
>
    Supplier Debit Notes
</Link>

                <button
                    v-if="purchaseOrder.can.delete"
                    type="button"
                    :disabled="
                        actionInProgress === 'delete'
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-white px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/30 dark:bg-gray-900 dark:text-red-400 dark:hover:bg-red-500/10"
                    @click="deletePurchaseOrder"
                >
                    {{
                        actionInProgress === 'delete'
                            ? 'Deleting...'
                            : 'Delete'
                    }}
                </button>
            </div>
        </div>

        <div
            v-if="hasWorkflowActions"
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <h2
                        class="text-base font-semibold text-gray-900 dark:text-white"
                    >
                        Workflow Actions
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Move this Purchase Order through
                        the purchasing approval process.
                    </p>
                </div>

                <div
                    class="flex flex-wrap items-center gap-2"
                >
                    <button
                        v-if="purchaseOrder.can.return_to_draft"
                        type="button"
                        :disabled="
                            actionInProgress !== null
                        "
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            returnPurchaseOrderToDraft
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
                        v-if="purchaseOrder.can.submit"
                        type="button"
                        :disabled="
                            actionInProgress !== null
                        "
                        class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="submitPurchaseOrder"
                    >
                        {{
                            actionInProgress === 'submit'
                                ? 'Submitting...'
                                : 'Submit for Approval'
                        }}
                    </button>

                    <button
                        v-if="purchaseOrder.can.approve"
                        type="button"
                        :disabled="
                            actionInProgress !== null
                        "
                        class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="approvePurchaseOrder"
                    >
                        {{
                            actionInProgress
                                === 'approve'
                                ? 'Approving...'
                                : 'Approve'
                        }}
                    </button>

                    <button
                        v-if="purchaseOrder.can.cancel"
                        type="button"
                        :disabled="
                            actionInProgress !== null
                        "
                        class="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="openCancellationModal"
                    >
                        Cancel Purchase Order
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="
                purchaseOrder.status
                    === 'cancelled'
            "
            class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-500/30 dark:bg-red-500/10"
        >
            <h2
                class="text-base font-semibold text-red-800 dark:text-red-300"
            >
                Purchase Order Cancelled
            </h2>

            <p
                class="mt-2 whitespace-pre-line text-sm text-red-700 dark:text-red-400"
            >
                {{
                    purchaseOrder.cancellation_reason
                        ?? 'No cancellation reason was recorded.'
                }}
            </p>

            <p
                class="mt-3 text-xs text-red-600 dark:text-red-400"
            >
                Cancelled by
                {{
                    purchaseOrder.cancelled_by
                        ?.name
                        ?? 'Unknown user'
                }}
                on
                {{
                    formatDateTime(
                        purchaseOrder.cancelled_at,
                    )
                }}
            </p>
        </div>

        <div
            class="grid grid-cols-1 gap-6 xl:grid-cols-3"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-2"
            >
                <h2
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Purchase Order Information
                </h2>

                <dl
                    class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2"
                >
                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Document Number
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{ documentTitle }}
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
                                purchaseOrder.supplier_reference
                                    ?? '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Order Date
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    purchaseOrder.order_date,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Expected Delivery
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    purchaseOrder.expected_delivery_date,
                                )
                            }}
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
                                purchaseOrder.branch.name
                            }}
                            ({{
                                purchaseOrder.branch.code
                            }})
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Receiving Warehouse
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            <template
                                v-if="
                                    purchaseOrder.warehouse
                                "
                            >
                                {{
                                    purchaseOrder.warehouse
                                        .name
                                }}
                                ({{
                                    purchaseOrder.warehouse
                                        .code
                                }})
                            </template>

                            <template v-else>
                                —
                            </template>
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
                                purchaseOrder.currency_code
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
                                    purchaseOrder.exchange_rate,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Payment Terms
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                purchaseOrder.payment_terms_days
                            }}
                            days
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
                                purchaseOrder.revision
                            }}
                        </dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Delivery Address
                        </dt>

                        <dd
                            class="mt-1 whitespace-pre-line text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                purchaseOrder.delivery_address
                                    ?? '—'
                            }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Supplier Snapshot
                </h2>

                <dl class="space-y-4">
                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Supplier
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                purchaseOrder.supplier_name
                            }}
                        </dd>

                        <dd
                            class="mt-0.5 text-xs text-gray-500"
                        >
                            {{
                                purchaseOrder.supplier_code
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Contact Person
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                purchaseOrder.supplier_contact_person
                                    ?? '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Email
                        </dt>

                        <dd
                            class="mt-1 break-words text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                purchaseOrder.supplier_email
                                    ?? '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Phone
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                purchaseOrder.supplier_phone
                                    ?? '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Tax Number
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                purchaseOrder.supplier_tax_number
                                    ?? '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Address
                        </dt>

                        <dd
                            class="mt-1 whitespace-pre-line text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                purchaseOrder.supplier_address
                                    ?? '—'
                            }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="border-b border-gray-200 p-5 dark:border-gray-800"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Order Lines
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Products and services included in
                    this Purchase Order.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table
                    class="min-w-[1200px] w-full"
                >
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-400"
                        >
                            <th class="w-14 px-5 py-3.5">
                                #
                            </th>

                            <th class="min-w-64 px-5 py-3.5">
                                Product
                            </th>

                            <th class="min-w-48 px-5 py-3.5">
                                Description
                            </th>

                            <th class="px-5 py-3.5">
                                Unit
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Ordered
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Received
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Unit Price
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
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="line in purchaseOrder.lines"
                            :key="line.id"
                            class="border-b border-gray-100 align-top last:border-b-0 dark:border-gray-800"
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
                                    {{ line.product_name }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{ line.product_sku }}
                                    ·
                                    {{
                                        line.product_type
                                            .replace(
                                                /_/g,
                                                ' ',
                                            )
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    line.description
                                        ?? '—'
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ line.unit_name }}
                                <span
                                    class="text-xs text-gray-500"
                                >
                                    ({{ line.unit_code }})
                                </span>
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-900 dark:text-white"
                            >
                                {{
                                    formatQuantity(
                                        line.ordered_quantity,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <div
                                    class="flex justify-end"
                                >
                                    <div
                                        class="min-w-28 text-right"
                                    >
                                        <p
                                            class="text-sm text-gray-900 dark:text-white"
                                        >
                                            {{
                                                formatQuantity(
                                                    line.received_quantity,
                                                )
                                            }}
                                        </p>

                                        <div
                                            class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
                                        >
                                            <div
                                                class="h-full rounded-full bg-emerald-500"
                                                :style="{
                                                    width: `${receivedPercentage(
                                                        line,
                                                    )}%`,
                                                }"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-900 dark:text-white"
                            >
                                {{
                                    formatAmount(
                                        line.unit_price,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-900 dark:text-white"
                            >
                                {{
                                    formatAmount(
                                        line.discount_amount,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-900 dark:text-white"
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
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_380px]"
        >
            <div
                class="space-y-6"
            >
                <div
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                >
                    <h2
                        class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Additional Information
                    </h2>

                    <div class="space-y-5">
                        <div>
                            <h3
                                class="text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                Terms and Conditions
                            </h3>

                            <p
                                class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    purchaseOrder.terms_and_conditions
                                        ?? '—'
                                }}
                            </p>
                        </div>

                        <div
                            class="border-t border-gray-100 pt-5 dark:border-gray-800"
                        >
                            <h3
                                class="text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                Internal Notes
                            </h3>

                            <p
                                class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    purchaseOrder.notes
                                        ?? '—'
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                >
                    <h2
                        class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Workflow History
                    </h2>

                    <div class="space-y-5">
                        <div
                            class="flex gap-4"
                        >
                            <div
                                class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-gray-400"
                            />

                            <div>
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    Created
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500"
                                >
                                    {{
                                        purchaseOrder.created_by
                                            ?.name
                                            ?? 'Unknown user'
                                    }}
                                    ·
                                    {{
                                        formatDateTime(
                                            purchaseOrder.created_at,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="
                                purchaseOrder.submitted_at
                            "
                            class="flex gap-4"
                        >
                            <div
                                class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-blue-500"
                            />

                            <div>
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    Submitted
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500"
                                >
                                    {{
                                        purchaseOrder.submitted_by
                                            ?.name
                                            ?? 'Unknown user'
                                    }}
                                    ·
                                    {{
                                        formatDateTime(
                                            purchaseOrder.submitted_at,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="
                                purchaseOrder.approved_at
                            "
                            class="flex gap-4"
                        >
                            <div
                                class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500"
                            />

                            <div>
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    Approved
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500"
                                >
                                    {{
                                        purchaseOrder.approved_by
                                            ?.name
                                            ?? 'Unknown user'
                                    }}
                                    ·
                                    {{
                                        formatDateTime(
                                            purchaseOrder.approved_at,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="
                                purchaseOrder.cancelled_at
                            "
                            class="flex gap-4"
                        >
                            <div
                                class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-red-500"
                            />

                            <div>
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    Cancelled
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500"
                                >
                                    {{
                                        purchaseOrder.cancelled_by
                                            ?.name
                                            ?? 'Unknown user'
                                    }}
                                    ·
                                    {{
                                        formatDateTime(
                                            purchaseOrder.cancelled_at,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="h-fit rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Financial Summary
                </h2>

                <div class="space-y-4">
                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Subtotal
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                purchaseOrder.currency_code
                            }}
                            {{
                                formatAmount(
                                    purchaseOrder.subtotal,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Line Discounts
                        </span>

                        <span
                            class="font-medium text-red-600 dark:text-red-400"
                        >
                            −
                            {{
                                purchaseOrder.currency_code
                            }}
                            {{
                                formatAmount(
                                    purchaseOrder.discount_amount,
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
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                purchaseOrder.currency_code
                            }}
                            {{
                                formatAmount(
                                    purchaseOrder.tax_amount,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Shipping
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                purchaseOrder.currency_code
                            }}
                            {{
                                formatAmount(
                                    purchaseOrder.shipping_amount,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Other Charges
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                purchaseOrder.currency_code
                            }}
                            {{
                                formatAmount(
                                    purchaseOrder.other_charges,
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
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                Grand Total
                            </span>

                            <span
                                class="text-lg font-bold text-gray-900 dark:text-white"
                            >
                                {{
                                    purchaseOrder.currency_code
                                }}
                                {{
                                    formatAmount(
                                        purchaseOrder.total_amount,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
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
            <div>
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Cancel Purchase Order
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Provide a clear reason for
                    cancelling {{ documentTitle }}.
                </p>
            </div>

            <form
                class="mt-5"
                @submit.prevent="cancelPurchaseOrder"
            >
                <label
                    for="purchase-order-cancellation-reason"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Cancellation Reason
                    <span class="text-red-500">*</span>
                </label>

                <textarea
                    id="purchase-order-cancellation-reason"
                    v-model="
                        cancellationForm.cancellation_reason
                    "
                    rows="5"
                    maxlength="500"
                    autofocus
                    placeholder="Explain why this Purchase Order is being cancelled"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                />

                <div
                    class="mt-1 flex items-start justify-between gap-4"
                >
                    <p
                        v-if="
                            cancellationForm.errors
                                .cancellation_reason
                        "
                        class="text-sm text-red-600"
                    >
                        {{
                            cancellationForm.errors
                                .cancellation_reason
                        }}
                    </p>

                    <span
                        class="ml-auto text-xs text-gray-400"
                    >
                        {{
                            cancellationForm
                                .cancellation_reason
                                .length
                        }}/500
                    </span>
                </div>

                <div
                    class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                >
                    <button
                        type="button"
                        :disabled="
                            cancellationForm.processing
                        "
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="closeCancellationModal"
                    >
                        Keep Purchase Order
                    </button>

                    <button
                        type="submit"
                        :disabled="
                            cancellationForm.processing
                        "
                        class="inline-flex items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{
                            cancellationForm.processing
                                ? 'Cancelling...'
                                : 'Confirm Cancellation'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>