<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import {
    computed,
    onBeforeUnmount,
    reactive,
    watch,
} from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    CustomerCreditNoteFilters,
    CustomerCreditNoteIndexProps,
    CustomerCreditNoteSort,
    CustomerCreditNoteStatus,
    CustomerCreditNoteSummary,
} from '@/Types/customer-credit-note';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<CustomerCreditNoteIndexProps>();

const filters = reactive<CustomerCreditNoteFilters>({
    search: props.filters.search ?? '',
    branch_id: props.filters.branch_id ?? null,
    customer_id: props.filters.customer_id ?? null,
    status: props.filters.status ?? '',
    posting_date_from: props.filters.posting_date_from ?? '',
    posting_date_to: props.filters.posting_date_to ?? '',
    sort: props.filters.sort ?? 'created_at',
    direction: props.filters.direction ?? 'desc',
    per_page: props.filters.per_page ?? 15,
});

let searchTimer: ReturnType<typeof setTimeout> | null = null;

const hasFilters = computed(
    (): boolean =>
        filters.search.trim() !== ''
        || filters.branch_id !== null
        || filters.customer_id !== null
        || filters.status !== ''
        || filters.posting_date_from !== ''
        || filters.posting_date_to !== '',
);

const query = (
    page?: number,
): Record<string, string | number> => {
    const result: Record<string, string | number> = {
        sort: filters.sort,
        direction: filters.direction,
        per_page: filters.per_page,
    };

    if (page !== undefined) {
        result.page = page;
    }

    if (filters.search.trim() !== '') {
        result.search = filters.search.trim();
    }

    if (filters.branch_id !== null) {
        result.branch_id = filters.branch_id;
    }

    if (filters.customer_id !== null) {
        result.customer_id = filters.customer_id;
    }

    if (filters.status !== '') {
        result.status = filters.status;
    }

    if (filters.posting_date_from !== '') {
        result.posting_date_from = filters.posting_date_from;
    }

    if (filters.posting_date_to !== '') {
        result.posting_date_to = filters.posting_date_to;
    }

    return result;
};

const visit = (page = 1): void => {
    router.get(
        route('sales-returns.index'),
        query(page),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

watch(
    () => filters.search,
    () => {
        if (searchTimer !== null) {
            clearTimeout(searchTimer);
        }

        searchTimer = setTimeout(() => visit(1), 400);
    },
);

onBeforeUnmount(() => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
});

const reset = (): void => {
    router.get(
        route('sales-returns.index'),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const sortBy = (column: CustomerCreditNoteSort): void => {
    if (filters.sort === column) {
        filters.direction = filters.direction === 'asc' ? 'desc' : 'asc';
    } else {
        filters.sort = column;
        filters.direction = 'asc';
    }

    visit(1);
};

const indicator = (column: CustomerCreditNoteSort): string => {
    if (filters.sort !== column) {
        return '';
    }

    return filters.direction === 'asc' ? '↑' : '↓';
};

const pages = computed((): number[] => {
    const current = props.creditNotes.meta.current_page;
    const last = props.creditNotes.meta.last_page;
    const result: number[] = [];

    for (
        let page = Math.max(1, current - 2);
        page <= Math.min(last, current + 2);
        page += 1
    ) {
        result.push(page);
    }

    return result;
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

const formatNumber = (
    value: string,
    maximumFractionDigits = 6,
): string => {
    const number = Number.parseFloat(value);

    if (!Number.isFinite(number)) {
        return value;
    }

    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: maximumFractionDigits === 6 ? 0 : 2,
        maximumFractionDigits,
    }).format(number);
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

const remove = (creditNote: CustomerCreditNoteSummary): void => {
    if (!window.confirm(`Delete Customer Credit Note draft #${creditNote.id}?`)) {
        return;
    }

    router.delete(
        route('sales-returns.destroy', creditNote.id),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Sales Returns and Credit Notes" />

    <div class="space-y-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                    Sales Returns and Credit Notes
                </h1>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Credit posted invoices, restore returned inventory, and settle Accounts Receivable.
                </p>
            </div>

            <Link
                v-if="can.create"
                :href="route('sales-returns.create')"
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
            >
                Create Credit Note
            </Link>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <div class="md:col-span-2">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Search
                    </label>

                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Credit note, invoice, Sales Order, customer, or reason"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Branch
                    </label>

                    <select
                        v-model="filters.branch_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">All branches</option>

                        <option
                            v-for="branch in branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }} ({{ branch.code }})
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Customer
                    </label>

                    <select
                        v-model="filters.customer_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="null">All customers</option>

                        <option
                            v-for="customer in customers"
                            :key="customer.id"
                            :value="customer.id"
                        >
                            {{ customer.name }} ({{ customer.code }})
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Status
                    </label>

                    <select
                        v-model="filters.status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option value="">All statuses</option>

                        <option
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Per Page
                    </label>

                    <select
                        v-model="filters.per_page"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                        <option :value="10">10</option>
                        <option :value="15">15</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Posting From
                    </label>

                    <input
                        v-model="filters.posting_date_from"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Posting To
                    </label>

                    <input
                        v-model="filters.posting_date_to"
                        :min="filters.posting_date_from"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    >
                </div>
            </div>

            <div class="mt-5 flex justify-end gap-3">
                <button
                    v-if="hasFilters"
                    type="button"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                    @click="reset"
                >
                    Reset
                </button>

                <button
                    type="button"
                    class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
                    @click="visit(1)"
                >
                    Apply Filters
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="min-w-[1150px] divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.03]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('credit_note_number')">
                                    Credit Note {{ indicator('credit_note_number') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('sales_invoice_number')">
                                    Source Invoice {{ indicator('sales_invoice_number') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('customer_name')">
                                    Customer {{ indicator('customer_name') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('credit_note_date')">
                                    Date {{ indicator('credit_note_date') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Returned Qty
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('total_amount')">
                                    Credit Total {{ indicator('total_amount') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <button type="button" @click="sortBy('status')">
                                    Status {{ indicator('status') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-if="creditNotes.data.length === 0">
                            <td colspan="8" class="px-5 py-14 text-center text-sm text-gray-500">
                                No Customer Credit Notes found.
                            </td>
                        </tr>

                        <tr
                            v-for="creditNote in creditNotes.data"
                            :key="creditNote.id"
                            class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]"
                        >
                            <td class="px-5 py-4">
                                <Link
                                    :href="route('sales-returns.show', creditNote.id)"
                                    class="font-medium text-brand-600 dark:text-brand-400"
                                >
                                    {{ creditNote.credit_note_number ?? `Draft #${creditNote.id}` }}
                                </Link>

                                <p class="mt-1 text-xs text-gray-500">
                                    {{ creditNote.sales_order_number }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ creditNote.sales_invoice_number }}
                            </td>

                            <td class="px-5 py-4">
                                <p class="font-medium text-gray-900 dark:text-white">
                                    {{ creditNote.customer_name }}
                                </p>

                                <p class="text-xs text-gray-500">
                                    {{ creditNote.customer_code }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ formatDate(creditNote.credit_note_date) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300">
                                {{ formatNumber(creditNote.returned_quantity) }}
                            </td>

                            <td class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white">
                                {{ creditNote.currency_code }}
                                {{ formatNumber(creditNote.total_amount, 2) }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                        statusClass(creditNote.status),
                                    ]"
                                >
                                    {{ creditNote.status_label }}
                                </span>
                            </td>

                            <td class="px-5 py-4 text-right">
                                <div class="inline-flex gap-2">
                                    <Link
                                        v-if="creditNote.can.view"
                                        :href="route('sales-returns.show', creditNote.id)"
                                        class="text-sm font-medium text-brand-600 dark:text-brand-400"
                                    >
                                        View
                                    </Link>

                                    <Link
                                        v-if="creditNote.can.update"
                                        :href="route('sales-returns.edit', creditNote.id)"
                                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="creditNote.can.delete"
                                        type="button"
                                        class="text-sm font-medium text-error-500"
                                        @click="remove(creditNote)"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-gray-500">
                    Showing {{ creditNotes.meta.from ?? 0 }} to {{ creditNotes.meta.to ?? 0 }} of {{ creditNotes.meta.total }}
                </p>

                <div v-if="creditNotes.meta.last_page > 1" class="flex items-center gap-1">
                    <button
                        :disabled="creditNotes.meta.current_page <= 1"
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                        @click="visit(creditNotes.meta.current_page - 1)"
                    >
                        Previous
                    </button>

                    <button
                        v-for="page in pages"
                        :key="page"
                        type="button"
                        :class="[
                            'min-w-10 rounded-lg px-3 py-2 text-sm',
                            page === creditNotes.meta.current_page
                                ? 'bg-brand-500 text-white'
                                : 'border border-gray-300 dark:border-gray-700',
                        ]"
                        @click="visit(page)"
                    >
                        {{ page }}
                    </button>

                    <button
                        :disabled="creditNotes.meta.current_page >= creditNotes.meta.last_page"
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm disabled:opacity-50 dark:border-gray-700"
                        @click="visit(creditNotes.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
