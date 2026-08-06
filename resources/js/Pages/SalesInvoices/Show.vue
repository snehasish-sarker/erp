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
    SalesInvoiceShowProps,
    SalesInvoiceStatus,
} from '@/Types/sales-invoice';

defineOptions({
    layout: ErpLayout,
});

const props =
    defineProps<SalesInvoiceShowProps>();

const processing =
    ref<string | null>(null);

const showReverseModal =
    ref(false);

const reverseForm = useForm({
    reversal_posting_date:
        new Date()
            .toISOString()
            .slice(0, 10),

    reversal_reason: '',
});

const title = computed(
    (): string => {
        return props.salesInvoice
            .invoice_number
            ?? `Sales Invoice Draft #${props.salesInvoice.id}`;
    },
);

const statusClass = (
    status: SalesInvoiceStatus,
): string => {
    if (status === 'posted') {
        return 'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-300';
    }

    if (status === 'reversed') {
        return 'bg-error-50 text-error-700 dark:bg-error-900/30 dark:text-error-300';
    }

    return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
};

const openItemStatusClass = (
    status: string,
): string => {
    if (status === 'settled') {
        return 'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-300';
    }

    if (
        status
        === 'partially_settled'
    ) {
        return 'bg-warning-50 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300';
    }

    if (status === 'reversed') {
        return 'bg-error-50 text-error-700 dark:bg-error-900/30 dark:text-error-300';
    }

    return 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
};

const formatDate = (
    value: string | null,
): string => {
    if (!value) {
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
    if (!value) {
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
    value: string | number,
): string => {
    const amount =
        typeof value === 'number'
            ? value
            : Number.parseFloat(
                value,
            );

    if (!Number.isFinite(amount)) {
        return String(value);
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

    if (
        !Number.isFinite(quantity)
    ) {
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

const grossProfit = computed(
    (): number => {
        const total =
            Number.parseFloat(
                props.salesInvoice
                    .total_amount,
            );

        const tax =
            Number.parseFloat(
                props.salesInvoice
                    .tax_amount,
            );

        const cost =
            Number.parseFloat(
                props.salesInvoice
                    .total_cost,
            );

        const shipping =
            Number.parseFloat(
                props.salesInvoice
                    .shipping_amount,
            );

        const other =
            Number.parseFloat(
                props.salesInvoice
                    .other_charges,
            );

        return (
            Number.isFinite(total)
                ? total
                : 0
        )
            - (
                Number.isFinite(tax)
                    ? tax
                    : 0
            )
            - (
                Number.isFinite(shipping)
                    ? shipping
                    : 0
            )
            - (
                Number.isFinite(other)
                    ? other
                    : 0
            )
            - (
                Number.isFinite(cost)
                    ? cost
                    : 0
            );
    },
);

const postInvoice = (): void => {
    if (
        !window.confirm(
            'Post this Sales Invoice? Accounts Receivable, revenue, and output-tax entries will be created, and the invoice will become immutable.',
        )
    ) {
        return;
    }

    processing.value = 'post';

    router.post(
        route(
            'sales-invoices.post',
            props.salesInvoice.id,
        ),
        {},
        {
            preserveScroll: true,

            onFinish: () => {
                processing.value =
                    null;
            },
        },
    );
};

const deleteInvoice = (): void => {
    if (
        !window.confirm(
            'Delete this Sales Invoice draft?',
        )
    ) {
        return;
    }

    processing.value = 'delete';

    router.delete(
        route(
            'sales-invoices.destroy',
            props.salesInvoice.id,
        ),
        {
            preserveScroll: true,

            onFinish: () => {
                processing.value =
                    null;
            },
        },
    );
};

const openReverseModal =
    (): void => {
        reverseForm.reset();
        reverseForm.clearErrors();

        reverseForm
            .reversal_posting_date =
                new Date()
                    .toISOString()
                    .slice(0, 10);

        showReverseModal.value =
            true;
    };

const closeReverseModal =
    (): void => {
        if (reverseForm.processing) {
            return;
        }

        showReverseModal.value =
            false;

        reverseForm.reset();
        reverseForm.clearErrors();
    };

const reverseInvoice = (): void => {
    reverseForm.post(
        route(
            'sales-invoices.reverse',
            props.salesInvoice.id,
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                showReverseModal.value =
                    false;

                reverseForm.reset();
            },
        },
    );
};
</script>

<template>
    <Head :title="title" />

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
                                'sales-invoices.index',
                            )
                        "
                        class="hover:text-brand-500"
                    >
                        Sales Invoices
                    </Link>

                    <span>/</span>

                    <span
                        class="text-gray-700 dark:text-gray-300"
                    >
                        {{ title }}
                    </span>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3"
                >
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        {{ title }}
                    </h1>

                    <span
                        :class="[
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                            statusClass(
                                salesInvoice.status,
                            ),
                        ]"
                    >
                        {{
                            salesInvoice.status_label
                        }}
                    </span>
                </div>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Sales Order
                    {{
                        salesInvoice.sales_order_number
                    }}
                    · Revision
                    {{ salesInvoice.revision }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="
                        route(
                            'sales-invoices.index',
                        )
                    "
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back
                </Link>

                <a
                    v-if="
                        salesInvoice.can.print
                    "
                    :href="
                        route(
                            'sales-invoices.print',
                            salesInvoice.id,
                        )
                    "
                    target="_blank"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Print Invoice
                </a>

                <Link
                    v-if="
                        salesInvoice.can.update
                    "
                    :href="
                        route(
                            'sales-invoices.edit',
                            salesInvoice.id,
                        )
                    "
                    class="rounded-lg border border-brand-300 bg-white px-4 py-2.5 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-800 dark:bg-gray-900 dark:text-brand-400 dark:hover:bg-brand-900/20"
                >
                    Edit
                </Link>

                <button
                    v-if="
                        salesInvoice.can.delete
                    "
                    :disabled="
                        processing !== null
                    "
                    type="button"
                    class="rounded-lg border border-error-300 bg-white px-4 py-2.5 text-sm font-medium text-error-600 hover:bg-error-50 disabled:opacity-60 dark:border-error-900 dark:bg-gray-900 dark:hover:bg-error-900/20"
                    @click="deleteInvoice"
                >
                    {{
                        processing
                            === 'delete'
                            ? 'Deleting...'
                            : 'Delete'
                    }}
                </button>
            </div>
        </div>

        <div
            v-if="
                salesInvoice.can.post
                || salesInvoice.can.reverse
            "
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
                        Posting creates the customer
                        receivable and General Ledger
                        entries. Reversal is blocked after
                        settlement allocation.
                    </p>
                </div>

                <div
                    class="flex flex-wrap gap-2"
                >
                    <button
                        v-if="
                            salesInvoice.can.post
                        "
                        :disabled="
                            processing !== null
                        "
                        type="button"
                        class="rounded-lg bg-success-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-success-700 disabled:opacity-60"
                        @click="postInvoice"
                    >
                        {{
                            processing
                                === 'post'
                                ? 'Posting...'
                                : 'Post Sales Invoice'
                        }}
                    </button>

                    <button
                        v-if="
                            salesInvoice.can.reverse
                        "
                        type="button"
                        class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-error-600"
                        @click="
                            openReverseModal
                        "
                    >
                        Reverse Invoice
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="
                salesInvoice.status
                    === 'reversed'
            "
            class="rounded-xl border border-error-200 bg-error-50 px-4 py-4 text-sm text-error-700 dark:border-error-900/50 dark:bg-error-900/20 dark:text-error-300"
        >
            <p class="font-semibold">
                Sales Invoice Reversed
            </p>

            <p class="mt-1">
                {{
                    salesInvoice.reversal_reason
                    ?? 'No reason recorded.'
                }}
            </p>

            <p class="mt-2 text-xs">
                Posting date:
                {{
                    formatDate(
                        salesInvoice.reversal_posting_date,
                    )
                }}
                · Reversed by
                {{
                    salesInvoice.reversed_by
                        ?.name
                    ?? 'Unknown user'
                }}
                on
                {{
                    formatDateTime(
                        salesInvoice.reversed_at,
                    )
                }}
            </p>
        </div>

        <div
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Invoice Total
                </p>

                <p
                    class="mt-2 text-xl font-semibold text-brand-600 dark:text-brand-400"
                >
                    {{
                        salesInvoice.currency_code
                    }}
                    {{
                        formatAmount(
                            salesInvoice.total_amount,
                        )
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Outstanding
                </p>

                <p
                    class="mt-2 text-xl font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        salesInvoice.currency_code
                    }}
                    {{
                        formatAmount(
                            salesInvoice
                                .open_item
                                ?.outstanding_amount
                            ?? '0',
                        )
                    }}
                </p>

                <p
                    class="mt-1 text-xs text-gray-500"
                >
                    {{
                        salesInvoice.open_item
                            ? salesInvoice
                                .open_item
                                .status
                                .replace(
                                    /_/g,
                                    ' ',
                                )
                            : 'No AR open item'
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Inventory Cost
                </p>

                <p
                    class="mt-2 text-xl font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        salesInvoice.currency_code
                    }}
                    {{
                        formatAmount(
                            salesInvoice.total_cost,
                        )
                    }}
                </p>

                <p
                    class="mt-1 text-xs text-gray-500"
                >
                    Traceable to posted dispatches
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Gross Profit
                </p>

                <p
                    class="mt-2 text-xl font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        salesInvoice.currency_code
                    }}
                    {{
                        formatAmount(
                            grossProfit,
                        )
                    }}
                </p>

                <p
                    class="mt-1 text-xs text-gray-500"
                >
                    Before operating expenses
                </p>
            </div>
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
                    Invoice Details
                </h2>

                <dl
                    class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Invoice Date
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    salesInvoice.invoice_date,
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
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    salesInvoice.posting_date,
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
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    salesInvoice.due_date,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Sales Order
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                salesInvoice.sales_order_number
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
                                salesInvoice.branch
                                    ?.name
                                ?? '—'
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
                                salesInvoice.payment_terms_days
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
                                salesInvoice.currency_code
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
                                salesInvoice.exchange_rate
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
                                    salesInvoice.credit_limit_snapshot,
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
                                salesInvoice.customer_name
                            }}
                        </dd>

                        <dd
                            class="text-xs text-gray-500"
                        >
                            {{
                                salesInvoice.customer_code
                            }}
                            ·
                            {{
                                salesInvoice.customer_type
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
                                salesInvoice.customer_contact_person
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
                            class="mt-1 break-all text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{
                                salesInvoice.customer_email
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
                            class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{
                                salesInvoice.customer_phone
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
                            class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{
                                salesInvoice.customer_tax_number
                                ?? '—'
                            }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div
            v-if="salesInvoice.open_item"
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Accounts Receivable Open Item
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Customer subledger item
                        #{{
                            salesInvoice.open_item.id
                        }}
                    </p>
                </div>

                <span
                    :class="[
                        'inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium capitalize',
                        openItemStatusClass(
                            salesInvoice.open_item.status,
                        ),
                    ]"
                >
                    {{
                        salesInvoice.open_item.status
                            .replace(
                                /_/g,
                                ' ',
                            )
                    }}
                </span>
            </div>

            <dl
                class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4"
            >
                <div>
                    <dt
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Original Amount
                    </dt>

                    <dd
                        class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{
                            salesInvoice.currency_code
                        }}
                        {{
                            formatAmount(
                                salesInvoice
                                    .open_item
                                    .original_amount,
                            )
                        }}
                    </dd>
                </div>

                <div>
                    <dt
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Settled Amount
                    </dt>

                    <dd
                        class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{
                            salesInvoice.currency_code
                        }}
                        {{
                            formatAmount(
                                salesInvoice
                                    .open_item
                                    .allocated_amount,
                            )
                        }}
                    </dd>
                </div>

                <div>
                    <dt
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Outstanding Amount
                    </dt>

                    <dd
                        class="mt-1 text-sm font-semibold text-brand-600 dark:text-brand-400"
                    >
                        {{
                            salesInvoice.currency_code
                        }}
                        {{
                            formatAmount(
                                salesInvoice
                                    .open_item
                                    .outstanding_amount,
                            )
                        }}
                    </dd>
                </div>

                <div>
                    <dt
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Base Outstanding
                    </dt>

                    <dd
                        class="mt-1 text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{
                            formatAmount(
                                salesInvoice
                                    .open_item
                                    .base_outstanding_amount,
                            )
                        }}
                    </dd>
                </div>
            </dl>
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
                        salesInvoice.billing_address
                        ?? '—'
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
                        salesInvoice.shipping_address
                        ?? '—'
                    }}
                </p>
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
                    Invoice Lines
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Each line retains dispatch-level
                    quantity and cost traceability.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table
                    class="min-w-[1200px] divide-y divide-gray-200 dark:divide-gray-800"
                >
                    <thead
                        class="bg-gray-50 dark:bg-white/[0.03]"
                    >
                        <tr>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Product
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Quantity
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Unit Price
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Gross
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

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Cost
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <template
                            v-for="line in salesInvoice.lines"
                            :key="line.id"
                        >
                            <tr>
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
                                        class="mt-1 max-w-md text-xs text-gray-500"
                                    >
                                        {{
                                            line.description
                                        }}
                                    </p>
                                </td>

                                <td
                                    class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatQuantity(
                                            line.invoiced_quantity,
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
                                            line.tax_amount,
                                        )
                                    }}

                                    <span
                                        class="block text-xs text-gray-500"
                                    >
                                        {{
                                            formatQuantity(
                                                line.tax_rate,
                                            )
                                        }}%
                                    </span>
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

                                <td
                                    class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatAmount(
                                            line.total_cost,
                                        )
                                    }}
                                </td>
                            </tr>

                            <tr
                                v-if="
                                    line.dispatch_allocations
                                        .length > 0
                                "
                                class="bg-gray-50/70 dark:bg-white/[0.015]"
                            >
                                <td
                                    colspan="8"
                                    class="px-5 py-3"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                                        >
                                            Dispatch allocations:
                                        </span>

                                        <span
                                            v-for="allocation in line.dispatch_allocations"
                                            :key="
                                                allocation.id
                                            "
                                            class="inline-flex rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        >
                                            {{
                                                allocation.dispatch_number
                                                ?? 'Dispatch'
                                            }}
                                            ·
                                            {{
                                                formatDate(
                                                    allocation.dispatch_date,
                                                )
                                            }}
                                            · Qty
                                            {{
                                                formatQuantity(
                                                    allocation.allocated_quantity,
                                                )
                                            }}
                                            · Cost
                                            {{
                                                formatAmount(
                                                    allocation.total_cost,
                                                )
                                            }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_390px]"
        >
            <div class="space-y-6">
                <div
                    v-if="
                        salesInvoice.notes
                    "
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
                >
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Notes
                    </h2>

                    <p
                        class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300"
                    >
                        {{ salesInvoice.notes }}
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
                >
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Accounting and Workflow
                    </h2>

                    <dl
                        class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div>
                            <dt
                                class="text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                Created
                            </dt>

                            <dd
                                class="mt-1 text-sm text-gray-800 dark:text-gray-200"
                            >
                                {{
                                    salesInvoice.created_by
                                        ?.name
                                    ?? '—'
                                }}
                            </dd>

                            <dd
                                class="text-xs text-gray-500"
                            >
                                {{
                                    formatDateTime(
                                        salesInvoice.created_at,
                                    )
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                Posted
                            </dt>

                            <dd
                                class="mt-1 text-sm text-gray-800 dark:text-gray-200"
                            >
                                {{
                                    salesInvoice.posted_by
                                        ?.name
                                    ?? '—'
                                }}
                            </dd>

                            <dd
                                class="text-xs text-gray-500"
                            >
                                {{
                                    formatDateTime(
                                        salesInvoice.posted_at,
                                    )
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                Reversed
                            </dt>

                            <dd
                                class="mt-1 text-sm text-gray-800 dark:text-gray-200"
                            >
                                {{
                                    salesInvoice.reversed_by
                                        ?.name
                                    ?? '—'
                                }}
                            </dd>

                            <dd
                                class="text-xs text-gray-500"
                            >
                                {{
                                    formatDateTime(
                                        salesInvoice.reversed_at,
                                    )
                                }}
                            </dd>
                        </div>

                        <div>
                            <dt
                                class="text-xs font-medium uppercase tracking-wide text-gray-500"
                            >
                                Posting Reference
                            </dt>

                            <dd
                                class="mt-1 break-all text-sm font-medium text-gray-900 dark:text-white"
                            >
                                {{
                                    salesInvoice.accounting_posting_reference
                                    ?? '—'
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
                                class="mt-1 break-all text-sm font-medium text-gray-900 dark:text-white"
                            >
                                {{
                                    salesInvoice.accounting_reversal_reference
                                    ?? '—'
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
                    Invoice Summary
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
                                    salesInvoice.subtotal,
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
                                    salesInvoice.discount_amount,
                                )
                            }}
                        </dd>
                    </div>

                    <div
                        class="flex items-center justify-between text-sm"
                    >
                        <dt class="text-gray-500">
                            Output Tax
                        </dt>

                        <dd
                            class="font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{
                                formatAmount(
                                    salesInvoice.tax_amount,
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
                                    salesInvoice.shipping_amount,
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
                                    salesInvoice.other_charges,
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
                                Invoice Total
                            </dt>

                            <dd
                                class="text-xl font-semibold text-brand-600 dark:text-brand-400"
                            >
                                {{
                                    salesInvoice.currency_code
                                }}
                                {{
                                    formatAmount(
                                        salesInvoice.total_amount,
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
            v-if="showReverseModal"
            class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-950/60 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reverse-sales-invoice-title"
            @click.self="
                closeReverseModal
            "
        >
            <form
                class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900"
                @submit.prevent="
                    reverseInvoice
                "
            >
                <h2
                    id="reverse-sales-invoice-title"
                    class="text-xl font-semibold text-gray-900 dark:text-white"
                >
                    Reverse Sales Invoice
                </h2>

                <p
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    Reversal creates opposite General Ledger
                    and customer subledger entries. It is
                    blocked after any receipt or credit has
                    been allocated to the invoice.
                </p>

                <div class="mt-5">
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Reversal Posting Date
                        <span
                            class="text-error-500"
                        >
                            *
                        </span>
                    </label>

                    <input
                        v-model="
                            reverseForm.reversal_posting_date
                        "
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-error-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            reverseForm.errors
                                .reversal_posting_date
                        }}
                    </p>
                </div>

                <div class="mt-5">
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Reversal Reason
                        <span
                            class="text-error-500"
                        >
                            *
                        </span>
                    </label>

                    <textarea
                        v-model="
                            reverseForm.reversal_reason
                        "
                        rows="4"
                        maxlength="500"
                        autofocus
                        placeholder="Explain why this Sales Invoice is being reversed"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-error-500 dark:border-gray-700 dark:text-white"
                    />

                    <div
                        class="mt-1 flex justify-between gap-3"
                    >
                        <p
                            class="text-xs text-error-500"
                        >
                            {{
                                reverseForm.errors
                                    .reversal_reason
                            }}
                        </p>

                        <p
                            class="text-xs text-gray-400"
                        >
                            {{
                                reverseForm
                                    .reversal_reason
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
                            reverseForm.processing
                        "
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                        @click="
                            closeReverseModal
                        "
                    >
                        Keep Invoice
                    </button>

                    <button
                        :disabled="
                            reverseForm.processing
                            || reverseForm.reversal_posting_date
                                === ''
                            || reverseForm.reversal_reason
                                .trim()
                                === ''
                        "
                        type="submit"
                        class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{
                            reverseForm.processing
                                ? 'Reversing...'
                                : 'Confirm Reversal'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>