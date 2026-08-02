<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import {
    computed,
    watch,
} from 'vue';

import type {
    ExistingPurchaseReturnFormData,
    PurchaseReturnFormData,
    PurchaseReturnFormLine,
    PurchaseReturnFormLinePayload,
    PurchaseReturnFormPayload,
    PurchaseReturnFormProps,
    PurchaseReturnGoodsReceiptLineOption,
    PurchaseReturnSupplierInvoiceOption,
} from '@/Types/purchase-return';

interface Props extends PurchaseReturnFormProps {
    purchaseReturn?: ExistingPurchaseReturnFormData;
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
    if (!Number.isFinite(value)) {
        return Number(0).toFixed(places);
    }

    return value.toFixed(places);
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

const initialGoodsReceiptId =
    props.purchaseReturn?.goods_receipt_id
    ?? props.selectedGoodsReceiptId
    ?? null;

const form = useForm<PurchaseReturnFormData>({
    goods_receipt_id:
        initialGoodsReceiptId,

    supplier_invoice_id:
        props.purchaseReturn
            ?.supplier_invoice_id
        ?? null,

    return_date:
        props.purchaseReturn?.return_date
        ?? props.defaults.return_date,

    posting_date:
        props.purchaseReturn?.posting_date
        ?? props.defaults.posting_date,

    supplier_reference:
        props.purchaseReturn
            ?.supplier_reference
        ?? '',

    return_reason:
        props.purchaseReturn?.return_reason
        ?? '',

    notes:
        props.purchaseReturn?.notes
        ?? '',

    lines:
        props.purchaseReturn?.lines.map(
            (
                line,
            ): PurchaseReturnFormLine => ({
                include: true,

                goods_receipt_line_id:
                    line.goods_receipt_line_id,

                return_quantity:
                    line.return_quantity,

                return_reason:
                    line.return_reason,

                notes:
                    line.notes,
            }),
        ) ?? [],
});

const isEditing = computed(
    (): boolean =>
        props.purchaseReturn !== undefined,
);

const selectedGoodsReceipt = computed(() => {
    if (form.goods_receipt_id === null) {
        return null;
    }

    return props.goodsReceipts.find(
        (goodsReceipt) =>
            goodsReceipt.id
            === form.goods_receipt_id,
    ) ?? null;
});

const supplierInvoiceOptions = computed(
    (): PurchaseReturnSupplierInvoiceOption[] =>
        selectedGoodsReceipt.value
            ?.supplier_invoices
        ?? [],
);

const selectedSupplierInvoice = computed(
    (): PurchaseReturnSupplierInvoiceOption | null => {
        if (
            form.supplier_invoice_id
            === null
        ) {
            return null;
        }

        return supplierInvoiceOptions.value.find(
            (supplierInvoice) =>
                supplierInvoice.id
                === form.supplier_invoice_id,
        ) ?? null;
    },
);

const sourceLine = (
    goodsReceiptLineId: number,
): PurchaseReturnGoodsReceiptLineOption | null => {
    return selectedGoodsReceipt.value
        ?.lines
        .find(
            (line) =>
                line.id
                === goodsReceiptLineId,
        )
        ?? null;
};

const isSerialized = (
    line: PurchaseReturnGoodsReceiptLineOption,
): boolean => {
    return line.serial_numbers.length > 0;
};

const serializedLineHasActivity = (
    line: PurchaseReturnGoodsReceiptLineOption,
): boolean => {
    if (!isSerialized(line)) {
        return false;
    }

    return decimalValue(
        line.returned_quantity,
    ) > 0
        || decimalValue(
            line.return_reserved_quantity,
        ) > 0;
};

const newFormLine = (
    line: PurchaseReturnGoodsReceiptLineOption,
): PurchaseReturnFormLine => ({
    include: false,

    goods_receipt_line_id:
        line.id,

    return_quantity:
        line.returnable_quantity,

    return_reason: '',
    notes: '',
});

const rebuildLines = (): void => {
    const goodsReceipt =
        selectedGoodsReceipt.value;

    form.supplier_invoice_id = null;

    if (goodsReceipt === null) {
        form.lines = [];

        return;
    }

    form.lines =
        goodsReceipt.lines.map(
            newFormLine,
        );
};

if (
    !isEditing.value
    && selectedGoodsReceipt.value !== null
) {
    rebuildLines();
}

watch(
    () => form.goods_receipt_id,
    (
        goodsReceiptId,
        previousGoodsReceiptId,
    ) => {
        if (
            goodsReceiptId
            === previousGoodsReceiptId
        ) {
            return;
        }

        rebuildLines();
    },
);

watch(
    supplierInvoiceOptions,
    (
        options,
    ) => {
        if (
            form.supplier_invoice_id
            === null
        ) {
            return;
        }

        const stillAvailable = options.some(
            (supplierInvoice) =>
                supplierInvoice.id
                === form.supplier_invoice_id,
        );

        if (!stillAvailable) {
            form.supplier_invoice_id = null;
        }
    },
);

watch(
    () => form.return_date,
    (
        returnDate,
        previousReturnDate,
    ) => {
        if (
            form.posting_date === ''
            || form.posting_date
                === previousReturnDate
        ) {
            form.posting_date =
                returnDate;
        }
    },
);

const quantityStep = (
    goodsReceiptLineId: number,
): string => {
    const line = sourceLine(
        goodsReceiptLineId,
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

const quantityMaximum = (
    line: PurchaseReturnFormLine,
): string | undefined => {
    return sourceLine(
        line.goods_receipt_line_id,
    )?.returnable_quantity;
};

const exceedsReturnableQuantity = (
    line: PurchaseReturnFormLine,
): boolean => {
    const source = sourceLine(
        line.goods_receipt_line_id,
    );

    if (source === null) {
        return true;
    }

    return decimalValue(
        line.return_quantity,
    ) > decimalValue(
        source.returnable_quantity,
    );
};

const serializedQuantityIsInvalid = (
    line: PurchaseReturnFormLine,
): boolean => {
    const source = sourceLine(
        line.goods_receipt_line_id,
    );

    if (
        source === null
        || !isSerialized(source)
    ) {
        return false;
    }

    return serializedLineHasActivity(source)
        || Math.abs(
            decimalValue(
                line.return_quantity,
            ) - decimalValue(
                source.accepted_quantity,
            ),
        ) > 0.000001;
};

const lineIsUnavailable = (
    line: PurchaseReturnFormLine,
): boolean => {
    const source = sourceLine(
        line.goods_receipt_line_id,
    );

    if (source === null) {
        return true;
    }

    return decimalValue(
        source.returnable_quantity,
    ) <= 0
        || serializedLineHasActivity(source);
};

const handleIncludeChange = (
    line: PurchaseReturnFormLine,
): void => {
    if (!line.include) {
        return;
    }

    const source = sourceLine(
        line.goods_receipt_line_id,
    );

    if (source === null) {
        line.include = false;

        return;
    }

    if (serializedLineHasActivity(source)) {
        line.include = false;

        return;
    }

    if (isSerialized(source)) {
        line.return_quantity =
            source.accepted_quantity;

        return;
    }

    if (
        decimalValue(
            line.return_quantity,
        ) <= 0
    ) {
        line.return_quantity =
            source.returnable_quantity;
    }
};

const selectedLines = computed(
    (): PurchaseReturnFormLine[] =>
        form.lines.filter(
            (line) =>
                line.include
                && decimalValue(
                    line.return_quantity,
                ) > 0,
        ),
);

const totalReturnQuantity = computed(
    (): number =>
        selectedLines.value.reduce(
            (total, line) =>
                total
                + decimalValue(
                    line.return_quantity,
                ),
            0,
        ),
);

const supplierValue = (
    line: PurchaseReturnFormLine,
): number => {
    const source = sourceLine(
        line.goods_receipt_line_id,
    );

    if (source === null) {
        return 0;
    }

    return decimalValue(
        line.return_quantity,
    ) * decimalValue(
        source.supplier_unit_cost,
    );
};

const totalSupplierValue = computed(
    (): number =>
        selectedLines.value.reduce(
            (total, line) =>
                total
                + supplierValue(line),
            0,
        ),
);

const selectedLineCount = computed(
    (): number =>
        selectedLines.value.length,
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
    line: PurchaseReturnFormLine,
): PurchaseReturnFormLinePayload => ({
    goods_receipt_line_id:
        line.goods_receipt_line_id,

    return_quantity:
        line.return_quantity.trim(),

    return_reason:
        line.return_reason.trim(),

    notes:
        line.notes.trim(),
});

const submit = (): void => {
    form.transform(
        (
            data,
        ): PurchaseReturnFormPayload => ({
            goods_receipt_id:
                data.goods_receipt_id,

            supplier_invoice_id:
                data.supplier_invoice_id,

            return_date:
                data.return_date.trim(),

            posting_date:
                data.posting_date.trim(),

            supplier_reference:
                data.supplier_reference
                    .trim(),

            return_reason:
                data.return_reason.trim(),

            notes:
                data.notes.trim(),

            lines: data.lines
                .filter(
                    (line) =>
                        line.include
                        && decimalValue(
                            line.return_quantity,
                        ) > 0,
                )
                .map(toPayloadLine),
        }),
    );

    if (
        props.purchaseReturn !== undefined
    ) {
        form.put(
            route(
                'purchase-returns.update',
                props.purchaseReturn.id,
            ),
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        route(
            'purchase-returns.store',
        ),
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
                    Return Information
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Select a posted Goods Receipt and record
                    the supplier return details.
                </p>
            </div>

            <div
                class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4"
            >
                <div
                    class="md:col-span-2 xl:col-span-2"
                >
                    <label
                        for="purchase-return-goods-receipt"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Goods Receipt
                        <span class="text-red-500">
                            *
                        </span>
                    </label>

                    <select
                        id="purchase-return-goods-receipt"
                        v-model="form.goods_receipt_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option :value="null">
                            Select posted Goods Receipt
                        </option>

                        <option
                            v-for="goodsReceipt in props.goodsReceipts"
                            :key="goodsReceipt.id"
                            :value="goodsReceipt.id"
                        >
                            {{
                                goodsReceipt.receipt_number
                                ?? `Goods Receipt #${goodsReceipt.id}`
                            }}
                            —
                            {{
                                goodsReceipt.supplier_name
                            }}
                            —
                            {{
                                goodsReceipt.branch_name
                            }}
                        </option>
                    </select>

                    <p
                        v-if="
                            fieldError(
                                'goods_receipt_id',
                            )
                        "
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            fieldError(
                                'goods_receipt_id',
                            )
                        }}
                    </p>
                </div>

                <div
                    class="md:col-span-2 xl:col-span-2"
                >
                    <label
                        for="purchase-return-supplier-invoice"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Linked Supplier Invoice
                    </label>

                    <select
                        id="purchase-return-supplier-invoice"
                        v-model="
                            form.supplier_invoice_id
                        "
                        :disabled="
                            selectedGoodsReceipt
                                === null
                            || supplierInvoiceOptions
                                .length === 0
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                    >
                        <option :value="null">
                            No linked Supplier Invoice
                        </option>

                        <option
                            v-for="supplierInvoice in supplierInvoiceOptions"
                            :key="supplierInvoice.id"
                            :value="supplierInvoice.id"
                        >
                            {{
                                supplierInvoice
                                    .document_number
                                ?? supplierInvoice
                                    .supplier_invoice_number
                            }}
                            —
                            {{
                                supplierInvoice
                                    .supplier_invoice_number
                            }}
                            —
                            {{
                                supplierInvoice.status
                            }}
                        </option>
                    </select>

                    <p
                        v-if="
                            fieldError(
                                'supplier_invoice_id',
                            )
                        "
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            fieldError(
                                'supplier_invoice_id',
                            )
                        }}
                    </p>

                    <p
                        v-else-if="
                            selectedGoodsReceipt
                                !== null
                            && supplierInvoiceOptions
                                .length === 0
                        "
                        class="mt-1 text-xs text-gray-500"
                    >
                        No eligible Supplier Invoice is
                        matched to this Goods Receipt.
                    </p>
                </div>

                <div>
                    <label
                        for="purchase-return-date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Return Date
                        <span class="text-red-500">
                            *
                        </span>
                    </label>

                    <input
                        id="purchase-return-date"
                        v-model="form.return_date"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="
                            fieldError(
                                'return_date',
                            )
                        "
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            fieldError(
                                'return_date',
                            )
                        }}
                    </p>
                </div>

                <div>
                    <label
                        for="purchase-return-posting-date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Posting Date
                        <span class="text-red-500">
                            *
                        </span>
                    </label>

                    <input
                        id="purchase-return-posting-date"
                        v-model="form.posting_date"
                        type="date"
                        :min="
                            form.return_date
                            || undefined
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="
                            fieldError(
                                'posting_date',
                            )
                        "
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            fieldError(
                                'posting_date',
                            )
                        }}
                    </p>
                </div>

                <div class="md:col-span-2">
                    <label
                        for="purchase-return-supplier-reference"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier Reference
                    </label>

                    <input
                        id="purchase-return-supplier-reference"
                        v-model="
                            form.supplier_reference
                        "
                        type="text"
                        maxlength="160"
                        placeholder="RMA, return authorization, or reference number"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="
                            fieldError(
                                'supplier_reference',
                            )
                        "
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            fieldError(
                                'supplier_reference',
                            )
                        }}
                    </p>
                </div>

                <div
                    class="md:col-span-2 xl:col-span-4"
                >
                    <label
                        for="purchase-return-reason"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Return Reason
                        <span class="text-red-500">
                            *
                        </span>
                    </label>

                    <textarea
                        id="purchase-return-reason"
                        v-model="form.return_reason"
                        rows="3"
                        maxlength="500"
                        placeholder="Explain why the goods are being returned"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="
                            fieldError(
                                'return_reason',
                            )
                        "
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            fieldError(
                                'return_reason',
                            )
                        }}
                    </p>
                </div>

                <div
                    class="md:col-span-2 xl:col-span-4"
                >
                    <label
                        for="purchase-return-notes"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Internal Notes
                    </label>

                    <textarea
                        id="purchase-return-notes"
                        v-model="form.notes"
                        rows="3"
                        maxlength="4000"
                        placeholder="Optional internal notes"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('notes')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('notes') }}
                    </p>
                </div>
            </div>
        </section>

        <section
            v-if="selectedGoodsReceipt !== null"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p class="text-xs uppercase text-gray-500">
                    Goods Receipt
                </p>

                <p
                    class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        selectedGoodsReceipt
                            .receipt_number
                        ?? `Receipt #${selectedGoodsReceipt.id}`
                    }}
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    {{
                        selectedGoodsReceipt
                            .receipt_date
                        ?? '—'
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p class="text-xs uppercase text-gray-500">
                    Purchase Order
                </p>

                <p
                    class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        selectedGoodsReceipt
                            .purchase_order_number
                        ?? `PO #${selectedGoodsReceipt.purchase_order_id}`
                    }}
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    Source commercial document
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p class="text-xs uppercase text-gray-500">
                    Supplier
                </p>

                <p
                    class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        selectedGoodsReceipt
                            .supplier_name
                    }}
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    {{
                        selectedGoodsReceipt
                            .supplier_code
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p class="text-xs uppercase text-gray-500">
                    Branch
                </p>

                <p
                    class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        selectedGoodsReceipt
                            .branch_name
                    }}
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    {{
                        selectedGoodsReceipt
                            .branch_code
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p class="text-xs uppercase text-gray-500">
                    Warehouse
                </p>

                <p
                    class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        selectedGoodsReceipt
                            .warehouse_name
                        ?? 'No warehouse'
                    }}
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    {{
                        selectedGoodsReceipt
                            .warehouse_code
                        ?? '—'
                    }}
                </p>
            </div>
        </section>

        <section
            v-if="selectedSupplierInvoice !== null"
            class="rounded-2xl border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-500/30 dark:bg-indigo-500/10"
        >
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p
                        class="text-sm font-semibold text-indigo-900 dark:text-indigo-200"
                    >
                        Linked Supplier Invoice
                    </p>

                    <p
                        class="mt-1 text-sm text-indigo-700 dark:text-indigo-300"
                    >
                        {{
                            selectedSupplierInvoice
                                .document_number
                            ?? selectedSupplierInvoice
                                .supplier_invoice_number
                        }}
                        ·
                        {{
                            selectedSupplierInvoice
                                .supplier_invoice_number
                        }}
                        ·
                        {{
                            selectedSupplierInvoice
                                .status
                        }}
                    </p>
                </div>

                <p
                    class="text-sm font-semibold text-indigo-900 dark:text-indigo-200"
                >
                    {{
                        formatAmount(
                            selectedSupplierInvoice
                                .total_amount,
                        )
                    }}
                </p>
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
                    Return Lines
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Select only the receipt lines being
                    returned. Available quantity excludes
                    posted and approved return activity.
                </p>

                <p
                    v-if="fieldError('lines')"
                    class="mt-3 text-sm text-red-600"
                >
                    {{ fieldError('lines') }}
                </p>
            </div>

            <div
                v-if="form.lines.length === 0"
                class="px-5 py-16 text-center"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Select a posted Goods Receipt with
                    returnable quantities.
                </p>
            </div>

            <div
                v-else
                class="overflow-x-auto"
            >
                <table class="w-full min-w-[1600px]">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-400"
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
                                Accepted
                            </th>

                            <th
                                class="w-32 px-4 py-3 text-right"
                            >
                                Reserved
                            </th>

                            <th
                                class="w-32 px-4 py-3 text-right"
                            >
                                Returned
                            </th>

                            <th
                                class="w-32 px-4 py-3 text-right"
                            >
                                Available
                            </th>

                            <th
                                class="w-40 px-4 py-3 text-right"
                            >
                                Return Qty
                            </th>

                            <th
                                class="w-40 px-4 py-3 text-right"
                            >
                                Supplier Cost
                            </th>

                            <th
                                class="w-44 px-4 py-3 text-right"
                            >
                                Supplier Value
                            </th>

                            <th class="min-w-56 px-4 py-3">
                                Batch / Serial
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <template
                            v-for="(line, index) in form.lines"
                            :key="
                                line.goods_receipt_line_id
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
                                        :disabled="
                                            lineIsUnavailable(
                                                line,
                                            )
                                        "
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-50"
                                        @change="
                                            handleIncludeChange(
                                                line,
                                            )
                                        "
                                    />
                                </td>

                                <td class="px-4 py-4">
                                    <p
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.product_name
                                            ?? 'Unavailable source line'
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        {{
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.product_sku
                                            ?? '—'
                                        }}
                                        ·
                                        {{
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.unit_code
                                            ?? '—'
                                        }}
                                        ·
                                        {{
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.product_type
                                            ?? '—'
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.storage_location
                                        "
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        Storage:
                                        {{
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.storage_location
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            ) === null
                                        "
                                        class="mt-2 text-xs font-medium text-red-600"
                                    >
                                        This source line is
                                        no longer available
                                        for return.
                                    </p>
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatQuantity(
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.accepted_quantity
                                            ?? '0',
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatQuantity(
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.return_reserved_quantity
                                            ?? '0',
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatQuantity(
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.returned_quantity
                                            ?? '0',
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatQuantity(
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.returnable_quantity
                                            ?? '0',
                                        )
                                    }}
                                </td>

                                <td class="px-4 py-4">
                                    <input
                                        v-model="
                                            line.return_quantity
                                        "
                                        type="number"
                                        min="0"
                                        :max="
                                            quantityMaximum(
                                                line,
                                            )
                                        "
                                        :step="
                                            quantityStep(
                                                line.goods_receipt_line_id,
                                            )
                                        "
                                        :disabled="
                                            !line.include
                                            || lineIsUnavailable(
                                                line,
                                            )
                                            || (
                                                sourceLine(
                                                    line.goods_receipt_line_id,
                                                )?.serial_numbers
                                                    .length
                                                ?? 0
                                            ) > 0
                                        "
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                    />

                                    <p
                                        v-if="
                                            fieldError(
                                                `lines.${index}.return_quantity`,
                                            )
                                        "
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{
                                            fieldError(
                                                `lines.${index}.return_quantity`,
                                            )
                                        }}
                                    </p>

                                    <p
                                        v-else-if="
                                            line.include
                                            && exceedsReturnableQuantity(
                                                line,
                                            )
                                        "
                                        class="mt-1 text-xs font-medium text-red-600"
                                    >
                                        Quantity exceeds the
                                        currently returnable
                                        amount.
                                    </p>

                                    <p
                                        v-else-if="
                                            line.include
                                            && serializedQuantityIsInvalid(
                                                line,
                                            )
                                        "
                                        class="mt-1 text-xs font-medium text-red-600"
                                    >
                                        Serialized receipt
                                        lines must be
                                        returned as one
                                        complete original
                                        line.
                                    </p>
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatAmount(
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.supplier_unit_cost
                                            ?? '0',
                                        )
                                    }}
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatAmount(
                                            supplierValue(
                                                line,
                                            ),
                                        )
                                    }}
                                </td>

                                <td class="px-4 py-4">
                                    <p
                                        class="text-sm text-gray-900 dark:text-white"
                                    >
                                        Batch:
                                        {{
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.batch_number
                                            ?? '—'
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        Serial count:
                                        {{
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.serial_numbers
                                                .length
                                            ?? 0
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.expiry_date
                                        "
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        Expiry:
                                        {{
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )?.expiry_date
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            sourceLine(
                                                line.goods_receipt_line_id,
                                            )
                                            && serializedLineHasActivity(
                                                sourceLine(
                                                    line.goods_receipt_line_id,
                                                )!,
                                            )
                                        "
                                        class="mt-2 text-xs font-medium text-red-600"
                                    >
                                        This serialized line
                                        already has return
                                        activity and cannot
                                        be selected.
                                    </p>
                                </td>
                            </tr>

                            <tr
                                v-if="line.include"
                                class="border-b border-gray-200 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-950/30"
                            >
                                <td
                                    colspan="10"
                                    class="px-4 py-4"
                                >
                                    <div
                                        class="grid grid-cols-1 gap-4 lg:grid-cols-2"
                                    >
                                        <div>
                                            <label
                                                :for="
                                                    `purchase-return-line-reason-${index}`
                                                "
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >
                                                Line Return
                                                Reason
                                            </label>

                                            <textarea
                                                :id="
                                                    `purchase-return-line-reason-${index}`
                                                "
                                                v-model="
                                                    line.return_reason
                                                "
                                                rows="3"
                                                maxlength="500"
                                                placeholder="Optional line-specific reason"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                            />

                                            <p
                                                v-if="
                                                    fieldError(
                                                        `lines.${index}.return_reason`,
                                                    )
                                                "
                                                class="mt-1 text-xs text-red-600"
                                            >
                                                {{
                                                    fieldError(
                                                        `lines.${index}.return_reason`,
                                                    )
                                                }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                :for="
                                                    `purchase-return-line-notes-${index}`
                                                "
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >
                                                Line Notes
                                            </label>

                                            <textarea
                                                :id="
                                                    `purchase-return-line-notes-${index}`
                                                "
                                                v-model="
                                                    line.notes
                                                "
                                                rows="3"
                                                maxlength="2000"
                                                placeholder="Optional handling or condition notes"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                            />

                                            <p
                                                v-if="
                                                    fieldError(
                                                        `lines.${index}.notes`,
                                                    )
                                                "
                                                class="mt-1 text-xs text-red-600"
                                            >
                                                {{
                                                    fieldError(
                                                        `lines.${index}.notes`,
                                                    )
                                                }}
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
                class="rounded-2xl border border-blue-200 bg-blue-50 p-5 xl:col-span-2 dark:border-blue-500/30 dark:bg-blue-500/10"
            >
                <h2
                    class="text-sm font-semibold text-blue-900 dark:text-blue-200"
                >
                    Inventory Valuation
                </h2>

                <p
                    class="mt-2 text-sm text-blue-700 dark:text-blue-300"
                >
                    The supplier value shown here uses the
                    original Goods Receipt commercial cost.
                    The inventory value is calculated using
                    the current weighted-average inventory
                    cost when the Purchase Return is posted.
                </p>

                <p
                    class="mt-2 text-sm text-blue-700 dark:text-blue-300"
                >
                    Any difference between supplier value
                    and inventory value is stored as the
                    Purchase Return cost variance.
                </p>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Return Summary
                </h2>

                <div class="mt-5 space-y-4">
                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Selected lines
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{ selectedLineCount }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">
                            Return quantity
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatQuantity(
                                    totalReturnQuantity,
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
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    Supplier Value
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Before inventory
                                    valuation
                                </p>
                            </div>

                            <p
                                class="text-xl font-bold text-gray-900 dark:text-white"
                            >
                                {{
                                    formatAmount(
                                        totalSupplierValue,
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
                    && props.purchaseReturn
                        ? route(
                            'purchase-returns.show',
                            props.purchaseReturn.id,
                        )
                        : route(
                            'purchase-returns.index',
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
                            ? 'Update Purchase Return'
                            : 'Create Purchase Return'
                }}
            </button>
        </div>
    </form>
</template>