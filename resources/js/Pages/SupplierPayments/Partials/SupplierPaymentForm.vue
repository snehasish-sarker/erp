<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import {
    computed,
    ref,
    watch,
} from 'vue';

import type {
    ExistingSupplierPaymentFormData,
    SupplierPaymentAccountOption,
    SupplierPaymentAllocationPayload,
    SupplierPaymentFormData,
    SupplierPaymentFormPayload,
    SupplierPaymentFormProps,
    SupplierPaymentMethodOption,
    SupplierPaymentOpenItemOption,
} from '@/Types/supplier-payment';

interface Props extends SupplierPaymentFormProps {
    supplierPayment?: ExistingSupplierPaymentFormData;
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

const fixedAmount = (
    value: string | number,
): string => {
    return decimalValue(value).toFixed(6);
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

const formatRate = (
    value: string | number,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 8,
        },
    ).format(decimalValue(value));
};

const firstBranchId =
    props.branches.length === 1
        ? props.branches[0]?.id ?? null
        : null;

const firstSupplierId =
    props.suppliers.length === 1
        ? props.suppliers[0]?.id ?? null
        : null;

const initialPaymentMethod =
    props.supplierPayment?.payment_method
    ?? props.paymentMethods[0]?.value
    ?? 'bank_transfer';

const form = useForm<SupplierPaymentFormData>({
    branch_id:
        props.supplierPayment?.branch_id
        ?? props.defaults.branch_id
        ?? firstBranchId,

    supplier_id:
        props.supplierPayment?.supplier_id
        ?? props.defaults.supplier_id
        ?? firstSupplierId,

    payment_account_id:
        props.supplierPayment?.payment_account_id
        ?? null,

    payment_date:
        props.supplierPayment?.payment_date
        ?? props.defaults.payment_date,

    posting_date:
        props.supplierPayment?.posting_date
        ?? props.defaults.posting_date,

    currency_code:
        props.supplierPayment?.currency_code
        ?? props.defaults.currency_code,

    exchange_rate:
        props.supplierPayment?.exchange_rate
        ?? props.defaults.exchange_rate,

    payment_method:
        initialPaymentMethod,

    payment_reference:
        props.supplierPayment?.payment_reference
        ?? '',

    cheque_number:
        props.supplierPayment?.cheque_number
        ?? '',

    cheque_date:
        props.supplierPayment?.cheque_date
        ?? '',

    total_amount:
        props.supplierPayment?.total_amount
        ?? '',

    notes:
        props.supplierPayment?.notes
        ?? '',

    allocations:
        props.supplierPayment?.allocations.map(
            (
                allocation,
            ): SupplierPaymentAllocationPayload => ({
                supplier_open_item_id:
                    allocation.supplier_open_item_id,

                amount:
                    allocation.amount,
            }),
        ) ?? [],
});

const openItemSearch = ref('');

const isEditing = computed(
    (): boolean =>
        props.supplierPayment !== undefined,
);

const hasAllocatedNumber = computed(
    (): boolean => {
        const paymentNumber =
            props.supplierPayment?.payment_number;

        return typeof paymentNumber === 'string'
            && paymentNumber.trim() !== '';
    },
);

const baseCurrencyCode = computed(
    (): string =>
        props.defaults.currency_code
            .trim()
            .toUpperCase(),
);

const selectedMethod = computed(
    (): SupplierPaymentMethodOption | null => {
        return props.paymentMethods.find(
            (method): boolean =>
                method.value
                === form.payment_method,
        ) ?? null;
    },
);

const isCheque = computed(
    (): boolean =>
        selectedMethod.value
            ?.requires_cheque_details
        ?? false,
);

const paymentAccountOptions = computed(
    (): SupplierPaymentAccountOption[] => {
        const controlType =
            selectedMethod.value
                ?.account_control_type;

        if (controlType === undefined) {
            return [];
        }

        return props.paymentAccounts.filter(
            (account): boolean =>
                account.control_type
                === controlType,
        );
    },
);

const selectedPaymentAccount = computed(
    (): SupplierPaymentAccountOption | null => {
        if (form.payment_account_id === null) {
            return null;
        }

        return props.paymentAccounts.find(
            (account): boolean =>
                account.id
                === form.payment_account_id,
        ) ?? null;
    },
);

const normalizedCurrencyCode = computed(
    (): string =>
        form.currency_code
            .trim()
            .toUpperCase(),
);

const allocationFor = (
    openItemId: number,
): SupplierPaymentAllocationPayload | undefined => {
    return form.allocations.find(
        (allocation): boolean =>
            allocation.supplier_open_item_id
            === openItemId,
    );
};

const openItemMatchesContext = (
    openItem: SupplierPaymentOpenItemOption,
): boolean => {
    return form.branch_id !== null
        && form.supplier_id !== null
        && openItem.branch_id
            === form.branch_id
        && openItem.supplier_id
            === form.supplier_id
        && openItem.currency_code
            .trim()
            .toUpperCase()
            === normalizedCurrencyCode.value;
};

const matchingOpenItems = computed(
    (): SupplierPaymentOpenItemOption[] => {
        return props.openItems.filter(
            (openItem): boolean =>
                openItemMatchesContext(openItem)
                && (
                    openItem.available
                    || allocationFor(openItem.id)
                        !== undefined
                ),
        );
    },
);

const visibleOpenItems = computed(
    (): SupplierPaymentOpenItemOption[] => {
        const search = openItemSearch.value
            .trim()
            .toLowerCase();

        if (search === '') {
            return matchingOpenItems.value;
        }

        return matchingOpenItems.value.filter(
            (openItem): boolean => {
                return [
                    openItem.document_number,
                    openItem.supplier_invoice_number,
                    openItem.document_date,
                    openItem.due_date,
                    openItem.currency_code,
                ]
                    .filter(
                        (value): value is string =>
                            typeof value === 'string',
                    )
                    .some(
                        (value): boolean =>
                            value
                                .toLowerCase()
                                .includes(search),
                    );
            },
        );
    },
);

const isSelected = (
    openItemId: number,
): boolean => {
    return allocationFor(openItemId)
        !== undefined;
};

const totalAmount = computed(
    (): number =>
        Math.max(
            0,
            decimalValue(form.total_amount),
        ),
);

const allocatedAmount = computed(
    (): number => {
        return form.allocations.reduce(
            (
                total,
                allocation,
            ): number => {
                return total
                    + Math.max(
                        0,
                        decimalValue(
                            allocation.amount,
                        ),
                    );
            },
            0,
        );
    },
);

const unallocatedAmount = computed(
    (): number => {
        return Math.max(
            0,
            totalAmount.value
                - allocatedAmount.value,
        );
    },
);

const overAllocatedAmount = computed(
    (): number => {
        return Math.max(
            0,
            allocatedAmount.value
                - totalAmount.value,
        );
    },
);

const allocationIsInvalid = (
    allocation: SupplierPaymentAllocationPayload,
): boolean => {
    const openItem = props.openItems.find(
        (item): boolean =>
            item.id
            === allocation.supplier_open_item_id,
    );

    if (
        openItem === undefined
        || !openItemMatchesContext(openItem)
    ) {
        return true;
    }

    const amount = decimalValue(
        allocation.amount,
    );

    return amount <= 0
        || amount
            > decimalValue(
                openItem.outstanding_amount,
            ) + 0.000001;
};

const invalidAllocationCount = computed(
    (): number => {
        return form.allocations.filter(
            allocationIsInvalid,
        ).length;
    },
);

const contextIsComplete = computed(
    (): boolean =>
        form.branch_id !== null
        && form.supplier_id !== null
        && /^[A-Z]{3}$/.test(
            normalizedCurrencyCode.value,
        ),
);

const canSubmitForm = computed(
    (): boolean => {
        return contextIsComplete.value
            && form.payment_account_id !== null
            && form.payment_date.trim() !== ''
            && form.posting_date.trim() !== ''
            && form.posting_date
                >= form.payment_date
            && decimalValue(
                form.exchange_rate,
            ) > 0
            && totalAmount.value > 0
            && invalidAllocationCount.value === 0
            && overAllocatedAmount.value <= 0.000001
            && (
                !isCheque.value
                || (
                    form.cheque_number
                        .trim() !== ''
                    && form.cheque_date
                        .trim() !== ''
                )
            );
    },
);

const allocationInputValue = (
    openItemId: number,
): string => {
    return allocationFor(openItemId)
        ?.amount
        ?? '';
};

const allocationIndex = (
    openItemId: number,
): number => {
    return form.allocations.findIndex(
        (allocation): boolean =>
            allocation.supplier_open_item_id
            === openItemId,
    );
};

const allocationIsInvalidForOpenItem = (
    openItemId: number,
): boolean => {
    const allocation = allocationFor(
        openItemId,
    );

    return allocation !== undefined
        && allocationIsInvalid(allocation);
};

const setAllocationAmount = (
    openItemId: number,
    event: Event,
): void => {
    const target = event.target;

    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    const allocation = allocationFor(
        openItemId,
    );

    if (allocation === undefined) {
        return;
    }

    allocation.amount = target.value;
};

const toggleAllocation = (
    openItem: SupplierPaymentOpenItemOption,
    event: Event,
): void => {
    const target = event.target;

    if (!(target instanceof HTMLInputElement)) {
        return;
    }

    if (!target.checked) {
        form.allocations = form.allocations.filter(
            (allocation): boolean =>
                allocation.supplier_open_item_id
                !== openItem.id,
        );

        return;
    }

    if (isSelected(openItem.id)) {
        return;
    }

    const outstanding = decimalValue(
        openItem.outstanding_amount,
    );

    const remaining = Math.max(
        0,
        totalAmount.value
            - allocatedAmount.value,
    );

    const defaultAmount = remaining > 0
        ? Math.min(
            outstanding,
            remaining,
        )
        : outstanding;

    form.allocations.push({
        supplier_open_item_id:
            openItem.id,

        amount:
            fixedAmount(defaultAmount),
    });
};

const removeAllocation = (
    openItemId: number,
): void => {
    form.allocations = form.allocations.filter(
        (allocation): boolean =>
            allocation.supplier_open_item_id
            !== openItemId,
    );
};

const clearAllocations = (): void => {
    form.allocations = [];
};

const autoAllocate = (): void => {
    let remaining = totalAmount.value;

    const allocations:
        SupplierPaymentAllocationPayload[] = [];

    for (const openItem of matchingOpenItems.value) {
        if (remaining <= 0.000001) {
            break;
        }

        if (!openItem.available) {
            continue;
        }

        const outstanding = decimalValue(
            openItem.outstanding_amount,
        );

        if (outstanding <= 0) {
            continue;
        }

        const amount = Math.min(
            outstanding,
            remaining,
        );

        allocations.push({
            supplier_open_item_id:
                openItem.id,

            amount:
                fixedAmount(amount),
        });

        remaining -= amount;
    }

    form.allocations = allocations;
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

const openItemLabel = (
    openItem: SupplierPaymentOpenItemOption,
): string => {
    return openItem.document_number
        ?? openItem.supplier_invoice_number
        ?? `Invoice #${openItem.supplier_invoice_id}`;
};

watch(
    () => form.payment_method,
    (): void => {
        const account =
            selectedPaymentAccount.value;

        const controlType =
            selectedMethod.value
                ?.account_control_type;

        if (
            account !== null
            && account.control_type
                !== controlType
        ) {
            form.payment_account_id = null;
        }

        if (!isCheque.value) {
            form.cheque_number = '';
            form.cheque_date = '';
        }
    },
);

watch(
    () => form.currency_code,
    (
        currencyCode,
    ): void => {
        const normalized = currencyCode
            .trim()
            .toUpperCase();

        if (currencyCode !== normalized) {
            form.currency_code = normalized;

            return;
        }

        if (
            normalized !== ''
            && normalized
                === baseCurrencyCode.value
        ) {
            form.exchange_rate = '1.00000000';
        }
    },
);

watch(
    () => form.payment_date,
    (
        paymentDate,
    ): void => {
        if (
            paymentDate !== ''
            && (
                form.posting_date === ''
                || form.posting_date
                    < paymentDate
            )
        ) {
            form.posting_date = paymentDate;
        }
    },
);

watch(
    [
        () => form.branch_id,
        () => form.supplier_id,
        () => form.currency_code,
    ],
    (): void => {
        form.allocations = form.allocations.filter(
            (allocation): boolean => {
                const openItem = props.openItems.find(
                    (item): boolean =>
                        item.id
                        === allocation.supplier_open_item_id,
                );

                return openItem !== undefined
                    && openItemMatchesContext(
                        openItem,
                    );
            },
        );
    },
);

const submit = (): void => {
    if (!canSubmitForm.value) {
        return;
    }

    form.transform(
        (
            data,
        ): SupplierPaymentFormPayload => ({
            branch_id:
                data.branch_id,

            supplier_id:
                data.supplier_id,

            payment_account_id:
                data.payment_account_id,

            payment_date:
                data.payment_date.trim(),

            posting_date:
                data.posting_date.trim(),

            currency_code:
                data.currency_code
                    .trim()
                    .toUpperCase(),

            exchange_rate:
                data.exchange_rate.trim(),

            payment_method:
                data.payment_method,

            payment_reference:
                data.payment_reference.trim(),

            cheque_number:
                isCheque.value
                    ? data.cheque_number.trim()
                    : '',

            cheque_date:
                isCheque.value
                    ? data.cheque_date.trim()
                    : '',

            total_amount:
                data.total_amount.trim(),

            notes:
                data.notes.trim(),

            allocations:
                data.allocations.map(
                    (
                        allocation,
                    ): SupplierPaymentAllocationPayload => ({
                        supplier_open_item_id:
                            allocation.supplier_open_item_id,

                        amount:
                            allocation.amount.trim(),
                    }),
                ),
        }),
    );

    if (props.supplierPayment !== undefined) {
        form.put(
            route(
                'supplier-payments.update',
                props.supplierPayment.id,
            ),
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        route('supplier-payments.store'),
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
                    Payment Information
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Select the supplier, settlement account,
                    payment method, currency, and business
                    dates.
                </p>
            </div>

            <div
                class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4"
            >
                <div>
                    <label
                        for="supplier-payment-branch"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Branch
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        id="supplier-payment-branch"
                        v-model="form.branch_id"
                        :disabled="hasAllocatedNumber"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                    >
                        <option :value="null">
                            Select branch
                        </option>

                        <option
                            v-for="branch in props.branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.code }} — {{ branch.name }}
                        </option>
                    </select>

                    <p
                        v-if="fieldError('branch_id')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('branch_id') }}
                    </p>

                    <p
                        v-else-if="hasAllocatedNumber"
                        class="mt-1 text-xs text-gray-500"
                    >
                        The branch is locked after document
                        number allocation.
                    </p>
                </div>

                <div>
                    <label
                        for="supplier-payment-supplier"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        id="supplier-payment-supplier"
                        v-model="form.supplier_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option :value="null">
                            Select supplier
                        </option>

                        <option
                            v-for="supplier in props.suppliers"
                            :key="supplier.id"
                            :value="supplier.id"
                        >
                            {{ supplier.code }} — {{ supplier.name }}
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
                        for="supplier-payment-method"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Payment Method
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        id="supplier-payment-method"
                        v-model="form.payment_method"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option
                            v-for="method in props.paymentMethods"
                            :key="method.value"
                            :value="method.value"
                        >
                            {{ method.label }}
                        </option>
                    </select>

                    <p
                        v-if="fieldError('payment_method')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('payment_method') }}
                    </p>
                </div>

                <div>
                    <label
                        for="supplier-payment-account"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        {{
                            selectedMethod?.account_control_type
                                === 'cash'
                                ? 'Cash Account'
                                : 'Bank Account'
                        }}
                        <span class="text-red-500">*</span>
                    </label>

                    <select
                        id="supplier-payment-account"
                        v-model="form.payment_account_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    >
                        <option :value="null">
                            Select payment account
                        </option>

                        <option
                            v-for="account in paymentAccountOptions"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.code }} — {{ account.name }}
                        </option>
                    </select>

                    <p
                        v-if="fieldError('payment_account_id')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('payment_account_id') }}
                    </p>

                    <p
                        v-else
                        class="mt-1 text-xs text-gray-500"
                    >
                        Only active posting accounts compatible
                        with the selected method are listed.
                    </p>
                </div>

                <div>
                    <label
                        for="supplier-payment-date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Payment Date
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="supplier-payment-date"
                        v-model="form.payment_date"
                        type="date"
                        :disabled="hasAllocatedNumber"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:disabled:bg-gray-800"
                    />

                    <p
                        v-if="fieldError('payment_date')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('payment_date') }}
                    </p>
                </div>

                <div>
                    <label
                        for="supplier-payment-posting-date"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Posting Date
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="supplier-payment-posting-date"
                        v-model="form.posting_date"
                        type="date"
                        :min="form.payment_date || undefined"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('posting_date')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('posting_date') }}
                    </p>
                </div>

                <div>
                    <label
                        for="supplier-payment-currency"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Currency
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="supplier-payment-currency"
                        v-model="form.currency_code"
                        type="text"
                        maxlength="3"
                        autocomplete="off"
                        placeholder="BDT"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm uppercase text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
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
                        for="supplier-payment-exchange-rate"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Exchange Rate
                        <span class="text-red-500">*</span>
                    </label>

                    <input
                        id="supplier-payment-exchange-rate"
                        v-model="form.exchange_rate"
                        type="number"
                        min="0.00000001"
                        step="0.00000001"
                        :readonly="normalizedCurrencyCode === baseCurrencyCode"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 read-only:cursor-not-allowed read-only:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-white dark:read-only:bg-gray-800"
                    />

                    <p
                        v-if="fieldError('exchange_rate')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('exchange_rate') }}
                    </p>

                    <p
                        v-else-if="normalizedCurrencyCode === baseCurrencyCode"
                        class="mt-1 text-xs text-gray-500"
                    >
                        Base-currency payments use exactly
                        1.00000000.
                    </p>
                </div>

                <div class="md:col-span-2">
                    <label
                        for="supplier-payment-reference"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Payment Reference
                    </label>

                    <input
                        id="supplier-payment-reference"
                        v-model="form.payment_reference"
                        type="text"
                        maxlength="160"
                        placeholder="Transaction, transfer, voucher, or receipt reference"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />

                    <p
                        v-if="fieldError('payment_reference')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('payment_reference') }}
                    </p>
                </div>

                <template v-if="isCheque">
                    <div>
                        <label
                            for="supplier-payment-cheque-number"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Cheque Number
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            id="supplier-payment-cheque-number"
                            v-model="form.cheque_number"
                            type="text"
                            maxlength="100"
                            placeholder="Cheque number"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        />

                        <p
                            v-if="fieldError('cheque_number')"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ fieldError('cheque_number') }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="supplier-payment-cheque-date"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                        >
                            Cheque Date
                            <span class="text-red-500">*</span>
                        </label>

                        <input
                            id="supplier-payment-cheque-date"
                            v-model="form.cheque_date"
                            type="date"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        />

                        <p
                            v-if="fieldError('cheque_date')"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ fieldError('cheque_date') }}
                        </p>
                    </div>
                </template>
            </div>
        </section>

        <section
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="max-w-2xl">
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Payment Amount and Allocation
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Allocate all or part of the payment to
                        posted Supplier Invoice open items.
                        Any remainder becomes an unallocated
                        supplier advance when posted.
                    </p>
                </div>

                <div class="w-full lg:w-72">
                    <label
                        for="supplier-payment-total-amount"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Total Payment Amount
                        <span class="text-red-500">*</span>
                    </label>

                    <div class="relative">
                        <input
                            id="supplier-payment-total-amount"
                            v-model="form.total_amount"
                            type="number"
                            min="0.000001"
                            step="0.000001"
                            placeholder="0.000000"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 pr-16 text-right text-sm font-semibold text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        />

                        <span
                            class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-semibold text-gray-500"
                        >
                            {{ normalizedCurrencyCode || 'CUR' }}
                        </span>
                    </div>

                    <p
                        v-if="fieldError('total_amount')"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ fieldError('total_amount') }}
                    </p>
                </div>
            </div>

            <div
                class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-3"
            >
                <div
                    class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-gray-500"
                    >
                        Payment Total
                    </p>

                    <p
                        class="mt-2 text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        {{ formatAmount(totalAmount) }}
                        {{ normalizedCurrencyCode }}
                    </p>
                </div>

                <div
                    class="rounded-xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-500/30 dark:bg-blue-500/10"
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide text-blue-700 dark:text-blue-300"
                    >
                        Invoice Allocations
                    </p>

                    <p
                        class="mt-2 text-lg font-semibold text-blue-900 dark:text-blue-200"
                    >
                        {{ formatAmount(allocatedAmount) }}
                        {{ normalizedCurrencyCode }}
                    </p>
                </div>

                <div
                    class="rounded-xl border p-4"
                    :class="
                        overAllocatedAmount > 0
                            ? 'border-red-200 bg-red-50 dark:border-red-500/30 dark:bg-red-500/10'
                            : 'border-emerald-200 bg-emerald-50 dark:border-emerald-500/30 dark:bg-emerald-500/10'
                    "
                >
                    <p
                        class="text-xs font-medium uppercase tracking-wide"
                        :class="
                            overAllocatedAmount > 0
                                ? 'text-red-700 dark:text-red-300'
                                : 'text-emerald-700 dark:text-emerald-300'
                        "
                    >
                        {{
                            overAllocatedAmount > 0
                                ? 'Overallocated'
                                : 'Unallocated Advance'
                        }}
                    </p>

                    <p
                        class="mt-2 text-lg font-semibold"
                        :class="
                            overAllocatedAmount > 0
                                ? 'text-red-900 dark:text-red-200'
                                : 'text-emerald-900 dark:text-emerald-200'
                        "
                    >
                        {{
                            formatAmount(
                                overAllocatedAmount > 0
                                    ? overAllocatedAmount
                                    : unallocatedAmount,
                            )
                        }}
                        {{ normalizedCurrencyCode }}
                    </p>
                </div>
            </div>

            <div
                class="mt-6 flex flex-col gap-3 border-t border-gray-200 pt-5 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h3
                        class="text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        Supplier Invoice Open Items
                    </h3>

                    <p
                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Matching branch, supplier, and currency
                        are required. Allocation is finalized
                        only when the payment is posted.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        :disabled="
                            !contextIsComplete
                            || totalAmount <= 0
                            || matchingOpenItems.length === 0
                        "
                        class="inline-flex items-center justify-center rounded-lg border border-brand-300 bg-brand-50 px-3 py-2 text-sm font-medium text-brand-700 transition hover:bg-brand-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-300 dark:hover:bg-brand-500/20"
                        @click="autoAllocate"
                    >
                        Auto Allocate
                    </button>

                    <button
                        type="button"
                        :disabled="form.allocations.length === 0"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="clearAllocations"
                    >
                        Clear Allocations
                    </button>
                </div>
            </div>

            <div
                v-if="!contextIsComplete"
                class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300"
            >
                Select a branch, supplier, and valid currency
                before choosing Supplier Invoice open items.
            </div>

            <template v-else>
                <div class="mt-5">
                    <label
                        for="supplier-payment-open-item-search"
                        class="sr-only"
                    >
                        Search Supplier Invoice open items
                    </label>

                    <input
                        id="supplier-payment-open-item-search"
                        v-model="openItemSearch"
                        type="search"
                        placeholder="Search invoice number, document number, or date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />
                </div>

                <div
                    v-if="visibleOpenItems.length === 0"
                    class="mt-5 rounded-xl border border-gray-200 bg-gray-50 px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-400"
                >
                    No payable Supplier Invoice open items match
                    the selected branch, supplier, currency, and
                    search.
                </div>

                <div
                    v-else
                    class="mt-5 overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800"
                >
                    <div class="overflow-x-auto">
                        <table
                            class="min-w-full divide-y divide-gray-200 dark:divide-gray-800"
                        >
                            <thead
                                class="bg-gray-50 dark:bg-gray-950"
                            >
                                <tr>
                                    <th
                                        class="w-12 px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                                    >
                                        Use
                                    </th>

                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                                    >
                                        Supplier Invoice
                                    </th>

                                    <th
                                        class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                                    >
                                        Dates
                                    </th>

                                    <th
                                        class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                                    >
                                        Outstanding
                                    </th>

                                    <th
                                        class="min-w-52 px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                                    >
                                        Payment Allocation
                                    </th>
                                </tr>
                            </thead>

                            <tbody
                                class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900"
                            >
                                <tr
                                    v-for="openItem in visibleOpenItems"
                                    :key="openItem.id"
                                    :class="
                                        isSelected(openItem.id)
                                            ? 'bg-brand-50/50 dark:bg-brand-500/5'
                                            : ''
                                    "
                                >
                                    <td
                                        class="px-4 py-4 align-top"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="isSelected(openItem.id)"
                                            :disabled="
                                                !openItem.available
                                                && !isSelected(openItem.id)
                                            "
                                            class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                            @change="
                                                toggleAllocation(
                                                    openItem,
                                                    $event,
                                                )
                                            "
                                        />
                                    </td>

                                    <td
                                        class="px-4 py-4 align-top"
                                    >
                                        <p
                                            class="text-sm font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{ openItemLabel(openItem) }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            Supplier invoice reference:
                                            {{
                                                openItem
                                                    .supplier_invoice_number
                                            }}
                                        </p>

                                        <div
                                            class="mt-2 flex flex-wrap gap-2"
                                        >
                                            <span
                                                class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                            >
                                                {{ openItem.currency_code }}
                                            </span>

                                            <span
                                                class="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                                            >
                                                Rate
                                                {{
                                                    formatRate(
                                                        openItem.exchange_rate,
                                                    )
                                                }}
                                            </span>

                                            <span
                                                v-if="!openItem.available"
                                                class="inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-medium text-amber-700 dark:bg-amber-500/20 dark:text-amber-300"
                                            >
                                                Existing selection
                                            </span>
                                        </div>
                                    </td>

                                    <td
                                        class="px-4 py-4 align-top"
                                    >
                                        <p
                                            class="text-sm text-gray-700 dark:text-gray-300"
                                        >
                                            Invoice:
                                            {{ openItem.document_date }}
                                        </p>

                                        <p
                                            class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                                        >
                                            Due:
                                            {{
                                                openItem.due_date
                                                ?? 'Not set'
                                            }}
                                        </p>
                                    </td>

                                    <td
                                        class="px-4 py-4 text-right align-top"
                                    >
                                        <p
                                            class="text-sm font-semibold text-gray-900 dark:text-white"
                                        >
                                            {{
                                                formatAmount(
                                                    openItem
                                                        .outstanding_amount,
                                                )
                                            }}
                                            {{ openItem.currency_code }}
                                        </p>

                                        <p
                                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                        >
                                            Original:
                                            {{
                                                formatAmount(
                                                    openItem
                                                        .original_amount,
                                                )
                                            }}
                                        </p>
                                    </td>

                                    <td
                                        class="px-4 py-4 align-top"
                                    >
                                        <div
                                            v-if="isSelected(openItem.id)"
                                            class="flex items-start justify-end gap-2"
                                        >
                                            <div class="w-40">
                                                <input
                                                    type="number"
                                                    min="0.000001"
                                                    step="0.000001"
                                                    :max="
                                                        openItem
                                                            .outstanding_amount
                                                    "
                                                    :value="
                                                        allocationInputValue(
                                                            openItem.id,
                                                        )
                                                    "
                                                    class="w-full rounded-lg border bg-white px-3 py-2 text-right text-sm text-gray-900 outline-none transition focus:ring-2 dark:bg-gray-950 dark:text-white"
                                                    :class="
                                                        allocationIsInvalidForOpenItem(
                                                            openItem.id,
                                                        )
                                                            ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20 dark:border-red-500'
                                                            : 'border-gray-300 focus:border-brand-500 focus:ring-brand-500/20 dark:border-gray-700'
                                                    "
                                                    @input="
                                                        setAllocationAmount(
                                                            openItem.id,
                                                            $event,
                                                        )
                                                    "
                                                />

                                                <p
                                                    v-if="
                                                        fieldError(
                                                            `allocations.${allocationIndex(openItem.id)}.amount`,
                                                        )
                                                    "
                                                    class="mt-1 text-xs text-red-600"
                                                >
                                                    {{
                                                        fieldError(
                                                            `allocations.${allocationIndex(openItem.id)}.amount`,
                                                        )
                                                    }}
                                                </p>
                                            </div>

                                            <button
                                                type="button"
                                                class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-2 text-xs font-medium text-red-700 transition hover:bg-red-100 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20"
                                                @click="
                                                    removeAllocation(
                                                        openItem.id,
                                                    )
                                                "
                                            >
                                                Remove
                                            </button>
                                        </div>

                                        <p
                                            v-else
                                            class="text-right text-sm text-gray-400"
                                        >
                                            Not allocated
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>

            <p
                v-if="fieldError('allocations')"
                class="mt-3 text-sm text-red-600"
            >
                {{ fieldError('allocations') }}
            </p>

            <div
                v-if="invalidAllocationCount > 0"
                class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
            >
                {{ invalidAllocationCount }} allocation(s) are
                invalid, exceed the invoice outstanding amount,
                or no longer match the selected payment context.
            </div>

            <div
                v-if="overAllocatedAmount > 0"
                class="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300"
            >
                Invoice allocations exceed the payment by
                {{ formatAmount(overAllocatedAmount) }}
                {{ normalizedCurrencyCode }}.
            </div>
        </section>

        <section
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="mb-5">
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Internal Notes
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Add internal payment instructions or
                    reconciliation notes. These do not replace
                    the external payment reference.
                </p>
            </div>

            <textarea
                id="supplier-payment-notes"
                v-model="form.notes"
                rows="4"
                maxlength="5000"
                placeholder="Optional internal notes"
                class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
            />

            <p
                v-if="fieldError('notes')"
                class="mt-1 text-sm text-red-600"
            >
                {{ fieldError('notes') }}
            </p>
        </section>

        <section
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <p
                        class="text-sm font-semibold text-gray-900 dark:text-white"
                    >
                        {{
                            isEditing
                                ? 'Update Supplier Payment Draft'
                                : 'Create Supplier Payment Draft'
                        }}
                    </p>

                    <p
                        class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                    >
                        Saving does not post General Ledger or
                        Accounts Payable records. Financial
                        effects begin only after approval and
                        posting.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="
                            isEditing && props.supplierPayment
                                ? route(
                                    'supplier-payments.show',
                                    props.supplierPayment.id,
                                )
                                : route('supplier-payments.index')
                        "
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    >
                        Cancel
                    </Link>

                    <button
                        type="submit"
                        :disabled="
                            form.processing
                            || !canSubmitForm
                        "
                        class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <span v-if="form.processing">
                            Saving...
                        </span>

                        <span v-else>
                            {{
                                isEditing
                                    ? 'Update Payment'
                                    : 'Create Payment'
                            }}
                        </span>
                    </button>
                </div>
            </div>
        </section>
    </form>
</template>