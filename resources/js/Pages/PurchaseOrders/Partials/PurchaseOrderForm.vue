<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import type {
    ExistingPurchaseOrderFormData,
    PurchaseOrderFormData,
    PurchaseOrderFormLine,
    PurchaseOrderFormProps,
    PurchaseOrderProductOption,
} from '@/Types/purchase-order';

interface Props extends PurchaseOrderFormProps {
    purchaseOrder?: ExistingPurchaseOrderFormData;
}

const props = defineProps<Props>();

const newLine = (): PurchaseOrderFormLine => ({
    product_id: null,
    unit_id: null,
    description: '',
    ordered_quantity: '1.000000',
    unit_price: '0.000000',
    discount_amount: '0.000000',
    tax_rate: '0.000000',
});

const form = useForm<PurchaseOrderFormData>({
    branch_id:
        props.purchaseOrder?.branch_id ?? null,

    warehouse_id:
        props.purchaseOrder?.warehouse_id ?? null,

    supplier_id:
        props.purchaseOrder?.supplier_id ?? null,

    order_date:
        props.purchaseOrder?.order_date
        ?? props.defaults.order_date,

    expected_delivery_date:
        props.purchaseOrder
            ?.expected_delivery_date
        ?? '',

    supplier_reference:
        props.purchaseOrder
            ?.supplier_reference
        ?? '',

    currency_code:
        props.purchaseOrder?.currency_code
        ?? props.defaults.currency_code,

    exchange_rate:
        props.purchaseOrder?.exchange_rate
        ?? props.defaults.exchange_rate,

    delivery_address:
        props.purchaseOrder?.delivery_address
        ?? '',

    payment_terms_days:
        props.purchaseOrder
            ?.payment_terms_days
        ?? null,

    shipping_amount:
        props.purchaseOrder?.shipping_amount
        ?? props.defaults.shipping_amount,

    other_charges:
        props.purchaseOrder?.other_charges
        ?? props.defaults.other_charges,

    terms_and_conditions:
        props.purchaseOrder
            ?.terms_and_conditions
        ?? '',

    notes:
        props.purchaseOrder?.notes ?? '',

    lines:
        props.purchaseOrder?.lines.length
            ? props.purchaseOrder.lines.map(
                (line): PurchaseOrderFormLine => ({
                    id: line.id,
                    product_id: line.product_id,
                    unit_id: line.unit_id,
                    description:
                        line.description ?? '',

                    ordered_quantity:
                        line.ordered_quantity,

                    unit_price:
                        line.unit_price,

                    discount_amount:
                        line.discount_amount,

                    tax_rate:
                        line.tax_rate,

                    gross_amount:
                        line.gross_amount,

                    tax_amount:
                        line.tax_amount,

                    line_total:
                        line.line_total,
                }),
            )
            : [newLine()],
});

const isEditing = computed(
    (): boolean => props.purchaseOrder !== undefined,
);

const availableWarehouses = computed(() => {
    if (form.branch_id === null) {
        return [];
    }

    return props.warehouseOptions.filter(
        (warehouse) =>
            warehouse.branch_id === form.branch_id,
    );
});

const selectedSupplier = computed(() => {
    if (form.supplier_id === null) {
        return null;
    }

    return props.supplierOptions.find(
        (supplier) =>
            supplier.value === form.supplier_id,
    ) ?? null;
});

const selectedWarehouse = computed(() => {
    if (form.warehouse_id === null) {
        return null;
    }

    return props.warehouseOptions.find(
        (warehouse) =>
            warehouse.value === form.warehouse_id,
    ) ?? null;
});

const hasStockLines = computed((): boolean => {
    return form.lines.some((line) => {
        const product = findProduct(
            line.product_id,
        );

        return product?.product_type === 'stock';
    });
});

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

const normalizeNumericInput = (
    value: string | number,
): string => String(value).trim();

const formatDecimal = (
    value: number,
    places = 6,
): string => {
    if (!Number.isFinite(value)) {
        return Number(0).toFixed(places);
    }

    return value.toFixed(places);
};

const findProduct = (
    productId: number | null,
): PurchaseOrderProductOption | null => {
    if (productId === null) {
        return null;
    }

    return props.productOptions.find(
        (product) =>
            product.value === productId,
    ) ?? null;
};

const productIsAvailable = (
    product: PurchaseOrderProductOption,
): boolean => {
    if (form.branch_id === null) {
        return false;
    }

    if (
        !product.branch_ids.includes(
            form.branch_id,
        )
    ) {
        return false;
    }

    if (product.product_type !== 'stock') {
        return true;
    }

    return form.warehouse_id !== null
        && product.warehouse_ids.includes(
            form.warehouse_id,
        );
};

const productsForLine = (
    lineIndex: number,
): PurchaseOrderProductOption[] => {
    const currentProductId =
        form.lines[lineIndex]?.product_id
        ?? null;

    return props.productOptions.filter(
        (product) =>
            productIsAvailable(product)
            || product.value === currentProductId,
    );
};

const resetProduct = (
    line: PurchaseOrderFormLine,
): void => {
    line.product_id = null;
    line.unit_id = null;
    line.unit_price = '0.000000';
};

const ensureLineProductsRemainAvailable = (): void => {
    form.lines.forEach((line) => {
        const product = findProduct(
            line.product_id,
        );

        if (
            product !== null
            && !productIsAvailable(product)
        ) {
            resetProduct(line);
        }
    });
};

watch(
    () => form.branch_id,
    (branchId, previousBranchId) => {
        if (branchId === previousBranchId) {
            return;
        }

        const warehouseStillAvailable =
            branchId !== null
            && props.warehouseOptions.some(
                (warehouse) =>
                    warehouse.value
                        === form.warehouse_id
                    && warehouse.branch_id
                        === branchId,
            );

        if (!warehouseStillAvailable) {
            form.warehouse_id = null;
        }

        ensureLineProductsRemainAvailable();

        if (
            form.delivery_address.trim() === ''
            && branchId !== null
        ) {
            const branch =
                props.branchOptions.find(
                    (option) =>
                        option.value === branchId,
                );

            form.delivery_address =
                branch?.address ?? '';
        }
    },
);

watch(
    () => form.warehouse_id,
    (warehouseId, previousWarehouseId) => {
        if (
            warehouseId
            === previousWarehouseId
        ) {
            return;
        }

        const warehouse =
            selectedWarehouse.value;

        if (
            warehouse?.address
            && warehouse.address.trim() !== ''
        ) {
            form.delivery_address =
                warehouse.address;
        }

        ensureLineProductsRemainAvailable();
    },
);

watch(
    () => form.supplier_id,
    (supplierId, previousSupplierId) => {
        if (
            supplierId
            === previousSupplierId
        ) {
            return;
        }

        const supplier =
            selectedSupplier.value;

        if (supplier === null) {
            return;
        }

        form.payment_terms_days =
            supplier.payment_terms_days ?? 0;

        if (
            supplier.address
            && form.delivery_address.trim()
                === ''
        ) {
            form.delivery_address =
                supplier.address;
        }
    },
);

const onProductChange = (
    lineIndex: number,
): void => {
    const line = form.lines[lineIndex];

    if (!line) {
        return;
    }

    const product = findProduct(
        line.product_id,
    );

    if (product === null) {
        resetProduct(line);

        return;
    }

    line.unit_id = product.base_unit.id;
    line.unit_price =
        product.default_unit_price;

    if (
        decimalValue(
            line.ordered_quantity,
        ) <= 0
    ) {
        line.ordered_quantity =
            product.base_unit.allow_decimal
                ? formatDecimal(
                    1,
                    product.base_unit
                        .decimal_places,
                )
                : '1';
    }
};

const addLine = (): void => {
    form.lines.push(newLine());
};

const removeLine = (
    lineIndex: number,
): void => {
    if (form.lines.length === 1) {
        form.lines[0] = newLine();

        return;
    }

    form.lines.splice(lineIndex, 1);
};

interface CalculatedLineAmounts {
    gross: number;
    discount: number;
    taxable: number;
    tax: number;
    total: number;
}

const calculateLine = (
    line: PurchaseOrderFormLine,
): CalculatedLineAmounts => {
    const quantity = Math.max(
        decimalValue(
            line.ordered_quantity,
        ),
        0,
    );

    const unitPrice = Math.max(
        decimalValue(line.unit_price),
        0,
    );

    const gross = quantity * unitPrice;

    const enteredDiscount = Math.max(
        decimalValue(
            line.discount_amount,
        ),
        0,
    );

    const discount = Math.min(
        enteredDiscount,
        gross,
    );

    const taxable = Math.max(
        gross - discount,
        0,
    );

    const taxRate = Math.min(
        Math.max(
            decimalValue(line.tax_rate),
            0,
        ),
        100,
    );

    const tax = taxable
        * (taxRate / 100);

    return {
        gross,
        discount,
        taxable,
        tax,
        total: taxable + tax,
    };
};

const subtotal = computed((): number => {
    return form.lines.reduce(
        (total, line) =>
            total
            + calculateLine(line).gross,
        0,
    );
});

const lineDiscountTotal = computed(
    (): number => {
        return form.lines.reduce(
            (total, line) =>
                total
                + calculateLine(
                    line,
                ).discount,
            0,
        );
    },
);

const taxTotal = computed((): number => {
    return form.lines.reduce(
        (total, line) =>
            total
            + calculateLine(line).tax,
        0,
    );
});

const grandTotal = computed((): number => {
    return subtotal.value
        - lineDiscountTotal.value
        + taxTotal.value
        + Math.max(
            decimalValue(
                form.shipping_amount,
            ),
            0,
        )
        + Math.max(
            decimalValue(
                form.other_charges,
            ),
            0,
        );
});

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
    form.transform(
        (
            data,
        ): PurchaseOrderFormData => ({
            ...data,

            currency_code:
                data.currency_code
                    .trim()
                    .toUpperCase(),

            exchange_rate:
                normalizeNumericInput(
                    data.exchange_rate,
                ),

            shipping_amount:
                normalizeNumericInput(
                    data.shipping_amount,
                ),

            other_charges:
                normalizeNumericInput(
                    data.other_charges,
                ),

            supplier_reference:
                data.supplier_reference.trim(),

            delivery_address:
                data.delivery_address.trim(),

            terms_and_conditions:
                data.terms_and_conditions.trim(),

            notes: data.notes.trim(),

            lines: data.lines.map(
                (
                    line,
                ): PurchaseOrderFormLine => ({
                    product_id:
                        line.product_id,

                    unit_id:
                        line.unit_id,

                    description:
                        line.description.trim(),

                    ordered_quantity:
                        normalizeNumericInput(
                            line.ordered_quantity,
                        ),

                    unit_price:
                        normalizeNumericInput(
                            line.unit_price,
                        ),

                    discount_amount:
                        normalizeNumericInput(
                            line.discount_amount,
                        ),

                    tax_rate:
                        normalizeNumericInput(
                            line.tax_rate,
                        ),
                }),
            ),
        }),
    );

    if (
        props.purchaseOrder !== undefined
    ) {
        form.put(
            route(
                'purchase-orders.update',
                props.purchaseOrder.id,
            ),
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        route('purchase-orders.store'),
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
            <div
                class="mb-5 flex flex-col gap-1"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Purchase Order Information
                </h2>

                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Select the purchasing location,
                    Supplier, dates, and commercial
                    terms.
                </p>
            </div>

            <div
                class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3"
            >
                <div>
                    <label
                        for="purchase-order-branch"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Branch
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        id="purchase-order-branch"
                        v-model="form.branch_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option :value="null">
                            Select branch
                        </option>

                        <option
                            v-for="branch in branchOptions"
                            :key="branch.value"
                            :value="branch.value"
                        >
                            {{ branch.label }}
                        </option>
                    </select>

                    <p
                        v-if="fieldError('branch_id')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('branch_id') }}
                    </p>
                </div>

                <div>
                    <label
                        for="purchase-order-warehouse"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Receiving Warehouse
                        <span
                            v-if="hasStockLines"
                            class="text-red-500"
                        >
                            *
                        </span>
                    </label>

                    <select
                        id="purchase-order-warehouse"
                        v-model="form.warehouse_id"
                        :disabled="form.branch_id === null"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                    >
                        <option :value="null">
                            No warehouse selected
                        </option>

                        <option
                            v-for="warehouse in availableWarehouses"
                            :key="warehouse.value"
                            :value="warehouse.value"
                        >
                            {{ warehouse.label }}
                            {{
                                warehouse.is_default
                                    ? ' — Default'
                                    : ''
                            }}
                        </option>
                    </select>

                    <p
                        v-if="fieldError('warehouse_id')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('warehouse_id') }}
                    </p>

                    <p
                        v-else-if="hasStockLines && form.warehouse_id === null"
                        class="mt-1 text-sm text-amber-600 dark:text-amber-400"
                    >
                        Stock products require a
                        receiving warehouse.
                    </p>
                </div>

                <div>
                    <label
                        for="purchase-order-supplier"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        id="purchase-order-supplier"
                        v-model="form.supplier_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option :value="null">
                            Select Supplier
                        </option>

                        <option
                            v-for="supplier in supplierOptions"
                            :key="supplier.value"
                            :value="supplier.value"
                        >
                            {{ supplier.label }}
                        </option>
                    </select>

                    <p
                        v-if="fieldError('supplier_id')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('supplier_id') }}
                    </p>
                </div>

                <div>
                    <label
                        for="purchase-order-date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Order Date
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="purchase-order-date"
                        v-model="form.order_date"
                        type="date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('order_date')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('order_date') }}
                    </p>
                </div>

                <div>
                    <label
                        for="purchase-order-delivery-date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Expected Delivery Date
                    </label>

                    <input
                        id="purchase-order-delivery-date"
                        v-model="form.expected_delivery_date"
                        type="date"
                        :min="form.order_date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('expected_delivery_date')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            fieldError(
                                'expected_delivery_date',
                            )
                        }}
                    </p>
                </div>

                <div>
                    <label
                        for="purchase-order-reference"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier Reference
                    </label>

                    <input
                        id="purchase-order-reference"
                        v-model="form.supplier_reference"
                        type="text"
                        maxlength="120"
                        placeholder="Quotation or reference number"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('supplier_reference')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            fieldError(
                                'supplier_reference',
                            )
                        }}
                    </p>
                </div>

                <div>
                    <label
                        for="purchase-order-currency"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Currency
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="purchase-order-currency"
                        v-model="form.currency_code"
                        type="text"
                        maxlength="3"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm uppercase text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('currency_code')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('currency_code') }}
                    </p>
                </div>

                <div>
                    <label
                        for="purchase-order-rate"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Exchange Rate
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="purchase-order-rate"
                        v-model="form.exchange_rate"
                        type="number"
                        min="0.00000001"
                        step="0.00000001"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('exchange_rate')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('exchange_rate') }}
                    </p>
                </div>

                <div>
                    <label
                        for="purchase-order-payment-terms"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Payment Terms
                    </label>

                    <div class="relative">
                        <input
                            id="purchase-order-payment-terms"
                            v-model.number="form.payment_terms_days"
                            type="number"
                            min="0"
                            max="3650"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 pr-14 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        />

                        <span
                            class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-gray-500"
                        >
                            days
                        </span>
                    </div>

                    <p
                        v-if="fieldError('payment_terms_days')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            fieldError(
                                'payment_terms_days',
                            )
                        }}
                    </p>
                </div>

                <div class="md:col-span-2 xl:col-span-3">
                    <label
                        for="purchase-order-address"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Delivery Address
                    </label>

                    <textarea
                        id="purchase-order-address"
                        v-model="form.delivery_address"
                        rows="3"
                        maxlength="4000"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('delivery_address')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{
                            fieldError(
                                'delivery_address',
                            )
                        }}
                    </p>
                </div>
            </div>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-800"
            >
                <div>
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Order Lines
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Add the products or services
                        being purchased.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-brand-50 px-4 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-100 dark:bg-brand-500/10 dark:text-brand-400 dark:hover:bg-brand-500/20"
                    @click="addLine"
                >
                    Add Line
                </button>
            </div>

            <div
                v-if="fieldError('lines')"
                class="mx-5 mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-400"
            >
                {{ fieldError('lines') }}
            </div>

            <div class="overflow-x-auto">
                <table
                    class="min-w-[1250px] w-full"
                >
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-400"
                        >
                            <th class="w-14 px-4 py-3">
                                #
                            </th>

                            <th class="min-w-64 px-4 py-3">
                                Product
                            </th>

                            <th class="min-w-36 px-4 py-3">
                                Unit
                            </th>

                            <th class="min-w-52 px-4 py-3">
                                Description
                            </th>

                            <th class="w-36 px-4 py-3">
                                Quantity
                            </th>

                            <th class="w-40 px-4 py-3">
                                Unit Price
                            </th>

                            <th class="w-40 px-4 py-3">
                                Discount
                            </th>

                            <th class="w-32 px-4 py-3">
                                Tax %
                            </th>

                            <th class="w-40 px-4 py-3 text-right">
                                Total
                            </th>

                            <th class="w-20 px-4 py-3" />
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="(line, index) in form.lines"
                            :key="line.id ?? index"
                            class="border-b border-gray-100 align-top last:border-b-0 dark:border-gray-800"
                        >
                            <td
                                class="px-4 py-4 text-sm font-medium text-gray-500"
                            >
                                {{ index + 1 }}
                            </td>

                            <td class="px-4 py-4">
                                <select
                                    v-model="line.product_id"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                    @change="onProductChange(index)"
                                >
                                    <option :value="null">
                                        Select product
                                    </option>

                                    <option
                                        v-for="product in productsForLine(index)"
                                        :key="product.value"
                                        :value="product.value"
                                    >
                                        {{ product.label }}
                                    </option>
                                </select>

                                <p
                                    v-if="fieldError(`lines.${index}.product_id`)"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{
                                        fieldError(
                                            `lines.${index}.product_id`,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <input
                                    :value="
                                        findProduct(line.product_id)
                                            ?.base_unit.code
                                        ?? ''
                                    "
                                    type="text"
                                    readonly
                                    class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                />

                                <p
                                    v-if="fieldError(`lines.${index}.unit_id`)"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{
                                        fieldError(
                                            `lines.${index}.unit_id`,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <input
                                    v-model="line.description"
                                    type="text"
                                    maxlength="4000"
                                    placeholder="Optional description"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                />

                                <p
                                    v-if="fieldError(`lines.${index}.description`)"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{
                                        fieldError(
                                            `lines.${index}.description`,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <input
                                    v-model="line.ordered_quantity"
                                    type="number"
                                    min="0.000001"
                                    step="0.000001"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                />

                                <p
                                    v-if="fieldError(`lines.${index}.ordered_quantity`)"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{
                                        fieldError(
                                            `lines.${index}.ordered_quantity`,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <input
                                    v-model="line.unit_price"
                                    type="number"
                                    min="0"
                                    step="0.000001"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                />

                                <p
                                    v-if="fieldError(`lines.${index}.unit_price`)"
                                    class="mt-1 text-xs text-red-600"
                                >
                                    {{
                                        fieldError(
                                            `lines.${index}.unit_price`,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <input
                                    v-model="line.discount_amount"
                                    type="number"
                                    min="0"
                                    step="0.000001"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                />

                                <p
                                    v-if="fieldError(`lines.${index}.discount_amount`)"
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
                                    v-model="line.tax_rate"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.000001"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                />

                                <p
                                    v-if="fieldError(`lines.${index}.tax_rate`)"
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
                                class="px-4 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    formatDecimal(
                                        calculateLine(line).total,
                                    )
                                }}
                            </td>

                            <td class="px-4 py-4 text-right">
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                                    @click="removeLine(index)"
                                >
                                    Remove
                                </button>
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
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Additional Information
                </h2>

                <div class="space-y-5">
                    <div>
                        <label
                            for="purchase-order-terms"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Terms and Conditions
                        </label>

                        <textarea
                            id="purchase-order-terms"
                            v-model="form.terms_and_conditions"
                            rows="5"
                            maxlength="10000"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        />

                        <p
                            v-if="fieldError('terms_and_conditions')"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{
                                fieldError(
                                    'terms_and_conditions',
                                )
                            }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="purchase-order-notes"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Internal Notes
                        </label>

                        <textarea
                            id="purchase-order-notes"
                            v-model="form.notes"
                            rows="4"
                            maxlength="4000"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
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
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Order Summary
                </h2>

                <div class="space-y-4">
                    <div
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-gray-500">
                            Subtotal
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{ form.currency_code }}
                            {{ formatDecimal(subtotal) }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-gray-500">
                            Line Discounts
                        </span>

                        <span
                            class="font-medium text-red-600"
                        >
                            − {{ form.currency_code }}
                            {{
                                formatDecimal(
                                    lineDiscountTotal,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-gray-500">
                            Tax
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{ form.currency_code }}
                            {{ formatDecimal(taxTotal) }}
                        </span>
                    </div>

                    <div>
                        <label
                            for="purchase-order-shipping"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Shipping Amount
                        </label>

                        <input
                            id="purchase-order-shipping"
                            v-model="form.shipping_amount"
                            type="number"
                            min="0"
                            step="0.000001"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        />

                        <p
                            v-if="fieldError('shipping_amount')"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{
                                fieldError(
                                    'shipping_amount',
                                )
                            }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="purchase-order-other-charges"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Other Charges
                        </label>

                        <input
                            id="purchase-order-other-charges"
                            v-model="form.other_charges"
                            type="number"
                            min="0"
                            step="0.000001"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-right text-sm text-gray-900 outline-none focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        />

                        <p
                            v-if="fieldError('other_charges')"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{
                                fieldError(
                                    'other_charges',
                                )
                            }}
                        </p>
                    </div>

                    <div
                        class="border-t border-gray-200 pt-4 dark:border-gray-800"
                    >
                        <div
                            class="flex items-center justify-between"
                        >
                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                Grand Total
                            </span>

                            <span
                                class="text-lg font-bold text-gray-900 dark:text-white"
                            >
                                {{ form.currency_code }}
                                {{
                                    formatDecimal(
                                        grandTotal,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
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
                            'purchase-orders.show',
                            purchaseOrder?.id,
                        )
                        : route(
                            'purchase-orders.index',
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
                            ? 'Update Purchase Order'
                            : 'Create Purchase Order'
                }}
            </button>
        </div>
    </form>
</template>