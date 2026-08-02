<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import type {
    ExistingSupplierInvoiceFormData,
    SupplierInvoiceFormData,
    SupplierInvoiceFormLine,
    SupplierInvoiceFormLinePayload,
    SupplierInvoiceFormMatch,
    SupplierInvoiceFormPayload,
    SupplierInvoiceFormProps,
    SupplierInvoiceGoodsReceiptLineOption,
    SupplierInvoiceMatchStatus,
    SupplierInvoicePurchaseOrderLineOption,
} from '@/Types/supplier-invoice';

interface Props extends SupplierInvoiceFormProps {
    supplierInvoice?: ExistingSupplierInvoiceFormData;
}

const props = defineProps<Props>();

const decimalValue = (
    value: string | number | null | undefined,
): number => {
    const parsed = Number.parseFloat(
        String(value ?? '0'),
    );

    return Number.isFinite(parsed)
        ? parsed
        : 0;
};

const formatDecimal = (
    value: number,
    places = 6,
): string => {
    return Number.isFinite(value)
        ? value.toFixed(places)
        : Number(0).toFixed(places);
};

const formatAmount = (
    value: number,
): string => {
    return new Intl.NumberFormat(
        undefined,
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

const addDays = (
    dateValue: string,
    days: number,
): string => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(
        dateValue,
    );

    if (match === null) {
        return '';
    }

    const date = new Date(
        Date.UTC(
            Number(match[1]),
            Number(match[2]) - 1,
            Number(match[3]),
        ),
    );

    date.setUTCDate(
        date.getUTCDate() + days,
    );

    return date.toISOString().slice(0, 10);
};

const form = useForm<SupplierInvoiceFormData>({
    purchase_order_id:
        props.supplierInvoice
            ?.purchase_order_id
        ?? props.selectedPurchaseOrderId
        ?? null,

    supplier_invoice_number:
        props.supplierInvoice
            ?.supplier_invoice_number
        ?? '',

    invoice_date:
        props.supplierInvoice?.invoice_date
        ?? props.defaults.invoice_date,

    posting_date:
        props.supplierInvoice?.posting_date
        ?? props.defaults.posting_date,

    due_date:
        props.supplierInvoice?.due_date
        ?? '',

    currency_code:
        props.supplierInvoice?.currency_code
        ?? '',

    exchange_rate:
        props.supplierInvoice?.exchange_rate
        ?? '1.00000000',

    other_charges:
        props.supplierInvoice?.other_charges
        ?? props.defaults.other_charges,

    rounding_adjustment:
        props.supplierInvoice
            ?.rounding_adjustment
        ?? props.defaults.rounding_adjustment,

    notes:
        props.supplierInvoice?.notes
        ?? '',

    matching_notes:
        props.supplierInvoice?.matching_notes
        ?? '',

    lines:
        props.supplierInvoice?.lines.map(
            (
                line,
            ): SupplierInvoiceFormLine => ({
                include: true,
                ...line,

                matches: line.matches.map(
                    (
                        match,
                    ): SupplierInvoiceFormMatch => ({
                        ...match,
                    }),
                ),
            }),
        ) ?? [],
});

const isEditing = computed(
    (): boolean =>
        props.supplierInvoice !== undefined,
);

const selectedPurchaseOrder = computed(() => {
    if (form.purchase_order_id === null) {
        return null;
    }

    return props.purchaseOrders.find(
        (purchaseOrder) =>
            purchaseOrder.id
            === form.purchase_order_id,
    ) ?? null;
});

const sourceLine = (
    purchaseOrderLineId: number,
): SupplierInvoicePurchaseOrderLineOption | null => {
    return selectedPurchaseOrder.value
        ?.lines
        .find(
            (line) =>
                line.id
                === purchaseOrderLineId,
        )
        ?? null;
};

const receiptOption = (
    purchaseOrderLineId: number,
    goodsReceiptLineId: number,
): SupplierInvoiceGoodsReceiptLineOption | null => {
    return sourceLine(
        purchaseOrderLineId,
    )
        ?.goods_receipt_lines
        .find(
            (line) =>
                line.goods_receipt_line_id
                === goodsReceiptLineId,
        )
        ?? null;
};

const quantityStep = (
    purchaseOrderLineId: number,
): string => {
    const line = sourceLine(
        purchaseOrderLineId,
    );

    if (
        line === null
        || !line.allow_decimal
        || line.decimal_places <= 0
    ) {
        return '1';
    }

    return `0.${'0'.repeat(
        line.decimal_places - 1,
    )}1`;
};

const proratedPurchaseOrderDiscount = (
    line: SupplierInvoicePurchaseOrderLineOption,
    quantity: string,
): string => {
    const orderedQuantity = decimalValue(
        line.ordered_quantity,
    );

    if (orderedQuantity <= 0) {
        return '0.000000';
    }

    return formatDecimal(
        decimalValue(
            line.discount_amount,
        )
        * decimalValue(quantity)
        / orderedQuantity,
    );
};

const allocateMatches = (
    line: SupplierInvoicePurchaseOrderLineOption,
    quantity: string,
): SupplierInvoiceFormMatch[] => {
    let remaining = Math.max(
        decimalValue(quantity),
        0,
    );

    return line.goods_receipt_lines.map(
        (
            receiptLine,
        ): SupplierInvoiceFormMatch => {
            const available = Math.max(
                decimalValue(
                    receiptLine.available_quantity,
                ),
                0,
            );

            const matched = Math.min(
                remaining,
                available,
            );

            remaining = Math.max(
                remaining - matched,
                0,
            );

            return {
                goods_receipt_line_id:
                    receiptLine
                        .goods_receipt_line_id,

                matched_quantity:
                    formatDecimal(
                        matched,
                        line.decimal_places,
                    ),
            };
        },
    );
};

const newLine = (
    line: SupplierInvoicePurchaseOrderLineOption,
): SupplierInvoiceFormLine => {
    const quantity =
        line.available_to_invoice_quantity;

    return {
        include:
            decimalValue(quantity) > 0,

        purchase_order_line_id:
            line.id,

        invoiced_quantity:
            quantity,

        invoice_unit_price:
            line.unit_price,

        discount_amount:
            proratedPurchaseOrderDiscount(
                line,
                quantity,
            ),

        tax_rate:
            line.tax_rate,

        variance_reason: '',

        matches:
            allocateMatches(
                line,
                quantity,
            ),
    };
};

const applyPurchaseOrder = (): void => {
    const purchaseOrder =
        selectedPurchaseOrder.value;

    if (purchaseOrder === null) {
        form.currency_code = '';
        form.exchange_rate = '1.00000000';
        form.due_date = '';
        form.lines = [];

        return;
    }

    form.currency_code =
        purchaseOrder.currency_code;

    form.exchange_rate =
        purchaseOrder.exchange_rate;

    form.due_date = addDays(
        form.invoice_date,
        purchaseOrder.payment_terms_days,
    );

    form.lines =
        purchaseOrder.lines.map(newLine);
};

if (
    !isEditing.value
    && selectedPurchaseOrder.value !== null
) {
    applyPurchaseOrder();
}

watch(
    () => form.purchase_order_id,
    (
        value,
        previousValue,
    ) => {
        if (value !== previousValue) {
            applyPurchaseOrder();
        }
    },
);

watch(
    () => form.invoice_date,
    (
        value,
        previousValue,
    ) => {
        const purchaseOrder =
            selectedPurchaseOrder.value;

        if (purchaseOrder === null) {
            return;
        }

        const previousDueDate = addDays(
            previousValue,
            purchaseOrder.payment_terms_days,
        );

        if (
            form.due_date === ''
            || form.due_date
                === previousDueDate
        ) {
            form.due_date = addDays(
                value,
                purchaseOrder
                    .payment_terms_days,
            );
        }

        if (
            form.posting_date === ''
            || form.posting_date
                === previousValue
        ) {
            form.posting_date = value;
        }
    },
);

const autoAllocate = (
    line: SupplierInvoiceFormLine,
): void => {
    const purchaseOrderLine = sourceLine(
        line.purchase_order_line_id,
    );

    line.matches =
        purchaseOrderLine === null
            ? []
            : allocateMatches(
                purchaseOrderLine,
                line.invoiced_quantity,
            );
};

const restorePurchaseOrderValues = (
    line: SupplierInvoiceFormLine,
): void => {
    const purchaseOrderLine = sourceLine(
        line.purchase_order_line_id,
    );

    if (purchaseOrderLine === null) {
        return;
    }

    line.invoice_unit_price =
        purchaseOrderLine.unit_price;

    line.discount_amount =
        proratedPurchaseOrderDiscount(
            purchaseOrderLine,
            line.invoiced_quantity,
        );

    line.tax_rate =
        purchaseOrderLine.tax_rate;
};

const matchedQuantity = (
    line: SupplierInvoiceFormLine,
): number => {
    return line.matches.reduce(
        (total, match) =>
            total
            + decimalValue(
                match.matched_quantity,
            ),
        0,
    );
};

const grossAmount = (
    line: SupplierInvoiceFormLine,
): number => {
    return Math.max(
        decimalValue(
            line.invoiced_quantity,
        ),
        0,
    ) * Math.max(
        decimalValue(
            line.invoice_unit_price,
        ),
        0,
    );
};

const netAmount = (
    line: SupplierInvoiceFormLine,
): number => {
    return Math.max(
        grossAmount(line)
        - Math.max(
            decimalValue(
                line.discount_amount,
            ),
            0,
        ),
        0,
    );
};

const taxAmount = (
    line: SupplierInvoiceFormLine,
): number => {
    return netAmount(line)
        * Math.max(
            decimalValue(line.tax_rate),
            0,
        )
        / 100;
};

const lineTotal = (
    line: SupplierInvoiceFormLine,
): number => {
    return netAmount(line)
        + taxAmount(line);
};

const exceedsReceiptAvailability = (
    line: SupplierInvoiceFormLine,
): boolean => {
    const purchaseOrderLine = sourceLine(
        line.purchase_order_line_id,
    );

    return purchaseOrderLine === null
        || decimalValue(
            line.invoiced_quantity,
        ) > decimalValue(
            purchaseOrderLine
                .available_to_invoice_quantity,
        );
};

const exceedsPurchaseOrderQuantity = (
    line: SupplierInvoiceFormLine,
): boolean => {
    const purchaseOrderLine = sourceLine(
        line.purchase_order_line_id,
    );

    return purchaseOrderLine === null
        || decimalValue(
            purchaseOrderLine
                .previously_invoiced_quantity,
        ) + decimalValue(
            line.invoiced_quantity,
        ) > decimalValue(
            purchaseOrderLine
                .ordered_quantity,
        );
};

const hasCommercialVariance = (
    line: SupplierInvoiceFormLine,
): boolean => {
    const purchaseOrderLine = sourceLine(
        line.purchase_order_line_id,
    );

    if (purchaseOrderLine === null) {
        return true;
    }

    const tolerance = 0.000001;

    return Math.abs(
        decimalValue(
            line.invoice_unit_price,
        ) - decimalValue(
            purchaseOrderLine.unit_price,
        ),
    ) > tolerance
        || Math.abs(
            decimalValue(
                line.discount_amount,
            ) - decimalValue(
                proratedPurchaseOrderDiscount(
                    purchaseOrderLine,
                    line.invoiced_quantity,
                ),
            ),
        ) > tolerance
        || Math.abs(
            decimalValue(
                line.tax_rate,
            ) - decimalValue(
                purchaseOrderLine.tax_rate,
            ),
        ) > tolerance;
};

const matchStatus = (
    line: SupplierInvoiceFormLine,
): SupplierInvoiceMatchStatus => {
    const invoiced = decimalValue(
        line.invoiced_quantity,
    );

    const matched =
        matchedQuantity(line);

    if (
        invoiced <= 0
        || exceedsReceiptAvailability(line)
        || Math.abs(
            invoiced - matched,
        ) > 0.000001
    ) {
        return 'blocked';
    }

    if (matched <= 0) {
        return 'unmatched';
    }

    if (
        exceedsPurchaseOrderQuantity(line)
        || hasCommercialVariance(line)
    ) {
        return 'variance';
    }

    return 'matched';
};

const matchStatusLabel = (
    status: SupplierInvoiceMatchStatus,
): string => {
    return props.matchStatuses.find(
        (option) =>
            option.value === status,
    )?.label ?? status;
};

const matchStatusClass = (
    status: SupplierInvoiceMatchStatus,
): string => {
    const classes: Record<
        SupplierInvoiceMatchStatus,
        string
    > = {
        matched:
            'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400',

        variance:
            'bg-yellow-50 text-yellow-700 dark:bg-yellow-500/10 dark:text-yellow-400',

        blocked:
            'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',

        unmatched:
            'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    };

    return classes[status];
};

const includedLines = computed(
    (): SupplierInvoiceFormLine[] =>
        form.lines.filter(
            (line) => line.include,
        ),
);

const subtotal = computed(
    (): number =>
        includedLines.value.reduce(
            (total, line) =>
                total + grossAmount(line),
            0,
        ),
);

const discountTotal = computed(
    (): number =>
        includedLines.value.reduce(
            (total, line) =>
                total
                + Math.max(
                    decimalValue(
                        line.discount_amount,
                    ),
                    0,
                ),
            0,
        ),
);

const taxTotal = computed(
    (): number =>
        includedLines.value.reduce(
            (total, line) =>
                total + taxAmount(line),
            0,
        ),
);

const invoiceTotal = computed(
    (): number =>
        includedLines.value.reduce(
            (total, line) =>
                total + lineTotal(line),
            0,
        )
        + decimalValue(
            form.other_charges,
        )
        + decimalValue(
            form.rounding_adjustment,
        ),
);

const fieldError = (
    field: string,
): string | undefined => {
    const errors = form.errors as Record<
        string,
        string | undefined
    >;

    return errors[field];
};

const toPayloadLine = (
    line: SupplierInvoiceFormLine,
): SupplierInvoiceFormLinePayload => ({
    purchase_order_line_id:
        line.purchase_order_line_id,

    invoiced_quantity:
        line.invoiced_quantity.trim(),

    invoice_unit_price:
        line.invoice_unit_price.trim(),

    discount_amount:
        line.discount_amount.trim(),

    tax_rate:
        line.tax_rate.trim(),

    variance_reason:
        line.variance_reason.trim(),

    matches: line.matches
        .filter(
            (match) =>
                decimalValue(
                    match.matched_quantity,
                ) > 0,
        )
        .map(
            (
                match,
            ): SupplierInvoiceFormMatch => ({
                goods_receipt_line_id:
                    match.goods_receipt_line_id,

                matched_quantity:
                    match.matched_quantity
                        .trim(),
            }),
        ),
});

const submit = (): void => {
    form.transform(
        (
            data,
        ): SupplierInvoiceFormPayload => ({
            purchase_order_id:
                data.purchase_order_id,

            supplier_invoice_number:
                data.supplier_invoice_number
                    .trim(),

            invoice_date:
                data.invoice_date.trim(),

            posting_date:
                data.posting_date.trim(),

            due_date:
                data.due_date.trim(),

            currency_code:
                data.currency_code
                    .trim()
                    .toUpperCase(),

            exchange_rate:
                data.exchange_rate.trim(),

            other_charges:
                data.other_charges.trim(),

            rounding_adjustment:
                data.rounding_adjustment
                    .trim(),

            notes:
                data.notes.trim(),

            matching_notes:
                data.matching_notes.trim(),

            lines: data.lines
                .filter(
                    (line) => line.include,
                )
                .map(toPayloadLine),
        }),
    );

    if (
        props.supplierInvoice !== undefined
    ) {
        form.put(
            route(
                'supplier-invoices.update',
                props.supplierInvoice.id,
            ),
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        route('supplier-invoices.store'),
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
        <section
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="mb-5">
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Supplier Invoice Details
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Enter the supplier document details and
                    select the Purchase Order to match.
                </p>
            </div>

            <div
                class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4"
            >
                <div class="xl:col-span-2">
                    <label
                        for="purchase_order_id"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Purchase Order
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        id="purchase_order_id"
                        v-model="form.purchase_order_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option :value="null">
                            Select a Purchase Order
                        </option>

                        <option
                            v-for="purchaseOrder in props.purchaseOrders"
                            :key="purchaseOrder.id"
                            :value="purchaseOrder.id"
                        >
                            {{
                                purchaseOrder.document_number
                                ?? `Purchase Order #${purchaseOrder.id}`
                            }}
                            — {{ purchaseOrder.supplier_name }}
                            — {{ purchaseOrder.branch_name }}
                        </option>
                    </select>

                    <p
                        v-if="form.errors.purchase_order_id"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.purchase_order_id }}
                    </p>
                </div>

                <div class="xl:col-span-2">
                    <label
                        for="supplier_invoice_number"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier Invoice Number
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="supplier_invoice_number"
                        v-model="form.supplier_invoice_number"
                        type="text"
                        maxlength="160"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        placeholder="INV-2026-00125"
                    />

                    <p
                        v-if="form.errors.supplier_invoice_number"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            form.errors
                                .supplier_invoice_number
                        }}
                    </p>
                </div>

                <div>
                    <label
                        for="invoice_date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Invoice Date
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="invoice_date"
                        v-model="form.invoice_date"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="form.errors.invoice_date"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.invoice_date }}
                    </p>
                </div>

                <div>
                    <label
                        for="posting_date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Posting Date
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="posting_date"
                        v-model="form.posting_date"
                        type="date"
                        :min="
                            form.invoice_date
                            || undefined
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="form.errors.posting_date"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.posting_date }}
                    </p>
                </div>

                <div>
                    <label
                        for="due_date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Due Date
                    </label>

                    <input
                        id="due_date"
                        v-model="form.due_date"
                        type="date"
                        :min="
                            form.invoice_date
                            || undefined
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="form.errors.due_date"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.due_date }}
                    </p>
                </div>

                <div>
                    <label
                        for="currency_code"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Currency
                    </label>

                    <input
                        id="currency_code"
                        v-model="form.currency_code"
                        type="text"
                        maxlength="3"
                        readonly
                        class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm uppercase text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />

                    <p
                        v-if="form.errors.currency_code"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.currency_code }}
                    </p>
                </div>

                <div>
                    <label
                        for="exchange_rate"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Exchange Rate
                    </label>

                    <input
                        id="exchange_rate"
                        v-model="form.exchange_rate"
                        type="number"
                        min="0.00000001"
                        step="0.00000001"
                        readonly
                        class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2.5 text-right text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                    />

                    <p
                        v-if="form.errors.exchange_rate"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.exchange_rate }}
                    </p>
                </div>
            </div>

            <div
                v-if="selectedPurchaseOrder !== null"
                class="mt-5 grid grid-cols-1 gap-3 rounded-xl bg-gray-50 p-4 sm:grid-cols-2 xl:grid-cols-4 dark:bg-gray-950/60"
            >
                <div>
                    <p
                        class="text-xs uppercase text-gray-500"
                    >
                        Supplier
                    </p>

                    <p
                        class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                    >
                        {{
                            selectedPurchaseOrder
                                .supplier_name
                        }}
                    </p>

                    <p class="text-xs text-gray-500">
                        {{
                            selectedPurchaseOrder
                                .supplier_code
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs uppercase text-gray-500"
                    >
                        Branch
                    </p>

                    <p
                        class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                    >
                        {{
                            selectedPurchaseOrder
                                .branch_name
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs uppercase text-gray-500"
                    >
                        Currency
                    </p>

                    <p
                        class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                    >
                        {{
                            selectedPurchaseOrder
                                .currency_code
                        }}
                        @
                        {{
                            selectedPurchaseOrder
                                .exchange_rate
                        }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs uppercase text-gray-500"
                    >
                        Payment Terms
                    </p>

                    <p
                        class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                    >
                        {{
                            selectedPurchaseOrder
                                .payment_terms_days
                        }}
                        days
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
                    Three-Way Matching
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Invoice quantities cannot exceed
                    accepted, uninvoiced quantities from
                    posted Goods Receipts.
                </p>

                <p
                    v-if="fieldError('lines')"
                    class="mt-2 text-sm text-red-600"
                >
                    {{ fieldError('lines') }}
                </p>
            </div>

            <div
                v-if="form.lines.length === 0"
                class="px-5 py-16 text-center text-sm text-gray-500"
            >
                Select a Purchase Order with available
                Goods Receipt quantities.
            </div>

            <div
                v-else
                class="overflow-x-auto"
            >
                <table class="min-w-[1600px] w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50"
                        >
                            <th class="w-16 px-4 py-3">
                                Use
                            </th>

                            <th class="min-w-64 px-4 py-3">
                                Product
                            </th>

                            <th
                                class="w-32 px-4 py-3 text-right"
                            >
                                PO Qty
                            </th>

                            <th
                                class="w-32 px-4 py-3 text-right"
                            >
                                Received
                            </th>

                            <th
                                class="w-32 px-4 py-3 text-right"
                            >
                                Available
                            </th>

                            <th
                                class="w-40 px-4 py-3 text-right"
                            >
                                Invoice Qty
                            </th>

                            <th
                                class="w-40 px-4 py-3 text-right"
                            >
                                Unit Price
                            </th>

                            <th
                                class="w-40 px-4 py-3 text-right"
                            >
                                Discount
                            </th>

                            <th
                                class="w-32 px-4 py-3 text-right"
                            >
                                Tax %
                            </th>

                            <th
                                class="w-36 px-4 py-3 text-right"
                            >
                                Matched
                            </th>

                            <th class="w-36 px-4 py-3">
                                Status
                            </th>

                            <th
                                class="w-44 px-4 py-3 text-right"
                            >
                                Line Total
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <template
                            v-for="(line, index) in form.lines"
                            :key="
                                line.purchase_order_line_id
                            "
                        >
                            <tr
                                class="border-b border-gray-100 align-top dark:border-gray-800"
                                :class="{
                                    'opacity-50':
                                        !line.include,
                                }"
                            >
                                <td class="px-4 py-4">
                                    <input
                                        v-model="line.include"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                                    />
                                </td>

                                <td class="px-4 py-4">
                                    <p
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{
                                            sourceLine(
                                                line.purchase_order_line_id,
                                            )?.product_name
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        {{
                                            sourceLine(
                                                line.purchase_order_line_id,
                                            )?.product_sku
                                        }}
                                        ·
                                        {{
                                            sourceLine(
                                                line.purchase_order_line_id,
                                            )?.unit_code
                                        }}
                                        ·
                                        {{
                                            sourceLine(
                                                line.purchase_order_line_id,
                                            )?.product_type
                                        }}
                                    </p>
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        sourceLine(
                                            line.purchase_order_line_id,
                                        )?.ordered_quantity
                                    }}
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        sourceLine(
                                            line.purchase_order_line_id,
                                        )?.received_quantity
                                    }}
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        sourceLine(
                                            line.purchase_order_line_id,
                                        )?.available_to_invoice_quantity
                                    }}
                                </td>

                                <td class="px-4 py-4">
                                    <input
                                        v-model="
                                            line.invoiced_quantity
                                        "
                                        type="number"
                                        min="0"
                                        :step="
                                            quantityStep(
                                                line.purchase_order_line_id,
                                            )
                                        "
                                        :disabled="
                                            !line.include
                                        "
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                        @input="
                                            autoAllocate(
                                                line,
                                            )
                                        "
                                    />

                                    <p
                                        v-if="
                                            fieldError(
                                                `lines.${index}.invoiced_quantity`,
                                            )
                                        "
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{
                                            fieldError(
                                                `lines.${index}.invoiced_quantity`,
                                            )
                                        }}
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    <input
                                        v-model="
                                            line.invoice_unit_price
                                        "
                                        type="number"
                                        min="0"
                                        step="0.000001"
                                        :disabled="
                                            !line.include
                                        "
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                    />

                                    <p
                                        v-if="
                                            fieldError(
                                                `lines.${index}.invoice_unit_price`,
                                            )
                                        "
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{
                                            fieldError(
                                                `lines.${index}.invoice_unit_price`,
                                            )
                                        }}
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    <input
                                        v-model="
                                            line.discount_amount
                                        "
                                        type="number"
                                        min="0"
                                        step="0.000001"
                                        :disabled="
                                            !line.include
                                        "
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                    />

                                    <p
                                        v-if="
                                            fieldError(
                                                `lines.${index}.discount_amount`,
                                            )
                                        "
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{
                                            fieldError(
                                                `lines.${index}.discount_amount`,
                                            )
                                        }}
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    <input
                                        v-model="
                                            line.tax_rate
                                        "
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.000001"
                                        :disabled="
                                            !line.include
                                        "
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                    />

                                    <p
                                        v-if="
                                            fieldError(
                                                `lines.${index}.tax_rate`,
                                            )
                                        "
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{
                                            fieldError(
                                                `lines.${index}.tax_rate`,
                                            )
                                        }}
                                    </p>
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatDecimal(
                                            matchedQuantity(
                                                line,
                                            ),
                                        )
                                    }}
                                </td>

                                <td class="px-4 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            matchStatusClass(
                                                matchStatus(
                                                    line,
                                                ),
                                            )
                                        "
                                    >
                                        {{
                                            matchStatusLabel(
                                                matchStatus(
                                                    line,
                                                ),
                                            )
                                        }}
                                    </span>
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatAmount(
                                            lineTotal(
                                                line,
                                            ),
                                        )
                                    }}
                                </td>
                            </tr>

                            <tr
                                v-if="line.include"
                                class="border-b border-gray-200 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-950/30"
                            >
                                <td
                                    colspan="12"
                                    class="px-4 py-4"
                                >
                                    <div
                                        class="flex flex-col gap-4 xl:flex-row xl:items-start"
                                    >
                                        <div
                                            class="min-w-0 flex-1"
                                        >
                                            <div
                                                class="mb-3 flex flex-wrap items-center justify-between gap-2"
                                            >
                                                <p
                                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                                >
                                                    Goods Receipt
                                                    Allocation
                                                </p>

                                                <div
                                                    class="flex gap-3"
                                                >
                                                    <button
                                                        type="button"
                                                        class="text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                                        @click="
                                                            restorePurchaseOrderValues(
                                                                line,
                                                            )
                                                        "
                                                    >
                                                        Use PO values
                                                    </button>

                                                    <button
                                                        type="button"
                                                        class="text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                                        @click="
                                                            autoAllocate(
                                                                line,
                                                            )
                                                        "
                                                    >
                                                        Auto-allocate
                                                    </button>
                                                </div>
                                            </div>

                                            <div
                                                class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3"
                                            >
                                                <div
                                                    v-for="(match, matchIndex) in line.matches"
                                                    :key="
                                                        match.goods_receipt_line_id
                                                    "
                                                    class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
                                                >
                                                    <div
                                                        class="flex items-start justify-between gap-3"
                                                    >
                                                        <div>
                                                            <p
                                                                class="text-sm font-medium text-gray-900 dark:text-white"
                                                            >
                                                                {{
                                                                    receiptOption(
                                                                        line.purchase_order_line_id,
                                                                        match.goods_receipt_line_id,
                                                                    )?.receipt_number
                                                                    ?? 'Goods Receipt'
                                                                }}
                                                            </p>

                                                            <p
                                                                class="mt-1 text-xs text-gray-500"
                                                            >
                                                                {{
                                                                    receiptOption(
                                                                        line.purchase_order_line_id,
                                                                        match.goods_receipt_line_id,
                                                                    )?.receipt_date
                                                                    ?? '—'
                                                                }}
                                                            </p>
                                                        </div>

                                                        <p
                                                            class="text-xs text-gray-500"
                                                        >
                                                            Available:
                                                            {{
                                                                receiptOption(
                                                                    line.purchase_order_line_id,
                                                                    match.goods_receipt_line_id,
                                                                )?.available_quantity
                                                                ?? '0.000000'
                                                            }}
                                                        </p>
                                                    </div>

                                                    <input
                                                        v-model="
                                                            match.matched_quantity
                                                        "
                                                        type="number"
                                                        min="0"
                                                        :step="
                                                            quantityStep(
                                                                line.purchase_order_line_id,
                                                            )
                                                        "
                                                        class="mt-3 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                                    />

                                                    <p
                                                        v-if="
                                                            fieldError(
                                                                `lines.${index}.matches.${matchIndex}.matched_quantity`,
                                                            )
                                                        "
                                                        class="mt-1 text-xs text-red-600"
                                                    >
                                                        {{
                                                            fieldError(
                                                                `lines.${index}.matches.${matchIndex}.matched_quantity`,
                                                            )
                                                        }}
                                                    </p>
                                                </div>
                                            </div>

                                            <p
                                                v-if="
                                                    Math.abs(
                                                        matchedQuantity(
                                                            line,
                                                        )
                                                        - decimalValue(
                                                            line.invoiced_quantity,
                                                        ),
                                                    ) > 0.000001
                                                "
                                                class="mt-3 text-xs font-medium text-red-600"
                                            >
                                                The invoice
                                                quantity must be
                                                fully allocated to
                                                Goods Receipt
                                                lines.
                                            </p>
                                        </div>

                                        <div
                                            class="w-full xl:w-96"
                                        >
                                            <label
                                                :for="
                                                    `variance_reason_${index}`
                                                "
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >
                                                Variance Reason
                                            </label>

                                            <textarea
                                                :id="
                                                    `variance_reason_${index}`
                                                "
                                                v-model="
                                                    line.variance_reason
                                                "
                                                rows="4"
                                                maxlength="500"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                                :placeholder="
                                                    matchStatus(
                                                        line,
                                                    ) === 'variance'
                                                        ? 'Required before validation'
                                                        : 'Optional'
                                                "
                                            />

                                            <p
                                                v-if="
                                                    fieldError(
                                                        `lines.${index}.variance_reason`,
                                                    )
                                                "
                                                class="mt-1 text-xs text-red-600"
                                            >
                                                {{
                                                    fieldError(
                                                        `lines.${index}.variance_reason`,
                                                    )
                                                }}
                                            </p>

                                            <p
                                                v-if="
                                                    exceedsReceiptAvailability(
                                                        line,
                                                    )
                                                "
                                                class="mt-2 text-xs font-medium text-red-600"
                                            >
                                                Quantity exceeds
                                                accepted Goods
                                                Receipt
                                                availability.
                                            </p>

                                            <p
                                                v-else-if="
                                                    exceedsPurchaseOrderQuantity(
                                                        line,
                                                    )
                                                "
                                                class="mt-2 text-xs font-medium text-yellow-600"
                                            >
                                                Cumulative quantity
                                                exceeds the Purchase
                                                Order quantity.
                                            </p>
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
            class="grid grid-cols-1 gap-6 xl:grid-cols-3"
        >
            <section
                class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm xl:col-span-2 dark:border-gray-800 dark:bg-gray-900"
            >
                <div>
                    <label
                        for="matching_notes"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Matching Notes
                    </label>

                    <textarea
                        id="matching_notes"
                        v-model="form.matching_notes"
                        rows="4"
                        maxlength="4000"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="form.errors.matching_notes"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.matching_notes }}
                    </p>
                </div>

                <div>
                    <label
                        for="notes"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Internal Notes
                    </label>

                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="4"
                        maxlength="4000"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="form.errors.notes"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ form.errors.notes }}
                    </p>
                </div>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Invoice Totals
                </h2>

                <div class="mt-5 space-y-4">
                    <div
                        class="flex justify-between text-sm"
                    >
                        <span class="text-gray-500">
                            Gross subtotal
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{ formatAmount(subtotal) }}
                        </span>
                    </div>

                    <div
                        class="flex justify-between text-sm"
                    >
                        <span class="text-gray-500">
                            Discount
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{ formatAmount(discountTotal) }}
                        </span>
                    </div>

                    <div
                        class="flex justify-between text-sm"
                    >
                        <span class="text-gray-500">
                            Tax
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{ formatAmount(taxTotal) }}
                        </span>
                    </div>

                    <div>
                        <label
                            for="other_charges"
                            class="mb-1.5 block text-sm text-gray-500"
                        >
                            Other Charges
                        </label>

                        <input
                            id="other_charges"
                            v-model="form.other_charges"
                            type="number"
                            min="0"
                            step="0.000001"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        />

                        <p
                            v-if="form.errors.other_charges"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ form.errors.other_charges }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="rounding_adjustment"
                            class="mb-1.5 block text-sm text-gray-500"
                        >
                            Rounding Adjustment
                        </label>

                        <input
                            id="rounding_adjustment"
                            v-model="
                                form.rounding_adjustment
                            "
                            type="number"
                            step="0.000001"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        />

                        <p
                            v-if="
                                form.errors
                                    .rounding_adjustment
                            "
                            class="mt-1 text-sm text-red-600"
                        >
                            {{
                                form.errors
                                    .rounding_adjustment
                            }}
                        </p>
                    </div>

                    <div
                        class="border-t border-gray-200 pt-4 dark:border-gray-800"
                    >
                        <div
                            class="flex items-end justify-between gap-4"
                        >
                            <div>
                                <p
                                    class="text-sm text-gray-500"
                                >
                                    Total
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-400"
                                >
                                    {{
                                        form.currency_code
                                        || 'Currency'
                                    }}
                                </p>
                            </div>

                            <p
                                class="text-2xl font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    formatAmount(
                                        invoiceTotal,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div
            class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
        >
            <Link
                :href="
                    isEditing
                    && props.supplierInvoice
                        ? route(
                            'supplier-invoices.show',
                            props.supplierInvoice.id,
                        )
                        : route(
                            'supplier-invoices.index',
                        )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Cancel
            </Link>

            <button
                type="submit"
                :disabled="form.processing"
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{
                    form.processing
                        ? 'Saving...'
                        : isEditing
                            ? 'Update Supplier Invoice'
                            : 'Create Supplier Invoice'
                }}
            </button>
        </div>
    </form>
</template>