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
    CustomerDispatchShowProps,
    CustomerDispatchStatus,
} from '@/Types/dispatch';

defineOptions({
    layout: ErpLayout,
});

const props =
    defineProps<CustomerDispatchShowProps>();

const processing =
    ref<string | null>(null);

const showReverseModal =
    ref(false);

const reverseForm = useForm({
    reversal_reason: '',
});

const title = computed(
    (): string =>
        props.dispatch.dispatch_number
        ?? `Dispatch Draft #${props.dispatch.id}`,
);

const statusClass = (
    status: CustomerDispatchStatus,
): string => {
    if (status === 'posted') {
        return 'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-300';
    }

    if (status === 'reversed') {
        return 'bg-error-50 text-error-700 dark:bg-error-900/30 dark:text-error-300';
    }

    return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
};

const formatDate = (
    value: string | null,
): string => {
    if (!value) {
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
    if (!value) {
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

const formatQuantity = (
    value: string,
): string => {
    const number =
        Number.parseFloat(value);

    if (!Number.isFinite(number)) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 6,
        },
    ).format(number);
};

const formatAmount = (
    value: string,
): string => {
    const number =
        Number.parseFloat(value);

    if (!Number.isFinite(number)) {
        return value;
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(number);
};

const totalQuantity = computed(
    (): number => {
        return props.dispatch.lines
            .reduce(
                (total, line) =>
                    total
                    + Number.parseFloat(
                        line.dispatched_quantity
                        || '0',
                    ),
                0,
            );
    },
);

const totalCost = computed(
    (): number => {
        return props.dispatch.lines
            .reduce(
                (total, line) =>
                    total
                    + Number.parseFloat(
                        line.total_cost
                        || '0',
                    ),
                0,
            );
    },
);

const postDispatch = (): void => {
    if (
        !window.confirm(
            'Post this dispatch? Stock lines will consume reservations, reduce inventory, and create immutable stock-ledger entries.',
        )
    ) {
        return;
    }

    processing.value = 'post';

    router.post(
        route(
            'dispatches.post',
            props.dispatch.id,
        ),
        {},
        {
            preserveScroll: true,

            onFinish: () => {
                processing.value = null;
            },
        },
    );
};

const removeDispatch = (): void => {
    if (
        !window.confirm(
            'Delete this dispatch draft?',
        )
    ) {
        return;
    }

    processing.value = 'delete';

    router.delete(
        route(
            'dispatches.destroy',
            props.dispatch.id,
        ),
        {
            preserveScroll: true,

            onFinish: () => {
                processing.value = null;
            },
        },
    );
};

const openReverseModal = (): void => {
    reverseForm.reset();
    reverseForm.clearErrors();

    showReverseModal.value = true;
};

const closeReverseModal = (): void => {
    if (reverseForm.processing) {
        return;
    }

    showReverseModal.value = false;

    reverseForm.reset();
    reverseForm.clearErrors();
};

const reverseDispatch = (): void => {
    reverseForm.post(
        route(
            'dispatches.reverse',
            props.dispatch.id,
        ),
        {
            preserveScroll: true,

            onSuccess: () => {
                showReverseModal.value =
                    false;

                reverseForm.reset();
            },
        },
    );
};
</script>

<template>
    <Head :title="title" />

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
                                'dispatches.index',
                            )
                        "
                        class="hover:text-brand-500"
                    >
                        Dispatches
                    </Link>

                    <span>/</span>

                    <span
                        class="text-gray-700 dark:text-gray-300"
                    >
                        {{ title }}
                    </span>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3"
                >
                    <h1
                        class="text-2xl font-semibold text-gray-900 dark:text-white"
                    >
                        {{ title }}
                    </h1>

                    <span
                        :class="[
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                            statusClass(
                                dispatch.status,
                            ),
                        ]"
                    >
                        {{
                            dispatch.status_label
                        }}
                    </span>
                </div>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Sales Order
                    {{ dispatch.sales_order_number }}
                    · Allocation revision
                    {{ dispatch.allocation_revision }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="
                        route(
                            'dispatches.index',
                        )
                    "
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Back
                </Link>

                <a
                    v-if="dispatch.can.print"
                    :href="
                        route(
                            'dispatches.print',
                            dispatch.id,
                        )
                    "
                    target="_blank"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
                >
                    Print Delivery Note
                </a>

                <Link
                    v-if="dispatch.can.update"
                    :href="
                        route(
                            'dispatches.edit',
                            dispatch.id,
                        )
                    "
                    class="rounded-lg border border-brand-300 bg-white px-4 py-2.5 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-800 dark:bg-gray-900 dark:text-brand-400 dark:hover:bg-brand-900/20"
                >
                    Edit
                </Link>

                <button
                    v-if="dispatch.can.delete"
                    :disabled="
                        processing !== null
                    "
                    type="button"
                    class="rounded-lg border border-error-300 bg-white px-4 py-2.5 text-sm font-medium text-error-600 hover:bg-error-50 disabled:opacity-60 dark:border-error-900 dark:bg-gray-900 dark:hover:bg-error-900/20"
                    @click="removeDispatch"
                >
                    {{
                        processing === 'delete'
                            ? 'Deleting...'
                            : 'Delete'
                    }}
                </button>
            </div>
        </div>

        <div
            v-if="
                dispatch.can.post
                || dispatch.can.reverse
            "
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <h2
                        class="font-semibold text-gray-900 dark:text-white"
                    >
                        Workflow Actions
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Posting issues inventory. Reversal
                        restores stock and the original
                        reservation.
                    </p>
                </div>

                <div
                    class="flex flex-wrap gap-2"
                >
                    <button
                        v-if="dispatch.can.post"
                        :disabled="
                            processing !== null
                        "
                        type="button"
                        class="rounded-lg bg-success-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-success-700 disabled:opacity-60"
                        @click="postDispatch"
                    >
                        {{
                            processing === 'post'
                                ? 'Posting...'
                                : 'Post Dispatch'
                        }}
                    </button>

                    <button
                        v-if="
                            dispatch.can.reverse
                        "
                        type="button"
                        class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-error-600"
                        @click="
                            openReverseModal
                        "
                    >
                        Reverse Dispatch
                    </button>
                </div>
            </div>
        </div>

        <div
            v-if="
                dispatch.status === 'reversed'
            "
            class="rounded-xl border border-error-200 bg-error-50 px-4 py-4 text-sm text-error-700 dark:border-error-900/50 dark:bg-error-900/20 dark:text-error-300"
        >
            <p class="font-semibold">
                Dispatch Reversed
            </p>

            <p class="mt-1">
                {{
                    dispatch.reversal_reason
                    ?? 'No reason recorded.'
                }}
            </p>

            <p class="mt-2 text-xs">
                {{
                    dispatch.reversed_by
                        ?.name
                    ?? 'Unknown user'
                }}
                ·
                {{
                    formatDateTime(
                        dispatch.reversed_at,
                    )
                }}
            </p>
        </div>

        <div
            class="grid gap-6 xl:grid-cols-3"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6 xl:col-span-2"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Dispatch Details
                </h2>

                <dl
                    class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Dispatch Date
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatDate(
                                    dispatch.dispatch_date,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Sales Order
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                dispatch.sales_order_number
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Allocation Revision
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                dispatch.allocation_revision
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Branch
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                dispatch.branch
                                    ?.name
                                ?? '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Warehouse
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                dispatch.warehouse
                                    ?.name
                                ?? 'No warehouse'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Tracking
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                dispatch.tracking_number
                                ?? '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Carrier
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                dispatch.carrier_name
                                ?? '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Vehicle
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                dispatch.vehicle_number
                                ?? '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Total Quantity
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                formatQuantity(
                                    String(
                                        totalQuantity,
                                    ),
                                )
                            }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Customer
                </h2>

                <dl class="mt-5 space-y-4">
                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Name
                        </dt>

                        <dd
                            class="mt-1 text-sm font-medium text-gray-900 dark:text-white"
                        >
                            {{
                                dispatch.customer_name
                            }}
                        </dd>

                        <dd
                            class="text-xs text-gray-500"
                        >
                            {{
                                dispatch.customer_code
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Contact
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{
                                dispatch.customer_contact_person
                                ?? '—'
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Phone
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-700 dark:text-gray-300"
                        >
                            {{
                                dispatch.customer_phone
                                ?? '—'
                            }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div
            class="grid gap-6 lg:grid-cols-2"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Shipping Address
                </h2>

                <p
                    class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300"
                >
                    {{
                        dispatch.shipping_address
                        ?? '—'
                    }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Delivery Instructions
                </h2>

                <p
                    class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300"
                >
                    {{
                        dispatch.delivery_instructions
                        ?? '—'
                    }}
                </p>
            </div>
        </div>

        <div
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
        >
            <div
                class="border-b border-gray-200 p-5 dark:border-gray-800 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Dispatch Lines
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table
                    class="min-w-[900px] divide-y divide-gray-200 dark:divide-gray-800"
                >
                    <thead
                        class="bg-gray-50 dark:bg-white/[0.03]"
                    >
                        <tr>
                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Product
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Quantity
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Unit Cost
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Issue Cost
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Ledger
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="line in dispatch.lines"
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
                                    class="text-xs text-gray-500"
                                >
                                    {{
                                        line.product_sku
                                    }}
                                    ·
                                    {{ line.unit_code }}
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
                                        line.description
                                    "
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    {{
                                        line.description
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatQuantity(
                                        line.dispatched_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    line.product_type
                                        === 'stock'
                                        ? formatAmount(
                                            line.unit_cost,
                                        )
                                        : '—'
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    line.product_type
                                        === 'stock'
                                        ? formatAmount(
                                            line.total_cost,
                                        )
                                        : '—'
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                <p>
                                    {{
                                        line.stock_ledger_entry_id
                                            ? `Issue #${line.stock_ledger_entry_id}`
                                            : '—'
                                    }}
                                </p>

                                <p
                                    v-if="
                                        line.reversal_stock_ledger_entry_id
                                    "
                                    class="text-xs text-error-500"
                                >
                                    Reversal #
                                    {{
                                        line.reversal_stock_ledger_entry_id
                                    }}
                                </p>
                            </td>
                        </tr>
                    </tbody>

                    <tfoot
                        v-if="
                            dispatch.status
                                !== 'draft'
                        "
                        class="bg-gray-50 dark:bg-white/[0.03]"
                    >
                        <tr>
                            <td
                                colspan="3"
                                class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                Total Inventory Issue Cost
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    formatAmount(
                                        String(
                                            totalCost,
                                        ),
                                    )
                                }}
                            </td>

                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div
            class="grid gap-6 lg:grid-cols-2"
        >
            <div
                v-if="dispatch.notes"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Internal Notes
                </h2>

                <p
                    class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-700 dark:text-gray-300"
                >
                    {{ dispatch.notes }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Workflow History
                </h2>

                <dl
                    class="mt-5 grid gap-5 sm:grid-cols-3"
                >
                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Created
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-800 dark:text-gray-200"
                        >
                            {{
                                dispatch.created_by
                                    ?.name
                                ?? '—'
                            }}
                        </dd>

                        <dd
                            class="text-xs text-gray-500"
                        >
                            {{
                                formatDateTime(
                                    dispatch.created_at,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Posted
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-800 dark:text-gray-200"
                        >
                            {{
                                dispatch.posted_by
                                    ?.name
                                ?? '—'
                            }}
                        </dd>

                        <dd
                            class="text-xs text-gray-500"
                        >
                            {{
                                formatDateTime(
                                    dispatch.posted_at,
                                )
                            }}
                        </dd>
                    </div>

                    <div>
                        <dt
                            class="text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            Reversed
                        </dt>

                        <dd
                            class="mt-1 text-sm text-gray-800 dark:text-gray-200"
                        >
                            {{
                                dispatch.reversed_by
                                    ?.name
                                ?? '—'
                            }}
                        </dd>

                        <dd
                            class="text-xs text-gray-500"
                        >
                            {{
                                formatDateTime(
                                    dispatch.reversed_at,
                                )
                            }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>

    <Teleport to="body">
        <div
            v-if="showReverseModal"
            class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-950/60 p-4"
            role="dialog"
            aria-modal="true"
            @click.self="
                closeReverseModal
            "
        >
            <form
                class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900"
                @submit.prevent="
                    reverseDispatch
                "
            >
                <h2
                    class="text-xl font-semibold text-gray-900 dark:text-white"
                >
                    Reverse Dispatch
                </h2>

                <p
                    class="mt-2 text-sm text-gray-500 dark:text-gray-400"
                >
                    Reversal is blocked when invoice
                    activity or later stock movements
                    exist.
                </p>

                <div class="mt-5">
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Reversal Reason
                        <span
                            class="text-error-500"
                        >
                            *
                        </span>
                    </label>

                    <textarea
                        v-model="
                            reverseForm.reversal_reason
                        "
                        rows="4"
                        maxlength="500"
                        autofocus
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-error-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            reverseForm.errors
                                .reversal_reason
                        }}
                    </p>
                </div>

                <div
                    class="mt-6 flex justify-end gap-3"
                >
                    <button
                        :disabled="
                            reverseForm.processing
                        "
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                        @click="
                            closeReverseModal
                        "
                    >
                        Keep Dispatch
                    </button>

                    <button
                        :disabled="
                            reverseForm.processing
                            || reverseForm
                                .reversal_reason
                                .trim()
                                === ''
                        "
                        type="submit"
                        class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-error-600 disabled:opacity-60"
                    >
                        {{
                            reverseForm.processing
                                ? 'Reversing...'
                                : 'Confirm Reversal'
                        }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>