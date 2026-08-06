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
    CustomerReceiptAllocationStatus,
    CustomerReceiptShowProps,
    CustomerReceiptStatus,
} from '@/Types/customer-receipt';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<CustomerReceiptShowProps>();

type DirectWorkflowAction =
    | 'submit'
    | 'return-to-draft'
    | 'approve'
    | 'post'
    | 'delete';

const actionInProgress =
    ref<DirectWorkflowAction | null>(null);

const showCancellationModal = ref(false);
const showReversalModal = ref(false);

const cancellationForm = useForm({
    cancellation_reason: '',
});

const localDate = (): string => {
    const currentDate = new Date();

    const localValue = new Date(
        currentDate.getTime()
        - currentDate.getTimezoneOffset()
            * 60_000,
    );

    return localValue
        .toISOString()
        .slice(0, 10);
};

const defaultReversalPostingDate =
    (): string => {
        const today = localDate();

        const originalPostingDate =
            props.customerReceipt.posting_date;

        return originalPostingDate > today
            ? originalPostingDate
            : today;
    };

const reversalForm = useForm({
    reversal_posting_date:
        defaultReversalPostingDate(),
    reversal_reason: '',
});

const documentTitle = computed(
    (): string => {
        return props.customerReceipt.receipt_number
            ?? `Draft #${props.customerReceipt.id}`;
    },
);

const statusClasses: Record<
    CustomerReceiptStatus,
    string
> = {
    draft:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    submitted:
        'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',

    approved:
        'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400',

    posted:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    reversed:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',

    cancelled:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const allocationStatusClasses: Record<
    CustomerReceiptAllocationStatus,
    string
> = {
    draft:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    applied:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    reversed:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',

    cancelled:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const decimalValue = (
    value: string | number | null,
): number => {
    const parsed = Number.parseFloat(
        String(value ?? '0'),
    );

    return Number.isFinite(parsed)
        ? parsed
        : 0;
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

const formatDate = (
    value: string | null,
): string => {
    if (value === null || value === '') {
        return '—';
    }

    const parts = value.split('-');

    if (parts.length !== 3) {
        return value;
    }

    const year = Number(parts[0]);
    const month = Number(parts[1]);
    const day = Number(parts[2]);

    if (
        !Number.isInteger(year)
        || !Number.isInteger(month)
        || !Number.isInteger(day)
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
    ).format(
        new Date(
            Date.UTC(
                year,
                month - 1,
                day,
            ),
        ),
    );
};

const formatDateTime = (
    value: string | null,
): string => {
    if (value === null || value === '') {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
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

const titleCase = (
    value: string,
): string => {
    return value
        .replaceAll('_', ' ')
        .replace(
            /\b\w/g,
            (
                character,
            ): string => character.toUpperCase(),
        );
};

const hasUnallocatedAmount = computed(
    (): boolean => {
        return Math.abs(
            decimalValue(
                props.customerReceipt.unallocated_amount,
            ),
        ) > 0.000001;
    },
);

const totalExchangeDifference = computed(
    (): number => {
        return props.customerReceipt.allocations.reduce(
            (
                total,
                allocation,
            ): number => {
                return total
                    + decimalValue(
                        allocation.exchange_difference_amount,
                    );
            },
            0,
        );
    },
);

const exchangeDifferenceLabel = computed(
    (): string => {
        if (
            Math.abs(totalExchangeDifference.value)
            <= 0.000001
        ) {
            return 'No realized exchange difference';
        }

        return totalExchangeDifference.value > 0
            ? 'Realized exchange gain'
            : 'Realized exchange loss';
    },
);

const exchangeDifferenceClasses = computed(
    (): string => {
        if (
            Math.abs(totalExchangeDifference.value)
            <= 0.000001
        ) {
            return 'text-gray-900 dark:text-white';
        }

        return totalExchangeDifference.value > 0
            ? 'text-emerald-700 dark:text-emerald-300'
            : 'text-red-700 dark:text-red-300';
    },
);

const runWorkflowAction = (
    action: Exclude<
        DirectWorkflowAction,
        'delete'
    >,
    confirmationMessage: string,
): void => {
    if (!window.confirm(confirmationMessage)) {
        return;
    }

    actionInProgress.value = action;

    router.post(
        route(
            `customer-receipts.${action}`,
            props.customerReceipt.id,
        ),
        {},
        {
            preserveScroll: true,
            onFinish: (): void => {
                actionInProgress.value = null;
            },
        },
    );
};

const deleteCustomerReceipt = (): void => {
    if (
        !window.confirm(
            `Delete ${documentTitle.value}? Only an unnumbered, never-submitted draft can be permanently deleted.`,
        )
    ) {
        return;
    }

    actionInProgress.value = 'delete';

    router.delete(
        route(
            'customer-receipts.destroy',
            props.customerReceipt.id,
        ),
        {
            preserveScroll: true,
            onFinish: (): void => {
                actionInProgress.value = null;
            },
        },
    );
};

const openCancellationModal = (): void => {
    cancellationForm.reset();
    cancellationForm.clearErrors();
    showCancellationModal.value = true;
};

const closeCancellationModal = (): void => {
    if (cancellationForm.processing) {
        return;
    }

    showCancellationModal.value = false;
    cancellationForm.reset();
    cancellationForm.clearErrors();
};

const submitCancellation = (): void => {
    cancellationForm.post(
        route(
            'customer-receipts.cancel',
            props.customerReceipt.id,
        ),
        {
            preserveScroll: true,
            onSuccess: (): void => {
                closeCancellationModal();
            },
        },
    );
};

const openReversalModal = (): void => {
    reversalForm.reset();
    reversalForm.reversal_posting_date =
        defaultReversalPostingDate();
    reversalForm.clearErrors();
    showReversalModal.value = true;
};

const closeReversalModal = (): void => {
    if (reversalForm.processing) {
        return;
    }

    showReversalModal.value = false;
    reversalForm.reset();
    reversalForm.reversal_posting_date =
        defaultReversalPostingDate();
    reversalForm.clearErrors();
};

const submitReversal = (): void => {
    reversalForm.post(
        route(
            'customer-receipts.reverse',
            props.customerReceipt.id,
        ),
        {
            preserveScroll: true,
            onSuccess: (): void => {
                closeReversalModal();
            },
        },
    );
};
</script>

<template>
    <Head :title="documentTitle" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
        >
            <div>
                <div
                    class="mb-2 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    <Link
                        :href="route('customer-receipts.index')"
                        class="transition hover:text-brand-500"
                    >
                        Customer Receipts
                    </Link>

                    <span>/</span>

                    <span
                        class="text-gray-700 dark:text-gray-300"
                    >
                        {{ documentTitle }}
                    </span>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3"
                >
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        {{ documentTitle }}
                    </h1>

                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="statusClasses[props.customerReceipt.status]"
                    >
                        {{ props.customerReceipt.status_label }}
                    </span>
                </div>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    {{ props.customerReceipt.customer.code }} —
                    {{ props.customerReceipt.customer.name }}
                    · {{ formatDate(props.customerReceipt.receipt_date) }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('customer-receipts.index')"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back to List
                </Link>

                <a
                    v-if="props.customerReceipt.can.print"
                    :href="route('customer-receipts.print', props.customerReceipt.id)"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Print Receipt
                </a>

                <Link
                    v-if="props.customerReceipt.can.update"
                    :href="route('customer-receipts.edit', props.customerReceipt.id)"
                    class="inline-flex items-center justify-center rounded-lg border border-brand-300 bg-brand-50 px-4 py-2.5 text-sm font-medium text-brand-700 transition hover:bg-brand-100 dark:border-brand-500/40 dark:bg-brand-500/10 dark:text-brand-300 dark:hover:bg-brand-500/20"
                >
                    Edit
                </Link>

                <button
                    v-if="props.customerReceipt.can.submit"
                    type="button"
                    :disabled="actionInProgress !== null"
                    class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="runWorkflowAction('submit', 'Submit this Customer Receipt and allocate its permanent document number?')"
                >
                    {{ actionInProgress === 'submit' ? 'Submitting...' : 'Submit' }}
                </button>

                <button
                    v-if="props.customerReceipt.can.return_to_draft"
                    type="button"
                    :disabled="actionInProgress !== null"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    @click="runWorkflowAction('return-to-draft', 'Return this submitted Customer Receipt to draft?')"
                >
                    {{ actionInProgress === 'return-to-draft' ? 'Returning...' : 'Return to Draft' }}
                </button>

                <button
                    v-if="props.customerReceipt.can.approve"
                    type="button"
                    :disabled="actionInProgress !== null"
                    class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="runWorkflowAction('approve', 'Approve this Customer Receipt for financial posting?')"
                >
                    {{ actionInProgress === 'approve' ? 'Approving...' : 'Approve' }}
                </button>

                <button
                    v-if="props.customerReceipt.can.post"
                    type="button"
                    :disabled="actionInProgress !== null"
                    class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="runWorkflowAction('post', 'Post this Customer Receipt? This creates the General Ledger journal, customer ledger, receipt open item, and invoice allocations atomically.')"
                >
                    {{ actionInProgress === 'post' ? 'Posting...' : 'Post' }}
                </button>

                <button
                    v-if="props.customerReceipt.can.cancel"
                    type="button"
                    :disabled="actionInProgress !== null"
                    class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-medium text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20"
                    @click="openCancellationModal"
                >
                    Cancel Receipt
                </button>

                <button
                    v-if="props.customerReceipt.can.reverse"
                    type="button"
                    :disabled="actionInProgress !== null"
                    class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-medium text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20"
                    @click="openReversalModal"
                >
                    Reverse Receipt
                </button>

                <button
                    v-if="props.customerReceipt.can.delete"
                    type="button"
                    :disabled="actionInProgress !== null"
                    class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-medium text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300 dark:hover:bg-red-500/20"
                    @click="deleteCustomerReceipt"
                >
                    {{ actionInProgress === 'delete' ? 'Deleting...' : 'Delete' }}
                </button>
            </div>
        </div>

        <section
            v-if="props.customerReceipt.status === 'posted'"
            class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 dark:border-emerald-500/30 dark:bg-emerald-500/10"
        >
            <h2
                class="font-semibold text-emerald-900 dark:text-emerald-200"
            >
                Customer Receipt Posted
            </h2>

            <p
                class="mt-2 text-sm text-emerald-700 dark:text-emerald-300"
            >
                The General Ledger journal, Customer Ledger
                entry, receipt open item, and invoice
                allocations were created atomically.
            </p>

            <p
                class="mt-3 break-all text-xs text-emerald-700 dark:text-emerald-400"
            >
                Accounting reference:
                <span class="font-semibold">
                    {{ props.customerReceipt.accounting_posting_reference ?? '—' }}
                </span>
            </p>
        </section>

        <section
            v-if="props.customerReceipt.status === 'cancelled'"
            class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-500/30 dark:bg-red-500/10"
        >
            <h2
                class="font-semibold text-red-900 dark:text-red-200"
            >
                Customer Receipt Cancelled
            </h2>

            <p
                class="mt-2 whitespace-pre-line text-sm text-red-700 dark:text-red-300"
            >
                {{ props.customerReceipt.cancellation_reason ?? 'No cancellation reason was recorded.' }}
            </p>

            <p
                class="mt-3 text-xs text-red-600 dark:text-red-400"
            >
                Cancelled by
                {{ props.customerReceipt.cancelled_by?.name ?? 'Unknown user' }}
                on
                {{ formatDateTime(props.customerReceipt.cancelled_at) }}
            </p>
        </section>

        <section
            v-if="props.customerReceipt.status === 'reversed'"
            class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-500/30 dark:bg-red-500/10"
        >
            <h2
                class="font-semibold text-red-900 dark:text-red-200"
            >
                Customer Receipt Reversed
            </h2>

            <p
                class="mt-2 whitespace-pre-line text-sm text-red-700 dark:text-red-300"
            >
                {{ props.customerReceipt.reversal_reason ?? 'No reversal reason was recorded.' }}
            </p>

            <div
                class="mt-3 flex flex-wrap gap-x-6 gap-y-2 text-xs text-red-600 dark:text-red-400"
            >
                <span>
                    Reversal posting date:
                    {{ formatDate(props.customerReceipt.reversal_posting_date) }}
                </span>

                <span>
                    Reversed by
                    {{ props.customerReceipt.reversed_by?.name ?? 'Unknown user' }}
                    on
                    {{ formatDateTime(props.customerReceipt.reversed_at) }}
                </span>
            </div>

            <p
                class="mt-3 break-all text-xs text-red-600 dark:text-red-400"
            >
                Accounting reversal reference:
                <span class="font-semibold">
                    {{ props.customerReceipt.accounting_reversal_reference ?? '—' }}
                </span>
            </p>
        </section>

        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Receipt Total
                </p>

                <p
                    class="mt-3 text-2xl font-bold text-gray-900 dark:text-white"
                >
                    {{ formatAmount(props.customerReceipt.total_amount) }}
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    {{ props.customerReceipt.currency_code }}
                </p>
            </section>

            <section
                class="rounded-2xl border border-blue-200 bg-blue-50 p-5 shadow-sm dark:border-blue-500/30 dark:bg-blue-500/10"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-blue-700 dark:text-blue-400"
                >
                    Invoice Allocations
                </p>

                <p
                    class="mt-3 text-2xl font-bold text-blue-900 dark:text-blue-200"
                >
                    {{ formatAmount(props.customerReceipt.allocated_amount) }}
                </p>

                <p
                    class="mt-1 text-sm text-blue-700 dark:text-blue-400"
                >
                    {{ props.customerReceipt.allocations.length }} invoice allocation(s)
                </p>
            </section>

            <section
                class="rounded-2xl border p-5 shadow-sm"
                :class="
                    hasUnallocatedAmount
                        ? 'border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10'
                        : 'border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900'
                "
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide"
                    :class="
                        hasUnallocatedAmount
                            ? 'text-amber-700 dark:text-amber-400'
                            : 'text-gray-500'
                    "
                >
                    Unallocated Advance
                </p>

                <p
                    class="mt-3 text-2xl font-bold"
                    :class="
                        hasUnallocatedAmount
                            ? 'text-amber-900 dark:text-amber-200'
                            : 'text-gray-900 dark:text-white'
                    "
                >
                    {{ formatAmount(props.customerReceipt.unallocated_amount) }}
                </p>

                <p
                    class="mt-1 text-sm"
                    :class="
                        hasUnallocatedAmount
                            ? 'text-amber-700 dark:text-amber-400'
                            : 'text-gray-500'
                    "
                >
                    Customer receipt credit
                </p>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Exchange Difference
                </p>

                <p
                    class="mt-3 text-2xl font-bold"
                    :class="exchangeDifferenceClasses"
                >
                    {{ formatAmount(Math.abs(totalExchangeDifference)) }}
                </p>

                <p class="mt-1 text-sm text-gray-500">
                    {{ exchangeDifferenceLabel }}
                </p>
            </section>
        </div>

        <div
            class="grid grid-cols-1 gap-6 xl:grid-cols-3"
        >
            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm xl:col-span-2 dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Receipt Information
                </h2>

                <dl
                    class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Customer
                        </dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ props.customerReceipt.customer.code }} —
                            {{ props.customerReceipt.customer.name }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Branch
                        </dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ props.customerReceipt.branch.code ?? '—' }} —
                            {{ props.customerReceipt.branch.name ?? 'Unknown branch' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Receipt Account
                        </dt>
                        <dd class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                            {{ props.customerReceipt.receipt_account.code }} —
                            {{ props.customerReceipt.receipt_account.name }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Receipt Method
                        </dt>
                        <dd class="mt-2 text-sm text-gray-900 dark:text-white">
                            {{ props.customerReceipt.receipt_method_label }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Receipt Date
                        </dt>
                        <dd class="mt-2 text-sm text-gray-900 dark:text-white">
                            {{ formatDate(props.customerReceipt.receipt_date) }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Posting Date
                        </dt>
                        <dd class="mt-2 text-sm text-gray-900 dark:text-white">
                            {{ formatDate(props.customerReceipt.posting_date) }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Currency
                        </dt>
                        <dd class="mt-2 text-sm text-gray-900 dark:text-white">
                            {{ props.customerReceipt.currency_code }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Exchange Rate
                        </dt>
                        <dd class="mt-2 text-sm text-gray-900 dark:text-white">
                            {{ formatRate(props.customerReceipt.exchange_rate) }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Receipt Reference
                        </dt>
                        <dd class="mt-2 break-words text-sm text-gray-900 dark:text-white">
                            {{ props.customerReceipt.receipt_reference ?? '—' }}
                        </dd>
                    </div>

                    <div v-if="props.customerReceipt.receipt_method === 'cheque'">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Cheque Number
                        </dt>
                        <dd class="mt-2 text-sm text-gray-900 dark:text-white">
                            {{ props.customerReceipt.cheque_number ?? '—' }}
                        </dd>
                    </div>

                    <div v-if="props.customerReceipt.receipt_method === 'cheque'">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Cheque Date
                        </dt>
                        <dd class="mt-2 text-sm text-gray-900 dark:text-white">
                            {{ formatDate(props.customerReceipt.cheque_date) }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">
                            Revision
                        </dt>
                        <dd class="mt-2 text-sm text-gray-900 dark:text-white">
                            {{ props.customerReceipt.revision }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Base-Currency Snapshot
                </h2>

                <dl class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">
                            Receipt value
                        </dt>
                        <dd class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ formatAmount(props.customerReceipt.base_total_amount) }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">
                            Allocated receipt value
                        </dt>
                        <dd class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ formatAmount(props.customerReceipt.base_allocated_amount) }}
                        </dd>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">
                            Remaining advance value
                        </dt>
                        <dd class="text-sm font-semibold text-gray-900 dark:text-white">
                            {{ formatAmount(props.customerReceipt.base_unallocated_amount) }}
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="props.customerReceipt.notes"
                    class="mt-5 border-t border-gray-200 pt-5 dark:border-gray-800"
                >
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">
                        Internal Notes
                    </p>
                    <p class="mt-2 whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">
                        {{ props.customerReceipt.notes }}
                    </p>
                </div>
            </section>
        </div>

        <section
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Sales Invoice Allocations
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Draft allocations are planning rows. Applied allocations are the immutable Accounts Receivable settlement evidence.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-950">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Line
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Sales Invoice
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Due Date
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Allocation
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Base Values
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Status
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        <tr
                            v-for="allocation in props.customerReceipt.allocations"
                            :key="allocation.id"
                        >
                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ allocation.line_number }}
                            </td>

                            <td class="px-5 py-4">
                                <Link
                                    :href="route('customer-invoices.show', allocation.sales_invoice_id)"
                                    class="text-sm font-semibold text-brand-600 transition hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300"
                                >
                                    {{ allocation.invoice_document_number ?? `Invoice #${allocation.sales_invoice_id}` }}
                                </Link>

                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Invoice rate: {{ formatRate(allocation.invoice_exchange_rate) }}
                                    · Receipt rate: {{ formatRate(allocation.receipt_exchange_rate) }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ formatDate(allocation.invoice_due_date) }}
                            </td>

                            <td class="px-5 py-4 text-right">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ formatAmount(allocation.amount) }}
                                    {{ allocation.currency_code }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-right">
                                <p class="text-sm text-gray-900 dark:text-white">
                                    Receivable: {{ formatAmount(allocation.receivable_base_amount) }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Receipt: {{ formatAmount(allocation.receipt_base_amount) }}
                                </p>
                                <p
                                    class="mt-1 text-xs font-medium"
                                    :class="
                                        Math.abs(decimalValue(allocation.exchange_difference_amount)) <= 0.000001
                                            ? 'text-gray-500 dark:text-gray-400'
                                            : decimalValue(allocation.exchange_difference_amount) > 0
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-red-600 dark:text-red-400'
                                    "
                                >
                                    FX difference:
                                    {{ formatAmount(Math.abs(decimalValue(allocation.exchange_difference_amount))) }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="allocationStatusClasses[allocation.status]"
                                >
                                    {{ titleCase(allocation.status) }}
                                </span>

                                <p
                                    v-if="allocation.applied_at"
                                    class="mt-2 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Applied {{ formatDateTime(allocation.applied_at) }}
                                </p>
                            </td>
                        </tr>

                        <tr v-if="props.customerReceipt.allocations.length === 0">
                            <td colspan="6" class="px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                No Sales Invoice allocations were recorded. The full receipt is an unallocated customer advance when posted.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <section
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        General Ledger Journals
                    </h2>
                </div>

                <div
                    v-if="props.customerReceipt.journal_entries.length === 0"
                    class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    No General Ledger journal has been posted.
                </div>

                <div v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div
                        v-for="journal in props.customerReceipt.journal_entries"
                        :key="journal.id"
                        class="p-5"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ journal.journal_number ?? `Journal #${journal.id}` }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ titleCase(journal.journal_type) }} · {{ formatDate(journal.posting_date) }}
                                </p>
                            </div>

                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                                {{ titleCase(journal.status) }}
                            </span>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Transaction debit</dt>
                                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">
                                    {{ formatAmount(journal.total_debit) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Base debit</dt>
                                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">
                                    {{ formatAmount(journal.base_total_debit) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Customer Ledger Entries
                    </h2>
                </div>

                <div
                    v-if="props.customerReceipt.customer_ledger_entries.length === 0"
                    class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400"
                >
                    No Customer Ledger entry has been posted.
                </div>

                <div v-else class="divide-y divide-gray-100 dark:divide-gray-800">
                    <div
                        v-for="entry in props.customerReceipt.customer_ledger_entries"
                        :key="entry.id"
                        class="p-5"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                    {{ entry.reference }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ titleCase(entry.entry_type) }} · {{ formatDate(entry.posting_date) }}
                                </p>
                            </div>

                            <span class="break-all text-xs font-medium text-gray-500 dark:text-gray-400">
                                {{ entry.journal_reference }}
                            </span>
                        </div>

                        <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Debit</dt>
                                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">
                                    {{ formatAmount(entry.debit_amount) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500 dark:text-gray-400">Base debit</dt>
                                <dd class="mt-1 font-semibold text-gray-900 dark:text-white">
                                    {{ formatAmount(entry.base_debit_amount) }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </section>
        </div>

        <section
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <h2 class="mb-5 text-lg font-semibold text-gray-900 dark:text-white">
                Workflow History
            </h2>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Created</p>
                    <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ props.customerReceipt.created_by?.name ?? 'Unknown user' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ formatDateTime(props.customerReceipt.created_at) }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Submitted</p>
                    <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ props.customerReceipt.submitted_by?.name ?? '—' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ formatDateTime(props.customerReceipt.submitted_at) }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Approved</p>
                    <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ props.customerReceipt.approved_by?.name ?? '—' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ formatDateTime(props.customerReceipt.approved_at) }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Posted</p>
                    <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ props.customerReceipt.posted_by?.name ?? '—' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ formatDateTime(props.customerReceipt.posted_at) }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Reversed</p>
                    <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ props.customerReceipt.reversed_by?.name ?? '—' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ formatDateTime(props.customerReceipt.reversed_at) }}
                    </p>
                </div>

                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Cancelled</p>
                    <p class="mt-2 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ props.customerReceipt.cancelled_by?.name ?? '—' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ formatDateTime(props.customerReceipt.cancelled_at) }}
                    </p>
                </div>
            </div>
        </section>
    </div>

    <div
        v-if="showCancellationModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/60 p-4"
        @click.self="closeCancellationModal"
    >
        <form
            class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900"
            @submit.prevent="submitCancellation"
        >
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Cancel Customer Receipt
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Cancellation is available only before financial posting. The document number remains consumed and cannot be reused.
            </p>

            <div class="mt-5">
                <label
                    for="customer-receipt-cancellation-reason"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Cancellation Reason
                    <span class="text-red-500">*</span>
                </label>

                <textarea
                    id="customer-receipt-cancellation-reason"
                    v-model="cancellationForm.cancellation_reason"
                    rows="4"
                    maxlength="500"
                    required
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                />

                <p
                    v-if="cancellationForm.errors.cancellation_reason"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ cancellationForm.errors.cancellation_reason }}
                </p>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    :disabled="cancellationForm.processing"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    @click="closeCancellationModal"
                >
                    Close
                </button>

                <button
                    type="submit"
                    :disabled="cancellationForm.processing || cancellationForm.cancellation_reason.trim() === ''"
                    class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ cancellationForm.processing ? 'Cancelling...' : 'Confirm Cancellation' }}
                </button>
            </div>
        </form>
    </div>

    <div
        v-if="showReversalModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/60 p-4"
        @click.self="closeReversalModal"
    >
        <form
            class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900"
            @submit.prevent="submitReversal"
        >
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Reverse Customer Receipt
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Reversal creates exact opposite General Ledger and Customer Ledger entries and reverses every original invoice allocation. It is blocked when later workflows consumed the remaining receipt credit.
            </p>

            <div class="mt-5">
                <label
                    for="customer-receipt-reversal-posting-date"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Reversal Posting Date
                    <span class="text-red-500">*</span>
                </label>

                <input
                    id="customer-receipt-reversal-posting-date"
                    v-model="reversalForm.reversal_posting_date"
                    type="date"
                    :min="props.customerReceipt.posting_date"
                    required
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                />

                <p
                    v-if="reversalForm.errors.reversal_posting_date"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ reversalForm.errors.reversal_posting_date }}
                </p>
            </div>

            <div class="mt-5">
                <label
                    for="customer-receipt-reversal-reason"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Reversal Reason
                    <span class="text-red-500">*</span>
                </label>

                <textarea
                    id="customer-receipt-reversal-reason"
                    v-model="reversalForm.reversal_reason"
                    rows="4"
                    maxlength="500"
                    required
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                />

                <p
                    v-if="reversalForm.errors.reversal_reason"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ reversalForm.errors.reversal_reason }}
                </p>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    :disabled="reversalForm.processing"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                    @click="closeReversalModal"
                >
                    Close
                </button>

                <button
                    type="submit"
                    :disabled="reversalForm.processing || reversalForm.reversal_posting_date === '' || reversalForm.reversal_reason.trim() === ''"
                    class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ reversalForm.processing ? 'Reversing...' : 'Confirm Reversal' }}
                </button>
            </div>
        </form>
    </div>
</template>