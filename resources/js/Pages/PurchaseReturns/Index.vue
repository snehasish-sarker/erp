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
    PurchaseReturnFilters,
    PurchaseReturnGoodsReceiptFilterOption,
    PurchaseReturnIndexProps,
    PurchaseReturnPurchaseOrderFilterOption,
    PurchaseReturnSort,
    PurchaseReturnStatus,
    PurchaseReturnSummary,
    PurchaseReturnSupplierInvoiceFilterOption,
    PurchaseReturnWarehouseOption,
} from '@/Types/purchase-return';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<PurchaseReturnIndexProps>();

const filters = reactive<PurchaseReturnFilters>({
    search:
        props.filters.search ?? '',

    branch_id:
        props.filters.branch_id ?? null,

    warehouse_id:
        props.filters.warehouse_id ?? null,

    supplier_id:
        props.filters.supplier_id ?? null,

    purchase_order_id:
        props.filters.purchase_order_id ?? null,

    goods_receipt_id:
        props.filters.goods_receipt_id ?? null,

    supplier_invoice_id:
        props.filters.supplier_invoice_id
        ?? null,

    status:
        props.filters.status ?? '',

    return_date_from:
        props.filters.return_date_from ?? '',

    return_date_to:
        props.filters.return_date_to ?? '',

    sort:
        props.filters.sort ?? 'created_at',

    direction:
        props.filters.direction ?? 'desc',

    per_page:
        props.filters.per_page ?? 15,
});

const availableWarehouses = computed<
    PurchaseReturnWarehouseOption[]
>(() => {
    if (filters.branch_id === null) {
        return props.warehouses;
    }

    return props.warehouses.filter(
        (
            warehouse,
        ): boolean =>
            warehouse.branch_id
            === filters.branch_id,
    );
});

const availablePurchaseOrders = computed<
    PurchaseReturnPurchaseOrderFilterOption[]
>(() => {
    return props.purchaseOrders.filter(
        (
            purchaseOrder,
        ): boolean => {
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
    PurchaseReturnGoodsReceiptFilterOption[]
>(() => {
    return props.goodsReceipts.filter(
        (
            goodsReceipt,
        ): boolean => {
            if (
                filters.branch_id !== null
                && goodsReceipt.branch_id
                    !== filters.branch_id
            ) {
                return false;
            }

            if (
                filters.warehouse_id !== null
                && goodsReceipt.warehouse_id
                    !== filters.warehouse_id
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
                filters.purchase_order_id
                    !== null
                && goodsReceipt
                    .purchase_order_id
                    !== filters
                        .purchase_order_id
            ) {
                return false;
            }

            return true;
        },
    );
});

const availableSupplierInvoices = computed<
    PurchaseReturnSupplierInvoiceFilterOption[]
>(() => {
    return props.supplierInvoices.filter(
        (
            supplierInvoice,
        ): boolean => {
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
                filters.purchase_order_id
                    !== null
                && supplierInvoice
                    .purchase_order_id
                    !== filters
                        .purchase_order_id
            ) {
                return false;
            }

            return true;
        },
    );
});

const hasActiveFilters = computed(
    (): boolean => {
        return filters.search !== ''
            || filters.branch_id !== null
            || filters.warehouse_id !== null
            || filters.supplier_id !== null
            || filters.purchase_order_id
                !== null
            || filters.goods_receipt_id
                !== null
            || filters.supplier_invoice_id
                !== null
            || filters.status !== ''
            || filters.return_date_from !== ''
            || filters.return_date_to !== '';
    },
);

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
        query.search =
            filters.search.trim();
    }

    if (filters.branch_id !== null) {
        query.branch_id =
            filters.branch_id;
    }

    if (filters.warehouse_id !== null) {
        query.warehouse_id =
            filters.warehouse_id;
    }

    if (filters.supplier_id !== null) {
        query.supplier_id =
            filters.supplier_id;
    }

    if (
        filters.purchase_order_id !== null
    ) {
        query.purchase_order_id =
            filters.purchase_order_id;
    }

    if (
        filters.goods_receipt_id !== null
    ) {
        query.goods_receipt_id =
            filters.goods_receipt_id;
    }

    if (
        filters.supplier_invoice_id
        !== null
    ) {
        query.supplier_invoice_id =
            filters.supplier_invoice_id;
    }

    if (filters.status !== '') {
        query.status = filters.status;
    }

    if (
        filters.return_date_from !== ''
    ) {
        query.return_date_from =
            filters.return_date_from;
    }

    if (
        filters.return_date_to !== ''
    ) {
        query.return_date_to =
            filters.return_date_to;
    }

    return query;
};

const applyFilters = (): void => {
    router.get(
        route('purchase-returns.index'),
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

watch(
    () => filters.search,
    () => {
        if (searchTimer !== null) {
            clearTimeout(searchTimer);
        }

        searchTimer = setTimeout(
            applyFilters,
            400,
        );
    },
);

onBeforeUnmount(() => {
    if (searchTimer !== null) {
        clearTimeout(searchTimer);
    }
});

const selectedOptionExists = <T extends {
    id: number;
}>(
    options: T[],
    selectedId: number | null,
): boolean => {
    if (selectedId === null) {
        return true;
    }

    return options.some(
        (
            option,
        ): boolean =>
            option.id === selectedId,
    );
};

const normalizeDependentFilters =
    (): void => {
        if (
            !selectedOptionExists(
                availableWarehouses.value,
                filters.warehouse_id,
            )
        ) {
            filters.warehouse_id = null;
        }

        if (
            !selectedOptionExists(
                availablePurchaseOrders.value,
                filters.purchase_order_id,
            )
        ) {
            filters.purchase_order_id =
                null;
        }

        if (
            !selectedOptionExists(
                availableGoodsReceipts.value,
                filters.goods_receipt_id,
            )
        ) {
            filters.goods_receipt_id =
                null;
        }

        if (
            !selectedOptionExists(
                availableSupplierInvoices
                    .value,
                filters.supplier_invoice_id,
            )
        ) {
            filters.supplier_invoice_id =
                null;
        }
    };

const handleBranchChange = (): void => {
    normalizeDependentFilters();
    applyFilters();
};

const handleWarehouseChange = (): void => {
    if (
        !selectedOptionExists(
            availableGoodsReceipts.value,
            filters.goods_receipt_id,
        )
    ) {
        filters.goods_receipt_id = null;
    }

    applyFilters();
};

const handleSupplierChange = (): void => {
    normalizeDependentFilters();
    applyFilters();
};

const handlePurchaseOrderChange =
    (): void => {
        if (
            !selectedOptionExists(
                availableGoodsReceipts.value,
                filters.goods_receipt_id,
            )
        ) {
            filters.goods_receipt_id = null;
        }

        if (
            !selectedOptionExists(
                availableSupplierInvoices
                    .value,
                filters.supplier_invoice_id,
            )
        ) {
            filters.supplier_invoice_id =
                null;
        }

        applyFilters();
    };

const resetFilters = (): void => {
    filters.search = '';
    filters.branch_id = null;
    filters.warehouse_id = null;
    filters.supplier_id = null;
    filters.purchase_order_id = null;
    filters.goods_receipt_id = null;
    filters.supplier_invoice_id = null;
    filters.status = '';
    filters.return_date_from = '';
    filters.return_date_to = '';
    filters.sort = 'created_at';
    filters.direction = 'desc';
    filters.per_page = 15;

    if (searchTimer !== null) {
        clearTimeout(searchTimer);
        searchTimer = null;
    }

    applyFilters();
};

const toggleSort = (
    sort: PurchaseReturnSort,
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
    sort: PurchaseReturnSort,
): string => {
    if (filters.sort !== sort) {
        return '';
    }

    return filters.direction === 'asc'
        ? '↑'
        : '↓';
};

const goToPage = (
    page: number,
): void => {
    const meta =
        props.purchaseReturns.meta;

    if (
        page < 1
        || page > meta.last_page
        || page === meta.current_page
    ) {
        return;
    }

    router.get(
        route('purchase-returns.index'),
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

const paginationItems = computed<
    PaginationItem[]
>(() => {
    const current =
        props.purchaseReturns.meta
            .current_page;

    const last =
        props.purchaseReturns.meta
            .last_page;

    if (last <= 7) {
        return Array.from(
            {
                length: last,
            },
            (
                _,
                index,
            ): number => index + 1,
        );
    }

    const items: PaginationItem[] = [1];

    if (current > 4) {
        items.push('left-ellipsis');
    }

    const start = Math.max(
        2,
        current - 1,
    );

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
});

const statusClasses: Record<
    PurchaseReturnStatus,
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
    purchaseReturn: PurchaseReturnSummary,
): string => {
    return purchaseReturn.return_number
        ?? `Draft #${purchaseReturn.id}`;
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

const formatQuantity = (
    value: string,
): string => {
    const parsed = Number.parseFloat(value);

    if (!Number.isFinite(parsed)) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        },
    ).format(parsed);
};

const formatAmount = (
    value: string,
): string => {
    const parsed = Number.parseFloat(value);

    if (!Number.isFinite(parsed)) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(parsed);
};

const varianceClasses = (
    value: string,
): string => {
    const parsed = Number.parseFloat(value);

    if (
        !Number.isFinite(parsed)
        || Math.abs(parsed) <= 0.000001
    ) {
        return 'text-gray-500 dark:text-gray-400';
    }

    return parsed > 0
        ? 'text-amber-600 dark:text-amber-400'
        : 'text-blue-600 dark:text-blue-400';
};

const deletePurchaseReturn = (
    purchaseReturn: PurchaseReturnSummary,
): void => {
    const confirmed = window.confirm(
        `Delete ${displayNumber(
            purchaseReturn,
        )}? Only an unnumbered, never-submitted draft can be deleted.`,
    );

    if (!confirmed) {
        return;
    }

    router.delete(
        route(
            'purchase-returns.destroy',
            purchaseReturn.id,
        ),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="Purchase Returns" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-900 dark:text-white"
                >
                    Purchase Returns
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Manage supplier returns against posted
                    Goods Receipts and track inventory
                    valuation differences.
                </p>
            </div>

            <Link
                v-if="props.can.create"
                :href="
                    route(
                        'purchase-returns.create',
                    )
                "
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
            >
                Create Purchase Return
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
                        for="purchase-return-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Search
                    </label>

                    <input
                        id="purchase-return-search"
                        v-model="filters.search"
                        type="search"
                        maxlength="160"
                        placeholder="Return, receipt, PO, invoice, supplier, reference, or reason"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        for="purchase-return-branch-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Branch
                    </label>

                    <select
                        id="purchase-return-branch-filter"
                        v-model.number="
                            filters.branch_id
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="handleBranchChange"
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
                        for="purchase-return-warehouse-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Warehouse
                    </label>

                    <select
                        id="purchase-return-warehouse-filter"
                        v-model.number="
                            filters.warehouse_id
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="
                            handleWarehouseChange
                        "
                    >
                        <option :value="null">
                            All warehouses
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
                </div>

                <div>
                    <label
                        for="purchase-return-supplier-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier
                    </label>

                    <select
                        id="purchase-return-supplier-filter"
                        v-model.number="
                            filters.supplier_id
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="handleSupplierChange"
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
                        for="purchase-return-po-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Purchase Order
                    </label>

                    <select
                        id="purchase-return-po-filter"
                        v-model.number="
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
                        for="purchase-return-gr-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Goods Receipt
                    </label>

                    <select
                        id="purchase-return-gr-filter"
                        v-model.number="
                            filters.goods_receipt_id
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
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
                        for="purchase-return-invoice-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Supplier Invoice
                    </label>

                    <select
                        id="purchase-return-invoice-filter"
                        v-model.number="
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
                        for="purchase-return-status-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Status
                    </label>

                    <select
                        id="purchase-return-status-filter"
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
                        for="purchase-return-date-from"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Return Date From
                    </label>

                    <input
                        id="purchase-return-date-from"
                        v-model="
                            filters.return_date_from
                        "
                        type="date"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label
                        for="purchase-return-date-to"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Return Date To
                    </label>

                    <input
                        id="purchase-return-date-to"
                        v-model="
                            filters.return_date_to
                        "
                        type="date"
                        :min="
                            filters.return_date_from
                            || undefined
                        "
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                        @change="applyFilters"
                    />
                </div>

                <div>
                    <label
                        for="purchase-return-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Records Per Page
                    </label>

                    <select
                        id="purchase-return-per-page"
                        v-model.number="
                            filters.per_page
                        "
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
                    class="w-full min-w-[1750px]"
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
                                            'return_number',
                                        )
                                    "
                                >
                                    Purchase Return
                                    {{
                                        sortIndicator(
                                            'return_number',
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
                                            'return_date',
                                        )
                                    "
                                >
                                    Return / Posting Date
                                    {{
                                        sortIndicator(
                                            'return_date',
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
                                            'goods_receipt_number',
                                        )
                                    "
                                >
                                    Goods Receipt
                                    {{
                                        sortIndicator(
                                            'goods_receipt_number',
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
                                    Purchase Order
                                    {{
                                        sortIndicator(
                                            'purchase_order_number',
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
                                Branch / Warehouse
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                <button
                                    type="button"
                                    @click="
                                        toggleSort(
                                            'total_return_quantity',
                                        )
                                    "
                                >
                                    Return Quantity
                                    {{
                                        sortIndicator(
                                            'total_return_quantity',
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
                                            'total_supplier_value',
                                        )
                                    "
                                >
                                    Supplier Value
                                    {{
                                        sortIndicator(
                                            'total_supplier_value',
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
                                            'total_inventory_value',
                                        )
                                    "
                                >
                                    Inventory / Variance
                                    {{
                                        sortIndicator(
                                            'total_inventory_value',
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
                            v-for="purchaseReturn in props.purchaseReturns.data"
                            :key="purchaseReturn.id"
                            class="border-b border-gray-100 last:border-b-0 dark:border-gray-800"
                        >
                            <td class="px-5 py-4">
                                <Link
                                    v-if="
                                        purchaseReturn
                                            .can.view
                                    "
                                    :href="
                                        route(
                                            'purchase-returns.show',
                                            purchaseReturn.id,
                                        )
                                    "
                                    class="font-medium text-gray-900 transition hover:text-brand-500 dark:text-white"
                                >
                                    {{
                                        displayNumber(
                                            purchaseReturn,
                                        )
                                    }}
                                </Link>

                                <span
                                    v-else
                                    class="font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        displayNumber(
                                            purchaseReturn,
                                        )
                                    }}
                                </span>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Created
                                    {{
                                        formatDateTime(
                                            purchaseReturn
                                                .created_at,
                                        )
                                    }}
                                </p>

                                <p
                                    v-if="
                                        purchaseReturn
                                            .created_by
                                    "
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    By
                                    {{
                                        purchaseReturn
                                            .created_by
                                            ?.name
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatDate(
                                            purchaseReturn
                                                .return_date,
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Posting:
                                    {{
                                        formatDate(
                                            purchaseReturn
                                                .posting_date,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <Link
                                    :href="
                                        route(
                                            'goods-receipts.show',
                                            purchaseReturn
                                                .goods_receipt_id,
                                        )
                                    "
                                    class="text-sm font-medium text-brand-600 transition hover:text-brand-700 dark:text-brand-400"
                                >
                                    {{
                                        purchaseReturn
                                            .goods_receipt_number
                                        ?? `GR #${purchaseReturn.goods_receipt_id}`
                                    }}
                                </Link>

                                <div
                                    v-if="
                                        purchaseReturn
                                            .supplier_invoice_id
                                        !== null
                                    "
                                    class="mt-2"
                                >
                                    <p
                                        class="text-xs text-gray-500"
                                    >
                                        Supplier Invoice
                                    </p>

                                    <Link
                                        :href="
                                            route(
                                                'supplier-invoices.show',
                                                purchaseReturn
                                                    .supplier_invoice_id,
                                            )
                                        "
                                        class="mt-0.5 inline-block text-xs font-medium text-indigo-600 transition hover:text-indigo-700 dark:text-indigo-400"
                                    >
                                        {{
                                            purchaseReturn
                                                .supplier_invoice_number
                                            ?? `Invoice #${purchaseReturn.supplier_invoice_id}`
                                        }}
                                    </Link>
                                </div>

                                <p
                                    v-else
                                    class="mt-2 text-xs text-gray-500"
                                >
                                    No linked Supplier Invoice
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <Link
                                    :href="
                                        route(
                                            'purchase-orders.show',
                                            purchaseReturn
                                                .purchase_order_id,
                                        )
                                    "
                                    class="text-sm font-medium text-brand-600 transition hover:text-brand-700 dark:text-brand-400"
                                >
                                    {{
                                        purchaseReturn
                                            .purchase_order_number
                                        ?? `PO #${purchaseReturn.purchase_order_id}`
                                    }}
                                </Link>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        purchaseReturn
                                            .supplier.name
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        purchaseReturn
                                            .supplier.code
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm text-gray-900 dark:text-white"
                                >
                                    {{
                                        purchaseReturn
                                            .branch.name
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        purchaseReturn
                                            .warehouse?.name
                                        ?? 'No warehouse'
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-400"
                                >
                                    {{
                                        purchaseReturn
                                            .branch.code
                                    }}
                                    <template
                                        v-if="
                                            purchaseReturn
                                                .warehouse
                                        "
                                    >
                                        ·
                                        {{
                                            purchaseReturn
                                                .warehouse
                                                ?.code
                                        }}
                                    </template>
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-right"
                            >
                                <p
                                    class="text-sm font-semibold text-gray-900 dark:text-white"
                                >
                                    {{
                                        formatQuantity(
                                            purchaseReturn
                                                .total_return_quantity,
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
                                            purchaseReturn
                                                .total_supplier_value,
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Receipt commercial cost
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
                                            purchaseReturn
                                                .total_inventory_value,
                                        )
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs"
                                    :class="
                                        varianceClasses(
                                            purchaseReturn
                                                .total_cost_variance,
                                        )
                                    "
                                >
                                    Variance:
                                    {{
                                        formatAmount(
                                            purchaseReturn
                                                .total_cost_variance,
                                        )
                                    }}
                                </p>

                                <p
                                    v-if="
                                        purchaseReturn.status
                                            !== 'posted'
                                        && purchaseReturn.status
                                            !== 'reversed'
                                    "
                                    class="mt-1 text-xs text-gray-400"
                                >
                                    Calculated at posting
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        statusClasses[
                                            purchaseReturn
                                                .status
                                        ]
                                    "
                                >
                                    {{
                                        purchaseReturn
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
                                            purchaseReturn
                                                .can.view
                                        "
                                        :href="
                                            route(
                                                'purchase-returns.show',
                                                purchaseReturn.id,
                                            )
                                        "
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-brand-600 transition hover:bg-brand-50 dark:text-brand-400 dark:hover:bg-brand-500/10"
                                    >
                                        View
                                    </Link>

                                    <Link
                                        v-if="
                                            purchaseReturn
                                                .can.update
                                        "
                                        :href="
                                            route(
                                                'purchase-returns.edit',
                                                purchaseReturn.id,
                                            )
                                        "
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="
                                            purchaseReturn
                                                .can.delete
                                        "
                                        type="button"
                                        class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10"
                                        @click="
                                            deletePurchaseReturn(
                                                purchaseReturn,
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
                                props.purchaseReturns
                                    .data.length === 0
                            "
                        >
                            <td
                                colspan="11"
                                class="px-5 py-16 text-center"
                            >
                                <h3
                                    class="font-semibold text-gray-900 dark:text-white"
                                >
                                    No Purchase Returns found
                                </h3>

                                <p
                                    class="mx-auto mt-2 max-w-lg text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Create a return against a
                                    posted Goods Receipt with
                                    remaining accepted quantity,
                                    or change the current filters.
                                </p>

                                <Link
                                    v-if="props.can.create"
                                    :href="
                                        route(
                                            'purchase-returns.create',
                                        )
                                    "
                                    class="mt-5 inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
                                >
                                    Create Purchase Return
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="
                    props.purchaseReturns.meta
                        .total > 0
                "
                class="flex flex-col gap-4 border-t border-gray-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between dark:border-gray-800"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Showing
                    {{
                        props.purchaseReturns.meta
                            .from
                    }}
                    to
                    {{
                        props.purchaseReturns.meta
                            .to
                    }}
                    of
                    {{
                        props.purchaseReturns.meta
                            .total
                    }}
                    Purchase Returns
                </p>

                <div
                    class="flex flex-wrap items-center gap-2"
                >
                    <button
                        type="button"
                        :disabled="
                            props.purchaseReturns.meta
                                .current_page <= 1
                        "
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            goToPage(
                                props.purchaseReturns
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
                                === props.purchaseReturns
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
                            props.purchaseReturns.meta
                                .current_page
                            >= props.purchaseReturns.meta
                                .last_page
                        "
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            goToPage(
                                props.purchaseReturns
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