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
    SupplierDebitNoteFilters,
    SupplierDebitNoteGoodsReceiptFilterOption,
    SupplierDebitNoteIndexProps,
    SupplierDebitNotePurchaseOrderFilterOption,
    SupplierDebitNotePurchaseReturnFilterOption,
    SupplierDebitNoteSort,
    SupplierDebitNoteStatus,
    SupplierDebitNoteSummary,
    SupplierDebitNoteSupplierInvoiceFilterOption,
} from '@/Types/supplier-debit-note';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<SupplierDebitNoteIndexProps>();

const filters = reactive<SupplierDebitNoteFilters>({
    search: props.filters.search ?? '',
    branch_id: props.filters.branch_id ?? null,
    supplier_id: props.filters.supplier_id ?? null,
    purchase_return_id:
        props.filters.purchase_return_id ?? null,
    supplier_invoice_id:
        props.filters.supplier_invoice_id ?? null,
    purchase_order_id:
        props.filters.purchase_order_id ?? null,
    goods_receipt_id:
        props.filters.goods_receipt_id ?? null,
    status: props.filters.status ?? '',
    debit_note_date_from:
        props.filters.debit_note_date_from ?? '',
    debit_note_date_to:
        props.filters.debit_note_date_to ?? '',
    sort: props.filters.sort ?? 'created_at',
    direction: props.filters.direction ?? 'desc',
    per_page: props.filters.per_page ?? 15,
});

const availablePurchaseOrders = computed<
    SupplierDebitNotePurchaseOrderFilterOption[]
>(() => {
    return props.purchaseOrders.filter(
        (purchaseOrder): boolean => {
            if (
                filters.branch_id !== null
                && purchaseOrder.branch_id
                    !== filters.branch_id
            ) {
                return false;
            }

            if (
                filters.supplier_id !== null
                && purchaseOrder.supplier_id
                    !== filters.supplier_id
            ) {
                return false;
            }

            return true;
        },
    );
});

const availableGoodsReceipts = computed<
    SupplierDebitNoteGoodsReceiptFilterOption[]
>(() => {
    return props.goodsReceipts.filter(
        (goodsReceipt): boolean => {
            if (
                filters.branch_id !== null
                && goodsReceipt.branch_id
                    !== filters.branch_id
            ) {
                return false;
            }

            if (
                filters.supplier_id !== null
                && goodsReceipt.supplier_id
                    !== filters.supplier_id
            ) {
                return false;
            }

            if (
                filters.purchase_order_id !== null
                && goodsReceipt.purchase_order_id
                    !== filters.purchase_order_id
            ) {
                return false;
            }

            return true;
        },
    );
});

const availableSupplierInvoices = computed<
    SupplierDebitNoteSupplierInvoiceFilterOption[]
>(() => {
    return props.supplierInvoices.filter(
        (supplierInvoice): boolean => {
            if (
                filters.branch_id !== null
                && supplierInvoice.branch_id
                    !== filters.branch_id
            ) {
                return false;
            }

            if (
                filters.supplier_id !== null
                && supplierInvoice.supplier_id
                    !== filters.supplier_id
            ) {
                return false;
            }

            if (
                filters.purchase_order_id !== null
                && supplierInvoice.purchase_order_id
                    !== filters.purchase_order_id
            ) {
                return false;
            }

            return true;
        },
    );
});

const availablePurchaseReturns = computed<
    SupplierDebitNotePurchaseReturnFilterOption[]
>(() => {
    return props.purchaseReturns.filter(
        (purchaseReturn): boolean => {
            if (
                filters.branch_id !== null
                && purchaseReturn.branch_id
                    !== filters.branch_id
            ) {
                return false;
            }

            if (
                filters.supplier_id !== null
                && purchaseReturn.supplier_id
                    !== filters.supplier_id
            ) {
                return false;
            }

            if (
                filters.purchase_order_id !== null
                && purchaseReturn.purchase_order_id
                    !== filters.purchase_order_id
            ) {
                return false;
            }

            if (
                filters.goods_receipt_id !== null
                && purchaseReturn.goods_receipt_id
                    !== filters.goods_receipt_id
            ) {
                return false;
            }

            return true;
        },
    );
});

const hasActiveFilters = computed((): boolean => {
    return filters.search.trim() !== ''
        || filters.branch_id !== null
        || filters.supplier_id !== null
        || filters.purchase_return_id !== null
        || filters.supplier_invoice_id !== null
        || filters.purchase_order_id !== null
        || filters.goods_receipt_id !== null
        || filters.status !== ''
        || filters.debit_note_date_from !== ''
        || filters.debit_note_date_to !== '';
});

const queryParameters = (): Record<
    string,
    string | number
> => {
    const query: Record<
        string,
        string | number
    > = {
        sort: filters.sort,
        direction: filters.direction,
        per_page: filters.per_page,
    };

    if (filters.search.trim() !== '') {
        query.search = filters.search.trim();
    }

    if (filters.branch_id !== null) {
        query.branch_id = filters.branch_id;
    }

    if (filters.supplier_id !== null) {
        query.supplier_id = filters.supplier_id;
    }

    if (filters.purchase_return_id !== null) {
        query.purchase_return_id =
            filters.purchase_return_id;
    }

    if (filters.supplier_invoice_id !== null) {
        query.supplier_invoice_id =
            filters.supplier_invoice_id;
    }

    if (filters.purchase_order_id !== null) {
        query.purchase_order_id =
            filters.purchase_order_id;
    }

    if (filters.goods_receipt_id !== null) {
        query.goods_receipt_id =
            filters.goods_receipt_id;
    }

    if (filters.status !== '') {
        query.status = filters.status;
    }

    if (filters.debit_note_date_from !== '') {
        query.debit_note_date_from =
            filters.debit_note_date_from;
    }

    if (filters.debit_note_date_to !== '') {
        query.debit_note_date_to =
            filters.debit_note_date_to;
    }

    return query;
};

const applyFilters = (): void => {
    router.get(
        route('supplier-debit-notes.index'),
        queryParameters(),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

let searchTimer:
    | ReturnType<typeof setTimeout>
    | null = null;

let suppressSearchWatch = false;

watch(
    () => filters.search,
    () => {
        if (suppressSearchWatch) {
            return;
        }

        if (searchTimer !== null) {
            clearTimeout(searchTimer);
        }

        searchTimer = setTimeout(
            applyFilters,
            400,
        );
    },
    {
        flush: 'sync',
    },
);

onBeforeUnmount(() => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
});

const optionExists = <T extends { id: number }>(
    options: T[],
    selectedId: number | null,
): boolean => {
    if (selectedId === null) {
        return true;
    }

    return options.some(
        (option): boolean =>
            option.id === selectedId,
    );
};

const normalizeDependentFilters = (): void => {
    if (
        !optionExists(
            availablePurchaseOrders.value,
            filters.purchase_order_id,
        )
    ) {
        filters.purchase_order_id = null;
    }

    if (
        !optionExists(
            availableGoodsReceipts.value,
            filters.goods_receipt_id,
        )
    ) {
        filters.goods_receipt_id = null;
    }

    if (
        !optionExists(
            availableSupplierInvoices.value,
            filters.supplier_invoice_id,
        )
    ) {
        filters.supplier_invoice_id = null;
    }

    if (
        !optionExists(
            availablePurchaseReturns.value,
            filters.purchase_return_id,
        )
    ) {
        filters.purchase_return_id = null;
    }
};

const handleBranchOrSupplierChange = (): void => {
    normalizeDependentFilters();
    applyFilters();
};

const handlePurchaseOrderChange = (): void => {
    if (
        !optionExists(
            availableGoodsReceipts.value,
            filters.goods_receipt_id,
        )
    ) {
        filters.goods_receipt_id = null;
    }

    if (
        !optionExists(
            availableSupplierInvoices.value,
            filters.supplier_invoice_id,
        )
    ) {
        filters.supplier_invoice_id = null;
    }

    if (
        !optionExists(
            availablePurchaseReturns.value,
            filters.purchase_return_id,
        )
    ) {
        filters.purchase_return_id = null;
    }

    applyFilters();
};

const handleGoodsReceiptChange = (): void => {
    if (
        !optionExists(
            availablePurchaseReturns.value,
            filters.purchase_return_id,
        )
    ) {
        filters.purchase_return_id = null;
    }

    applyFilters();
};

const resetFilters = (): void => {
    suppressSearchWatch = true;

    if (searchTimer !== null) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }

    filters.search = '';
    filters.branch_id = null;
    filters.supplier_id = null;
    filters.purchase_return_id = null;
    filters.supplier_invoice_id = null;
    filters.purchase_order_id = null;
    filters.goods_receipt_id = null;
    filters.status = '';
    filters.debit_note_date_from = '';
    filters.debit_note_date_to = '';
    filters.sort = 'created_at';
    filters.direction = 'desc';
    filters.per_page = 15;

    applyFilters();
    suppressSearchWatch = false;
};

const toggleSort = (
    sort: SupplierDebitNoteSort,
): void => {
    if (filters.sort === sort) {
        filters.direction =
            filters.direction === 'asc'
                ? 'desc'
                : 'asc';
    } else {
        filters.sort = sort;
        filters.direction = 'asc';
    }

    applyFilters();
};

const sortIndicator = (
    sort: SupplierDebitNoteSort,
): string => {
    if (filters.sort !== sort) {
        return '';
    }

    return filters.direction === 'asc'
        ? '↑'
        : '↓';
};

const goToPage = (page: number): void => {
    const meta = props.supplierDebitNotes.meta;

    if (
        page < 1
        || page > meta.last_page
        || page === meta.current_page
    ) {
        return;
    }

    router.get(
        route('supplier-debit-notes.index'),
        {
            ...queryParameters(),
            page,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

type PaginationItem =
    | number
    | 'left-ellipsis'
    | 'right-ellipsis';

const paginationItems = computed<PaginationItem[]>(
    () => {
        const current =
            props.supplierDebitNotes.meta.current_page;

        const last =
            props.supplierDebitNotes.meta.last_page;

        if (last <= 7) {
            return Array.from(
                {
                    length: last,
                },
                (_, index): number => index + 1,
            );
        }

        const items: PaginationItem[] = [1];

        if (current > 4) {
            items.push('left-ellipsis');
        }

        const start = Math.max(2, current - 1);
        const end = Math.min(
            last - 1,
            current + 1,
        );

        for (
            let page = start;
            page <= end;
            page += 1
        ) {
            items.push(page);
        }

        if (current < last - 3) {
            items.push('right-ellipsis');
        }

        items.push(last);

        return items;
    },
);

const statusClasses: Record<
    SupplierDebitNoteStatus,
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

const displayNumber = (
    supplierDebitNote: SupplierDebitNoteSummary,
): string => {
    return supplierDebitNote.debit_note_number
        ?? `Draft #${supplierDebitNote.id}`;
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

const formatAmount = (
    value: string | number,
): string => {
    const parsed = Number.parseFloat(
        String(value),
    );

    if (!Number.isFinite(parsed)) {
        return String(value);
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(parsed);
};

const allocationClasses = (
    supplierDebitNote: SupplierDebitNoteSummary,
): string => {
    const unallocated = Number.parseFloat(
        supplierDebitNote.unallocated_amount,
    );

    if (
        Number.isFinite(unallocated)
        && Math.abs(unallocated) > 0.000001
    ) {
        return 'text-amber-600 dark:text-amber-400';
    }

    return 'text-emerald-600 dark:text-emerald-400';
};

const deleteSupplierDebitNote = (
    supplierDebitNote: SupplierDebitNoteSummary,
): void => {
    const confirmed = window.confirm(
        `Delete ${displayNumber(
            supplierDebitNote,
        )}? Only an unnumbered, never-submitted draft can be deleted.`,
    );

    if (!confirmed) {
        return;
    }

    router.delete(
        route(
            'supplier-debit-notes.destroy',
            supplierDebitNote.id,
        ),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Supplier Debit Notes" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Supplier Debit Notes
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Manage supplier claims created from posted
                    Purchase Returns and track invoice
                    allocations.
                </p>
            </div>

            <Link
                v-if="props.can.create"
                :href="
                    route(
                        'supplier-debit-notes.create',
                    )
                "
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
            >
                Create Supplier Debit Note
            </Link>
        </div>

        <section
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                <div class="xl:col-span-2">
                    <label
                        for="supplier-debit-note-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Search
                    </label>

                    <input
                        id="supplier-debit-note-search"
                        v-model="filters.search"
                        type="search"
                        maxlength="160"
                        placeholder="Debit note, return, invoice, PO, receipt, supplier, reference, or reason"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        for="supplier-debit-note-branch"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Branch
                    </label>

                    <select
                        id="supplier-debit-note-branch"
                        v-model="filters.branch_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="
                            handleBranchOrSupplierChange
                        "
                    >
                        <option :value="null">
                            All branches
                        </option>

                        <option
                            v-for="branch in props.branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }}
                            ({{ branch.code }})
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier-debit-note-supplier"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier
                    </label>

                    <select
                        id="supplier-debit-note-supplier"
                        v-model="filters.supplier_id"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="
                            handleBranchOrSupplierChange
                        "
                    >
                        <option :value="null">
                            All suppliers
                        </option>

                        <option
                            v-for="supplier in props.suppliers"
                            :key="supplier.id"
                            :value="supplier.id"
                        >
                            {{ supplier.name }}
                            ({{ supplier.code }})
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier-debit-note-purchase-order"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Purchase Order
                    </label>

                    <select
                        id="supplier-debit-note-purchase-order"
                        v-model="
                            filters.purchase_order_id
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="
                            handlePurchaseOrderChange
                        "
                    >
                        <option :value="null">
                            All Purchase Orders
                        </option>

                        <option
                            v-for="purchaseOrder in availablePurchaseOrders"
                            :key="purchaseOrder.id"
                            :value="purchaseOrder.id"
                        >
                            {{
                                purchaseOrder
                                    .document_number
                                ?? `PO #${purchaseOrder.id}`
                            }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier-debit-note-goods-receipt"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Goods Receipt
                    </label>

                    <select
                        id="supplier-debit-note-goods-receipt"
                        v-model="
                            filters.goods_receipt_id
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="
                            handleGoodsReceiptChange
                        "
                    >
                        <option :value="null">
                            All Goods Receipts
                        </option>

                        <option
                            v-for="goodsReceipt in availableGoodsReceipts"
                            :key="goodsReceipt.id"
                            :value="goodsReceipt.id"
                        >
                            {{
                                goodsReceipt
                                    .receipt_number
                                ?? `GR #${goodsReceipt.id}`
                            }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier-debit-note-supplier-invoice"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier Invoice
                    </label>

                    <select
                        id="supplier-debit-note-supplier-invoice"
                        v-model="
                            filters.supplier_invoice_id
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="null">
                            All Supplier Invoices
                        </option>

                        <option
                            v-for="supplierInvoice in availableSupplierInvoices"
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
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier-debit-note-purchase-return"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Purchase Return
                    </label>

                    <select
                        id="supplier-debit-note-purchase-return"
                        v-model="
                            filters.purchase_return_id
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="null">
                            All Purchase Returns
                        </option>

                        <option
                            v-for="purchaseReturn in availablePurchaseReturns"
                            :key="purchaseReturn.id"
                            :value="purchaseReturn.id"
                        >
                            {{
                                purchaseReturn
                                    .return_number
                                ?? `Return #${purchaseReturn.id}`
                            }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier-debit-note-status"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Status
                    </label>

                    <select
                        id="supplier-debit-note-status"
                        v-model="filters.status"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="status in props.statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="supplier-debit-note-date-from"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Debit Note Date From
                    </label>

                    <input
                        id="supplier-debit-note-date-from"
                        v-model="
                            filters.debit_note_date_from
                        "
                        type="date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label
                        for="supplier-debit-note-date-to"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Debit Note Date To
                    </label>

                    <input
                        id="supplier-debit-note-date-to"
                        v-model="
                            filters.debit_note_date_to
                        "
                        type="date"
                        :min="
                            filters.debit_note_date_from
                            || undefined
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label
                        for="supplier-debit-note-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Records Per Page
                    </label>

                    <select
                        id="supplier-debit-note-per-page"
                        v-model="filters.per_page"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    >
                        <option :value="10">
                            10
                        </option>

                        <option :value="15">
                            15
                        </option>

                        <option :value="25">
                            25
                        </option>

                        <option :value="50">
                            50
                        </option>

                        <option :value="100">
                            100
                        </option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="resetFilters"
                    >
                        Clear Filters
                    </button>
                </div>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div class="overflow-x-auto">
                <table
                    class="w-full min-w-[1850px]"
                >
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-950/50 dark:text-gray-400"
                        >
                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="text-left"
                                    @click="
                                        toggleSort(
                                            'debit_note_number',
                                        )
                                    "
                                >
                                    Debit Note
                                    {{
                                        sortIndicator(
                                            'debit_note_number',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="text-left"
                                    @click="
                                        toggleSort(
                                            'debit_note_date',
                                        )
                                    "
                                >
                                    Debit / Posting Date
                                    {{
                                        sortIndicator(
                                            'debit_note_date',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="text-left"
                                    @click="
                                        toggleSort(
                                            'supplier_name',
                                        )
                                    "
                                >
                                    Supplier
                                    {{
                                        sortIndicator(
                                            'supplier_name',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="text-left"
                                    @click="
                                        toggleSort(
                                            'purchase_return_number',
                                        )
                                    "
                                >
                                    Purchase Return
                                    {{
                                        sortIndicator(
                                            'purchase_return_number',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="text-left"
                                    @click="
                                        toggleSort(
                                            'supplier_invoice_number',
                                        )
                                    "
                                >
                                    Supplier Invoice
                                    {{
                                        sortIndicator(
                                            'supplier_invoice_number',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    class="text-left"
                                    @click="
                                        toggleSort(
                                            'purchase_order_number',
                                        )
                                    "
                                >
                                    PO / Goods Receipt
                                    {{
                                        sortIndicator(
                                            'purchase_order_number',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                Branch
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Commercial Values
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                <button
                                    type="button"
                                    @click="
                                        toggleSort(
                                            'total_amount',
                                        )
                                    "
                                >
                                    Total
                                    {{
                                        sortIndicator(
                                            'total_amount',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                <button
                                    type="button"
                                    @click="
                                        toggleSort(
                                            'allocated_amount',
                                        )
                                    "
                                >
                                    Allocation
                                    {{
                                        sortIndicator(
                                            'allocated_amount',
                                        )
                                    }}
                                </button>
                            </th>

                            <th class="px-5 py-3.5">
                                <button
                                    type="button"
                                    @click="
                                        toggleSort(
                                            'status',
                                        )
                                    "
                                >
                                    Status
                                    {{
                                        sortIndicator(
                                            'status',
                                        )
                                    }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="supplierDebitNote in props.supplierDebitNotes.data"
                            :key="supplierDebitNote.id"
                            class="border-b border-gray-100 last:border-b-0 dark:border-gray-800"
                        >
                            <td class="px-5 py-4">
                                <Link
                                    v-if="
                                        supplierDebitNote
                                            .can.view
                                    "
                                    :href="
                                        route(
                                            'supplier-debit-notes.show',
                                            supplierDebitNote.id,
                                        )
                                    "
                                    class="font-medium text-gray-900 transition hover:text-brand-500 dark:text-white"
                                >
                                    {{
                                        displayNumber(
                                            supplierDebitNote,
                                        )
                                    }}
                                </Link>

                                <span
                                    v-else
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        displayNumber(
                                            supplierDebitNote,
                                        )
                                    }}
                                </span>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Created
                                    {{
                                        formatDateTime(
                                            supplierDebitNote
                                                .created_at,
                                        )
                                    }}
                                </p>

                                <p
                                    v-if="
                                        supplierDebitNote
                                            .created_by
                                    "
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    By
                                    {{
                                        supplierDebitNote
                                            .created_by.name
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatDate(
                                            supplierDebitNote
                                                .debit_note_date,
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Posting:
                                    {{
                                        formatDate(
                                            supplierDebitNote
                                                .posting_date,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        supplierDebitNote
                                            .supplier.name
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        supplierDebitNote
                                            .supplier.code
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <Link
                                    :href="
                                        route(
                                            'purchase-returns.show',
                                            supplierDebitNote
                                                .purchase_return_id,
                                        )
                                    "
                                    class="text-sm font-medium text-amber-600 transition hover:text-amber-700 dark:text-amber-400"
                                >
                                    {{
                                        supplierDebitNote
                                            .purchase_return_number
                                        ?? `Return #${supplierDebitNote.purchase_return_id}`
                                    }}
                                </Link>
                            </td>

                            <td class="px-5 py-4">
                                <Link
                                    v-if="
                                        supplierDebitNote
                                            .supplier_invoice_id
                                        !== null
                                    "
                                    :href="
                                        route(
                                            'supplier-invoices.show',
                                            supplierDebitNote
                                                .supplier_invoice_id,
                                        )
                                    "
                                    class="text-sm font-medium text-indigo-600 transition hover:text-indigo-700 dark:text-indigo-400"
                                >
                                    {{
                                        supplierDebitNote
                                            .supplier_invoice_number
                                        ?? `Invoice #${supplierDebitNote.supplier_invoice_id}`
                                    }}
                                </Link>

                                <span
                                    v-else
                                    class="text-sm text-gray-500"
                                >
                                    Unallocated supplier credit
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <Link
                                    :href="
                                        route(
                                            'purchase-orders.show',
                                            supplierDebitNote
                                                .purchase_order_id,
                                        )
                                    "
                                    class="text-sm font-medium text-brand-600 transition hover:text-brand-700 dark:text-brand-400"
                                >
                                    {{
                                        supplierDebitNote
                                            .purchase_order_number
                                        ?? `PO #${supplierDebitNote.purchase_order_id}`
                                    }}
                                </Link>

                                <p
                                    class="mt-2 text-xs text-gray-500"
                                >
                                    Goods Receipt
                                </p>

                                <Link
                                    :href="
                                        route(
                                            'goods-receipts.show',
                                            supplierDebitNote
                                                .goods_receipt_id,
                                        )
                                    "
                                    class="mt-0.5 inline-block text-xs font-medium text-brand-600 transition hover:text-brand-700 dark:text-brand-400"
                                >
                                    {{
                                        supplierDebitNote
                                            .goods_receipt_number
                                        ?? `GR #${supplierDebitNote.goods_receipt_id}`
                                    }}
                                </Link>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm text-gray-900 dark:text-white"
                                >
                                    {{
                                        supplierDebitNote
                                            .branch.name
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        supplierDebitNote
                                            .branch.code
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-right"
                            >
                                <p
                                    class="text-xs text-gray-500"
                                >
                                    Gross:
                                    {{
                                        formatAmount(
                                            supplierDebitNote
                                                .gross_amount,
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Discount:
                                    {{
                                        formatAmount(
                                            supplierDebitNote
                                                .discount_amount,
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Tax:
                                    {{
                                        formatAmount(
                                            supplierDebitNote
                                                .tax_amount,
                                        )
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-right"
                            >
                                <p
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatAmount(
                                            supplierDebitNote
                                                .total_amount,
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        supplierDebitNote
                                            .currency_code
                                    }}
                                </p>

                                <p
                                    v-if="
                                        Number.parseFloat(
                                            supplierDebitNote
                                                .exchange_rate,
                                        ) !== 1
                                    "
                                    class="mt-1 text-xs text-gray-400"
                                >
                                    Rate:
                                    {{
                                        formatAmount(
                                            supplierDebitNote
                                                .exchange_rate,
                                        )
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-right"
                            >
                                <p
                                    class="text-sm font-semibold"
                                    :class="
                                        allocationClasses(
                                            supplierDebitNote,
                                        )
                                    "
                                >
                                    {{
                                        formatAmount(
                                            supplierDebitNote
                                                .allocated_amount,
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Unallocated:
                                    {{
                                        formatAmount(
                                            supplierDebitNote
                                                .unallocated_amount,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        statusClasses[
                                            supplierDebitNote
                                                .status
                                        ]
                                    "
                                >
                                    {{
                                        supplierDebitNote
                                            .status_label
                                    }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <div
                                    class="flex justify-end gap-2"
                                >
                                    <Link
                                        v-if="
                                            supplierDebitNote
                                                .can.view
                                        "
                                        :href="
                                            route(
                                                'supplier-debit-notes.show',
                                                supplierDebitNote.id,
                                            )
                                        "
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-brand-600 transition hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-500/10"
                                    >
                                        View
                                    </Link>

                                    <Link
                                        v-if="
                                            supplierDebitNote
                                                .can.update
                                        "
                                        :href="
                                            route(
                                                'supplier-debit-notes.edit',
                                                supplierDebitNote.id,
                                            )
                                        "
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="
                                            supplierDebitNote
                                                .can.delete
                                        "
                                        type="button"
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                                        @click="
                                            deleteSupplierDebitNote(
                                                supplierDebitNote,
                                            )
                                        "
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-if="
                                props.supplierDebitNotes
                                    .data.length === 0
                            "
                        >
                            <td
                                colspan="12"
                                class="px-5 py-16 text-center"
                            >
                                <h3
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    No Supplier Debit Notes found
                                </h3>

                                <p
                                    class="mx-auto mt-2 max-w-lg text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Create a Supplier Debit Note
                                    from a posted Purchase Return,
                                    or change the current filters.
                                </p>

                                <Link
                                    v-if="props.can.create"
                                    :href="
                                        route(
                                            'supplier-debit-notes.create',
                                        )
                                    "
                                    class="mt-5 inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
                                >
                                    Create Supplier Debit Note
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="
                    props.supplierDebitNotes.meta
                        .total > 0
                "
                class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-800"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Showing
                    {{
                        props.supplierDebitNotes.meta
                            .from
                    }}
                    to
                    {{
                        props.supplierDebitNotes.meta
                            .to
                    }}
                    of
                    {{
                        props.supplierDebitNotes.meta
                            .total
                    }}
                    Supplier Debit Notes
                </p>

                <div
                    class="flex flex-wrap items-center gap-2"
                >
                    <button
                        type="button"
                        :disabled="
                            props.supplierDebitNotes.meta
                                .current_page <= 1
                        "
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            goToPage(
                                props.supplierDebitNotes
                                    .meta.current_page
                                - 1,
                            )
                        "
                    >
                        Previous
                    </button>

                    <template
                        v-for="item in paginationItems"
                        :key="item"
                    >
                        <span
                            v-if="
                                item
                                === 'left-ellipsis'
                                || item
                                === 'right-ellipsis'
                            "
                            class="px-2 py-2 text-sm text-gray-500"
                        >
                            …
                        </span>

                        <button
                            v-else
                            type="button"
                            class="min-w-10 rounded-lg border px-3 py-2 text-sm transition"
                            :class="
                                item
                                === props.supplierDebitNotes
                                    .meta.current_page
                                    ? 'border-brand-500 bg-brand-500 text-white'
                                    : 'border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800'
                            "
                            @click="goToPage(item)"
                        >
                            {{ item }}
                        </button>
                    </template>

                    <button
                        type="button"
                        :disabled="
                            props.supplierDebitNotes.meta
                                .current_page
                            >= props.supplierDebitNotes.meta
                                .last_page
                        "
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            goToPage(
                                props.supplierDebitNotes
                                    .meta.current_page
                                + 1,
                            )
                        "
                    >
                        Next
                    </button>
                </div>
            </div>
        </section>
    </div>
</template>