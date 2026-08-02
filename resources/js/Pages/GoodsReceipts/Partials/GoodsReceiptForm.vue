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
    ExistingGoodsReceiptFormData,
    GoodsReceiptFormData,
    GoodsReceiptFormLine,
    GoodsReceiptFormProps,
    GoodsReceiptPurchaseOrderLineOption,
} from '@/Types/goods-receipt';

interface Props extends GoodsReceiptFormProps {
    goodsReceipt?: ExistingGoodsReceiptFormData;
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

const initialPurchaseOrderId =
    props.goodsReceipt?.purchase_order_id
    ?? props.defaults.selected_purchase_order_id
    ?? null;

const form = useForm<GoodsReceiptFormData>({
    purchase_order_id:
        initialPurchaseOrderId,

    receipt_date:
        props.goodsReceipt?.receipt_date
        ?? props.defaults.receipt_date,

    supplier_delivery_note:
        props.goodsReceipt
            ?.supplier_delivery_note
        ?? '',

    inspection_status:
        props.goodsReceipt
            ?.inspection_status
        ?? props.defaults.inspection_status,

    notes:
        props.goodsReceipt?.notes
        ?? '',

    lines:
        props.goodsReceipt?.lines
            .map(
                (
                    line,
                ): GoodsReceiptFormLine => ({
                    ...line,
                    serial_numbers: [
                        ...line.serial_numbers,
                    ],
                }),
            )
        ?? [],
});

const isEditing = computed(
    (): boolean =>
        props.goodsReceipt !== undefined,
);

const selectedPurchaseOrder = computed(() => {
    if (form.purchase_order_id === null) {
        return null;
    }

    return props.purchaseOrderOptions.find(
        (purchaseOrder) =>
            purchaseOrder.value
            === form.purchase_order_id,
    ) ?? null;
});

const purchaseOrderLine = (
    purchaseOrderLineId: number,
): GoodsReceiptPurchaseOrderLineOption | null => {
    return selectedPurchaseOrder.value
        ?.lines
        .find(
            (line) =>
                line.id
                === purchaseOrderLineId,
        )
        ?? null;
};

const newReceiptLine = (
    line: GoodsReceiptPurchaseOrderLineOption,
): GoodsReceiptFormLine => ({
    include: true,

    purchase_order_line_id:
        line.id,

    receipt_quantity:
        line.outstanding_quantity,

    accepted_quantity:
        line.outstanding_quantity,

    rejected_quantity:
        '0.000000',

    batch_number: '',
    manufacturing_date: '',
    expiry_date: '',
    serial_numbers: [],
    storage_location: '',
    variance_reason: '',
});

const rebuildLines = (): void => {
    const purchaseOrder =
        selectedPurchaseOrder.value;

    if (purchaseOrder === null) {
        form.lines = [];

        return;
    }

    form.lines = purchaseOrder.lines.map(
        newReceiptLine,
    );
};

if (
    !isEditing.value
    && selectedPurchaseOrder.value !== null
) {
    rebuildLines();
}

watch(
    () => form.purchase_order_id,
    (
        purchaseOrderId,
        previousPurchaseOrderId,
    ) => {
        if (
            purchaseOrderId
            === previousPurchaseOrderId
        ) {
            return;
        }

        rebuildLines();
    },
);

const recalculateLine = (
    line: GoodsReceiptFormLine,
): void => {
    const accepted = Math.max(
        decimalValue(
            line.accepted_quantity,
        ),
        0,
    );

    const rejected = Math.max(
        decimalValue(
            line.rejected_quantity,
        ),
        0,
    );

    line.receipt_quantity =
        formatDecimal(
            accepted + rejected,
        );
};

const acceptedExceedsOutstanding = (
    line: GoodsReceiptFormLine,
): boolean => {
    const sourceLine = purchaseOrderLine(
        line.purchase_order_line_id,
    );

    if (sourceLine === null) {
        return false;
    }

    return decimalValue(
        line.accepted_quantity,
    ) > decimalValue(
        sourceLine.outstanding_quantity,
    );
};

const selectedLines = computed(
    (): GoodsReceiptFormLine[] =>
        form.lines.filter(
            (line) =>
                line.include
                && decimalValue(
                    line.receipt_quantity,
                ) > 0,
        ),
);

const totalReceived = computed(
    (): number =>
        selectedLines.value.reduce(
            (total, line) =>
                total
                + decimalValue(
                    line.receipt_quantity,
                ),
            0,
        ),
);

const totalAccepted = computed(
    (): number =>
        selectedLines.value.reduce(
            (total, line) =>
                total
                + decimalValue(
                    line.accepted_quantity,
                ),
            0,
        ),
);

const totalRejected = computed(
    (): number =>
        selectedLines.value.reduce(
            (total, line) =>
                total
                + decimalValue(
                    line.rejected_quantity,
                ),
            0,
        ),
);

const estimatedInventoryValue = computed(
    (): number =>
        selectedLines.value.reduce(
            (total, line) => {
                const sourceLine =
                    purchaseOrderLine(
                        line.purchase_order_line_id,
                    );

                if (
                    sourceLine === null
                    || sourceLine.product_type
                        !== 'stock'
                ) {
                    return total;
                }

                return total
                    + (
                        decimalValue(
                            line.accepted_quantity,
                        )
                        * decimalValue(
                            sourceLine
                                .provisional_unit_cost,
                        )
                    );
            },
            0,
        ),
);

const serialNumbersText = (
    line: GoodsReceiptFormLine,
): string => {
    return line.serial_numbers.join(
        '\n',
    );
};

const updateSerialNumbers = (
    line: GoodsReceiptFormLine,
    event: Event,
): void => {
    const target = event.target;

    if (
        !(target instanceof HTMLTextAreaElement)
    ) {
        return;
    }

    line.serial_numbers = target.value
        .split(/\r\n|\r|\n/)
        .map(
            (value) => value.trim(),
        )
        .filter(
            (value) => value !== '',
        );
};

const fieldError = (
    field: string,
): string | undefined => {
    const errors = form.errors as Record<
        string,
        string | undefined
    >;

    return errors[field];
};

const submit = (): void => {
    form.lines.forEach(
        recalculateLine,
    );

    form.transform(
        (
            data,
        ): GoodsReceiptFormData => ({
            ...data,

            supplier_delivery_note:
                data.supplier_delivery_note
                    .trim(),

            notes:
                data.notes.trim(),

            lines: data.lines
                .filter(
                    (line) =>
                        line.include
                        && decimalValue(
                            line.receipt_quantity,
                        ) > 0,
                )
                .map(
                    (
                        line,
                    ): GoodsReceiptFormLine => ({
                        ...line,

                        receipt_quantity:
                            line.receipt_quantity
                                .trim(),

                        accepted_quantity:
                            line.accepted_quantity
                                .trim(),

                        rejected_quantity:
                            line.rejected_quantity
                                .trim(),

                        batch_number:
                            line.batch_number
                                .trim(),

                        manufacturing_date:
                            line.manufacturing_date
                                .trim(),

                        expiry_date:
                            line.expiry_date
                                .trim(),

                        serial_numbers:
                            line.serial_numbers
                                .map(
                                    (value) =>
                                        value.trim(),
                                )
                                .filter(
                                    (value) =>
                                        value !== '',
                                ),

                        storage_location:
                            line.storage_location
                                .trim(),

                        variance_reason:
                            line.variance_reason
                                .trim(),
                    }),
                ),
        }),
    );

    if (props.goodsReceipt !== undefined) {
        form.put(
            route(
                'goods-receipts.update',
                props.goodsReceipt.id,
            ),
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        route(
            'goods-receipts.store',
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
        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="mb-5">
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Receipt Information
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Select the Purchase Order and record
                    the delivery and inspection details.
                </p>
            </div>

            <div
                class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3"
            >
                <div class="md:col-span-2">
                    <label
                        for="goods-receipt-purchase-order"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Purchase Order
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        id="goods-receipt-purchase-order"
                        v-model="form.purchase_order_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option :value="null">
                            Select Purchase Order
                        </option>

                        <option
                            v-for="purchaseOrder in purchaseOrderOptions"
                            :key="purchaseOrder.value"
                            :value="purchaseOrder.value"
                        >
                            {{ purchaseOrder.label }}
                        </option>
                    </select>

                    <p
                        v-if="fieldError('purchase_order_id')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('purchase_order_id') }}
                    </p>
                </div>

                <div>
                    <label
                        for="goods-receipt-date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Receipt Date
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="goods-receipt-date"
                        v-model="form.receipt_date"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('receipt_date')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('receipt_date') }}
                    </p>
                </div>

                <div>
                    <label
                        for="goods-receipt-delivery-note"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier Delivery Note
                    </label>

                    <input
                        id="goods-receipt-delivery-note"
                        v-model="form.supplier_delivery_note"
                        type="text"
                        maxlength="160"
                        placeholder="Delivery note or challan number"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('supplier_delivery_note')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            fieldError(
                                'supplier_delivery_note',
                            )
                        }}
                    </p>
                </div>

                <div>
                    <label
                        for="goods-receipt-inspection-status"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Inspection Status
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        id="goods-receipt-inspection-status"
                        v-model="form.inspection_status"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option
                            v-for="status in inspectionStatusOptions"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>

                    <p
                        v-if="fieldError('inspection_status')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            fieldError(
                                'inspection_status',
                            )
                        }}
                    </p>
                </div>

                <div class="md:col-span-2 xl:col-span-3">
                    <label
                        for="goods-receipt-notes"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Notes
                    </label>

                    <textarea
                        id="goods-receipt-notes"
                        v-model="form.notes"
                        rows="3"
                        maxlength="4000"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('notes')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('notes') }}
                    </p>
                </div>
            </div>
        </div>

        <div
            v-if="selectedPurchaseOrder"
            class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p class="text-xs uppercase text-gray-500">
                    Supplier
                </p>

                <p
                    class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    {{ selectedPurchaseOrder.supplier.name }}
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    {{ selectedPurchaseOrder.supplier.code }}
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
                    {{ selectedPurchaseOrder.branch.name }}
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    {{ selectedPurchaseOrder.branch.code }}
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
                        selectedPurchaseOrder.warehouse
                            ?.name
                        ?? 'No warehouse'
                    }}
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    {{
                        selectedPurchaseOrder.warehouse
                            ?.code
                        ?? '—'
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p class="text-xs uppercase text-gray-500">
                    Order Date
                </p>

                <p
                    class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        selectedPurchaseOrder.order_date
                        ?? '—'
                    }}
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    Expected:
                    {{
                        selectedPurchaseOrder
                            .expected_delivery_date
                        ?? '—'
                    }}
                </p>
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
                    Receipt Lines
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Accepted quantity updates the Purchase
                    Order and inventory. Rejected quantity
                    records the delivery variance but does
                    not add stock.
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
                    Select a Purchase Order with outstanding
                    quantities.
                </p>
            </div>

            <div
                v-else
                class="overflow-x-auto"
            >
                <table class="min-w-[1850px] w-full">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-400"
                        >
                            <th class="w-16 px-4 py-3">
                                Use
                            </th>

                            <th class="min-w-60 px-4 py-3">
                                Product
                            </th>

                            <th class="w-36 px-4 py-3 text-right">
                                Outstanding
                            </th>

                            <th class="w-36 px-4 py-3 text-right">
                                Accepted
                            </th>

                            <th class="w-36 px-4 py-3 text-right">
                                Rejected
                            </th>

                            <th class="w-36 px-4 py-3 text-right">
                                Total Receipt
                            </th>

                            <th class="min-w-44 px-4 py-3">
                                Batch
                            </th>

                            <th class="w-44 px-4 py-3">
                                Manufactured
                            </th>

                            <th class="w-44 px-4 py-3">
                                Expiry
                            </th>

                            <th class="min-w-48 px-4 py-3">
                                Storage
                            </th>

                            <th class="min-w-64 px-4 py-3">
                                Serial Numbers
                            </th>

                            <th class="min-w-64 px-4 py-3">
                                Variance Reason
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="(line, index) in form.lines"
                            :key="line.purchase_order_line_id"
                            class="border-b border-gray-100 align-top last:border-b-0 dark:border-gray-800"
                            :class="{
                                'opacity-50': !line.include,
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
                                <template
                                    v-if="
                                        purchaseOrderLine(
                                            line.purchase_order_line_id,
                                        )
                                    "
                                >
                                    <p
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{
                                            purchaseOrderLine(
                                                line.purchase_order_line_id,
                                            )?.product_name
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        {{
                                            purchaseOrderLine(
                                                line.purchase_order_line_id,
                                            )?.product_sku
                                        }}
                                        ·
                                        {{
                                            purchaseOrderLine(
                                                line.purchase_order_line_id,
                                            )?.unit_code
                                        }}
                                        ·
                                        {{
                                            purchaseOrderLine(
                                                line.purchase_order_line_id,
                                            )?.product_type
                                        }}
                                    </p>
                                </template>
                            </td>

                            <td
                                class="px-4 py-4 text-right text-sm font-medium text-gray-900 dark:text-white"
                            >
                                {{
                                    purchaseOrderLine(
                                        line.purchase_order_line_id,
                                    )?.outstanding_quantity
                                }}
                            </td>

                            <td class="px-4 py-4">
                                <input
                                    v-model="line.accepted_quantity"
                                    type="number"
                                    min="0"
                                    step="0.000001"
                                    :disabled="!line.include"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                    @input="recalculateLine(line)"
                                />

                                <p
                                    v-if="
                                        acceptedExceedsOutstanding(
                                            line,
                                        )
                                    "
                                    class="mt-1 text-xs text-red-600"
                                >
                                    Exceeds outstanding quantity.
                                </p>

                                <p
                                    v-if="
                                        fieldError(
                                            `lines.${index}.accepted_quantity`,
                                        )
                                    "
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{
                                        fieldError(
                                            `lines.${index}.accepted_quantity`,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <input
                                    v-model="line.rejected_quantity"
                                    type="number"
                                    min="0"
                                    step="0.000001"
                                    :disabled="!line.include"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                    @input="recalculateLine(line)"
                                />

                                <p
                                    v-if="
                                        fieldError(
                                            `lines.${index}.rejected_quantity`,
                                        )
                                    "
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{
                                        fieldError(
                                            `lines.${index}.rejected_quantity`,
                                        )
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-4 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{ line.receipt_quantity }}

                                <p
                                    v-if="
                                        fieldError(
                                            `lines.${index}.receipt_quantity`,
                                        )
                                    "
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{
                                        fieldError(
                                            `lines.${index}.receipt_quantity`,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <input
                                    v-model="line.batch_number"
                                    type="text"
                                    maxlength="120"
                                    :disabled="!line.include"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                />
                            </td>

                            <td class="px-4 py-4">
                                <input
                                    v-model="line.manufacturing_date"
                                    type="date"
                                    :disabled="!line.include"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                />
                            </td>

                            <td class="px-4 py-4">
                                <input
                                    v-model="line.expiry_date"
                                    type="date"
                                    :min="
                                        line.manufacturing_date
                                        || undefined
                                    "
                                    :disabled="!line.include"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                />
                            </td>

                            <td class="px-4 py-4">
                                <input
                                    v-model="line.storage_location"
                                    type="text"
                                    maxlength="160"
                                    :disabled="!line.include"
                                    placeholder="Rack, bin, aisle"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                />
                            </td>

                            <td class="px-4 py-4">
                                <textarea
                                    :value="serialNumbersText(line)"
                                    rows="4"
                                    maxlength="10000"
                                    :disabled="!line.include"
                                    placeholder="One serial number per line"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                    @input="
                                        updateSerialNumbers(
                                            line,
                                            $event,
                                        )
                                    "
                                />

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{ line.serial_numbers.length }}
                                    serial number(s)
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <textarea
                                    v-model="line.variance_reason"
                                    rows="4"
                                    maxlength="500"
                                    :disabled="!line.include"
                                    placeholder="Required operational explanation for shortages, damage, or rejection"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <h2
                class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
            >
                Receipt Summary
            </h2>

            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
            >
                <div
                    class="rounded-xl bg-gray-50 p-4 dark:bg-gray-950"
                >
                    <p class="text-xs uppercase text-gray-500">
                        Total Received
                    </p>

                    <p
                        class="mt-2 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        {{ formatDecimal(totalReceived) }}
                    </p>
                </div>

                <div
                    class="rounded-xl bg-emerald-50 p-4 dark:bg-emerald-500/10"
                >
                    <p
                        class="text-xs uppercase text-emerald-700 dark:text-emerald-400"
                    >
                        Accepted
                    </p>

                    <p
                        class="mt-2 text-lg font-semibold text-emerald-700 dark:text-emerald-400"
                    >
                        {{ formatDecimal(totalAccepted) }}
                    </p>
                </div>

                <div
                    class="rounded-xl bg-red-50 p-4 dark:bg-red-500/10"
                >
                    <p
                        class="text-xs uppercase text-red-700 dark:text-red-400"
                    >
                        Rejected
                    </p>

                    <p
                        class="mt-2 text-lg font-semibold text-red-700 dark:text-red-400"
                    >
                        {{ formatDecimal(totalRejected) }}
                    </p>
                </div>

                <div
                    class="rounded-xl bg-blue-50 p-4 dark:bg-blue-500/10"
                >
                    <p
                        class="text-xs uppercase text-blue-700 dark:text-blue-400"
                    >
                        Estimated Inventory Value
                    </p>

                    <p
                        class="mt-2 text-lg font-semibold text-blue-700 dark:text-blue-400"
                    >
                        {{
                            selectedPurchaseOrder
                                ?.currency_code
                            ?? ''
                        }}
                        {{
                            formatDecimal(
                                estimatedInventoryValue,
                            )
                        }}
                    </p>
                </div>
            </div>
        </div>

        <div
            class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
        >
            <Link
                :href="
                    isEditing
                        ? route(
                            'goods-receipts.show',
                            goodsReceipt?.id,
                        )
                        : route(
                            'goods-receipts.index',
                        )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Cancel
            </Link>

            <button
                type="submit"
                :disabled="
                    form.processing
                    || selectedLines.length === 0
                "
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{
                    form.processing
                        ? 'Saving...'
                        : isEditing
                            ? 'Update Goods Receipt'
                            : 'Create Goods Receipt'
                }}
            </button>
        </div>
    </form>
</template>