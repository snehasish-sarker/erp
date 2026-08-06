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
    SalesOrderLine,
    SalesOrderShowProps,
    SalesOrderStatus,
} from '@/Types/sales-order';

defineOptions({
    layout: ErpLayout,
});

const props =
    defineProps<SalesOrderShowProps>();

const actionInProgress =
    ref<string | null>(null);

const showCancellationModal =
    ref(false);

const cancellationForm = useForm({
    cancellation_reason: '',
});

const orderTitle = computed(
    (): string => {
        return props.salesOrder
            .document_number
            ?? `Draft #${props.salesOrder.id}`;
    },
);

const hasWorkflowActions = computed(
    (): boolean => {
        const permissions =
            props.salesOrder.can;

        return permissions.submit
            || permissions.return_to_draft
            || permissions.approve
            || permissions.cancel;
    },
);

const canOpenAllocation = computed(
    (): boolean => {
        return props.salesOrder.can.allocate;
    },
);

const statusClasses = (
    status: SalesOrderStatus,
): string => {
    const classes:
        Record<SalesOrderStatus, string> = {
            draft:
                'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

            submitted:
                'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',

            approved:
                'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-300',

            partially_allocated:
                'bg-warning-50 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300',

            allocated:
                'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',

            partially_dispatched:
                'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',

            dispatched:
                'bg-cyan-50 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',

            partially_invoiced:
                'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',

            invoiced:
                'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300',

            closed:
                'bg-success-100 text-success-800 dark:bg-success-900/50 dark:text-success-200',

            cancelled:
                'bg-error-50 text-error-700 dark:bg-error-900/30 dark:text-error-300',
        };

    return classes[status];
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

    const date = new Date(
        `${value}T00:00:00`,
    );

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
        },
    ).format(date);
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

const formatAmount = (
    value: string,
): string => {
    const amount =
        Number.parseFloat(value);

    if (!Number.isFinite(amount)) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(amount);
};

const formatQuantity = (
    value: string,
): string => {
    const quantity =
        Number.parseFloat(value);

    if (!Number.isFinite(quantity)) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        },
    ).format(quantity);
};

const percentage = (
    completed: string,
    ordered: string,
): number => {
    const completedValue =
        Number.parseFloat(completed);

    const orderedValue =
        Number.parseFloat(ordered);

    if (
        !Number.isFinite(
            completedValue,
        )
        || !Number.isFinite(
            orderedValue,
        )
        || orderedValue <= 0
    ) {
        return 0;
    }

    return Math.min(
        Math.max(
            (
                completedValue
                / orderedValue
            ) * 100,
            0,
        ),
        100,
    );
};

const lineProgressLabel = (
    line: SalesOrderLine,
): string => {
    if (line.is_fully_invoiced) {
        return 'Fully invoiced';
    }

    if (line.is_fully_dispatched) {
        return 'Fully dispatched';
    }

    if (line.is_fully_allocated) {
        return 'Fully allocated';
    }

    if (
        Number.parseFloat(
            line.allocated_quantity,
        ) > 0
    ) {
        return 'Partially allocated';
    }

    return 'Pending allocation';
};

const workflowAction = (
    action:
        | 'submit'
        | 'return-to-draft'
        | 'approve',

    confirmation: string,
): void => {
    if (
        !window.confirm(
            confirmation,
        )
    ) {
        return;
    }

    actionInProgress.value = action;

    router.post(
        route(
            `sales-orders.${action}`,
            props.salesOrder.id,
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

const submitSalesOrder = (): void => {
    workflowAction(
        'submit',
        'Submit this Sales Order for approval? A permanent document number will be allocated and the order will be locked from editing.',
    );
};

const returnToDraft = (): void => {
    workflowAction(
        'return-to-draft',
        'Return this Sales Order to draft? It will become editable again and its revision will increase.',
    );
};

const approveSalesOrder = (): void => {
    workflowAction(
        'approve',
        'Approve this Sales Order? Current customer, product, branch, warehouse, and unit configuration will be revalidated.',
    );
};

const deleteSalesOrder = (): void => {
    if (
        !window.confirm(
            `Delete ${orderTitle.value}? This draft will be soft-deleted.`,
        )
    ) {
        return;
    }

    actionInProgress.value = 'delete';

    router.delete(
        route(
            'sales-orders.destroy',
            props.salesOrder.id,
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

        showCancellationModal.value =
            false;

        cancellationForm.reset();
        cancellationForm.clearErrors();
    };

const cancelSalesOrder = (): void => {
    cancellationForm.post(
        route(
            'sales-orders.cancel',
            props.salesOrder.id,
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                showCancellationModal.value =
                    false;

                cancellationForm.reset();
            },
        },
    );
};
</script>

<template>
    <Head :title="orderTitle" />

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
                                'sales-orders.index',
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        Sales Orders
                    </Link>

                    <span>/</span>

                    <span
                        class="text-gray-700 dark:text-gray-300"
                    >
                        {{ orderTitle }}
                    </span>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3"
                >
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        {{ orderTitle }}
                    </h1>

                    <span
                        :class="[
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                            statusClasses(
                                salesOrder.status,
                            ),
                        ]"
                    >
                        {{
                            salesOrder.status_label
                        }}
                    </span>
                </div>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Customer
                    {{ salesOrder.customer_name }}
                    · Revision
                    {{ salesOrder.revision }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="
                        route(
                            'sales-orders.index',
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back
                </Link>

                <Link
                    v-if="canOpenAllocation"
                    :href="
                        route(
                            'sales-orders.allocation.show',
                            salesOrder.id,
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-purple-300 bg-white px-4 py-2.5 text-sm font-medium text-purple-700 transition hover:bg-purple-50 dark:border-purple-800 dark:bg-gray-900 dark:text-purple-300 dark:hover:bg-purple-900/20"
                >
                    Manage Allocation
                </Link>

                <Link
                    v-if="
                        salesOrder.can.update
                    "
                    :href="
                        route(
                            'sales-orders.edit',
                            salesOrder.id,
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-brand-300 bg-white px-4 py-2.5 text-sm font-medium text-brand-600 transition hover:bg-brand-50 dark:border-brand-800 dark:bg-gray-900 dark:text-brand-400 dark:hover:bg-brand-900/20"
                >
                    Edit
                </Link>

                <button
                    v-if="
                        salesOrder.can.delete
                    "
                    :disabled="
                        actionInProgress !== null
                    "
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-error-300 bg-white px-4 py-2.5 text-sm font-medium text-error-600 transition hover:bg-error-50 disabled:opacity-60 dark:border-error-900 dark:bg-gray-900 dark:hover:bg-error-900/20"
                    @click="deleteSalesOrder"
                >
                    {{
                        actionInProgress
                            === 'delete'
                            ? 'Deleting...'
                            : 'Delete'
                    }}
                </button>
            </div>
        </div>

        <div
            v-if="hasWorkflowActions"
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <h2
                        class="font-semibold text-gray-900 dark:text-white"
                    >
                        Workflow Actions
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Actions are controlled by
                        permission, status, branch
                        access, and downstream
                        activity.
                    </p>
                </div>

                <div
                    class="flex flex-wrap gap-2"
                >
                    <button
                        v-if="
                            salesOrder.can.submit
                        "
                        :disabled="
                            actionInProgress
                                !== null
                        "
                        type="button"
                        class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:opacity-60"
                        @click="
                            submitSalesOrder
                        "
                    >
                        {{
                            actionInProgress
                                === 'submit'
                                ? 'Submitting...'
                                : 'Submit for Approval'
                        }}
                    </button>

                    <button
                        v-if="
                            salesOrder.can.return_to_draft
                        "
                        :disabled="
                            actionInProgress
                                !== null
                        "
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="returnToDraft"
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
                            salesOrder.can.approve
                        "
                        :disabled="
                            actionInProgress
                                !== null
                        "
                        type="button"
                        class="rounded-lg bg-success-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-success-700 disabled:opacity-60"
                        @click="
                            approveSalesOrder
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
                            salesOrder.can.cancel
                        "
                        :disabled="
                            actionInProgress
                                !== null
                        "
                        type="button"
                        class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-error-600 disabled:opacity-60"
                        @click="
                            openCancellationModal
                        "
                    >
                        Cancel Order
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="salesOrder.can.allocate"
            class="flex flex-col gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-4 text-sm text-brand-700 dark:border-brand-900/50 dark:bg-brand-900/20 dark:text-brand-300 sm:flex-row sm:items-center sm:justify-between"
        >
            <p>
                This Sales Order is eligible
                for inventory allocation and
                reservation.
            </p>

            <Link
                :href="
                    route(
                        'sales-orders.allocation.show',
                        salesOrder.id,
                    )
                "
                class="inline-flex shrink-0 items-center justify-center rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600"
            >
                Allocate Inventory
            </Link>
        </div>

        <div
            v-if="
                salesOrder.status
                    === 'cancelled'
            "
            class="rounded-2xl border border-error-200 bg-error-50 p-5 dark:border-error-900/50 dark:bg-error-900/20"
        >
            <h2
                class="font-semibold text-error-700 dark:text-error-300"
            >
                Sales Order Cancelled
            </h2>

            <p
                class="mt-2 text-sm text-error-700 dark:text-error-300"
            >
                {{
                    salesOrder.cancellation_reason
                    ?? 'No cancellation reason was recorded.'
                }}
            </p>

            <p
                class="mt-2 text-xs text-error-600 dark:text-error-400"
            >
                Cancelled by
                {{
                    salesOrder.cancelled_by
                        ?.name
                    ?? 'Unknown user'
                }}
                on
                {{
                    formatDateTime(
                        salesOrder.cancelled_at,
                    )
                }}
            </p>
        </div>

        <div
            class="grid gap-6 xl:grid-cols-3"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6 xl:col-span-2"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Order Details
                </h2>

                <dl
                    class="mt-5 grid gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Order Date
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    salesOrder.order_date,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Requested Delivery
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    salesOrder.requested_delivery_date,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Customer Reference
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                salesOrder.customer_reference
                                || '—'
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
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                salesOrder.branch
                                    ?.name
                                ?? '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Warehouse
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                salesOrder.warehouse
                                    ?.name
                                ?? 'No warehouse'
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
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                salesOrder.payment_terms_days
                            }}
                            days
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Currency
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                salesOrder.currency_code
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
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                salesOrder.exchange_rate
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Credit Limit Snapshot
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    salesOrder.credit_limit_snapshot,
                                )
                            }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Customer Snapshot
                </h2>

                <dl class="mt-5 space-y-4">
                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Customer
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                salesOrder.customer_name
                            }}
                        </dd>

                        <dd
                            class="text-xs text-gray-500"
                        >
                            {{
                                salesOrder.customer_code
                            }}
                            ·
                            {{
                                salesOrder.customer_type
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Contact
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{
                                salesOrder.customer_contact_person
                                || '—'
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
                            class="mt-1 break-all text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{
                                salesOrder.customer_email
                                || '—'
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
                            class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{
                                salesOrder.customer_phone
                                || '—'
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
                            class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{
                                salesOrder.customer_tax_number
                                || '—'
                            }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div
            class="grid gap-6 lg:grid-cols-2"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Billing Address
                </h2>

                <p
                    class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300"
                >
                    {{
                        salesOrder.billing_address
                        || '—'
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Shipping Address
                </h2>

                <p
                    class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300"
                >
                    {{
                        salesOrder.shipping_address
                        || '—'
                    }}
                </p>

                <div
                    v-if="
                        salesOrder.delivery_instructions
                    "
                    class="mt-5 border-t border-gray-200 pt-4 dark:border-gray-800"
                >
                    <h3
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Delivery Instructions
                    </h3>

                    <p
                        class="mt-2 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300"
                    >
                        {{
                            salesOrder.delivery_instructions
                        }}
                    </p>
                </div>
            </div>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="border-b border-gray-200 p-5 dark:border-gray-800 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Order Lines
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Commercial values and
                    downstream fulfillment
                    quantities.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table
                    class="min-w-[1180px] divide-y divide-gray-200 dark:divide-gray-800"
                >
                    <thead
                        class="bg-gray-50 dark:bg-white/[0.03]"
                    >
                        <tr>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Line / Product
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Ordered
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Allocated
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Dispatched
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Invoiced
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Unit Price
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Discount
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Tax
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Total
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="line in salesOrder.lines"
                            :key="line.id"
                        >
                            <td class="px-5 py-4">
                                <p
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        line.line_number
                                    }}.
                                    {{
                                        line.product_name
                                    }}
                                </p>

                                <p
                                    class="text-xs text-gray-500"
                                >
                                    {{
                                        line.product_sku
                                    }}
                                    ·
                                    {{ line.unit_code }}
                                    ·
                                    {{
                                        line.product_type
                                            .replace(
                                                /_/g,
                                                ' ',
                                            )
                                    }}
                                </p>

                                <p
                                    v-if="
                                        line.description
                                    "
                                    class="mt-1 max-w-sm text-xs text-gray-500"
                                >
                                    {{
                                        line.description
                                    }}
                                </p>

                                <div
                                    class="mt-3 w-52"
                                >
                                    <div
                                        class="flex justify-between text-[11px] text-gray-500"
                                    >
                                        <span>
                                            {{
                                                lineProgressLabel(
                                                    line,
                                                )
                                            }}
                                        </span>

                                        <span>
                                            {{
                                                Math.round(
                                                    percentage(
                                                        line.allocated_quantity,
                                                        line.ordered_quantity,
                                                    ),
                                                )
                                            }}%
                                        </span>
                                    </div>

                                    <div
                                        class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"
                                    >
                                        <div
                                            class="h-full rounded-full bg-brand-500"
                                            :style="{
                                                width: `${percentage(
                                                    line.allocated_quantity,
                                                    line.ordered_quantity,
                                                )}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatQuantity(
                                        line.ordered_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatQuantity(
                                        line.allocated_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatQuantity(
                                        line.dispatched_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatQuantity(
                                        line.invoiced_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatAmount(
                                        line.unit_price,
                                    )
                                }}
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatAmount(
                                        line.discount_amount,
                                    )
                                }}
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatAmount(
                                        line.tax_amount,
                                    )
                                }}
                            </td>

                            <td
                                class="whitespace-nowrap px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
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
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]"
        >
            <div class="space-y-6">
                <div
                    v-if="
                        salesOrder.terms_and_conditions
                    "
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
                >
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Terms and Conditions
                    </h2>

                    <p
                        class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300"
                    >
                        {{
                            salesOrder.terms_and_conditions
                        }}
                    </p>
                </div>

                <div
                    v-if="salesOrder.notes"
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
                >
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Internal Notes
                    </h2>

                    <p
                        class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300"
                    >
                        {{ salesOrder.notes }}
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
                >
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Workflow History
                    </h2>

                    <dl
                        class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div>
                            <dt
                                class="text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                Created By
                            </dt>

                            <dd
                                class="mt-1 text-sm text-gray-800 dark:text-gray-200"
                            >
                                {{
                                    salesOrder.created_by
                                        ?.name
                                    ?? '—'
                                }}
                            </dd>

                            <dd
                                class="text-xs text-gray-500"
                            >
                                {{
                                    formatDateTime(
                                        salesOrder.created_at,
                                    )
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                Submitted By
                            </dt>

                            <dd
                                class="mt-1 text-sm text-gray-800 dark:text-gray-200"
                            >
                                {{
                                    salesOrder.submitted_by
                                        ?.name
                                    ?? '—'
                                }}
                            </dd>

                            <dd
                                class="text-xs text-gray-500"
                            >
                                {{
                                    formatDateTime(
                                        salesOrder.submitted_at,
                                    )
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                Approved By
                            </dt>

                            <dd
                                class="mt-1 text-sm text-gray-800 dark:text-gray-200"
                            >
                                {{
                                    salesOrder.approved_by
                                        ?.name
                                    ?? '—'
                                }}
                            </dd>

                            <dd
                                class="text-xs text-gray-500"
                            >
                                {{
                                    formatDateTime(
                                        salesOrder.approved_at,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div
                class="h-fit rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Financial Summary
                </h2>

                <dl class="mt-5 space-y-4">
                    <div
                        class="flex items-center justify-between text-sm"
                    >
                        <dt class="text-gray-500">
                            Subtotal
                        </dt>

                        <dd
                            class="font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{
                                formatAmount(
                                    salesOrder.subtotal,
                                )
                            }}
                        </dd>
                    </div>

                    <div
                        class="flex items-center justify-between text-sm"
                    >
                        <dt class="text-gray-500">
                            Discount
                        </dt>

                        <dd
                            class="font-medium text-gray-800 dark:text-gray-200"
                        >
                            -{{
                                formatAmount(
                                    salesOrder.discount_amount,
                                )
                            }}
                        </dd>
                    </div>

                    <div
                        class="flex items-center justify-between text-sm"
                    >
                        <dt class="text-gray-500">
                            Tax
                        </dt>

                        <dd
                            class="font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{
                                formatAmount(
                                    salesOrder.tax_amount,
                                )
                            }}
                        </dd>
                    </div>

                    <div
                        class="flex items-center justify-between text-sm"
                    >
                        <dt class="text-gray-500">
                            Shipping
                        </dt>

                        <dd
                            class="font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{
                                formatAmount(
                                    salesOrder.shipping_amount,
                                )
                            }}
                        </dd>
                    </div>

                    <div
                        class="flex items-center justify-between text-sm"
                    >
                        <dt class="text-gray-500">
                            Other Charges
                        </dt>

                        <dd
                            class="font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{
                                formatAmount(
                                    salesOrder.other_charges,
                                )
                            }}
                        </dd>
                    </div>

                    <div
                        class="border-t border-gray-200 pt-4 dark:border-gray-800"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <dt
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                Total
                            </dt>

                            <dd
                                class="text-xl font-semibold text-brand-600 dark:text-brand-400"
                            >
                                {{
                                    salesOrder.currency_code
                                }}
                                {{
                                    formatAmount(
                                        salesOrder.total_amount,
                                    )
                                }}
                            </dd>
                        </div>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="showCancellationModal"
            class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-950/60 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cancel-sales-order-title"
            @click.self="
                closeCancellationModal
            "
        >
            <form
                class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900"
                @submit.prevent="
                    cancelSalesOrder
                "
            >
                <h2
                    id="cancel-sales-order-title"
                    class="text-xl font-semibold text-gray-900 dark:text-white"
                >
                    Cancel Sales Order
                </h2>

                <p
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    Cancellation is final. Release
                    any active allocation before
                    cancelling the order.
                </p>

                <div class="mt-5">
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Cancellation Reason

                        <span
                            class="text-error-500"
                        >
                            *
                        </span>
                    </label>

                    <textarea
                        v-model="
                            cancellationForm.cancellation_reason
                        "
                        rows="4"
                        maxlength="500"
                        autofocus
                        placeholder="Explain why this Sales Order is being cancelled"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-error-500 dark:border-gray-700 dark:text-white"
                    />

                    <div
                        class="mt-1 flex justify-between gap-3"
                    >
                        <p
                            class="text-xs text-error-500"
                        >
                            {{
                                cancellationForm
                                    .errors
                                    .cancellation_reason
                            }}
                        </p>

                        <p
                            class="text-xs text-gray-400"
                        >
                            {{
                                cancellationForm
                                    .cancellation_reason
                                    .length
                            }}/500
                        </p>
                    </div>
                </div>

                <div
                    class="mt-6 flex justify-end gap-3"
                >
                    <button
                        :disabled="
                            cancellationForm.processing
                        "
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            closeCancellationModal
                        "
                    >
                        Keep Order
                    </button>

                    <button
                        :disabled="
                            cancellationForm.processing
                            || cancellationForm
                                .cancellation_reason
                                .trim()
                                === ''
                        "
                        type="submit"
                        class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-60"
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
    </Teleport>
</template>