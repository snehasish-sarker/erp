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
    GoodsReceiptShowProps,
    GoodsReceiptStatus,
} from '@/Types/goods-receipt';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<GoodsReceiptShowProps>();

const actionInProgress = ref<string | null>(
    null,
);

const showReversalModal = ref(false);

const reversalForm = useForm({
    reversal_reason: '',
});

const documentTitle = computed(
    (): string =>
        props.goodsReceipt.receipt_number
        ?? `Draft #${props.goodsReceipt.id}`,
);

const statusClasses: Record<
    GoodsReceiptStatus,
    string
> = {
    draft:
        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

    posted:
        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400',

    reversed:
        'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400',
};

const formatNumber = (
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

const formatDate = (
    value: string | null,
): string => {
    if (!value) {
        return '—';
    }

    const parts = value.split('-');

    if (parts.length !== 3) {
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
            Number(parts[0]),
            Number(parts[1]) - 1,
            Number(parts[2]),
        ),
    );
};

const formatDateTime = (
    value: string | null,
): string => {
    if (!value) {
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

const postGoodsReceipt = (): void => {
    const confirmed = window.confirm(
        'Post this Goods Receipt? Accepted stock will update inventory and the Purchase Order. The document will become read-only.',
    );

    if (!confirmed) {
        return;
    }

    actionInProgress.value = 'post';

    router.post(
        route(
            'goods-receipts.post',
            props.goodsReceipt.id,
        ),
        {},
        {
            preserveScroll: true,

            onFinish: () => {
                actionInProgress.value = null;
            },
        },
    );
};

const deleteGoodsReceipt = (): void => {
    const confirmed = window.confirm(
        'Delete this draft Goods Receipt? This action permanently removes the draft and cannot be undone.',
    );

    if (!confirmed) {
        return;
    }

    actionInProgress.value = 'delete';

    router.delete(
        route(
            'goods-receipts.destroy',
            props.goodsReceipt.id,
        ),
        {
            preserveScroll: true,

            onFinish: () => {
                actionInProgress.value = null;
            },
        },
    );
};

const openReversalModal = (): void => {
    reversalForm.reset();
    reversalForm.clearErrors();
    showReversalModal.value = true;
};

const closeReversalModal = (): void => {
    if (reversalForm.processing) {
        return;
    }

    reversalForm.reset();
    reversalForm.clearErrors();
    showReversalModal.value = false;
};

const reverseGoodsReceipt = (): void => {
    reversalForm.post(
        route(
            'goods-receipts.reverse',
            props.goodsReceipt.id,
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                reversalForm.reset();
                showReversalModal.value = false;
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
                    class="mb-2 flex flex-wrap items-center gap-2 text-sm text-gray-500"
                >
                    <Link
                        :href="
                            route(
                                'goods-receipts.index',
                            )
                        "
                        class="hover:text-brand-500"
                    >
                        Goods Receipts
                    </Link>

                    <span>/</span>

                    <span>{{ documentTitle }}</span>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        {{ documentTitle }}
                    </h1>

                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="
                            statusClasses[
                                goodsReceipt.status
                            ]
                        "
                    >
                        {{ goodsReceipt.status_label }}
                    </span>

                    <span
                        class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-medium text-blue-700 dark:bg-blue-500/10 dark:text-blue-400"
                    >
                        {{
                            goodsReceipt.inspection_status_label
                        }}
                    </span>
                </div>

                <p class="mt-1 text-sm text-gray-500">
                    Purchase Order:
                    {{
                        goodsReceipt.purchase_order_number
                        ?? `#${goodsReceipt.purchase_order_id}`
                    }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
    <Link
        :href="
            route(
                'goods-receipts.index',
            )
        "
        class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
    >
        Back
    </Link>

    <Link
        v-if="goodsReceipt.can.update"
        :href="
            route(
                'goods-receipts.edit',
                goodsReceipt.id,
            )
        "
        class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
    >
        Edit
    </Link>

    <Link
        v-if="
            goodsReceipt
                .can_create_supplier_invoice
        "
        :href="
            route(
                'supplier-invoices.create',
                {
                    purchase_order_id:
                        goodsReceipt
                            .purchase_order_id,
                },
            )
        "
        class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-indigo-700"
    >
        Create Supplier Invoice
    </Link>

    <Link
        v-if="
            goodsReceipt
                .can_create_purchase_return
        "
        :href="
            route(
                'purchase-returns.create',
                {
                    goods_receipt_id:
                        goodsReceipt.id,
                },
            )
        "
        class="rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-amber-700"
    >
        Create Purchase Return
    </Link>

    <Link
        v-if="
            goodsReceipt
                .can_view_purchase_returns
        "
        :href="
            route(
                'purchase-returns.index',
                {
                    goods_receipt_id:
                        goodsReceipt.id,
                },
            )
        "
        class="rounded-lg border border-amber-300 bg-white px-4 py-2.5 text-sm font-medium text-amber-700 transition hover:bg-amber-50 dark:border-amber-500/40 dark:bg-gray-900 dark:text-amber-400 dark:hover:bg-amber-500/10"
    >
        View Purchase Returns
    </Link>

    <Link
    v-if="
        goodsReceipt
            .can_view_supplier_debit_notes
    "
    :href="
        route(
            'supplier-debit-notes.index',
            {
                goods_receipt_id:
                    goodsReceipt.id,
            },
        )
    "
    class="rounded-lg border border-violet-300 bg-white px-4 py-2.5 text-sm font-medium text-violet-700 transition hover:bg-violet-50 dark:border-violet-500/40 dark:bg-gray-900 dark:text-violet-400 dark:hover:bg-violet-500/10"
>
    Supplier Debit Notes
</Link>

    <button
        v-if="goodsReceipt.can.delete"
        type="button"
        :disabled="
            actionInProgress !== null
        "
        class="rounded-lg border border-red-300 bg-white px-4 py-2.5 text-sm font-medium text-red-600 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-red-500/40 dark:bg-gray-900 dark:text-red-400 dark:hover:bg-red-500/10"
        @click="deleteGoodsReceipt"
    >
        {{
            actionInProgress === 'delete'
                ? 'Deleting...'
                : 'Delete Draft'
        }}
    </button>

    <button
        v-if="goodsReceipt.can.post"
        type="button"
        :disabled="
            actionInProgress !== null
        "
        class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
        @click="postGoodsReceipt"
    >
        {{
            actionInProgress === 'post'
                ? 'Posting...'
                : 'Post Receipt'
        }}
    </button>

    <button
        v-if="goodsReceipt.can.reverse"
        type="button"
        :disabled="
            actionInProgress !== null
        "
        class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
        @click="openReversalModal"
    >
        Reverse Receipt
    </button>
</div>
        </div>

        <div
            v-if="goodsReceipt.status === 'reversed'"
            class="rounded-2xl border border-red-200 bg-red-50 p-5 dark:border-red-500/30 dark:bg-red-500/10"
        >
            <h2
                class="font-semibold text-red-800 dark:text-red-300"
            >
                Goods Receipt Reversed
            </h2>

            <p
                class="mt-2 whitespace-pre-line text-sm text-red-700 dark:text-red-400"
            >
                {{
                    goodsReceipt.reversal_reason
                    ?? 'No reversal reason was recorded.'
                }}
            </p>

            <p class="mt-3 text-xs text-red-600">
                Reversed by
                {{
                    goodsReceipt.reversed_by?.name
                    ?? 'Unknown user'
                }}
                on
                {{
                    formatDateTime(
                        goodsReceipt.reversed_at,
                    )
                }}
            </p>
        </div>

        <div
            class="grid grid-cols-1 gap-6 xl:grid-cols-3"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 xl:col-span-2"
            >
                <h2
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Receipt Information
                </h2>

                <dl
                    class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2"
                >
                    <div>
                        <dt class="text-xs uppercase text-gray-500">
                            Receipt Date
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    goodsReceipt.receipt_date,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase text-gray-500">
                            Supplier Delivery Note
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                goodsReceipt.supplier_delivery_note
                                ?? '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase text-gray-500">
                            Branch
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{ goodsReceipt.branch.name }}
                            ({{ goodsReceipt.branch.code }})
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase text-gray-500">
                            Warehouse
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                goodsReceipt.warehouse
                                    ? `${goodsReceipt.warehouse.name} (${goodsReceipt.warehouse.code})`
                                    : '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase text-gray-500">
                            Supplier
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{ goodsReceipt.supplier_name }}
                            ({{ goodsReceipt.supplier_code }})
                        </dd>
                    </div>

                    <div>
                        <dt class="text-xs uppercase text-gray-500">
                            Inspection
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-900 dark:text-white"
                        >
                            {{
                                goodsReceipt.inspection_status_label
                            }}
                        </dd>
                    </div>

                    <div class="sm:col-span-2">
                        <dt class="text-xs uppercase text-gray-500">
                            Notes
                        </dt>

                        <dd
                            class="mt-1 whitespace-pre-line text-sm text-gray-900 dark:text-white"
                        >
                            {{ goodsReceipt.notes ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <h2
                    class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Receipt Totals
                </h2>

                <div class="space-y-4">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">
                            Received
                        </span>

                        <span
                            class="font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatNumber(
                                    goodsReceipt.total_received_quantity,
                                )
                            }}
                        </span>
                    </div>

                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">
                            Accepted
                        </span>

                        <span
                            class="font-medium text-emerald-600"
                        >
                            {{
                                formatNumber(
                                    goodsReceipt.total_accepted_quantity,
                                )
                            }}
                        </span>
                    </div>

                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">
                            Rejected
                        </span>

                        <span
                            class="font-medium text-red-600"
                        >
                            {{
                                formatNumber(
                                    goodsReceipt.total_rejected_quantity,
                                )
                            }}
                        </span>
                    </div>

                    <div
                        class="border-t border-gray-200 pt-4 dark:border-gray-800"
                    >
                        <div class="flex justify-between">
                            <span
                                class="font-semibold text-gray-900 dark:text-white"
                            >
                                Inventory Value
                            </span>

                            <span
                                class="font-bold text-gray-900 dark:text-white"
                            >
                                {{
                                    goodsReceipt.purchase_order
                                        .currency_code
                                }}
                                {{
                                    formatNumber(
                                        goodsReceipt.total_inventory_value,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="border-b border-gray-200 p-5 dark:border-gray-800"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Receipt Lines
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1500px]">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500 dark:border-gray-800 dark:bg-gray-950/50"
                        >
                            <th class="px-5 py-3.5">
                                Product
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Ordered
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Previously Received
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Accepted
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Rejected
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Unit Cost
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Total Cost
                            </th>

                            <th class="px-5 py-3.5">
                                Batch / Expiry
                            </th>

                            <th class="px-5 py-3.5">
                                Storage
                            </th>

                            <th class="px-5 py-3.5">
                                Serials / Variance
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="line in goodsReceipt.lines"
                            :key="line.id"
                            class="border-b border-gray-100 align-top last:border-b-0 dark:border-gray-800"
                        >
                            <td class="px-5 py-4">
                                <p
                                    class="text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{ line.product_name }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{ line.product_sku }}
                                    · {{ line.unit_code }}
                                    · {{ line.product_type }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm"
                            >
                                {{
                                    formatNumber(
                                        line.ordered_quantity_snapshot,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm"
                            >
                                {{
                                    formatNumber(
                                        line.previously_received_quantity_snapshot,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-medium text-emerald-600"
                            >
                                {{
                                    formatNumber(
                                        line.accepted_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-medium text-red-600"
                            >
                                {{
                                    formatNumber(
                                        line.rejected_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm"
                            >
                                {{
                                    formatNumber(
                                        line.unit_cost,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-semibold"
                            >
                                {{
                                    formatNumber(
                                        line.total_cost,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4 text-sm">
                                <p>
                                    Batch:
                                    {{
                                        line.batch_number
                                        ?? '—'
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    MFG:
                                    {{
                                        formatDate(
                                            line.manufacturing_date,
                                        )
                                    }}
                                    · EXP:
                                    {{
                                        formatDate(
                                            line.expiry_date,
                                        )
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-sm">
                                {{
                                    line.storage_location
                                    ?? '—'
                                }}
                            </td>

                            <td class="px-5 py-4 text-sm">
                                <p>
                                    Serials:
                                    {{
                                        line.serial_numbers.length
                                    }}
                                </p>

                                <p
                                    v-if="line.variance_reason"
                                    class="mt-2 max-w-xs whitespace-pre-line text-xs text-gray-500"
                                >
                                    {{
                                        line.variance_reason
                                    }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="
                goodsReceipt.stock_ledger_entries
                    .length > 0
            "
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="border-b border-gray-200 p-5 dark:border-gray-800"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Stock Ledger Entries
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px]">
                    <thead>
                        <tr
                            class="border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500 dark:border-gray-800 dark:bg-gray-950/50"
                        >
                            <th class="px-5 py-3.5">
                                Date
                            </th>

                            <th class="px-5 py-3.5">
                                Movement
                            </th>

                            <th class="px-5 py-3.5">
                                Product
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                In
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Out
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Unit Cost
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Balance Qty
                            </th>

                            <th
                                class="px-5 py-3.5 text-right"
                            >
                                Balance Value
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="
                                entry in goodsReceipt.stock_ledger_entries
                            "
                            :key="entry.id"
                            class="border-b border-gray-100 last:border-b-0 dark:border-gray-800"
                        >
                            <td class="px-5 py-4 text-sm">
                                {{
                                    formatDateTime(
                                        entry.occurred_at,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-sm capitalize"
                            >
                                {{
                                    entry.movement_type.replace(
                                        /_/g,
                                        ' ',
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <p
                                    class="text-sm font-medium"
                                >
                                    {{
                                        entry.product_name
                                    }}
                                </p>

                                <p
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        entry.product_sku
                                    }}
                                    · {{
                                        entry.unit_code
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-emerald-600"
                            >
                                {{
                                    formatNumber(
                                        entry.quantity_in,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-red-600"
                            >
                                {{
                                    formatNumber(
                                        entry.quantity_out,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm"
                            >
                                {{
                                    formatNumber(
                                        entry.unit_cost,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm"
                            >
                                {{
                                    formatNumber(
                                        entry.balance_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm"
                            >
                                {{
                                    formatNumber(
                                        entry.balance_value,
                                    )
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <h2
                class="mb-5 text-lg font-semibold text-gray-900 dark:text-white"
            >
                Workflow History
            </h2>

            <div class="space-y-4">
                <p
                    class="text-sm text-gray-600 dark:text-gray-300"
                >
                    Created by
                    {{
                        goodsReceipt.created_by?.name
                        ?? 'Unknown user'
                    }}
                    on
                    {{
                        formatDateTime(
                            goodsReceipt.created_at,
                        )
                    }}
                </p>

                <p
                    v-if="goodsReceipt.posted_at"
                    class="text-sm text-emerald-700 dark:text-emerald-400"
                >
                    Posted by
                    {{
                        goodsReceipt.posted_by?.name
                        ?? 'Unknown user'
                    }}
                    on
                    {{
                        formatDateTime(
                            goodsReceipt.posted_at,
                        )
                    }}
                </p>

                <p
                    v-if="goodsReceipt.reversed_at"
                    class="text-sm text-red-700 dark:text-red-400"
                >
                    Reversed by
                    {{
                        goodsReceipt.reversed_by?.name
                        ?? 'Unknown user'
                    }}
                    on
                    {{
                        formatDateTime(
                            goodsReceipt.reversed_at,
                        )
                    }}
                </p>
            </div>
        </div>
    </div>

    <div
        v-if="showReversalModal"
        class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-950/60 p-4"
        @click.self="closeReversalModal"
    >
        <div
            class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-900"
        >
            <h2
                class="text-lg font-semibold text-gray-900 dark:text-white"
            >
                Reverse Goods Receipt
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Reversal removes the accepted stock and
                reduces the Purchase Order received
                quantities. It is blocked when the stock
                is no longer available.
            </p>

            <form
                class="mt-5"
                @submit.prevent="reverseGoodsReceipt"
            >
                <label
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Reversal Reason
                    <span class="text-red-500">
                        *
                    </span>
                </label>

                <textarea
                    v-model="
                        reversalForm.reversal_reason
                    "
                    rows="5"
                    maxlength="500"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                />

                <p
                    v-if="
                        reversalForm.errors
                            .reversal_reason
                    "
                    class="mt-1 text-sm text-red-600"
                >
                    {{
                        reversalForm.errors
                            .reversal_reason
                    }}
                </p>

                <div
                    class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
                >
                    <button
                        type="button"
                        :disabled="
                            reversalForm.processing
                        "
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm dark:border-gray-700"
                        @click="
                            closeReversalModal
                        "
                    >
                        Keep Receipt
                    </button>

                    <button
                        type="submit"
                        :disabled="
                            reversalForm.processing
                        "
                        class="rounded-lg bg-red-600 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
                    >
                        {{
                            reversalForm.processing
                                ? 'Reversing...'
                                : 'Confirm Reversal'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>