<script setup lang="ts">
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/vue3';
import {
    computed,
    ref,
} from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    SalesOrderAllocationFormLine,
    SalesOrderAllocationPageLine,
    SalesOrderAllocationProps,
    SalesOrderAllocationStatus,
    SalesOrderStatus,
} from '@/Types/sales-order';

defineOptions({
    layout: ErpLayout,
});

const props =
    defineProps<SalesOrderAllocationProps>();

const allocationForm = useForm<{
    notes: string;
    lines: SalesOrderAllocationFormLine[];
}>({
    notes:
        props.activeAllocation?.notes
        ?? '',

    lines:
        props.salesOrder.lines.map(
            (
                line,
            ): SalesOrderAllocationFormLine => ({
                sales_order_line_id:
                    line.id,

                allocated_quantity:
                    line.product_type
                        === 'stock'
                            ? line
                                .allocated_quantity
                            : line
                                .ordered_quantity,
            }),
        ),
});

const showReleaseModal = ref(false);

const releaseForm = useForm({
    release_reason: '',
});

const orderTitle = computed(
    (): string => {
        return props.salesOrder
            .document_number
            ?? `Sales Order #${props.salesOrder.id}`;
    },
);

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

const formatQuantity = (
    value: string | number,
): string => {
    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        },
    ).format(
        decimalValue(value),
    );
};

const formatDate = (
    value: string | null,
): string => {
    if (
        value === null
        || value === ''
    ) {
        return '—';
    }

    const date = new Date(
        `${value}T00:00:00`,
    );

    if (
        Number.isNaN(
            date.getTime(),
        )
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
    ).format(date);
};

const formatDateTime = (
    value: string | null,
): string => {
    if (
        value === null
        || value === ''
    ) {
        return '—';
    }

    const date = new Date(value);

    if (
        Number.isNaN(
            date.getTime(),
        )
    ) {
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

const statusClasses = (
    status: SalesOrderStatus,
): string => {
    const classes:
        Record<SalesOrderStatus, string> = {
            draft:
                'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',

            submitted:
                'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',

            approved:
                'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-300',

            partially_allocated:
                'bg-warning-50 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300',

            allocated:
                'bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',

            partially_dispatched:
                'bg-orange-50 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',

            dispatched:
                'bg-cyan-50 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-300',

            partially_invoiced:
                'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300',

            invoiced:
                'bg-brand-50 text-brand-700 dark:bg-brand-900/30 dark:text-brand-300',

            closed:
                'bg-success-100 text-success-800 dark:bg-success-900/50 dark:text-success-200',

            cancelled:
                'bg-error-50 text-error-700 dark:bg-error-900/30 dark:text-error-300',
        };

    return classes[status];
};

const allocationStatusClasses = (
    status: SalesOrderAllocationStatus,
): string => {
    if (status === 'active') {
        return 'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-300';
    }

    if (status === 'superseded') {
        return 'bg-warning-50 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300';
    }

    return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
};

const formLine = (
    lineId: number,
): SalesOrderAllocationFormLine
    | undefined => {
    return allocationForm.lines.find(
        (line) =>
            line.sales_order_line_id
            === lineId,
    );
};

const setFullQuantity = (
    line: SalesOrderAllocationPageLine,
): void => {
    const target = formLine(line.id);

    if (
        target === undefined
        || line.product_type !== 'stock'
    ) {
        return;
    }

    target.allocated_quantity =
        line.maximum_allocatable_quantity;
};

const clearQuantity = (
    line: SalesOrderAllocationPageLine,
): void => {
    const target = formLine(line.id);

    if (
        target === undefined
        || line.product_type !== 'stock'
    ) {
        return;
    }

    target.allocated_quantity =
        '0.000000';
};

const requestedByProduct = computed(
    (): Map<number, number> => {
        const totals =
            new Map<number, number>();

        props.salesOrder.lines.forEach(
            (line) => {
                if (
                    line.product_type
                    !== 'stock'
                ) {
                    return;
                }

                const target =
                    formLine(line.id);

                const current =
                    totals.get(
                        line.product_id,
                    ) ?? 0;

                totals.set(
                    line.product_id,

                    current
                    + decimalValue(
                        target
                            ?.allocated_quantity,
                    ),
                );
            },
        );

        return totals;
    },
);

const availabilityWarning = (
    line: SalesOrderAllocationPageLine,
): string | null => {
    if (
        line.product_type
        !== 'stock'
    ) {
        return null;
    }

    const requested =
        requestedByProduct.value.get(
            line.product_id,
        ) ?? 0;

    const available = decimalValue(
        line.quantity_available_to_order,
    );

    if (
        requested
        > available + 0.0000001
    ) {
        return `Total requested for this product is ${formatQuantity(requested)}, but only ${formatQuantity(available)} is available after other reservations.`;
    }

    return null;
};

const hasAvailabilityConflict =
    computed(
        (): boolean => {
            return props.salesOrder.lines
                .some(
                    (line) =>
                        availabilityWarning(
                            line,
                        ) !== null,
                );
        },
    );

const totalOrdered = computed(
    (): number => {
        return props.salesOrder.lines
            .reduce(
                (total, line) =>
                    total
                    + decimalValue(
                        line.ordered_quantity,
                    ),
                0,
            );
    },
);

const totalProposed = computed(
    (): number => {
        return allocationForm.lines
            .reduce(
                (total, line) =>
                    total
                    + decimalValue(
                        line
                            .allocated_quantity,
                    ),
                0,
            );
    },
);

const totalCurrent = computed(
    (): number => {
        return props.salesOrder.lines
            .reduce(
                (total, line) =>
                    total
                    + decimalValue(
                        line
                            .allocated_quantity,
                    ),
                0,
            );
    },
);

const submitAllocation = (): void => {
    if (
        hasAvailabilityConflict.value
    ) {
        return;
    }

    allocationForm.put(
        route(
            'sales-orders.allocation.store',
            props.salesOrder.id,
        ),
        {
            preserveScroll: true,
        },
    );
};

const openReleaseModal = (): void => {
    releaseForm.reset();
    releaseForm.clearErrors();

    showReleaseModal.value = true;
};

const closeReleaseModal = (): void => {
    if (releaseForm.processing) {
        return;
    }

    showReleaseModal.value = false;

    releaseForm.reset();
    releaseForm.clearErrors();
};

const releaseAllocation = (): void => {
    releaseForm.post(
        route(
            'sales-orders.allocation.release',
            props.salesOrder.id,
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                showReleaseModal.value =
                    false;

                releaseForm.reset();
            },
        },
    );
};
</script>

<template>
    <Head
        :title="`Allocate ${orderTitle}`"
    />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between"
        >
            <div>
                <div
                    class="mb-2 flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    <Link
                        :href="
                            route(
                                'sales-orders.index',
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        Sales Orders
                    </Link>

                    <span>/</span>

                    <Link
                        :href="
                            route(
                                'sales-orders.show',
                                salesOrder.id,
                            )
                        "
                        class="transition hover:text-brand-500"
                    >
                        {{ orderTitle }}
                    </Link>

                    <span>/</span>

                    <span
                        class="text-gray-700 dark:text-gray-300"
                    >
                        Allocation
                    </span>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3"
                >
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        Inventory Allocation
                    </h1>

                    <span
                        :class="[
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                            statusClasses(
                                salesOrder.status,
                            ),
                        ]"
                    >
                        {{
                            salesOrder.status_label
                        }}
                    </span>
                </div>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Reserve stock atomically for
                    {{ salesOrder.customer_name }}.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="
                        route(
                            'sales-orders.show',
                            salesOrder.id,
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    View Sales Order
                </Link>

                <button
                    v-if="salesOrder.can.release"
                    type="button"
                    class="inline-flex items-center justify-center rounded-lg border border-error-300 bg-white px-4 py-2.5 text-sm font-medium text-error-600 transition hover:bg-error-50 dark:border-error-900 dark:bg-gray-900 dark:hover:bg-error-900/20"
                    @click="openReleaseModal"
                >
                    Release Allocation
                </button>
            </div>
        </div>

        <div
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Order
                </p>

                <p
                    class="mt-2 font-semibold text-gray-900 dark:text-white"
                >
                    {{ orderTitle }}
                </p>

                <p
                    class="mt-1 text-sm text-gray-500"
                >
                    {{
                        formatDate(
                            salesOrder.order_date,
                        )
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Location
                </p>

                <p
                    class="mt-2 font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        salesOrder.branch?.name
                        ?? '—'
                    }}
                </p>

                <p
                    class="mt-1 text-sm text-gray-500"
                >
                    {{
                        salesOrder.warehouse
                            ?.name
                        ?? 'No warehouse'
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Current Allocation
                </p>

                <p
                    class="mt-2 text-xl font-semibold text-gray-900 dark:text-white"
                >
                    {{
                        formatQuantity(
                            totalCurrent,
                        )
                    }}
                </p>

                <p
                    class="mt-1 text-sm text-gray-500"
                >
                    {{
                        activeAllocation
                            ? `Revision ${activeAllocation.revision}`
                            : 'No active allocation'
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <p
                    class="text-xs font-medium uppercase tracking-wide text-gray-500"
                >
                    Proposed Allocation
                </p>

                <p
                    class="mt-2 text-xl font-semibold text-brand-600 dark:text-brand-400"
                >
                    {{
                        formatQuantity(
                            totalProposed,
                        )
                    }}
                    /
                    {{
                        formatQuantity(
                            totalOrdered,
                        )
                    }}
                </p>

                <p
                    class="mt-1 text-sm text-gray-500"
                >
                    Across all order lines
                </p>
            </div>
        </div>

        <div
            v-if="activeAllocation"
            class="rounded-xl border border-success-200 bg-success-50 px-4 py-3 text-sm text-success-700 dark:border-success-900/50 dark:bg-success-900/20 dark:text-success-300"
        >
            Allocation revision
            {{ activeAllocation.revision }}
            is active. Saving creates a new
            revision and supersedes the current
            reservation atomically.
        </div>

        <form
            class="space-y-6"
            @submit.prevent="submitAllocation"
        >
            <div
                class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
            >
                <div
                    class="border-b border-gray-200 p-5 dark:border-gray-800 sm:p-6"
                >
                    <h2
                        class="text-lg font-semibold text-gray-900 dark:text-white"
                    >
                        Allocation Lines
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Available quantity equals
                        on-hand stock minus reservations
                        belonging to other orders. The
                        current order reservation is
                        included so it can be retained
                        or changed.
                    </p>
                </div>

                <div
                    v-if="
                        allocationForm.errors.lines
                    "
                    class="m-5 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-900/50 dark:bg-error-900/20 dark:text-error-300 sm:m-6"
                >
                    {{
                        allocationForm.errors.lines
                    }}
                </div>

                <div class="overflow-x-auto">
                    <table
                        class="min-w-[1180px] divide-y divide-gray-200 dark:divide-gray-800"
                    >
                        <thead
                            class="bg-gray-50 dark:bg-white/[0.03]"
                        >
                            <tr>
                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    Line / Product
                                </th>

                                <th
                                    class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    Ordered
                                </th>

                                <th
                                    class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    On Hand
                                </th>

                                <th
                                    class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    Other Reserved
                                </th>

                                <th
                                    class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    Available
                                </th>

                                <th
                                    class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    Current
                                </th>

                                <th
                                    class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                                >
                                    Proposed Allocation
                                </th>
                            </tr>
                        </thead>

                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-800"
                        >
                            <tr
                                v-for="(
                                    line,
                                    lineIndex
                                ) in salesOrder.lines"
                                :key="line.id"
                            >
                                <td class="px-5 py-4">
                                    <p
                                        class="font-medium text-gray-900 dark:text-white"
                                    >
                                        {{
                                            line.line_number
                                        }}.
                                        {{
                                            line.product_name
                                        }}
                                    </p>

                                    <p
                                        class="mt-1 text-xs text-gray-500"
                                    >
                                        {{
                                            line.product_sku
                                        }}
                                        ·
                                        {{
                                            line.unit_code
                                        }}
                                        ·
                                        {{
                                            line.product_type
                                                .replace(
                                                    /_/g,
                                                    ' ',
                                                )
                                        }}
                                    </p>

                                    <p
                                        v-if="
                                            line.product_type
                                                !== 'stock'
                                        "
                                        class="mt-2 text-xs text-brand-600 dark:text-brand-400"
                                    >
                                        No inventory
                                        reservation is
                                        required. The full
                                        ordered quantity is
                                        allocated
                                        operationally.
                                    </p>
                                </td>

                                <td
                                    class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatQuantity(
                                            line.ordered_quantity,
                                        )
                                    }}
                                </td>

                                <td
                                    class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        line.product_type
                                            === 'stock'
                                            ? formatQuantity(
                                                line.quantity_on_hand,
                                            )
                                            : '—'
                                    }}
                                </td>

                                <td
                                    class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        line.product_type
                                            === 'stock'
                                            ? formatQuantity(
                                                line.quantity_reserved_other,
                                            )
                                            : '—'
                                    }}
                                </td>

                                <td
                                    class="whitespace-nowrap px-5 py-4 text-right text-sm font-medium text-gray-900 dark:text-white"
                                >
                                    {{
                                        line.product_type
                                            === 'stock'
                                            ? formatQuantity(
                                                line.quantity_available_to_order,
                                            )
                                            : formatQuantity(
                                                line.ordered_quantity,
                                            )
                                    }}
                                </td>

                                <td
                                    class="whitespace-nowrap px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        formatQuantity(
                                            line.allocated_quantity,
                                        )
                                    }}
                                </td>

                                <td class="px-5 py-4">
                                    <div
                                        class="flex min-w-72 items-center gap-2"
                                    >
                                        <input
                                            v-model="
                                                allocationForm
                                                    .lines[
                                                        lineIndex
                                                    ]
                                                    .allocated_quantity
                                            "
                                            :readonly="
                                                line.product_type
                                                    !== 'stock'
                                            "
                                            :max="
                                                line.maximum_allocatable_quantity
                                            "
                                            min="0"
                                            step="0.000001"
                                            type="number"
                                            :class="[
                                                'h-11 w-40 rounded-lg border border-gray-300 px-3 text-right text-sm text-gray-800 outline-none transition focus:border-brand-500 dark:border-gray-700 dark:text-white',
                                                line.product_type
                                                    !== 'stock'
                                                    ? 'bg-gray-50 dark:bg-gray-800'
                                                    : 'bg-transparent',
                                            ]"
                                        />

                                        <button
                                            v-if="
                                                line.product_type
                                                    === 'stock'
                                            "
                                            type="button"
                                            class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                            @click="
                                                setFullQuantity(
                                                    line,
                                                )
                                            "
                                        >
                                            Full
                                        </button>

                                        <button
                                            v-if="
                                                line.product_type
                                                    === 'stock'
                                            "
                                            type="button"
                                            class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                            @click="
                                                clearQuantity(
                                                    line,
                                                )
                                            "
                                        >
                                            Zero
                                        </button>
                                    </div>

                                    <p
                                        v-if="
                                            availabilityWarning(
                                                line,
                                            )
                                        "
                                        class="mt-2 max-w-sm text-xs text-error-500"
                                    >
                                        {{
                                            availabilityWarning(
                                                line,
                                            )
                                        }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <label
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Allocation Notes
                </label>

                <textarea
                    v-model="allocationForm.notes"
                    rows="4"
                    maxlength="4000"
                    placeholder="Optional warehouse, fulfillment, or priority notes"
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:text-white"
                />

                <p
                    v-if="
                        allocationForm.errors.notes
                    "
                    class="mt-1 text-xs text-error-500"
                >
                    {{
                        allocationForm.errors.notes
                    }}
                </p>
            </div>

            <div
                class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
            >
                <Link
                    :href="
                        route(
                            'sales-orders.show',
                            salesOrder.id,
                        )
                    "
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Cancel
                </Link>

                <button
                    v-if="
                        salesOrder.can.allocate
                    "
                    :disabled="
                        allocationForm.processing
                        || hasAvailabilityConflict
                    "
                    type="submit"
                    class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{
                        allocationForm.processing
                            ? 'Saving Allocation...'
                            : activeAllocation
                                ? 'Save New Allocation Revision'
                                : 'Save Allocation'
                    }}
                </button>
            </div>
        </form>

        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="border-b border-gray-200 p-5 dark:border-gray-800 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Allocation History
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    The latest 20 revisions are
                    shown.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-gray-200 dark:divide-gray-800"
                >
                    <thead
                        class="bg-gray-50 dark:bg-white/[0.03]"
                    >
                        <tr>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Revision
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Status
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Created
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Released / Superseded
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Notes / Reason
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-if="
                                history.length === 0
                            "
                        >
                            <td
                                colspan="5"
                                class="px-5 py-10 text-center text-sm text-gray-500"
                            >
                                No allocation revisions
                                have been created.
                            </td>
                        </tr>

                        <tr
                            v-for="allocation in history"
                            :key="allocation.id"
                        >
                            <td
                                class="px-5 py-4 font-medium text-gray-900 dark:text-white"
                            >
                                {{
                                    allocation.revision
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    :class="[
                                        'inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize',
                                        allocationStatusClasses(
                                            allocation.status,
                                        ),
                                    ]"
                                >
                                    {{
                                        allocation.status
                                    }}
                                </span>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <p>
                                    {{
                                        allocation.created_by
                                            ?.name
                                        ?? '—'
                                    }}
                                </p>

                                <p
                                    class="text-xs text-gray-500"
                                >
                                    {{
                                        formatDateTime(
                                            allocation.created_at,
                                        )
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <p>
                                    {{
                                        allocation.released_by
                                            ?.name
                                        ?? '—'
                                    }}
                                </p>

                                <p
                                    class="text-xs text-gray-500"
                                >
                                    {{
                                        formatDateTime(
                                            allocation.released_at,
                                        )
                                    }}
                                </p>
                            </td>

                            <td
                                class="max-w-md px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    allocation.release_reason
                                    ?? allocation.notes
                                    ?? '—'
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="showReleaseModal"
            class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-950/60 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="release-allocation-title"
            @click.self="closeReleaseModal"
        >
            <form
                class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900"
                @submit.prevent="
                    releaseAllocation
                "
            >
                <h2
                    id="release-allocation-title"
                    class="text-xl font-semibold text-gray-900 dark:text-white"
                >
                    Release Active Allocation
                </h2>

                <p
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    This removes all open
                    reservations for the order and
                    returns its status to Approved.
                    The action is blocked after any
                    reservation has been consumed
                    by dispatch.
                </p>

                <div class="mt-5">
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Release Reason

                        <span
                            class="text-error-500"
                        >
                            *
                        </span>
                    </label>

                    <textarea
                        v-model="
                            releaseForm.release_reason
                        "
                        rows="4"
                        maxlength="500"
                        autofocus
                        placeholder="Explain why the allocation is being released"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-error-500 dark:border-gray-700 dark:text-white"
                    />

                    <div
                        class="mt-1 flex justify-between gap-3"
                    >
                        <p
                            class="text-xs text-error-500"
                        >
                            {{
                                releaseForm.errors
                                    .release_reason
                            }}
                        </p>

                        <p
                            class="text-xs text-gray-400"
                        >
                            {{
                                releaseForm
                                    .release_reason
                                    .length
                            }}/500
                        </p>
                    </div>
                </div>

                <div
                    class="mt-6 flex justify-end gap-3"
                >
                    <button
                        :disabled="
                            releaseForm.processing
                        "
                        type="button"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                        @click="
                            closeReleaseModal
                        "
                    >
                        Keep Allocation
                    </button>

                    <button
                        :disabled="
                            releaseForm.processing
                            || releaseForm
                                .release_reason
                                .trim()
                                === ''
                        "
                        type="submit"
                        class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-error-600 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{
                            releaseForm.processing
                                ? 'Releasing...'
                                : 'Confirm Release'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>