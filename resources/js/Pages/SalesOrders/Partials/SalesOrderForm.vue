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
    ExistingSalesOrderFormData,
    SalesOrderFormData,
    SalesOrderFormLine,
    SalesOrderFormProps,
    SalesOrderProductOption,
} from '@/Types/sales-order';

interface Props extends SalesOrderFormProps {
    salesOrder?: ExistingSalesOrderFormData;
}

const props = defineProps<Props>();

const emptyLine = (): SalesOrderFormLine => ({
    product_id: null,
    unit_id: null,
    description: '',
    ordered_quantity: '1.000000',
    unit_price: '0.000000',
    discount_amount: '0.000000',
    tax_rate: '0.000000',
});

const form = useForm<SalesOrderFormData>({
    branch_id:
        props.salesOrder?.branch_id ?? null,

    warehouse_id:
        props.salesOrder?.warehouse_id ?? null,

    customer_id:
        props.salesOrder?.customer_id ?? null,

    order_date:
        props.salesOrder?.order_date
        ?? props.defaults.order_date,

    requested_delivery_date:
        props.salesOrder
            ?.requested_delivery_date
        ?? '',

    customer_reference:
        props.salesOrder
            ?.customer_reference
        ?? '',

    currency_code:
        props.salesOrder?.currency_code
        ?? props.defaults.currency_code,

    exchange_rate:
        props.salesOrder?.exchange_rate
        ?? props.defaults.exchange_rate,

    billing_address:
        props.salesOrder?.billing_address
        ?? '',

    shipping_address:
        props.salesOrder?.shipping_address
        ?? '',

    payment_terms_days:
        props.salesOrder
            ?.payment_terms_days
        ?? null,

    shipping_amount:
        props.salesOrder?.shipping_amount
        ?? props.defaults.shipping_amount,

    other_charges:
        props.salesOrder?.other_charges
        ?? props.defaults.other_charges,

    delivery_instructions:
        props.salesOrder
            ?.delivery_instructions
        ?? '',

    terms_and_conditions:
        props.salesOrder
            ?.terms_and_conditions
        ?? '',

    notes:
        props.salesOrder?.notes ?? '',

    lines:
        props.salesOrder?.lines.length
            ? props.salesOrder.lines.map(
                (
                    line,
                ): SalesOrderFormLine => ({
                    id: line.id,

                    product_id:
                        line.product_id,

                    unit_id:
                        line.unit_id,

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
            : [emptyLine()],
});

const isEditing = computed(
    (): boolean =>
        props.salesOrder !== undefined,
);

const availableWarehouses = computed(() => {
    if (form.branch_id === null) {
        return [];
    }

    return props.warehouses.filter(
        (warehouse) =>
            warehouse.branch_id
                === form.branch_id
            && warehouse.status
                === 'active',
    );
});

const selectedCustomer = computed(() => {
    if (form.customer_id === null) {
        return null;
    }

    return props.customers.find(
        (customer) =>
            customer.id === form.customer_id,
    ) ?? null;
});

const decimalValue = (
    value:
        | string
        | number
        | null
        | undefined,
): number => {
    const parsed = Number.parseFloat(
        String(value ?? '0'),
    );

    return Number.isFinite(parsed)
        ? parsed
        : 0;
};

const fixedDecimal = (
    value: number,
    places = 6,
): string => {
    if (!Number.isFinite(value)) {
        return Number(0).toFixed(places);
    }

    return value.toFixed(places);
};

const formattedAmount = (
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

const findProduct = (
    productId: number | null,
): SalesOrderProductOption | null => {
    if (productId === null) {
        return null;
    }

    return props.products.find(
        (product) =>
            product.id === productId,
    ) ?? null;
};

const branchPrice = (
    product: SalesOrderProductOption,
): string => {
    if (form.branch_id === null) {
        return product.selling_price;
    }

    return product.branch_settings.find(
        (setting) =>
            setting.branch_id
                === form.branch_id,
    )?.selling_price
        ?? product.selling_price;
};

const productIsAvailable = (
    product: SalesOrderProductOption,
): boolean => {
    if (
        form.branch_id === null
        || product.base_unit === null
    ) {
        return false;
    }

    const enabledInBranch =
        product.branch_settings.some(
            (setting) =>
                setting.branch_id
                    === form.branch_id,
        );

    if (!enabledInBranch) {
        return false;
    }

    if (product.product_type !== 'stock') {
        return true;
    }

    if (form.warehouse_id === null) {
        return false;
    }

    return product.warehouse_settings.some(
        (setting) =>
            setting.branch_id
                === form.branch_id
            && setting.warehouse_id
                === form.warehouse_id,
    );
};

const productsForLine = (
    lineIndex: number,
): SalesOrderProductOption[] => {
    const currentProductId =
        form.lines[lineIndex]?.product_id
        ?? null;

    return props.products.filter(
        (product) =>
            productIsAvailable(product)
            || product.id
                === currentProductId,
    );
};

const lineAmounts = (
    line: SalesOrderFormLine,
): {
    gross: number;
    discount: number;
    tax: number;
    total: number;
} => {
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

    const gross =
        quantity * unitPrice;

    const discount = Math.min(
        Math.max(
            decimalValue(
                line.discount_amount,
            ),
            0,
        ),
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

    const tax =
        taxable * (taxRate / 100);

    return {
        gross,
        discount,
        tax,
        total: taxable + tax,
    };
};

const subtotal = computed(
    (): number => {
        return form.lines.reduce(
            (total, line) =>
                total
                + lineAmounts(line).gross,
            0,
        );
    },
);

const discountTotal = computed(
    (): number => {
        return form.lines.reduce(
            (total, line) =>
                total
                + lineAmounts(
                    line,
                ).discount,
            0,
        );
    },
);

const taxTotal = computed(
    (): number => {
        return form.lines.reduce(
            (total, line) =>
                total
                + lineAmounts(line).tax,
            0,
        );
    },
);

const grandTotal = computed(
    (): number => {
        return subtotal.value
            - discountTotal.value
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
    },
);

const hasStockLines = computed(
    (): boolean => {
        return form.lines.some(
            (line) =>
                findProduct(
                    line.product_id,
                )?.product_type
                    === 'stock',
        );
    },
);

const hasUnavailableLine = computed(
    (): boolean => {
        return form.lines.some(
            (line) => {
                const product =
                    findProduct(
                        line.product_id,
                    );

                return product !== null
                    && !productIsAvailable(
                        product,
                    );
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

const resetLineProduct = (
    line: SalesOrderFormLine,
): void => {
    line.product_id = null;
    line.unit_id = null;
    line.unit_price = '0.000000';
    line.discount_amount = '0.000000';
};

const removeUnavailableProducts =
    (): void => {
        form.lines.forEach((line) => {
            const product = findProduct(
                line.product_id,
            );

            if (
                product !== null
                && !productIsAvailable(
                    product,
                )
            ) {
                resetLineProduct(line);
            }
        });
    };

const onProductChanged = (
    lineIndex: number,
): void => {
    const line = form.lines[lineIndex];

    if (line === undefined) {
        return;
    }

    const product = findProduct(
        line.product_id,
    );

    if (
        product === null
        || product.base_unit === null
    ) {
        line.unit_id = null;
        line.unit_price = '0.000000';

        return;
    }

    line.unit_id =
        product.base_unit.id;

    line.unit_price = fixedDecimal(
        decimalValue(
            branchPrice(product),
        ),
    );
};

const addLine = (): void => {
    form.lines.push(emptyLine());
};

const removeLine = (
    lineIndex: number,
): void => {
    if (form.lines.length === 1) {
        form.lines[0] = emptyLine();

        return;
    }

    form.lines.splice(lineIndex, 1);
};

const applyCustomerDefaults =
    (): void => {
        const customer =
            selectedCustomer.value;

        if (customer === null) {
            form.billing_address = '';
            form.shipping_address = '';
            form.payment_terms_days = null;

            return;
        }

        form.billing_address =
            customer.billing_address ?? '';

        form.shipping_address =
            customer.shipping_address
            ?? customer.billing_address
            ?? '';

        form.payment_terms_days =
            customer.payment_terms_days ?? 0;
    };

watch(
    () => form.customer_id,
    (current, previous) => {
        if (current === previous) {
            return;
        }

        applyCustomerDefaults();
    },
);

watch(
    () => form.branch_id,
    (current, previous) => {
        if (current === previous) {
            return;
        }

        const warehouseStillValid =
            props.warehouses.some(
                (warehouse) =>
                    warehouse.id
                        === form.warehouse_id
                    && warehouse.branch_id
                        === current
                    && warehouse.status
                        === 'active',
            );

        if (!warehouseStillValid) {
            form.warehouse_id = null;
        }

        removeUnavailableProducts();

        form.lines.forEach((line) => {
            const product = findProduct(
                line.product_id,
            );

            if (product !== null) {
                line.unit_price =
                    fixedDecimal(
                        decimalValue(
                            branchPrice(
                                product,
                            ),
                        ),
                    );
            }
        });
    },
);

watch(
    () => form.warehouse_id,
    (current, previous) => {
        if (current !== previous) {
            removeUnavailableProducts();
        }
    },
);

const submit = (): void => {
    const options = {
        preserveScroll: true,
    };

    if (props.salesOrder !== undefined) {
        form.put(
            route(
                'sales-orders.update',
                props.salesOrder.id,
            ),
            options,
        );

        return;
    }

    form.post(
        route('sales-orders.store'),
        options,
    );
};
</script>

<template>
    <form
        class="space-y-6"
        @submit.prevent="submit"
    >
        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
        >
            <div class="mb-5">
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Order Information
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Select the selling branch, customer,
                    fulfillment location, commercial dates,
                    and order currency.
                </p>
            </div>

            <div
                class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
            >
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Branch
                        <span class="text-error-500">
                            *
                        </span>
                    </label>

                    <select
                        v-model="form.branch_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">
                            Select a branch
                        </option>

                        <option
                            v-for="branch in branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }}
                            ({{ branch.code }})
                        </option>
                    </select>

                    <p
                        v-if="form.errors.branch_id"
                        class="mt-1 text-xs text-error-500"
                    >
                        {{ form.errors.branch_id }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Fulfillment Warehouse

                        <span
                            v-if="hasStockLines"
                            class="text-error-500"
                        >
                            *
                        </span>
                    </label>

                    <select
                        v-model="form.warehouse_id"
                        :disabled="
                            form.branch_id === null
                        "
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:text-white dark:disabled:bg-gray-800"
                    >
                        <option :value="null">
                            {{
                                form.branch_id
                                    === null
                                    ? 'Select a branch first'
                                    : 'No warehouse'
                            }}
                        </option>

                        <option
                            v-for="warehouse in availableWarehouses"
                            :key="warehouse.id"
                            :value="warehouse.id"
                        >
                            {{ warehouse.name }}
                            ({{ warehouse.code }})
                        </option>
                    </select>

                    <p
                        v-if="
                            form.errors.warehouse_id
                        "
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            form.errors.warehouse_id
                        }}
                    </p>

                    <p
                        v-else-if="
                            hasStockLines
                            && form.warehouse_id
                                === null
                        "
                        class="mt-1 text-xs text-warning-600 dark:text-warning-400"
                    >
                        Stock products require an active
                        warehouse.
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Customer
                        <span class="text-error-500">
                            *
                        </span>
                    </label>

                    <select
                        v-model="form.customer_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">
                            Select a customer
                        </option>

                        <option
                            v-for="customer in customers"
                            :key="customer.id"
                            :value="customer.id"
                        >
                            {{ customer.name }}
                            ({{ customer.code }})
                        </option>
                    </select>

                    <p
                        v-if="form.errors.customer_id"
                        class="mt-1 text-xs text-error-500"
                    >
                        {{ form.errors.customer_id }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Order Date
                        <span class="text-error-500">
                            *
                        </span>
                    </label>

                    <input
                        v-model="form.order_date"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="form.errors.order_date"
                        class="mt-1 text-xs text-error-500"
                    >
                        {{ form.errors.order_date }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Requested Delivery Date
                    </label>

                    <input
                        v-model="
                            form.requested_delivery_date
                        "
                        :min="form.order_date"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="
                            form.errors.requested_delivery_date
                        "
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            form.errors.requested_delivery_date
                        }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Customer Reference
                    </label>

                    <input
                        v-model="
                            form.customer_reference
                        "
                        maxlength="120"
                        type="text"
                        placeholder="PO number, requisition, or reference"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="
                            form.errors.customer_reference
                        "
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            form.errors.customer_reference
                        }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Currency
                        <span class="text-error-500">
                            *
                        </span>
                    </label>

                    <input
                        v-model="form.currency_code"
                        maxlength="3"
                        type="text"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm uppercase text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="
                            form.errors.currency_code
                        "
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            form.errors.currency_code
                        }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Exchange Rate
                        <span class="text-error-500">
                            *
                        </span>
                    </label>

                    <input
                        v-model="form.exchange_rate"
                        min="0.00000001"
                        step="0.00000001"
                        type="number"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="
                            form.errors.exchange_rate
                        "
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            form.errors.exchange_rate
                        }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Payment Terms (Days)
                    </label>

                    <input
                        v-model.number="
                            form.payment_terms_days
                        "
                        min="0"
                        max="3650"
                        step="1"
                        type="number"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="
                            form.errors.payment_terms_days
                        "
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            form.errors.payment_terms_days
                        }}
                    </p>
                </div>
            </div>

            <div
                v-if="selectedCustomer !== null"
                class="mt-5 rounded-xl bg-gray-50 p-4 text-sm dark:bg-white/[0.03]"
            >
                <div
                    class="grid gap-3 md:grid-cols-3"
                >
                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Customer
                        </p>

                        <p
                            class="mt-1 font-medium text-gray-800 dark:text-white"
                        >
                            {{
                                selectedCustomer.name
                            }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Contact
                        </p>

                        <p
                            class="mt-1 text-gray-700 dark:text-gray-300"
                        >
                            {{
                                selectedCustomer.contact_person
                                || selectedCustomer.phone
                                || '—'
                            }}
                        </p>
                    </div>

                    <div>
                        <p
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Credit Limit
                        </p>

                        <p
                            class="mt-1 text-gray-700 dark:text-gray-300"
                        >
                            {{
                                selectedCustomer.credit_limit
                                || '0.000000'
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
        >
            <div
                class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Addresses and Delivery
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        These values are stored as order
                        snapshots.
                    </p>
                </div>

                <button
                    v-if="selectedCustomer !== null"
                    type="button"
                    class="text-sm font-medium text-brand-500 hover:text-brand-600"
                    @click="applyCustomerDefaults"
                >
                    Reload customer defaults
                </button>
            </div>

            <div
                class="grid gap-5 lg:grid-cols-2"
            >
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Billing Address
                    </label>

                    <textarea
                        v-model="form.billing_address"
                        rows="4"
                        maxlength="4000"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="
                            form.errors.billing_address
                        "
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            form.errors.billing_address
                        }}
                    </p>
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
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="
                            form.errors.shipping_address
                        "
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            form.errors.shipping_address
                        }}
                    </p>
                </div>

                <div class="lg:col-span-2">
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Delivery Instructions
                    </label>

                    <textarea
                        v-model="
                            form.delivery_instructions
                        "
                        rows="3"
                        maxlength="4000"
                        placeholder="Delivery window, receiving contact, unloading requirements, or special handling"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="
                            form.errors.delivery_instructions
                        "
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            form.errors.delivery_instructions
                        }}
                    </p>
                </div>
            </div>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="flex flex-col gap-3 border-b border-gray-200 p-5 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between sm:p-6"
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
                        Product availability follows the
                        selected branch and warehouse
                        configuration.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
                    @click="addLine"
                >
                    Add Line
                </button>
            </div>

            <div
                v-if="hasUnavailableLine"
                class="m-5 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-900/50 dark:bg-warning-900/20 dark:text-warning-300 sm:m-6"
            >
                One or more products are no longer
                available for the selected branch or
                warehouse. Select a valid product before
                saving.
            </div>

            <p
                v-if="form.errors.lines"
                class="px-5 pt-4 text-sm text-error-500 sm:px-6"
            >
                {{ form.errors.lines }}
            </p>

            <div class="space-y-5 p-5 sm:p-6">
                <div
                    v-for="(
                        line,
                        lineIndex
                    ) in form.lines"
                    :key="
                        line.id
                        ?? `new-${lineIndex}`
                    "
                    class="rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                >
                    <div
                        class="mb-4 flex items-center justify-between"
                    >
                        <h3
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            Line {{ lineIndex + 1 }}
                        </h3>

                        <button
                            type="button"
                            class="text-sm font-medium text-error-500 hover:text-error-600"
                            @click="
                                removeLine(
                                    lineIndex,
                                )
                            "
                        >
                            Remove
                        </button>
                    </div>

                    <div
                        class="grid gap-4 lg:grid-cols-12"
                    >
                        <div
                            class="lg:col-span-4"
                        >
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Product
                                <span
                                    class="text-error-500"
                                >
                                    *
                                </span>
                            </label>

                            <select
                                v-model="
                                    line.product_id
                                "
                                :disabled="
                                    form.branch_id
                                        === null
                                "
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:text-white dark:disabled:bg-gray-800"
                                @change="
                                    onProductChanged(
                                        lineIndex,
                                    )
                                "
                            >
                                <option :value="null">
                                    {{
                                        form.branch_id
                                            === null
                                            ? 'Select a branch first'
                                            : 'Select a product'
                                    }}
                                </option>

                                <option
                                    v-for="product in productsForLine(
                                        lineIndex,
                                    )"
                                    :key="
                                        product.id
                                    "
                                    :value="
                                        product.id
                                    "
                                    :disabled="
                                        !productIsAvailable(
                                            product,
                                        )
                                    "
                                >
                                    {{
                                        product.name
                                    }}
                                    ({{
                                        product.sku
                                    }})

                                    {{
                                        product.product_type
                                            === 'stock'
                                            ? '— Stock'
                                            : ''
                                    }}
                                </option>
                            </select>

                            <p
                                v-if="
                                    fieldError(
                                        `lines.${lineIndex}.product_id`,
                                    )
                                "
                                class="mt-1 text-xs text-error-500"
                            >
                                {{
                                    fieldError(
                                        `lines.${lineIndex}.product_id`,
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="lg:col-span-2"
                        >
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Unit
                            </label>

                            <input
                                :value="
                                    findProduct(
                                        line.product_id,
                                    )?.base_unit
                                        ?.code
                                    ?? ''
                                "
                                readonly
                                type="text"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-gray-50 px-3 text-sm text-gray-700 outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                            />

                            <p
                                v-if="
                                    fieldError(
                                        `lines.${lineIndex}.unit_id`,
                                    )
                                "
                                class="mt-1 text-xs text-error-500"
                            >
                                {{
                                    fieldError(
                                        `lines.${lineIndex}.unit_id`,
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="lg:col-span-2"
                        >
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Quantity
                                <span
                                    class="text-error-500"
                                >
                                    *
                                </span>
                            </label>

                            <input
                                v-model="
                                    line.ordered_quantity
                                "
                                min="0.000001"
                                step="0.000001"
                                type="number"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                            />

                            <p
                                v-if="
                                    fieldError(
                                        `lines.${lineIndex}.ordered_quantity`,
                                    )
                                "
                                class="mt-1 text-xs text-error-500"
                            >
                                {{
                                    fieldError(
                                        `lines.${lineIndex}.ordered_quantity`,
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="lg:col-span-2"
                        >
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Unit Price
                                <span
                                    class="text-error-500"
                                >
                                    *
                                </span>
                            </label>

                            <input
                                v-model="
                                    line.unit_price
                                "
                                :readonly="
                                    !can.override_price
                                "
                                min="0"
                                step="0.000001"
                                type="number"
                                :class="[
                                    'h-11 w-full rounded-lg border border-gray-300 px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white',
                                    !can.override_price
                                        ? 'bg-gray-50 dark:bg-gray-800'
                                        : 'bg-transparent',
                                ]"
                            />

                            <p
                                v-if="
                                    fieldError(
                                        `lines.${lineIndex}.unit_price`,
                                    )
                                "
                                class="mt-1 text-xs text-error-500"
                            >
                                {{
                                    fieldError(
                                        `lines.${lineIndex}.unit_price`,
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="lg:col-span-2"
                        >
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Discount
                            </label>

                            <input
                                v-model="
                                    line.discount_amount
                                "
                                :readonly="
                                    !can.override_discount
                                "
                                min="0"
                                step="0.000001"
                                type="number"
                                :class="[
                                    'h-11 w-full rounded-lg border border-gray-300 px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white',
                                    !can.override_discount
                                        ? 'bg-gray-50 dark:bg-gray-800'
                                        : 'bg-transparent',
                                ]"
                            />

                            <p
                                v-if="
                                    fieldError(
                                        `lines.${lineIndex}.discount_amount`,
                                    )
                                "
                                class="mt-1 text-xs text-error-500"
                            >
                                {{
                                    fieldError(
                                        `lines.${lineIndex}.discount_amount`,
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="lg:col-span-2"
                        >
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Tax Rate (%)
                            </label>

                            <input
                                v-model="
                                    line.tax_rate
                                "
                                min="0"
                                max="100"
                                step="0.000001"
                                type="number"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                            />

                            <p
                                v-if="
                                    fieldError(
                                        `lines.${lineIndex}.tax_rate`,
                                    )
                                "
                                class="mt-1 text-xs text-error-500"
                            >
                                {{
                                    fieldError(
                                        `lines.${lineIndex}.tax_rate`,
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="lg:col-span-10"
                        >
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Description
                            </label>

                            <input
                                v-model="
                                    line.description
                                "
                                maxlength="4000"
                                type="text"
                                placeholder="Optional product or service description"
                                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white"
                            />

                            <p
                                v-if="
                                    fieldError(
                                        `lines.${lineIndex}.description`,
                                    )
                                "
                                class="mt-1 text-xs text-error-500"
                            >
                                {{
                                    fieldError(
                                        `lines.${lineIndex}.description`,
                                    )
                                }}
                            </p>
                        </div>

                        <div
                            class="lg:col-span-2"
                        >
                            <label
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                            >
                                Line Total
                            </label>

                            <div
                                class="flex h-11 items-center justify-end rounded-lg bg-gray-50 px-3 text-sm font-semibold text-gray-900 dark:bg-gray-800 dark:text-white"
                            >
                                {{
                                    formattedAmount(
                                        lineAmounts(
                                            line,
                                        ).total,
                                    )
                                }}
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-4 grid gap-3 rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/[0.03] sm:grid-cols-4"
                    >
                        <div>
                            <span
                                class="text-gray-500"
                            >
                                Gross:
                            </span>

                            <span
                                class="ml-1 font-medium text-gray-800 dark:text-gray-200"
                            >
                                {{
                                    formattedAmount(
                                        lineAmounts(
                                            line,
                                        ).gross,
                                    )
                                }}
                            </span>
                        </div>

                        <div>
                            <span
                                class="text-gray-500"
                            >
                                Discount:
                            </span>

                            <span
                                class="ml-1 font-medium text-gray-800 dark:text-gray-200"
                            >
                                {{
                                    formattedAmount(
                                        lineAmounts(
                                            line,
                                        ).discount,
                                    )
                                }}
                            </span>
                        </div>

                        <div>
                            <span
                                class="text-gray-500"
                            >
                                Tax:
                            </span>

                            <span
                                class="ml-1 font-medium text-gray-800 dark:text-gray-200"
                            >
                                {{
                                    formattedAmount(
                                        lineAmounts(
                                            line,
                                        ).tax,
                                    )
                                }}
                            </span>
                        </div>

                        <div>
                            <span
                                class="text-gray-500"
                            >
                                Net:
                            </span>

                            <span
                                class="ml-1 font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    formattedAmount(
                                        lineAmounts(
                                            line,
                                        ).total,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Terms and Notes
                </h2>

                <div class="mt-5 space-y-5">
                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Terms and Conditions
                        </label>

                        <textarea
                            v-model="
                                form.terms_and_conditions
                            "
                            rows="5"
                            maxlength="10000"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                        />

                        <p
                            v-if="
                                form.errors.terms_and_conditions
                            "
                            class="mt-1 text-xs text-error-500"
                        >
                            {{
                                form.errors.terms_and_conditions
                            }}
                        </p>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Internal Notes
                        </label>

                        <textarea
                            v-model="form.notes"
                            rows="4"
                            maxlength="4000"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                        />

                        <p
                            v-if="form.errors.notes"
                            class="mt-1 text-xs text-error-500"
                        >
                            {{ form.errors.notes }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Order Summary
                </h2>

                <div class="mt-5 space-y-4">
                    <div
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-gray-500">
                            Subtotal
                        </span>

                        <span
                            class="font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{
                                formattedAmount(
                                    subtotal,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="flex items-center justify-between text-sm"
                    >
                        <span class="text-gray-500">
                            Discount
                        </span>

                        <span
                            class="font-medium text-gray-800 dark:text-gray-200"
                        >
                            -{{
                                formattedAmount(
                                    discountTotal,
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
                            class="font-medium text-gray-800 dark:text-gray-200"
                        >
                            {{
                                formattedAmount(
                                    taxTotal,
                                )
                            }}
                        </span>
                    </div>

                    <div>
                        <label
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Shipping Amount
                        </label>

                        <input
                            v-model="
                                form.shipping_amount
                            "
                            min="0"
                            step="0.000001"
                            type="number"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                        />

                        <p
                            v-if="
                                form.errors.shipping_amount
                            "
                            class="mt-1 text-xs text-error-500"
                        >
                            {{
                                form.errors.shipping_amount
                            }}
                        </p>
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
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white"
                        />

                        <p
                            v-if="
                                form.errors.other_charges
                            "
                            class="mt-1 text-xs text-error-500"
                        >
                            {{
                                form.errors.other_charges
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
                                class="text-xl font-semibold text-brand-600 dark:text-brand-400"
                            >
                                {{
                                    form.currency_code
                                }}
                                {{
                                    formattedAmount(
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
            v-if="form.hasErrors"
            class="rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-900/50 dark:bg-error-900/20 dark:text-error-300"
        >
            Please correct the highlighted validation
            errors before saving.
        </div>

        <div
            class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
        >
            <Link
                :href="
                    isEditing && salesOrder
                        ? route(
                            'sales-orders.show',
                            salesOrder.id,
                        )
                        : route(
                            'sales-orders.index',
                        )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Cancel
            </Link>

            <button
                :disabled="
                    form.processing
                    || hasUnavailableLine
                "
                type="submit"
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{
                    form.processing
                        ? 'Saving...'
                        : isEditing
                            ? 'Update Sales Order'
                            : 'Create Sales Order'
                }}
            </button>
        </div>
    </form>
</template>