<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import { computed } from 'vue';

import type {
    CreditableSalesInvoice,
    CreditableSalesInvoiceLine,
    CustomerCreditNoteFormData,
    CustomerCreditNoteFormLine,
    CustomerCreditLineType,
    ExistingCustomerCreditNoteFormData,
} from '@/Types/customer-credit-note';

interface Props {
    salesInvoice: CreditableSalesInvoice;
    creditNote?: ExistingCustomerCreditNoteFormData;
    defaults?: {
        credit_note_date: string;
        posting_date: string;
    };
}

const props = defineProps<Props>();

const existingByLine = new Map<number, CustomerCreditNoteFormLine>(
    (props.creditNote?.lines ?? []).map(
        (line) => [line.sales_invoice_line_id, line],
    ),
);

const form = useForm<CustomerCreditNoteFormData>({
    sales_invoice_id: props.salesInvoice.id,
    credit_note_date:
        props.creditNote?.credit_note_date
        ?? props.defaults?.credit_note_date
        ?? '',
    posting_date:
        props.creditNote?.posting_date
        ?? props.defaults?.posting_date
        ?? '',
    return_address:
        props.creditNote?.return_address
        ?? props.salesInvoice.shipping_address
        ?? '',
    reason: props.creditNote?.reason ?? '',
    notes: props.creditNote?.notes ?? '',
    lines: props.salesInvoice.lines.map(
        (line): CustomerCreditNoteFormLine => {
            const existing = existingByLine.get(line.id);

            return {
                id: existing?.id,
                sales_invoice_line_id: line.id,
                line_type: existing?.line_type ?? 'quantity',
                credit_quantity: existing?.credit_quantity ?? '0.000000',
                credit_amount: existing?.credit_amount ?? '0.000000',
                return_to_stock: existing?.return_to_stock ?? false,
                description: existing?.description ?? line.description ?? '',
            };
        },
    ),
});

const isEditing = computed((): boolean => props.creditNote !== undefined);

const decimal = (
    value: string | number | null | undefined,
): number => {
    const parsed = Number.parseFloat(String(value ?? '0'));

    return Number.isFinite(parsed) ? parsed : 0;
};

const formatAmount = (value: number): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    }).format(Number.isFinite(value) ? value : 0);
};

const formatQuantity = (value: string | number): string => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 6,
    }).format(decimal(value));
};

const linePreview = (
    source: CreditableSalesInvoiceLine,
    line: CustomerCreditNoteFormLine,
): {
    subtotal: number;
    tax: number;
    total: number;
    quantity: number;
    returnsStock: boolean;
} => {
    if (line.line_type === 'amount') {
        const total = Math.max(decimal(line.credit_amount), 0);
        const remainingTotal = Math.max(
            decimal(source.remaining_creditable_amount),
            0,
        );
        const remainingTax = Math.max(
            decimal(source.tax_amount)
            * (
                remainingTotal > 0
                    ? remainingTotal / Math.max(decimal(source.line_total), 0.000001)
                    : 0
            ),
            0,
        );
        const taxRatio = remainingTotal > 0
            ? Math.min(remainingTax / remainingTotal, 1)
            : 0;
        const tax = total * taxRatio;

        return {
            subtotal: Math.max(total - tax, 0),
            tax,
            total,
            quantity: 0,
            returnsStock: false,
        };
    }

    const quantity = Math.max(decimal(line.credit_quantity), 0);
    const sourceQuantity = Math.max(decimal(source.invoiced_quantity), 0);
    const ratio = sourceQuantity > 0
        ? Math.min(quantity / sourceQuantity, 1)
        : 0;
    const gross = decimal(source.gross_amount) * ratio;
    const discount = decimal(source.discount_amount) * ratio;
    const tax = decimal(source.tax_amount) * ratio;
    const subtotal = Math.max(gross - discount, 0);

    return {
        subtotal,
        tax,
        total: subtotal + tax,
        quantity,
        returnsStock:
            line.return_to_stock
            && source.product_type === 'stock'
            && quantity > 0,
    };
};

const selectedLineCount = computed((): number => {
    return form.lines.filter((line) => {
        return line.line_type === 'quantity'
            ? decimal(line.credit_quantity) > 0
            : decimal(line.credit_amount) > 0;
    }).length;
});

const subtotal = computed((): number => {
    return props.salesInvoice.lines.reduce(
        (total, source, index) => {
            const line = form.lines[index];

            return total + (
                line
                    ? linePreview(source, line).subtotal
                    : 0
            );
        },
        0,
    );
});

const taxTotal = computed((): number => {
    return props.salesInvoice.lines.reduce(
        (total, source, index) => {
            const line = form.lines[index];

            return total + (
                line
                    ? linePreview(source, line).tax
                    : 0
            );
        },
        0,
    );
});

const totalAmount = computed((): number => subtotal.value + taxTotal.value);

const returnedQuantity = computed((): number => {
    return props.salesInvoice.lines.reduce(
        (total, source, index) => {
            const line = form.lines[index];

            if (!line) {
                return total;
            }

            const preview = linePreview(source, line);

            return total + (preview.returnsStock ? preview.quantity : 0);
        },
        0,
    );
});

const autoAllocationPreview = computed((): number => {
    return Math.min(
        totalAmount.value,
        Math.max(decimal(props.salesInvoice.open_item_outstanding), 0),
    );
});

const remainingCustomerCredit = computed((): number => {
    return Math.max(totalAmount.value - autoAllocationPreview.value, 0);
});

const hasLineConflict = computed((): boolean => {
    return form.lines.some((line, index) => {
        const source = props.salesInvoice.lines[index];

        if (!source) {
            return true;
        }

        if (line.line_type === 'quantity') {
            return decimal(line.credit_quantity)
                > decimal(source.remaining_creditable_quantity) + 0.000001;
        }

        return decimal(line.credit_amount)
            > decimal(source.remaining_creditable_amount) + 0.000001;
    });
});

const fieldError = (field: string): string | undefined => {
    return (
        form.errors as Record<string, string | undefined>
    )[field];
};

const setLineType = (
    index: number,
    lineType: CustomerCreditLineType,
): void => {
    const line = form.lines[index];

    if (!line) {
        return;
    }

    line.line_type = lineType;

    if (lineType === 'quantity') {
        line.credit_amount = '0.000000';
    } else {
        line.credit_quantity = '0.000000';
        line.return_to_stock = false;
    }
};

const handleLineTypeChange = (
    index: number,
    event: Event,
): void => {
    const target = event.target;

    if (!(target instanceof HTMLSelectElement)) {
        return;
    }

    const value = target.value;

    if (value !== 'quantity' && value !== 'amount') {
        return;
    }

    setLineType(index, value);
};

const setMaximum = (index: number): void => {
    const source = props.salesInvoice.lines[index];
    const line = form.lines[index];

    if (!source || !line) {
        return;
    }

    if (line.line_type === 'quantity') {
        line.credit_quantity = source.remaining_creditable_quantity;
    } else {
        line.credit_amount = source.remaining_creditable_amount;
    }
};

const clearLine = (index: number): void => {
    const line = form.lines[index];

    if (!line) {
        return;
    }

    line.credit_quantity = '0.000000';
    line.credit_amount = '0.000000';
    line.return_to_stock = false;
};

const submit = (): void => {
    if (props.creditNote) {
        form.put(
            route('sales-returns.update', props.creditNote.id),
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        route('sales-returns.store'),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                    Sales Invoice
                </p>

                <p class="mt-2 font-semibold text-gray-900 dark:text-white">
                    {{ salesInvoice.invoice_number }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                    Customer
                </p>

                <p class="mt-2 font-semibold text-gray-900 dark:text-white">
                    {{ salesInvoice.customer_name }}
                </p>

                <p class="mt-1 text-xs text-gray-500">
                    {{ salesInvoice.customer_code }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                    Invoice Total
                </p>

                <p class="mt-2 font-semibold text-gray-900 dark:text-white">
                    {{ salesInvoice.currency_code }}
                    {{ formatAmount(decimal(salesInvoice.total_amount)) }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                    AR Outstanding
                </p>

                <p class="mt-2 font-semibold text-brand-600 dark:text-brand-400">
                    {{ salesInvoice.currency_code }}
                    {{ formatAmount(decimal(salesInvoice.open_item_outstanding)) }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                    Return Warehouse
                </p>

                <p class="mt-2 font-semibold text-gray-900 dark:text-white">
                    {{ salesInvoice.warehouse?.name ?? 'No warehouse' }}
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <div class="mb-5">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Credit Note Information
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    The document number is assigned during submission. Posting date controls the accounting period.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Credit Note Date <span class="text-error-500">*</span>
                    </label>

                    <input
                        v-model="form.credit_note_date"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >

                    <p v-if="form.errors.credit_note_date" class="mt-1 text-xs text-error-500">
                        {{ form.errors.credit_note_date }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Posting Date <span class="text-error-500">*</span>
                    </label>

                    <input
                        v-model="form.posting_date"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >

                    <p v-if="form.errors.posting_date" class="mt-1 text-xs text-error-500">
                        {{ form.errors.posting_date }}
                    </p>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Currency
                    </label>

                    <input
                        :value="`${salesInvoice.currency_code} @ ${salesInvoice.exchange_rate}`"
                        readonly
                        class="h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                    >
                </div>

                <div class="md:col-span-2 xl:col-span-3">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Reason <span class="text-error-500">*</span>
                    </label>

                    <input
                        v-model="form.reason"
                        type="text"
                        maxlength="500"
                        placeholder="Damaged goods, pricing correction, service adjustment, customer return, etc."
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >

                    <p v-if="form.errors.reason" class="mt-1 text-xs text-error-500">
                        {{ form.errors.reason }}
                    </p>
                </div>

                <div class="md:col-span-2 xl:col-span-3">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Return Address
                    </label>

                    <textarea
                        v-model="form.return_address"
                        rows="3"
                        maxlength="4000"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-5 dark:border-gray-800 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Credit Lines
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Quantity credits follow original invoice pricing and dispatch cost. Amount credits reverse value only.
                </p>
            </div>

            <p v-if="form.errors.lines" class="px-5 pt-4 text-sm text-error-500 sm:px-6">
                {{ form.errors.lines }}
            </p>

            <div class="overflow-x-auto">
                <table class="min-w-[1450px] divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.03]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Invoice Line
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Invoiced
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Remaining Qty
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Remaining Value
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Credit Type
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Quantity / Amount
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Inventory
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Preview Total
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr
                            v-for="(source, index) in salesInvoice.lines"
                            :key="source.id"
                        >
                            <td class="px-5 py-4">
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ source.line_number }}. {{ source.product_name }}
                                </p>

                                <p class="text-xs text-gray-500">
                                    {{ source.product_sku }} · {{ source.unit_code }} · {{ source.product_type.replace(/_/g, ' ') }}
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Unit price {{ formatAmount(decimal(source.unit_price)) }} · Tax {{ formatQuantity(source.tax_rate) }}%
                                </p>
                            </td>

                            <td class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                {{ formatQuantity(source.invoiced_quantity) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                {{ formatQuantity(source.remaining_creditable_quantity) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                {{ salesInvoice.currency_code }}
                                {{ formatAmount(decimal(source.remaining_creditable_amount)) }}
                            </td>

                            <td class="px-5 py-4">
                                <select
                                    :value="form.lines[index]?.line_type"
                                    class="h-11 w-36 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                                    @change="handleLineTypeChange(index, $event)"
                                >
                                    <option value="quantity">Quantity</option>
                                    <option value="amount">Amount only</option>
                                </select>
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex min-w-72 items-center gap-2">
                                    <input
                                        v-if="form.lines[index]?.line_type === 'quantity'"
                                        v-model="form.lines[index].credit_quantity"
                                        :max="source.remaining_creditable_quantity"
                                        min="0"
                                        step="0.000001"
                                        type="number"
                                        class="h-11 w-40 rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                                    >

                                    <input
                                        v-else
                                        v-model="form.lines[index].credit_amount"
                                        :max="source.remaining_creditable_amount"
                                        min="0"
                                        step="0.000001"
                                        type="number"
                                        class="h-11 w-40 rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                                    >

                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                        @click="setMaximum(index)"
                                    >
                                        Full
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                        @click="clearLine(index)"
                                    >
                                        Zero
                                    </button>
                                </div>

                                <p
                                    v-if="fieldError(`lines.${index}.credit_quantity`) || fieldError(`lines.${index}.credit_amount`)"
                                    class="mt-1 text-xs text-error-500"
                                >
                                    {{ fieldError(`lines.${index}.credit_quantity`) ?? fieldError(`lines.${index}.credit_amount`) }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <label
                                    v-if="source.product_type === 'stock' && form.lines[index]?.line_type === 'quantity'"
                                    class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300"
                                >
                                    <input
                                        v-model="form.lines[index].return_to_stock"
                                        :disabled="salesInvoice.warehouse === null"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500"
                                    >
                                    Return to stock
                                </label>

                                <span v-else class="text-sm text-gray-400">
                                    Financial credit only
                                </span>

                                <p
                                    v-if="source.product_type === 'stock' && salesInvoice.warehouse === null"
                                    class="mt-1 text-xs text-warning-600 dark:text-warning-400"
                                >
                                    Source order has no warehouse.
                                </p>
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                {{ salesInvoice.currency_code }}
                                {{ formatAmount(linePreview(source, form.lines[index]).total) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_390px]">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Internal Notes
                </label>

                <textarea
                    v-model="form.notes"
                    rows="6"
                    maxlength="4000"
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                />
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Credit Summary
                </h2>

                <dl class="mt-5 space-y-4 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Selected Lines</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ selectedLineCount }}</dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Subtotal</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ formatAmount(subtotal) }}</dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Output Tax Reversal</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ formatAmount(taxTotal) }}</dd>
                    </div>

                    <div class="flex justify-between gap-4">
                        <dt class="text-gray-500">Returned Quantity</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">{{ formatQuantity(returnedQuantity) }}</dd>
                    </div>

                    <div class="border-t border-gray-200 pt-4 dark:border-gray-800">
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-gray-900 dark:text-white">Credit Total</dt>
                            <dd class="text-xl font-semibold text-brand-600 dark:text-brand-400">
                                {{ salesInvoice.currency_code }} {{ formatAmount(totalAmount) }}
                            </dd>
                        </div>
                    </div>

                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Auto-applied to invoice</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">
                                {{ formatAmount(autoAllocationPreview) }}
                            </dd>
                        </div>

                        <div class="mt-2 flex justify-between gap-4">
                            <dt class="text-gray-500">Remaining customer credit</dt>
                            <dd class="font-medium text-gray-900 dark:text-white">
                                {{ formatAmount(remainingCustomerCredit) }}
                            </dd>
                        </div>
                    </div>
                </dl>
            </div>
        </div>

        <div
            v-if="hasLineConflict"
            class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-900/50 dark:bg-error-900/20 dark:text-error-300"
        >
            One or more line values exceed the remaining creditable quantity or amount.
        </div>

        <div
            v-if="form.hasErrors"
            class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-900/50 dark:bg-error-900/20 dark:text-error-300"
        >
            Correct the validation errors before saving.
        </div>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <Link
                :href="creditNote
                    ? route('sales-returns.show', creditNote.id)
                    : route('sales-returns.index')"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Cancel
            </Link>

            <button
                :disabled="
                    form.processing
                    || selectedLineCount === 0
                    || hasLineConflict
                    || form.reason.trim() === ''
                "
                type="submit"
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{
                    form.processing
                        ? 'Saving...'
                        : isEditing
                            ? 'Update Credit Note Draft'
                            : 'Create Credit Note Draft'
                }}
            </button>
        </div>
    </form>
</template>
