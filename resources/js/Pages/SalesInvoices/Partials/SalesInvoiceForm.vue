<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import { computed } from 'vue';

import type {
    ExistingSalesInvoiceFormData,
    InvoiceableSalesOrder,
    InvoiceableSalesOrderLine,
    SalesInvoiceFormData,
    SalesInvoiceFormLine,
} from '@/Types/sales-invoice';

interface Props {
    salesOrder:
        InvoiceableSalesOrder;

    salesInvoice?:
        ExistingSalesInvoiceFormData;

    defaults?: {
        invoice_date: string;
        posting_date: string;
        due_date: string;
    };
}

const props = defineProps<Props>();

const existingByLine =
    new Map<
        number,
        SalesInvoiceFormLine
    >(
        (
            props.salesInvoice?.lines
            ?? []
        ).map(
            (line) => [
                line.sales_order_line_id,
                line,
            ],
        ),
    );

const form =
    useForm<SalesInvoiceFormData>({
        sales_order_id:
            props.salesOrder.id,

        invoice_date:
            props.salesInvoice
                ?.invoice_date
            ?? props.defaults
                ?.invoice_date
            ?? '',

        posting_date:
            props.salesInvoice
                ?.posting_date
            ?? props.defaults
                ?.posting_date
            ?? '',

        due_date:
            props.salesInvoice
                ?.due_date
            ?? props.defaults?.due_date
            ?? '',

        billing_address:
            props.salesInvoice
                ?.billing_address
            ?? props.salesOrder
                .billing_address
            ?? '',

        shipping_address:
            props.salesInvoice
                ?.shipping_address
            ?? props.salesOrder
                .shipping_address
            ?? '',

        shipping_amount:
            props.salesInvoice
                ?.shipping_amount
            ?? '0.000000',

        other_charges:
            props.salesInvoice
                ?.other_charges
            ?? '0.000000',

        notes:
            props.salesInvoice?.notes
            ?? '',

        lines:
            props.salesOrder.lines
                .map(
                    (
                        line,
                    ): SalesInvoiceFormLine => {
                        const existing =
                            existingByLine.get(
                                line.id,
                            );

                        return {
                            id:
                                existing?.id,

                            sales_order_line_id:
                                line.id,

                            invoiced_quantity:
                                existing
                                    ?.invoiced_quantity
                                ?? line
                                    .remaining_invoiceable_quantity,

                            description:
                                existing
                                    ?.description
                                ?? line.description
                                ?? '',
                        };
                    },
                ),
    });

const isEditing = computed(
    (): boolean =>
        props.salesInvoice !== undefined,
);

const decimal = (
    value:
        | string
        | number
        | null
        | undefined,
): number => {
    const parsed =
        Number.parseFloat(
            String(value ?? '0'),
        );

    return Number.isFinite(parsed)
        ? parsed
        : 0;
};

const money = (
    value: number,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(
        Number.isFinite(value)
            ? value
            : 0,
    );
};

const quantity = (
    value: string | number,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        },
    ).format(
        decimal(value),
    );
};

const lineAmounts = (
    orderLine:
        InvoiceableSalesOrderLine,

    formLine:
        SalesInvoiceFormLine,
): {
    gross: number;
    discount: number;
    tax: number;
    total: number;
} => {
    const invoiceQuantity =
        Math.max(
            decimal(
                formLine
                    .invoiced_quantity,
            ),
            0,
        );

    const orderedQuantity =
        Math.max(
            decimal(
                orderLine
                    .ordered_quantity,
            ),
            0,
        );

    const gross =
        invoiceQuantity
        * decimal(
            orderLine.unit_price,
        );

    const discount =
        orderedQuantity > 0
            ? decimal(
                orderLine
                    .discount_amount,
            )
                * invoiceQuantity
                / orderedQuantity
            : 0;

    const taxable = Math.max(
        gross - discount,
        0,
    );

    const tax =
        taxable
        * decimal(
            orderLine.tax_rate,
        )
        / 100;

    return {
        gross,
        discount,
        tax,
        total: taxable + tax,
    };
};

const subtotal = computed(
    (): number => {
        return props.salesOrder
            .lines
            .reduce(
                (
                    total,
                    orderLine,
                    index,
                ) => {
                    const formLine =
                        form.lines[index];

                    return total + (
                        formLine
                            ? lineAmounts(
                                orderLine,
                                formLine,
                            ).gross
                            : 0
                    );
                },
                0,
            );
    },
);

const discountTotal = computed(
    (): number => {
        return props.salesOrder
            .lines
            .reduce(
                (
                    total,
                    orderLine,
                    index,
                ) => {
                    const formLine =
                        form.lines[index];

                    return total + (
                        formLine
                            ? lineAmounts(
                                orderLine,
                                formLine,
                            ).discount
                            : 0
                    );
                },
                0,
            );
    },
);

const taxTotal = computed(
    (): number => {
        return props.salesOrder
            .lines
            .reduce(
                (
                    total,
                    orderLine,
                    index,
                ) => {
                    const formLine =
                        form.lines[index];

                    return total + (
                        formLine
                            ? lineAmounts(
                                orderLine,
                                formLine,
                            ).tax
                            : 0
                    );
                },
                0,
            );
    },
);

const totalAmount = computed(
    (): number => {
        return subtotal.value
            - discountTotal.value
            + taxTotal.value
            + Math.max(
                decimal(
                    form.shipping_amount,
                ),
                0,
            )
            + Math.max(
                decimal(
                    form.other_charges,
                ),
                0,
            );
    },
);

const proposedBaseExposure =
    computed(
        (): number => {
            return decimal(
                props.salesOrder
                    .current_base_outstanding,
            )
                + totalAmount.value
                * decimal(
                    props.salesOrder
                        .exchange_rate,
                );
        },
    );

const hasCreditLimitConflict =
    computed(
        (): boolean => {
            const limit = decimal(
                props.salesOrder
                    .credit_limit,
            );

            return limit > 0
                && proposedBaseExposure
                    .value
                    > limit + 0.000001;
        },
    );

const selectedLineCount =
    computed(
        (): number => {
            return form.lines.filter(
                (line) =>
                    decimal(
                        line
                            .invoiced_quantity,
                    ) > 0,
            ).length;
        },
    );

const hasQuantityConflict =
    computed(
        (): boolean => {
            return form.lines.some(
                (
                    formLine,
                    index,
                ) => {
                    const orderLine =
                        props.salesOrder
                            .lines[index];

                    return orderLine
                        !== undefined
                        && decimal(
                            formLine
                                .invoiced_quantity,
                        )
                            > decimal(
                                orderLine
                                    .remaining_invoiceable_quantity,
                            )
                                + 0.000001;
                },
            );
        },
    );

const fieldError = (
    field: string,
): string | undefined => {
    return (
        form.errors as Record<
            string,
            string | undefined
        >
    )[field];
};

const setMaximum = (
    index: number,
): void => {
    const orderLine =
        props.salesOrder.lines[index];

    const formLine =
        form.lines[index];

    if (orderLine && formLine) {
        formLine.invoiced_quantity =
            orderLine
                .remaining_invoiceable_quantity;
    }
};

const clearLine = (
    index: number,
): void => {
    const formLine =
        form.lines[index];

    if (formLine) {
        formLine.invoiced_quantity =
            '0.000000';
    }
};

const submit = (): void => {
    if (props.salesInvoice) {
        form.put(
            route(
                'sales-invoices.update',
                props.salesInvoice.id,
            ),
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        route('sales-invoices.store'),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <form
        class="space-y-6"
        @submit.prevent="submit"
    >
        <div
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Sales Order
                </p>

                <p
                    class="mt-2 font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        salesOrder.document_number
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Customer
                </p>

                <p
                    class="mt-2 font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        salesOrder.customer_name
                    }}
                </p>

                <p
                    class="mt-1 text-xs text-gray-500"
                >
                    {{
                        salesOrder.customer_code
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Current AR Exposure
                </p>

                <p
                    class="mt-2 font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        money(
                            decimal(
                                salesOrder.current_base_outstanding,
                            ),
                        )
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Credit Limit
                </p>

                <p
                    class="mt-2 font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        decimal(
                            salesOrder.credit_limit,
                        ) > 0
                            ? money(
                                decimal(
                                    salesOrder.credit_limit,
                                ),
                            )
                            : 'Unlimited'
                    }}
                </p>
            </div>
        </div>

        <div
            v-if="
                hasCreditLimitConflict
            "
            class="rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-900/50 dark:bg-error-900/20 dark:text-error-300"
        >
            Proposed base-currency exposure
            {{
                money(
                    proposedBaseExposure,
                )
            }}
            exceeds the customer credit limit
            {{
                money(
                    decimal(
                        salesOrder.credit_limit,
                    ),
                )
            }}.
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
        >
            <div class="mb-5">
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Invoice Information
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Posting date controls the accounting
                    period. The invoice number is assigned
                    during posting.
                </p>
            </div>

            <div
                class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
            >
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Invoice Date
                        <span
                            class="text-error-500"
                        >
                            *
                        </span>
                    </label>

                    <input
                        v-model="
                            form.invoice_date
                        "
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="
                            form.errors.invoice_date
                        "
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            form.errors.invoice_date
                        }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Posting Date
                        <span
                            class="text-error-500"
                        >
                            *
                        </span>
                    </label>

                    <input
                        v-model="
                            form.posting_date
                        "
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="
                            form.errors.posting_date
                        "
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            form.errors.posting_date
                        }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Due Date
                        <span
                            class="text-error-500"
                        >
                            *
                        </span>
                    </label>

                    <input
                        v-model="form.due_date"
                        :min="form.invoice_date"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="
                            form.errors.due_date
                        "
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            form.errors.due_date
                        }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Currency
                    </label>

                    <input
                        :value="
                            salesOrder.currency_code
                        "
                        readonly
                        class="h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    />
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Exchange Rate
                    </label>

                    <input
                        :value="
                            salesOrder.exchange_rate
                        "
                        readonly
                        class="h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    />
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Payment Terms
                    </label>

                    <input
                        :value="`${salesOrder.payment_terms_days} days`"
                        readonly
                        class="h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    />
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Billing Address
                    </label>

                    <textarea
                        v-model="
                            form.billing_address
                        "
                        rows="4"
                        maxlength="4000"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Shipping Address
                    </label>

                    <textarea
                        v-model="
                            form.shipping_address
                        "
                        rows="4"
                        maxlength="4000"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
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
                    Invoice Lines
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Quantities cannot exceed posted dispatch
                    quantities that remain uninvoiced.
                </p>
            </div>

            <p
                v-if="form.errors.lines"
                class="px-5 pt-4 text-sm text-error-500 sm:px-6"
            >
                {{ form.errors.lines }}
            </p>

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
                                Dispatched
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Already Invoiced
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Remaining
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Unit Price
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                This Invoice
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Line Total
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="(
                                line,
                                index
                            ) in salesOrder.lines"
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
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    quantity(
                                        line.dispatched_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    quantity(
                                        line.already_invoiced_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    quantity(
                                        line.remaining_invoiceable_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    money(
                                        decimal(
                                            line.unit_price,
                                        ),
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <div
                                    class="flex min-w-72 items-center gap-2"
                                >
                                    <input
                                        v-model="
                                            form.lines[
                                                index
                                            ]
                                            .invoiced_quantity
                                        "
                                        :max="
                                            line.remaining_invoiceable_quantity
                                        "
                                        min="0"
                                        step="0.000001"
                                        type="number"
                                        class="h-11 w-40 rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                                    />

                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                        @click="
                                            setMaximum(
                                                index,
                                            )
                                        "
                                    >
                                        Full
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                        @click="
                                            clearLine(
                                                index,
                                            )
                                        "
                                    >
                                        Zero
                                    </button>
                                </div>

                                <p
                                    v-if="
                                        fieldError(
                                            `lines.${index}.invoiced_quantity`,
                                        )
                                    "
                                    class="mt-1 text-xs text-error-500"
                                >
                                    {{
                                        fieldError(
                                            `lines.${index}.invoiced_quantity`,
                                        )
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    money(
                                        lineAmounts(
                                            line,
                                            form.lines[
                                                index
                                            ],
                                        ).total,
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
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <label
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Internal Notes
                </label>

                <textarea
                    v-model="form.notes"
                    rows="5"
                    maxlength="4000"
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                />
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Invoice Summary
                </h2>

                <div class="mt-5 space-y-4">
                    <div
                        class="flex justify-between text-sm"
                    >
                        <span
                            class="text-gray-500"
                        >
                            Selected Lines
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                selectedLineCount
                            }}
                        </span>
                    </div>

                    <div
                        class="flex justify-between text-sm"
                    >
                        <span
                            class="text-gray-500"
                        >
                            Subtotal
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{ money(subtotal) }}
                        </span>
                    </div>

                    <div
                        class="flex justify-between text-sm"
                    >
                        <span
                            class="text-gray-500"
                        >
                            Discount
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            -{{
                                money(
                                    discountTotal,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex justify-between text-sm"
                    >
                        <span
                            class="text-gray-500"
                        >
                            Tax
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                money(taxTotal)
                            }}
                        </span>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Shipping
                        </label>

                        <input
                            v-model="
                                form.shipping_amount
                            "
                            min="0"
                            step="0.000001"
                            type="number"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                        />
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Other Charges
                        </label>

                        <input
                            v-model="
                                form.other_charges
                            "
                            min="0"
                            step="0.000001"
                            type="number"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                        />
                    </div>

                    <div
                        class="border-t border-gray-200 pt-4 dark:border-gray-800"
                    >
                        <div
                            class="flex justify-between gap-4"
                        >
                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                Total
                            </span>

                            <span
                                class="text-xl font-semibold text-brand-600 dark:text-brand-400"
                            >
                                {{
                                    salesOrder.currency_code
                                }}
                                {{
                                    money(
                                        totalAmount,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="form.hasErrors"
            class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-900/50 dark:bg-error-900/20 dark:text-error-300"
        >
            Correct the validation errors before saving.
        </div>

        <div
            class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
        >
            <Link
                :href="
                    salesInvoice
                        ? route(
                            'sales-invoices.show',
                            salesInvoice.id,
                        )
                        : route(
                            'sales-invoices.index',
                        )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Cancel
            </Link>

            <button
                :disabled="
                    form.processing
                    || selectedLineCount === 0
                    || hasQuantityConflict
                    || hasCreditLimitConflict
                "
                type="submit"
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{
                    form.processing
                        ? 'Saving...'
                        : isEditing
                            ? 'Update Invoice Draft'
                            : 'Create Invoice Draft'
                }}
            </button>
        </div>
    </form>
</template>