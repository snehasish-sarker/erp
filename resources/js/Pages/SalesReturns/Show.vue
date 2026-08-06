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
    CustomerCreditNoteDetail,
    CustomerCreditNoteStatus,
} from '@/Types/customer-credit-note';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    creditNote: CustomerCreditNoteDetail;
}>();

const processing = ref<string | null>(null);
const showCancelModal = ref(false);
const showReverseModal = ref(false);

const cancelForm = useForm({
    cancellation_reason: '',
});

const reverseForm = useForm({
    reversal_posting_date: new Date()
        .toISOString()
        .slice(0, 10),
    reversal_reason: '',
});

const title = computed((): string => {
    return props.creditNote.credit_note_number
        ?? `Credit Note Draft #${props.creditNote.id}`;
});

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(`${value}T00:00:00`);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

const formatAmount = (value: string | number): string => {
    const parsed = typeof value === 'number'
        ? value
        : Number.parseFloat(value);

    if (!Number.isFinite(parsed)) {
        return String(value);
    }

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 6,
    }).format(parsed);
};

const formatQuantity = (value: string | number): string => {
    const parsed = typeof value === 'number'
        ? value
        : Number.parseFloat(value);

    if (!Number.isFinite(parsed)) {
        return String(value);
    }

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 6,
    }).format(parsed);
};

const statusClass = (status: CustomerCreditNoteStatus): string => {
    if (status === 'posted') {
        return 'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-300';
    }

    if (status === 'approved') {
        return 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
    }

    if (status === 'submitted') {
        return 'bg-warning-50 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300';
    }

    if (status === 'reversed' || status === 'cancelled') {
        return 'bg-error-50 text-error-700 dark:bg-error-900/30 dark:text-error-300';
    }

    return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
};

const openItemStatusClass = (status: string): string => {
    if (status === 'settled') {
        return 'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-300';
    }

    if (status === 'partially_settled') {
        return 'bg-warning-50 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300';
    }

    if (status === 'reversed') {
        return 'bg-error-50 text-error-700 dark:bg-error-900/30 dark:text-error-300';
    }

    return 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300';
};

const executeAction = (
    action: 'submit' | 'return-to-draft' | 'approve' | 'post',
    confirmation: string,
): void => {
    if (!window.confirm(confirmation)) {
        return;
    }

    processing.value = action;

    router.post(
        route(`sales-returns.${action}`, props.creditNote.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = null;
            },
        },
    );
};

const deleteCreditNote = (): void => {
    if (!window.confirm('Delete this Customer Credit Note draft?')) {
        return;
    }

    processing.value = 'delete';

    router.delete(
        route('sales-returns.destroy', props.creditNote.id),
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = null;
            },
        },
    );
};

const openCancellation = (): void => {
    cancelForm.reset();
    cancelForm.clearErrors();
    showCancelModal.value = true;
};

const closeCancellation = (): void => {
    if (cancelForm.processing) {
        return;
    }

    showCancelModal.value = false;
    cancelForm.reset();
    cancelForm.clearErrors();
};

const cancelCreditNote = (): void => {
    cancelForm.post(
        route('sales-returns.cancel', props.creditNote.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                showCancelModal.value = false;
                cancelForm.reset();
            },
        },
    );
};

const openReversal = (): void => {
    reverseForm.reset();
    reverseForm.clearErrors();
    reverseForm.reversal_posting_date = new Date()
        .toISOString()
        .slice(0, 10);
    showReverseModal.value = true;
};

const closeReversal = (): void => {
    if (reverseForm.processing) {
        return;
    }

    showReverseModal.value = false;
    reverseForm.reset();
    reverseForm.clearErrors();
};

const reverseCreditNote = (): void => {
    reverseForm.post(
        route('sales-returns.reverse', props.creditNote.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                showReverseModal.value = false;
                reverseForm.reset();
            },
        },
    );
};
</script>

<template>
    <Head :title="title" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <div class="mb-2 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <Link
                        :href="route('sales-returns.index')"
                        class="hover:text-brand-500"
                    >
                        Sales Returns
                    </Link>
                    <span>/</span>
                    <span class="text-gray-700 dark:text-gray-300">
                        {{ title }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                        {{ title }}
                    </h1>

                    <span
                        :class="[
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                            statusClass(creditNote.status),
                        ]"
                    >
                        {{ creditNote.status_label }}
                    </span>
                </div>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Sales Invoice {{ creditNote.sales_invoice_number }}
                    · Sales Order {{ creditNote.sales_order_number }}
                    · Revision {{ creditNote.revision }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('sales-returns.index')"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back
                </Link>

                <a
                    v-if="creditNote.can.print"
                    :href="route('sales-returns.print', creditNote.id)"
                    target="_blank"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Print Credit Note
                </a>

                <Link
                    v-if="creditNote.can.update"
                    :href="route('sales-returns.edit', creditNote.id)"
                    class="rounded-lg border border-brand-300 bg-white px-4 py-2.5 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-800 dark:bg-gray-900 dark:text-brand-400 dark:hover:bg-brand-900/20"
                >
                    Edit
                </Link>

                <button
                    v-if="creditNote.can.delete"
                    :disabled="processing !== null"
                    type="button"
                    class="rounded-lg border border-error-300 bg-white px-4 py-2.5 text-sm font-medium text-error-600 hover:bg-error-50 disabled:opacity-60 dark:border-error-900 dark:bg-gray-900 dark:hover:bg-error-900/20"
                    @click="deleteCreditNote"
                >
                    {{ processing === 'delete' ? 'Deleting...' : 'Delete' }}
                </button>
            </div>
        </div>

        <div
            v-if="
                creditNote.can.submit
                    || creditNote.can.return_to_draft
                    || creditNote.can.approve
                    || creditNote.can.cancel
                    || creditNote.can.post
                    || creditNote.can.reverse
            "
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
        >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="font-semibold text-gray-900 dark:text-white">
                        Workflow Actions
                    </h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Posting creates the customer credit, applies it to the source invoice, and restores stock only for lines marked for return.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="creditNote.can.submit"
                        :disabled="processing !== null"
                        type="button"
                        class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-60"
                        @click="executeAction(
                            'submit',
                            'Submit this Customer Credit Note for approval?',
                        )"
                    >
                        {{ processing === 'submit' ? 'Submitting...' : 'Submit' }}
                    </button>

                    <button
                        v-if="creditNote.can.return_to_draft"
                        :disabled="processing !== null"
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="executeAction(
                            'return-to-draft',
                            'Return this Customer Credit Note to draft?',
                        )"
                    >
                        {{ processing === 'return-to-draft' ? 'Returning...' : 'Return to Draft' }}
                    </button>

                    <button
                        v-if="creditNote.can.approve"
                        :disabled="processing !== null"
                        type="button"
                        class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                        @click="executeAction(
                            'approve',
                            'Approve this Customer Credit Note?',
                        )"
                    >
                        {{ processing === 'approve' ? 'Approving...' : 'Approve' }}
                    </button>

                    <button
                        v-if="creditNote.can.post"
                        :disabled="processing !== null"
                        type="button"
                        class="rounded-lg bg-success-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-success-700 disabled:opacity-60"
                        @click="executeAction(
                            'post',
                            'Post this Customer Credit Note? Revenue, tax, Accounts Receivable, automatic settlement, and selected inventory returns will be recorded atomically.',
                        )"
                    >
                        {{ processing === 'post' ? 'Posting...' : 'Post Credit Note' }}
                    </button>

                    <button
                        v-if="creditNote.can.cancel"
                        type="button"
                        class="rounded-lg border border-error-300 px-4 py-2.5 text-sm font-medium text-error-600 hover:bg-error-50 dark:border-error-900 dark:hover:bg-error-900/20"
                        @click="openCancellation"
                    >
                        Cancel
                    </button>

                    <button
                        v-if="creditNote.can.reverse"
                        type="button"
                        class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-error-600"
                        @click="openReversal"
                    >
                        Reverse Credit Note
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="creditNote.status === 'cancelled'"
            class="rounded-xl border border-error-200 bg-error-50 px-4 py-4 text-sm text-error-700 dark:border-error-900/50 dark:bg-error-900/20 dark:text-error-300"
        >
            <p class="font-semibold">Credit Note Cancelled</p>
            <p class="mt-1">{{ creditNote.cancellation_reason ?? 'No reason recorded.' }}</p>
            <p class="mt-2 text-xs">
                {{ creditNote.cancelled_by?.name ?? 'Unknown user' }}
                · {{ formatDateTime(creditNote.cancelled_at) }}
            </p>
        </div>

        <div
            v-if="creditNote.status === 'reversed'"
            class="rounded-xl border border-error-200 bg-error-50 px-4 py-4 text-sm text-error-700 dark:border-error-900/50 dark:bg-error-900/20 dark:text-error-300"
        >
            <p class="font-semibold">Credit Note Reversed</p>
            <p class="mt-1">{{ creditNote.reversal_reason ?? 'No reason recorded.' }}</p>
            <p class="mt-2 text-xs">
                Posting date {{ formatDate(creditNote.reversal_posting_date) }}
                · {{ creditNote.reversed_by?.name ?? 'Unknown user' }}
                · {{ formatDateTime(creditNote.reversed_at) }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Credit Total</p>
                <p class="mt-2 text-xl font-semibold text-brand-600 dark:text-brand-400">
                    {{ creditNote.currency_code }} {{ formatAmount(creditNote.total_amount) }}
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Returned Quantity</p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ formatQuantity(creditNote.returned_quantity) }}
                </p>
                <p class="mt-1 text-xs text-gray-500">Physical stock return only</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Inventory Restored</p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ formatAmount(creditNote.inventory_return_value) }}
                </p>
                <p class="mt-1 text-xs text-gray-500">Original dispatch issue cost</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Auto-applied to Invoice</p>
                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-white">
                    {{ creditNote.currency_code }}
                    {{ formatAmount(creditNote.automatic_allocation?.amount ?? '0') }}
                </p>
                <p class="mt-1 text-xs text-gray-500">
                    {{ creditNote.automatic_allocation?.status?.replace(/_/g, ' ') ?? 'Not posted' }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6 xl:col-span-2">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Credit Note Details</h2>

                <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Credit Note Date</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(creditNote.credit_note_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Posting Date</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ formatDate(creditNote.posting_date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Sales Invoice</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ creditNote.sales_invoice_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Sales Order</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ creditNote.sales_order_number }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Branch</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ creditNote.branch?.name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Return Warehouse</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ creditNote.warehouse?.name ?? 'No stock return' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Currency</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ creditNote.currency_code }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Exchange Rate</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ creditNote.exchange_rate }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Reason</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ creditNote.reason }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Customer Snapshot</h2>

                <dl class="mt-5 space-y-4">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Customer</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white">{{ creditNote.customer_name }}</dd>
                        <dd class="text-xs text-gray-500">{{ creditNote.customer_code }} · {{ creditNote.customer_type }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Contact</dt>
                        <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ creditNote.customer_contact_person ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Email</dt>
                        <dd class="mt-1 break-all text-sm text-gray-700 dark:text-gray-300">{{ creditNote.customer_email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Phone</dt>
                        <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ creditNote.customer_phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Tax Number</dt>
                        <dd class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ creditNote.customer_tax_number ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div
            v-if="creditNote.customer_open_item"
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Customer Credit Open Item</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Open item #{{ creditNote.customer_open_item.id }}</p>
                </div>

                <span
                    :class="[
                        'inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium capitalize',
                        openItemStatusClass(creditNote.customer_open_item.status),
                    ]"
                >
                    {{ creditNote.customer_open_item.status.replace(/_/g, ' ') }}
                </span>
            </div>

            <dl class="mt-5 grid gap-5 sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Original Credit</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ creditNote.currency_code }} {{ formatAmount(creditNote.customer_open_item.original_amount) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Applied</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ creditNote.currency_code }} {{ formatAmount(creditNote.customer_open_item.allocated_amount) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Unallocated Credit</dt>
                    <dd class="mt-1 text-sm font-semibold text-brand-600 dark:text-brand-400">
                        {{ creditNote.currency_code }} {{ formatAmount(creditNote.customer_open_item.outstanding_amount) }}
                    </dd>
                </div>
            </dl>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Billing Address</h2>
                <p class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ creditNote.billing_address ?? '—' }}</p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Return Address</h2>
                <p class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ creditNote.return_address ?? '—' }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 p-5 dark:border-gray-800 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Credit Lines</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Dispatch allocations reserve the exact invoiced source quantities and historical issue costs.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1300px] divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.03]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Product</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Credit Type</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Quantity</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Stock Return</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Subtotal</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Tax</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Credit Total</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Return Cost</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <template
                            v-for="line in creditNote.lines"
                            :key="line.id"
                        >
                            <tr>
                                <td class="px-5 py-4">
                                    <p class="font-medium text-gray-900 dark:text-white">
                                        {{ line.line_number }}. {{ line.product_name }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ line.product_sku }} · {{ line.unit_code }} · {{ line.product_type.replace(/_/g, ' ') }}
                                    </p>
                                    <p v-if="line.description" class="mt-1 max-w-md text-xs text-gray-500">{{ line.description }}</p>
                                </td>
                                <td class="px-5 py-4 text-sm capitalize text-gray-700 dark:text-gray-300">{{ line.line_type }}</td>
                                <td class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                    {{ line.line_type === 'quantity' ? formatQuantity(line.credit_quantity) : '—' }}
                                </td>
                                <td class="px-5 py-4 text-sm">
                                    <span
                                        :class="[
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                            line.return_to_stock
                                                ? 'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-300'
                                                : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
                                        ]"
                                    >
                                        {{ line.return_to_stock ? 'Returned' : 'No stock movement' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatAmount(line.subtotal) }}</td>
                                <td class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                    {{ formatAmount(line.tax_amount) }}
                                    <span class="block text-xs text-gray-500">{{ formatQuantity(line.tax_rate) }}%</span>
                                </td>
                                <td class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ formatAmount(line.line_total) }}</td>
                                <td class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300">{{ formatAmount(line.total_cost) }}</td>
                            </tr>

                            <tr
                                v-if="line.dispatch_allocations.length > 0"
                                class="bg-gray-50/70 dark:bg-white/[0.015]"
                            >
                                <td colspan="8" class="px-5 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-xs font-medium uppercase tracking-wide text-gray-500">Invoice dispatch sources:</span>
                                        <span
                                            v-for="allocation in line.dispatch_allocations"
                                            :key="allocation.id"
                                            class="inline-flex rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        >
                                            {{ allocation.dispatch_number ?? 'Dispatch' }}
                                            · {{ formatDate(allocation.dispatch_date) }}
                                            · Qty {{ formatQuantity(allocation.allocated_quantity) }}
                                            · Cost {{ formatAmount(allocation.total_cost) }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_390px]">
            <div class="space-y-6">
                <div
                    v-if="creditNote.notes"
                    class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
                >
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Notes</h2>
                    <p class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300">{{ creditNote.notes }}</p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Workflow and Accounting</h2>

                    <dl class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Created</dt>
                            <dd class="mt-1 text-sm text-gray-800 dark:text-gray-200">{{ creditNote.created_by?.name ?? '—' }}</dd>
                            <dd class="text-xs text-gray-500">{{ formatDateTime(creditNote.created_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Submitted</dt>
                            <dd class="mt-1 text-sm text-gray-800 dark:text-gray-200">{{ creditNote.submitted_by?.name ?? '—' }}</dd>
                            <dd class="text-xs text-gray-500">{{ formatDateTime(creditNote.submitted_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Approved</dt>
                            <dd class="mt-1 text-sm text-gray-800 dark:text-gray-200">{{ creditNote.approved_by?.name ?? '—' }}</dd>
                            <dd class="text-xs text-gray-500">{{ formatDateTime(creditNote.approved_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Posted</dt>
                            <dd class="mt-1 text-sm text-gray-800 dark:text-gray-200">{{ creditNote.posted_by?.name ?? '—' }}</dd>
                            <dd class="text-xs text-gray-500">{{ formatDateTime(creditNote.posted_at) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Financial Journal</dt>
                            <dd class="mt-1 break-all text-sm font-medium text-gray-900 dark:text-white">{{ creditNote.accounting_posting_reference ?? '—' }}</dd>
                            <dd v-if="creditNote.accounting_reversal_reference" class="mt-1 break-all text-xs text-error-500">Reversal: {{ creditNote.accounting_reversal_reference }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-500">Inventory Journal</dt>
                            <dd class="mt-1 break-all text-sm font-medium text-gray-900 dark:text-white">{{ creditNote.inventory_posting_reference ?? '—' }}</dd>
                            <dd v-if="creditNote.inventory_reversal_reference" class="mt-1 break-all text-xs text-error-500">Reversal: {{ creditNote.inventory_reversal_reference }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <div class="h-fit rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Credit Summary</h2>

                <dl class="mt-5 space-y-4">
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-gray-500">Gross</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ formatAmount(creditNote.gross_amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-gray-500">Discount Reversal</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">-{{ formatAmount(creditNote.discount_amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-gray-500">Subtotal</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ formatAmount(creditNote.subtotal) }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-gray-500">Output Tax Reversal</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ formatAmount(creditNote.tax_amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-gray-500">Quantity Credits</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ formatAmount(creditNote.quantity_credit_amount) }}</dd>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <dt class="text-gray-500">Amount-only Credits</dt>
                        <dd class="font-medium text-gray-800 dark:text-gray-200">{{ formatAmount(creditNote.amount_only_credit_amount) }}</dd>
                    </div>
                    <div class="border-t border-gray-200 pt-4 dark:border-gray-800">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="font-semibold text-gray-900 dark:text-white">Credit Total</dt>
                            <dd class="text-xl font-semibold text-brand-600 dark:text-brand-400">
                                {{ creditNote.currency_code }} {{ formatAmount(creditNote.total_amount) }}
                            </dd>
                        </div>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="showCancelModal"
            class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-950/60 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="cancel-credit-note-title"
            @click.self="closeCancellation"
        >
            <form
                class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900"
                @submit.prevent="cancelCreditNote"
            >
                <h2 id="cancel-credit-note-title" class="text-xl font-semibold text-gray-900 dark:text-white">
                    Cancel Customer Credit Note
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Cancellation is available before posting and preserves the document for audit history.
                </p>

                <div class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Cancellation Reason <span class="text-error-500">*</span>
                    </label>
                    <textarea
                        v-model="cancelForm.cancellation_reason"
                        rows="4"
                        maxlength="500"
                        autofocus
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-error-500 dark:border-gray-700 dark:text-white"
                    />
                    <p class="mt-1 text-xs text-error-500">{{ cancelForm.errors.cancellation_reason }}</p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        :disabled="cancelForm.processing"
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                        @click="closeCancellation"
                    >
                        Keep Credit Note
                    </button>
                    <button
                        :disabled="cancelForm.processing || cancelForm.cancellation_reason.trim() === ''"
                        type="submit"
                        class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-error-600 disabled:opacity-60"
                    >
                        {{ cancelForm.processing ? 'Cancelling...' : 'Confirm Cancellation' }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>

    <Teleport to="body">
        <div
            v-if="showReverseModal"
            class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-950/60 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="reverse-credit-note-title"
            @click.self="closeReversal"
        >
            <form
                class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900"
                @submit.prevent="reverseCreditNote"
            >
                <h2 id="reverse-credit-note-title" class="text-xl font-semibold text-gray-900 dark:text-white">
                    Reverse Customer Credit Note
                </h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Reversal restores the source invoice balance and removes returned stock only when no later dependent transaction exists.
                </p>

                <div class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Reversal Posting Date <span class="text-error-500">*</span>
                    </label>
                    <input
                        v-model="reverseForm.reversal_posting_date"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-error-500 dark:border-gray-700 dark:text-white"
                    />
                    <p class="mt-1 text-xs text-error-500">{{ reverseForm.errors.reversal_posting_date }}</p>
                </div>

                <div class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Reversal Reason <span class="text-error-500">*</span>
                    </label>
                    <textarea
                        v-model="reverseForm.reversal_reason"
                        rows="4"
                        maxlength="500"
                        autofocus
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-error-500 dark:border-gray-700 dark:text-white"
                    />
                    <p class="mt-1 text-xs text-error-500">{{ reverseForm.errors.reversal_reason }}</p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        :disabled="reverseForm.processing"
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                        @click="closeReversal"
                    >
                        Keep Credit Note
                    </button>
                    <button
                        :disabled="
                            reverseForm.processing
                                || reverseForm.reversal_posting_date === ''
                                || reverseForm.reversal_reason.trim() === ''
                        "
                        type="submit"
                        class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-error-600 disabled:opacity-60"
                    >
                        {{ reverseForm.processing ? 'Reversing...' : 'Confirm Reversal' }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>
