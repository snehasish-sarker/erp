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
    ExistingSupplierDebitNoteFormData,
    SupplierDebitNoteFormData,
    SupplierDebitNoteFormLinePayload,
    SupplierDebitNoteFormPayload,
    SupplierDebitNoteFormProps,
    SupplierDebitNotePurchaseReturnLineOption,
    SupplierDebitNoteSupplierInvoiceLineOption,
    SupplierDebitNoteSupplierInvoiceOption,
} from '@/Types/supplier-debit-note';

interface Props extends SupplierDebitNoteFormProps {
    supplierDebitNote?: ExistingSupplierDebitNoteFormData;
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

const formatQuantity = (
    value: string | number,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        },
    ).format(decimalValue(value));
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
    ).format(decimalValue(value));
};

const initialPurchaseReturnId =
    props.supplierDebitNote?.purchase_return_id
    ?? props.selectedPurchaseReturnId
    ?? null;

const form = useForm<SupplierDebitNoteFormData>({
    purchase_return_id:
        initialPurchaseReturnId,

    supplier_invoice_id:
        props.supplierDebitNote
            ?.supplier_invoice_id
        ?? null,

    debit_note_date:
        props.supplierDebitNote
            ?.debit_note_date
        ?? props.defaults.debit_note_date,

    posting_date:
        props.supplierDebitNote?.posting_date
        ?? props.defaults.posting_date,

    supplier_reference:
        props.supplierDebitNote
            ?.supplier_reference
        ?? '',

    reason:
        props.supplierDebitNote?.reason
        ?? '',

    notes:
        props.supplierDebitNote?.notes
        ?? '',

    lines:
        props.supplierDebitNote?.lines.map(
            (
                line,
            ): SupplierDebitNoteFormLinePayload => ({
                purchase_return_line_id:
                    line.purchase_return_line_id,

                supplier_invoice_line_id:
                    line.supplier_invoice_line_id,

                return_quantity:
                    line.return_quantity,

                unit_price:
                    line.unit_price,

                discount_per_unit:
                    line.discount_per_unit,

                tax_rate:
                    line.tax_rate,

                description:
                    line.description,

                notes:
                    line.notes,
            }),
        ) ?? [],
});

const isEditing = computed(
    (): boolean =>
        props.supplierDebitNote !== undefined,
);

const hasAllocatedNumber = computed(
    (): boolean => {
        const number = props.supplierDebitNote
            ?.debit_note_number;

        return typeof number === 'string'
            && number.trim() !== '';
    },
);

const selectedPurchaseReturn = computed(() => {
    if (form.purchase_return_id === null) {
        return null;
    }

    return props.purchaseReturns.find(
        (purchaseReturn) =>
            purchaseReturn.id
            === form.purchase_return_id,
    ) ?? null;
});

const supplierInvoiceOptions = computed(
    (): SupplierDebitNoteSupplierInvoiceOption[] =>
        selectedPurchaseReturn.value
            ?.supplier_invoices
        ?? [],
);

const selectedSupplierInvoice = computed(
    (): SupplierDebitNoteSupplierInvoiceOption | null => {
        if (form.supplier_invoice_id === null) {
            return null;
        }

        return supplierInvoiceOptions.value.find(
            (supplierInvoice) =>
                supplierInvoice.id
                === form.supplier_invoice_id,
        ) ?? null;
    },
);

const currencyCode = computed(
    (): string =>
        selectedSupplierInvoice.value
            ?.currency_code
        ?? selectedPurchaseReturn.value
            ?.currency_code
        ?? '',
);

const exchangeRate = computed(
    (): string =>
        selectedSupplierInvoice.value
            ?.exchange_rate
        ?? selectedPurchaseReturn.value
            ?.exchange_rate
        ?? '1.00000000',
);

const sourceLine = (
    purchaseReturnLineId: number,
): SupplierDebitNotePurchaseReturnLineOption | null => {
    return selectedPurchaseReturn.value
        ?.lines
        .find(
            (line) =>
                line.id
                === purchaseReturnLineId,
        )
        ?? null;
};

const compatibleInvoiceLines = (
    formLine: SupplierDebitNoteFormLinePayload,
): SupplierDebitNoteSupplierInvoiceLineOption[] => {
    const invoice =
        selectedSupplierInvoice.value;

    const source = sourceLine(
        formLine.purchase_return_line_id,
    );

    if (
        invoice === null
        || source === null
    ) {
        return [];
    }

    return invoice.lines.filter(
        (invoiceLine): boolean =>
            invoiceLine.product_id
                === source.product_id
            && invoiceLine.unit_id
                === source.unit_id,
    );
};

const synchronizeInvoiceLineMappings = (): void => {
    if (selectedSupplierInvoice.value === null) {
        form.lines.forEach((line): void => {
            line.supplier_invoice_line_id = null;
        });

        return;
    }

    form.lines.forEach((line): void => {
        const compatible =
            compatibleInvoiceLines(line);

        const currentStillValid =
            line.supplier_invoice_line_id
                !== null
            && compatible.some(
                (invoiceLine): boolean =>
                    invoiceLine.id
                    === line.supplier_invoice_line_id,
            );

        if (currentStillValid) {
            return;
        }

        line.supplier_invoice_line_id =
            compatible.length === 1
                ? compatible[0].id
                : null;
    });
};

const preferredSupplierInvoiceId = (): number | null => {
    const purchaseReturn =
        selectedPurchaseReturn.value;

    if (purchaseReturn === null) {
        return null;
    }

    const sourceInvoiceId =
        purchaseReturn.source_supplier_invoice_id;

    if (
        sourceInvoiceId !== null
        && purchaseReturn.supplier_invoices.some(
            (invoice): boolean =>
                invoice.id === sourceInvoiceId,
        )
    ) {
        return sourceInvoiceId;
    }

    return null;
};

const rebuildLines = (): void => {
    const purchaseReturn =
        selectedPurchaseReturn.value;

    if (purchaseReturn === null) {
        form.supplier_invoice_id = null;
        form.lines = [];

        return;
    }

    form.supplier_invoice_id =
        preferredSupplierInvoiceId();

    form.lines = purchaseReturn.lines.map(
        (
            line,
        ): SupplierDebitNoteFormLinePayload => ({
            purchase_return_line_id:
                line.id,

            supplier_invoice_line_id:
                null,

            return_quantity:
                line.return_quantity,

            unit_price:
                line.supplier_unit_cost,

            discount_per_unit:
                '0.000000',

            tax_rate:
                '0.000000',

            description: '',
            notes: '',
        }),
    );

    synchronizeInvoiceLineMappings();
};

if (
    !isEditing.value
    && selectedPurchaseReturn.value !== null
) {
    rebuildLines();
}

watch(
    () => form.purchase_return_id,
    (
        purchaseReturnId,
        previousPurchaseReturnId,
    ) => {
        if (
            purchaseReturnId
            === previousPurchaseReturnId
        ) {
            return;
        }

        rebuildLines();
    },
);

watch(
    () => form.supplier_invoice_id,
    (
        supplierInvoiceId,
        previousSupplierInvoiceId,
    ) => {
        if (
            supplierInvoiceId
            === previousSupplierInvoiceId
        ) {
            return;
        }

        synchronizeInvoiceLineMappings();
    },
);

watch(
    supplierInvoiceOptions,
    (
        options,
    ) => {
        if (form.supplier_invoice_id === null) {
            return;
        }

        const stillAvailable = options.some(
            (supplierInvoice): boolean =>
                supplierInvoice.id
                === form.supplier_invoice_id,
        );

        if (!stillAvailable) {
            form.supplier_invoice_id = null;
        }
    },
);

watch(
    () => form.debit_note_date,
    (
        debitNoteDate,
        previousDebitNoteDate,
    ) => {
        if (
            form.posting_date === ''
            || form.posting_date
                === previousDebitNoteDate
        ) {
            form.posting_date =
                debitNoteDate;
        }
    },
);

const lineGrossAmount = (
    line: SupplierDebitNoteFormLinePayload,
): number => {
    return decimalValue(
        line.return_quantity,
    ) * decimalValue(
        line.unit_price,
    );
};

const lineDiscountAmount = (
    line: SupplierDebitNoteFormLinePayload,
): number => {
    return decimalValue(
        line.return_quantity,
    ) * decimalValue(
        line.discount_per_unit,
    );
};

const lineSubtotal = (
    line: SupplierDebitNoteFormLinePayload,
): number => {
    return lineGrossAmount(line)
        - lineDiscountAmount(line);
};

const lineTaxAmount = (
    line: SupplierDebitNoteFormLinePayload,
): number => {
    const subtotal = lineSubtotal(line);

    if (subtotal <= 0) {
        return 0;
    }

    return subtotal
        * decimalValue(line.tax_rate)
        / 100;
};

const lineTotalAmount = (
    line: SupplierDebitNoteFormLinePayload,
): number => {
    return lineSubtotal(line)
        + lineTaxAmount(line);
};

const grossAmount = computed(
    (): number =>
        form.lines.reduce(
            (total, line): number =>
                total
                + lineGrossAmount(line),
            0,
        ),
);

const discountAmount = computed(
    (): number =>
        form.lines.reduce(
            (total, line): number =>
                total
                + lineDiscountAmount(line),
            0,
        ),
);

const subtotal = computed(
    (): number =>
        form.lines.reduce(
            (total, line): number =>
                total
                + lineSubtotal(line),
            0,
        ),
);

const taxAmount = computed(
    (): number =>
        form.lines.reduce(
            (total, line): number =>
                total
                + lineTaxAmount(line),
            0,
        ),
);

const totalAmount = computed(
    (): number =>
        subtotal.value
        + taxAmount.value,
);

const allocatedAmount = computed(
    (): number =>
        selectedSupplierInvoice.value
            === null
            ? 0
            : totalAmount.value,
);

const unallocatedAmount = computed(
    (): number =>
        selectedSupplierInvoice.value
            === null
            ? totalAmount.value
            : 0,
);

const allocationExceedsAvailable = computed(
    (): boolean => {
        const invoice =
            selectedSupplierInvoice.value;

        if (invoice === null) {
            return false;
        }

        return totalAmount.value
            > decimalValue(
                invoice.available_debit_note_amount,
            ) + 0.000001;
    },
);

const lineDiscountIsInvalid = (
    line: SupplierDebitNoteFormLinePayload,
): boolean => {
    return decimalValue(
        line.discount_per_unit,
    ) > decimalValue(
        line.unit_price,
    ) + 0.000001;
};

const lineTaxIsInvalid = (
    line: SupplierDebitNoteFormLinePayload,
): boolean => {
    const rate = decimalValue(
        line.tax_rate,
    );

    return rate < 0 || rate > 100;
};

const lineMappingIsInvalid = (
    line: SupplierDebitNoteFormLinePayload,
): boolean => {
    if (selectedSupplierInvoice.value === null) {
        return false;
    }

    if (line.supplier_invoice_line_id === null) {
        return true;
    }

    return !compatibleInvoiceLines(line).some(
        (invoiceLine): boolean =>
            invoiceLine.id
            === line.supplier_invoice_line_id,
    );
};

const unmappedLineCount = computed(
    (): number => {
        if (selectedSupplierInvoice.value === null) {
            return 0;
        }

        return form.lines.filter(
            lineMappingIsInvalid,
        ).length;
    },
);

const hasInvalidCommercialLine = computed(
    (): boolean =>
        form.lines.some(
            (line): boolean =>
                decimalValue(
                    line.return_quantity,
                ) <= 0
                || decimalValue(
                    line.unit_price,
                ) < 0
                || decimalValue(
                    line.discount_per_unit,
                ) < 0
                || lineDiscountIsInvalid(line)
                || lineTaxIsInvalid(line),
        ),
);

const canSubmitForm = computed(
    (): boolean =>
        form.purchase_return_id !== null
        && form.lines.length > 0
        && unmappedLineCount.value === 0
        && !hasInvalidCommercialLine.value,
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

const submit = (): void => {
    if (!canSubmitForm.value) {
        return;
    }

    form.transform(
        (
            data,
        ): SupplierDebitNoteFormPayload => ({
            purchase_return_id:
                data.purchase_return_id,

            supplier_invoice_id:
                data.supplier_invoice_id,

            debit_note_date:
                data.debit_note_date.trim(),

            posting_date:
                data.posting_date.trim(),

            supplier_reference:
                data.supplier_reference.trim(),

            reason:
                data.reason.trim(),

            notes:
                data.notes.trim(),

            lines: data.lines.map(
                (
                    line,
                ): SupplierDebitNoteFormLinePayload => ({
                    purchase_return_line_id:
                        line.purchase_return_line_id,

                    supplier_invoice_line_id:
                        data.supplier_invoice_id
                            === null
                            ? null
                            : line.supplier_invoice_line_id,

                    return_quantity:
                        line.return_quantity.trim(),

                    unit_price:
                        line.unit_price.trim(),

                    discount_per_unit:
                        line.discount_per_unit.trim(),

                    tax_rate:
                        line.tax_rate.trim(),

                    description:
                        line.description.trim(),

                    notes:
                        line.notes.trim(),
                }),
            ),
        }),
    );

    if (props.supplierDebitNote !== undefined) {
        form.put(
            route(
                'supplier-debit-notes.update',
                props.supplierDebitNote.id,
            ),
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        route(
            'supplier-debit-notes.store',
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
                    Debit Note Information
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Create the supplier commercial claim from
                    one posted Purchase Return.
                </p>
            </div>

            <div
                class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4"
            >
                <div
                    class="md:col-span-2 xl:col-span-2"
                >
                    <label
                        for="supplier-debit-note-purchase-return"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Purchase Return
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        id="supplier-debit-note-purchase-return"
                        v-model="form.purchase_return_id"
                        :disabled="isEditing"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                    >
                        <option :value="null">
                            Select posted Purchase Return
                        </option>

                        <option
                            v-for="purchaseReturn in props.purchaseReturns"
                            :key="purchaseReturn.id"
                            :value="purchaseReturn.id"
                        >
                            {{
                                purchaseReturn.return_number
                                ?? `Purchase Return #${purchaseReturn.id}`
                            }}
                            — {{ purchaseReturn.supplier_name }}
                            — {{ purchaseReturn.branch_name }}
                        </option>
                    </select>

                    <p
                        v-if="fieldError('purchase_return_id')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('purchase_return_id') }}
                    </p>

                    <p
                        v-else-if="isEditing"
                        class="mt-1 text-xs text-gray-500"
                    >
                        The source Purchase Return cannot be
                        changed after creation.
                    </p>
                </div>

                <div
                    class="md:col-span-2 xl:col-span-2"
                >
                    <label
                        for="supplier-debit-note-invoice"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier Invoice Allocation
                    </label>

                    <select
                        id="supplier-debit-note-invoice"
                        v-model="form.supplier_invoice_id"
                        :disabled="selectedPurchaseReturn === null"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                    >
                        <option :value="null">
                            Leave unallocated supplier credit
                        </option>

                        <option
                            v-for="supplierInvoice in supplierInvoiceOptions"
                            :key="supplierInvoice.id"
                            :value="supplierInvoice.id"
                        >
                            {{
                                supplierInvoice.document_number
                                ?? supplierInvoice.supplier_invoice_number
                            }}
                            — {{ supplierInvoice.supplier_invoice_number }}
                            — Available
                            {{
                                formatAmount(
                                    supplierInvoice.available_debit_note_amount,
                                )
                            }}
                        </option>
                    </select>

                    <p
                        v-if="fieldError('supplier_invoice_id')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('supplier_invoice_id') }}
                    </p>

                    <p
                        v-else
                        class="mt-1 text-xs text-gray-500"
                    >
                        No invoice means the Debit Note remains
                        an unallocated supplier credit.
                    </p>
                </div>

                <div>
                    <label
                        for="supplier-debit-note-date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Debit Note Date
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="supplier-debit-note-date"
                        v-model="form.debit_note_date"
                        type="date"
                        :disabled="hasAllocatedNumber"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                    />

                    <p
                        v-if="fieldError('debit_note_date')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('debit_note_date') }}
                    </p>

                    <p
                        v-else-if="hasAllocatedNumber"
                        class="mt-1 text-xs text-gray-500"
                    >
                        The date is locked after number
                        allocation.
                    </p>
                </div>

                <div>
                    <label
                        for="supplier-debit-note-posting-date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Posting Date
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="supplier-debit-note-posting-date"
                        v-model="form.posting_date"
                        type="date"
                        :min="form.debit_note_date || undefined"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('posting_date')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('posting_date') }}
                    </p>
                </div>

                <div class="md:col-span-2">
                    <label
                        for="supplier-debit-note-reference"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier Reference
                    </label>

                    <input
                        id="supplier-debit-note-reference"
                        v-model="form.supplier_reference"
                        type="text"
                        maxlength="160"
                        placeholder="Supplier RMA, claim, or authorization reference"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('supplier_reference')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('supplier_reference') }}
                    </p>
                </div>

                <div class="md:col-span-2 xl:col-span-4">
                    <label
                        for="supplier-debit-note-reason"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Debit Note Reason
                        <span class="text-red-500">*</span>
                    </label>

                    <textarea
                        id="supplier-debit-note-reason"
                        v-model="form.reason"
                        rows="3"
                        maxlength="500"
                        placeholder="Explain the commercial reason for the supplier claim"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('reason')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('reason') }}
                    </p>
                </div>

                <div class="md:col-span-2 xl:col-span-4">
                    <label
                        for="supplier-debit-note-notes"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Internal Notes
                    </label>

                    <textarea
                        id="supplier-debit-note-notes"
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
            v-if="selectedPurchaseReturn !== null"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p class="text-xs uppercase text-gray-500">
                    Purchase Return
                </p>

                <p
                    class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        selectedPurchaseReturn.return_number
                        ?? `Return #${selectedPurchaseReturn.id}`
                    }}
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    {{ selectedPurchaseReturn.return_date ?? '—' }}
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
                    {{ selectedPurchaseReturn.supplier_name }}
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    {{ selectedPurchaseReturn.supplier_code }}
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
                        selectedPurchaseReturn.purchase_order_number
                        ?? `PO #${selectedPurchaseReturn.purchase_order_id}`
                    }}
                </p>
            </div>

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
                        selectedPurchaseReturn.goods_receipt_number
                        ?? `GR #${selectedPurchaseReturn.goods_receipt_id}`
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p class="text-xs uppercase text-gray-500">
                    Currency
                </p>

                <p
                    class="mt-2 text-sm font-semibold text-gray-900 dark:text-white"
                >
                    {{ currencyCode || '—' }}
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    Rate {{ exchangeRate }}
                </p>
            </div>
        </section>

        <section
            v-if="selectedSupplierInvoice !== null"
            class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-500/30 dark:bg-indigo-500/10"
        >
            <div
                class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-5"
            >
                <div class="md:col-span-2">
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-indigo-600 dark:text-indigo-400"
                    >
                        Selected Supplier Invoice
                    </p>

                    <p
                        class="mt-2 font-semibold text-indigo-900 dark:text-indigo-200"
                    >
                        {{
                            selectedSupplierInvoice.document_number
                            ?? selectedSupplierInvoice.supplier_invoice_number
                        }}
                    </p>

                    <p
                        class="mt-1 text-sm text-indigo-700 dark:text-indigo-300"
                    >
                        Supplier number:
                        {{ selectedSupplierInvoice.supplier_invoice_number }}
                        · {{ selectedSupplierInvoice.status }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs uppercase text-indigo-600 dark:text-indigo-400"
                    >
                        Invoice Total
                    </p>

                    <p
                        class="mt-2 font-semibold text-indigo-900 dark:text-indigo-200"
                    >
                        {{ formatAmount(selectedSupplierInvoice.total_amount) }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs uppercase text-indigo-600 dark:text-indigo-400"
                    >
                        Reserved / Debited
                    </p>

                    <p
                        class="mt-2 font-semibold text-indigo-900 dark:text-indigo-200"
                    >
                        {{
                            formatAmount(
                                selectedSupplierInvoice.debit_note_reserved_amount,
                            )
                        }}
                        /
                        {{ formatAmount(selectedSupplierInvoice.debited_amount) }}
                    </p>
                </div>

                <div>
                    <p
                        class="text-xs uppercase text-indigo-600 dark:text-indigo-400"
                    >
                        Available
                    </p>

                    <p
                        class="mt-2 font-semibold text-indigo-900 dark:text-indigo-200"
                    >
                        {{
                            formatAmount(
                                selectedSupplierInvoice.available_debit_note_amount,
                            )
                        }}
                    </p>
                </div>
            </div>

            <p
                v-if="unmappedLineCount > 0"
                class="mt-4 text-sm font-medium text-red-600 dark:text-red-400"
            >
                {{ unmappedLineCount }} Debit Note line(s)
                still require a compatible Supplier Invoice
                line mapping.
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
                    Debit Note Lines
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Every posted Purchase Return line must be
                    represented exactly once. Quantities are
                    fixed to the posted return quantities.
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
                    Select a posted Purchase Return to load
                    its commercial lines.
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[1900px]">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-400"
                        >
                            <th class="w-20 px-4 py-3">
                                Line
                            </th>

                            <th class="min-w-64 px-4 py-3">
                                Product
                            </th>

                            <th class="w-36 px-4 py-3 text-right">
                                Return Qty
                            </th>

                            <th class="w-52 px-4 py-3">
                                Invoice Line
                            </th>

                            <th class="w-40 px-4 py-3 text-right">
                                Unit Price
                            </th>

                            <th class="w-44 px-4 py-3 text-right">
                                Discount / Unit
                            </th>

                            <th class="w-36 px-4 py-3 text-right">
                                Tax %
                            </th>

                            <th class="w-40 px-4 py-3 text-right">
                                Gross
                            </th>

                            <th class="w-40 px-4 py-3 text-right">
                                Discount
                            </th>

                            <th class="w-40 px-4 py-3 text-right">
                                Tax
                            </th>

                            <th class="w-44 px-4 py-3 text-right">
                                Total
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <template
                            v-for="(line, index) in form.lines"
                            :key="line.purchase_return_line_id"
                        >
                            <tr
                                class="border-b border-gray-100 align-top dark:border-gray-800"
                            >
                                <td
                                    class="px-4 py-4 text-sm text-gray-500"
                                >
                                    {{
                                        sourceLine(line.purchase_return_line_id)
                                            ?.line_number
                                        ?? index + 1
                                    }}
                                </td>

                                <td class="px-4 py-4">
                                    <p
                                        class="text-sm font-medium text-gray-900 dark:text-white"
                                    >
                                        {{
                                            sourceLine(line.purchase_return_line_id)
                                                ?.product_name
                                            ?? 'Unavailable source line'
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        {{
                                            sourceLine(line.purchase_return_line_id)
                                                ?.product_sku
                                            ?? '—'
                                        }}
                                        ·
                                        {{
                                            sourceLine(line.purchase_return_line_id)
                                                ?.unit_code
                                            ?? '—'
                                        }}
                                    </p>

                                    <p
                                        class="mt-2 text-xs text-gray-500"
                                    >
                                        Return supplier cost:
                                        {{
                                            formatAmount(
                                                sourceLine(line.purchase_return_line_id)
                                                    ?.supplier_unit_cost
                                                ?? '0',
                                            )
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        Inventory cost:
                                        {{
                                            formatAmount(
                                                sourceLine(line.purchase_return_line_id)
                                                    ?.inventory_unit_cost
                                                ?? '0',
                                            )
                                        }}
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    <input
                                        v-model="line.return_quantity"
                                        type="number"
                                        readonly
                                        tabindex="-1"
                                        class="w-full cursor-not-allowed rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-right text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    />

                                    <p
                                        v-if="fieldError(`lines.${index}.return_quantity`)"
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{
                                            fieldError(
                                                `lines.${index}.return_quantity`,
                                            )
                                        }}
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    <select
                                        v-model="line.supplier_invoice_line_id"
                                        :disabled="selectedSupplierInvoice === null"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                                    >
                                        <option :value="null">
                                            {{
                                                selectedSupplierInvoice === null
                                                    ? 'Not allocated'
                                                    : 'Select invoice line'
                                            }}
                                        </option>

                                        <option
                                            v-for="invoiceLine in compatibleInvoiceLines(line)"
                                            :key="invoiceLine.id"
                                            :value="invoiceLine.id"
                                        >
                                            {{ invoiceLine.product_name }}
                                            — {{ invoiceLine.product_sku }}
                                            — {{ invoiceLine.unit_code }}
                                        </option>
                                    </select>

                                    <p
                                        v-if="fieldError(`lines.${index}.supplier_invoice_line_id`)"
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{
                                            fieldError(
                                                `lines.${index}.supplier_invoice_line_id`,
                                            )
                                        }}
                                    </p>

                                    <p
                                        v-else-if="lineMappingIsInvalid(line)"
                                        class="mt-1 text-xs font-medium text-red-600"
                                    >
                                        A compatible invoice line is required.
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    <input
                                        v-model="line.unit_price"
                                        type="number"
                                        min="0"
                                        step="0.000001"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    />

                                    <p
                                        v-if="fieldError(`lines.${index}.unit_price`)"
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{ fieldError(`lines.${index}.unit_price`) }}
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    <input
                                        v-model="line.discount_per_unit"
                                        type="number"
                                        min="0"
                                        step="0.000001"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    />

                                    <p
                                        v-if="fieldError(`lines.${index}.discount_per_unit`)"
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{ fieldError(`lines.${index}.discount_per_unit`) }}
                                    </p>

                                    <p
                                        v-else-if="lineDiscountIsInvalid(line)"
                                        class="mt-1 text-xs font-medium text-red-600"
                                    >
                                        Discount cannot exceed unit price.
                                    </p>
                                </td>

                                <td class="px-4 py-4">
                                    <input
                                        v-model="line.tax_rate"
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.000001"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    />

                                    <p
                                        v-if="fieldError(`lines.${index}.tax_rate`)"
                                        class="mt-1 text-xs text-red-600"
                                    >
                                        {{ fieldError(`lines.${index}.tax_rate`) }}
                                    </p>

                                    <p
                                        v-else-if="lineTaxIsInvalid(line)"
                                        class="mt-1 text-xs font-medium text-red-600"
                                    >
                                        Tax must be between 0 and 100.
                                    </p>
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{ formatAmount(lineGrossAmount(line)) }}
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{ formatAmount(lineDiscountAmount(line)) }}
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{ formatAmount(lineTaxAmount(line)) }}
                                </td>

                                <td
                                    class="px-4 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{ formatAmount(lineTotalAmount(line)) }}
                                </td>
                            </tr>

                            <tr
                                class="border-b border-gray-200 bg-gray-50/60 dark:border-gray-800 dark:bg-gray-950/30"
                            >
                                <td colspan="11" class="px-4 py-4">
                                    <div
                                        class="grid grid-cols-1 gap-5 lg:grid-cols-2"
                                    >
                                        <div>
                                            <label
                                                :for="`supplier-debit-note-line-description-${index}`"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >
                                                Line Description
                                            </label>

                                            <textarea
                                                :id="`supplier-debit-note-line-description-${index}`"
                                                v-model="line.description"
                                                rows="3"
                                                maxlength="500"
                                                placeholder="Optional commercial line description"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                            />

                                            <p
                                                v-if="fieldError(`lines.${index}.description`)"
                                                class="mt-1 text-xs text-red-600"
                                            >
                                                {{ fieldError(`lines.${index}.description`) }}
                                            </p>
                                        </div>

                                        <div>
                                            <label
                                                :for="`supplier-debit-note-line-notes-${index}`"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                                            >
                                                Internal Line Notes
                                            </label>

                                            <textarea
                                                :id="`supplier-debit-note-line-notes-${index}`"
                                                v-model="line.notes"
                                                rows="3"
                                                maxlength="2000"
                                                placeholder="Optional internal line notes"
                                                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                            />

                                            <p
                                                v-if="fieldError(`lines.${index}.notes`)"
                                                class="mt-1 text-xs text-red-600"
                                            >
                                                {{ fieldError(`lines.${index}.notes`) }}
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
            v-if="selectedPurchaseReturn !== null"
            class="grid grid-cols-1 gap-6 xl:grid-cols-3"
        >
            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm xl:col-span-2 dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Source Purchase Return Valuation
                </h2>

                <div
                    class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
                >
                    <div>
                        <p class="text-xs uppercase text-gray-500">
                            Return Quantity
                        </p>

                        <p
                            class="mt-2 font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                formatQuantity(
                                    selectedPurchaseReturn.total_return_quantity,
                                )
                            }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs uppercase text-gray-500">
                            Supplier Value
                        </p>

                        <p
                            class="mt-2 font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    selectedPurchaseReturn.total_supplier_value,
                                )
                            }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs uppercase text-gray-500">
                            Inventory Value
                        </p>

                        <p
                            class="mt-2 font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    selectedPurchaseReturn.total_inventory_value,
                                )
                            }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs uppercase text-gray-500">
                            Cost Variance
                        </p>

                        <p
                            class="mt-2 font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                formatAmount(
                                    selectedPurchaseReturn.total_cost_variance,
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-5 rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/10 dark:text-blue-300"
                >
                    Debit Note commercial values may include
                    discount and tax adjustments. Purchase
                    Return inventory values remain immutable
                    source snapshots for future accounting.
                </div>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Commercial Summary
                </h2>

                <div class="mt-5 space-y-4">
                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">Gross</span>
                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{ formatAmount(grossAmount) }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">Discount</span>
                        <span
                            class="font-medium text-red-600 dark:text-red-400"
                        >
                            -{{ formatAmount(discountAmount) }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">Subtotal</span>
                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{ formatAmount(subtotal) }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between gap-4 text-sm"
                    >
                        <span class="text-gray-500">Tax</span>
                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{ formatAmount(taxAmount) }}
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
                                    Total Debit Note
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    {{ currencyCode || 'Currency pending' }}
                                </p>
                            </div>

                            <p
                                class="text-xl font-bold text-gray-900 dark:text-white"
                            >
                                {{ formatAmount(totalAmount) }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="border-t border-gray-200 pt-4 dark:border-gray-800"
                    >
                        <div
                            class="flex items-center justify-between gap-4 text-sm"
                        >
                            <span class="text-gray-500">Allocated</span>
                            <span
                                class="font-medium text-indigo-600 dark:text-indigo-400"
                            >
                                {{ formatAmount(allocatedAmount) }}
                            </span>
                        </div>

                        <div
                            class="mt-3 flex items-center justify-between gap-4 text-sm"
                        >
                            <span class="text-gray-500">Unallocated</span>
                            <span
                                class="font-medium text-amber-600 dark:text-amber-400"
                            >
                                {{ formatAmount(unallocatedAmount) }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section
            v-if="allocationExceedsAvailable"
            class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-500/30 dark:bg-red-500/10"
        >
            <h2
                class="font-semibold text-red-900 dark:text-red-200"
            >
                Allocation Exceeds Invoice Availability
            </h2>

            <p
                class="mt-2 text-sm text-red-700 dark:text-red-300"
            >
                The Debit Note total is greater than the
                amount currently available against the
                selected Supplier Invoice. The draft may be
                saved, but approval will be blocked until the
                amount or invoice allocation is corrected.
            </p>
        </section>

        <section
            v-if="!canSubmitForm && form.lines.length > 0"
            class="rounded-2xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-500/30 dark:bg-amber-500/10"
        >
            <h2
                class="font-semibold text-amber-900 dark:text-amber-200"
            >
                Complete the Debit Note Lines
            </h2>

            <p
                class="mt-2 text-sm text-amber-700 dark:text-amber-300"
            >
                Resolve missing Supplier Invoice mappings,
                excessive discounts, or invalid tax values
                before saving.
            </p>
        </section>

        <div
            class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
        >
            <Link
                :href="
                    isEditing
                    && props.supplierDebitNote
                        ? route(
                            'supplier-debit-notes.show',
                            props.supplierDebitNote.id,
                        )
                        : route(
                            'supplier-debit-notes.index',
                        )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Cancel
            </Link>

            <button
                type="submit"
                :disabled="form.processing || !canSubmitForm"
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{
                    form.processing
                        ? 'Saving...'
                        : isEditing
                            ? 'Update Supplier Debit Note'
                            : 'Create Supplier Debit Note'
                }}
            </button>
        </div>
    </form>
</template>
