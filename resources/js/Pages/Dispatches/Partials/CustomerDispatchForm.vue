<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import { computed } from 'vue';

import type {
    CustomerDispatchFormData,
    CustomerDispatchFormLine,
    DispatchableSalesOrder,
    ExistingCustomerDispatchFormData,
} from '@/Types/dispatch';

interface Props {
    salesOrder:
        DispatchableSalesOrder;

    dispatch?:
        ExistingCustomerDispatchFormData;

    defaultDispatchDate?: string;
}

const props = defineProps<Props>();

const existingByLine =
    new Map<
        number,
        CustomerDispatchFormLine
    >(
        (
            props.dispatch?.lines
            ?? []
        ).map(
            (line) => [
                line.sales_order_line_id,
                line,
            ],
        ),
    );

const form =
    useForm<CustomerDispatchFormData>({
        sales_order_id:
            props.salesOrder.id,

        dispatch_date:
            props.dispatch
                ?.dispatch_date
            ?? props.defaultDispatchDate
            ?? '',

        shipping_address:
            props.dispatch
                ?.shipping_address
            ?? props.salesOrder
                .shipping_address
            ?? '',

        delivery_instructions:
            props.dispatch
                ?.delivery_instructions
            ?? props.salesOrder
                .delivery_instructions
            ?? '',

        carrier_name:
            props.dispatch
                ?.carrier_name
            ?? '',

        vehicle_number:
            props.dispatch
                ?.vehicle_number
            ?? '',

        tracking_number:
            props.dispatch
                ?.tracking_number
            ?? '',

        notes:
            props.dispatch?.notes
            ?? '',

        lines:
            props.salesOrder.lines
                .map(
                    (
                        line,
                    ): CustomerDispatchFormLine => {
                        const existing =
                            existingByLine.get(
                                line.id,
                            );

                        return {
                            id:
                                existing?.id,

                            sales_order_line_id:
                                line.id,

                            dispatched_quantity:
                                existing
                                    ?.dispatched_quantity
                                ?? line
                                    .remaining_dispatchable_quantity,

                            description:
                                existing
                                    ?.description
                                ?? line.description
                                ?? '',
                        };
                    },
                ),
    });

const isEditing = computed(
    (): boolean =>
        props.dispatch !== undefined,
);

const decimal = (
    value: string,
): number => {
    const parsed =
        Number.parseFloat(value);

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
        typeof value === 'number'
            ? value
            : decimal(value),
    );
};

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

const selectedLineCount = computed(
    (): number => {
        return form.lines.filter(
            (line) =>
                decimal(
                    line.dispatched_quantity,
                ) > 0,
        ).length;
    },
);

const totalQuantity = computed(
    (): number => {
        return form.lines.reduce(
            (total, line) =>
                total
                + Math.max(
                    decimal(
                        line.dispatched_quantity,
                    ),
                    0,
                ),
            0,
        );
    },
);

const setMaximum = (
    index: number,
): void => {
    const source =
        props.salesOrder.lines[index];

    const target =
        form.lines[index];

    if (source && target) {
        target.dispatched_quantity =
            source
                .remaining_dispatchable_quantity;
    }
};

const clearLine = (
    index: number,
): void => {
    const target = form.lines[index];

    if (target) {
        target.dispatched_quantity =
            '0.000000';
    }
};

const submit = (): void => {
    if (props.dispatch) {
        form.put(
            route(
                'dispatches.update',
                props.dispatch.id,
            ),
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        route('dispatches.store'),
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
        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
        >
            <div class="mb-5">
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Dispatch Information
                </h2>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Delivery note for
                    {{ salesOrder.document_number }}
                    and
                    {{ salesOrder.customer_name }}.
                </p>
            </div>

            <div
                class="grid gap-5 md:grid-cols-2 xl:grid-cols-3"
            >
                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Dispatch Date
                        <span class="text-error-500">
                            *
                        </span>
                    </label>

                    <input
                        v-model="form.dispatch_date"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />

                    <p
                        v-if="
                            form.errors.dispatch_date
                        "
                        class="mt-1 text-xs text-error-500"
                    >
                        {{
                            form.errors.dispatch_date
                        }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Carrier
                    </label>

                    <input
                        v-model="form.carrier_name"
                        type="text"
                        maxlength="160"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Vehicle Number
                    </label>

                    <input
                        v-model="
                            form.vehicle_number
                        "
                        type="text"
                        maxlength="80"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>

                <div>
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Tracking Number
                    </label>

                    <input
                        v-model="
                            form.tracking_number
                        "
                        type="text"
                        maxlength="120"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>

                <div class="md:col-span-2">
                    <label
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                    >
                        Shipping Address
                    </label>

                    <textarea
                        v-model="
                            form.shipping_address
                        "
                        rows="3"
                        maxlength="4000"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>

                <div
                    class="md:col-span-2 xl:col-span-3"
                >
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
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                    />
                </div>
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

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Quantities cannot exceed the remaining
                    active allocation.
                </p>
            </div>

            <p
                v-if="form.errors.lines"
                class="px-5 pt-4 text-sm text-error-500 sm:px-6"
            >
                {{ form.errors.lines }}
            </p>

            <div class="overflow-x-auto">
                <table
                    class="min-w-[1100px] divide-y divide-gray-200 dark:divide-gray-800"
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
                                Ordered
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Allocated
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Already Dispatched
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                Remaining
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"
                            >
                                This Dispatch
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="(
                                line,
                                index
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
                                        line.reservation_outstanding_quantity
                                        !== null
                                    "
                                    class="mt-1 text-xs text-brand-600 dark:text-brand-400"
                                >
                                    Reservation outstanding:
                                    {{
                                        formatQuantity(
                                            line.reservation_outstanding_quantity,
                                        )
                                    }}
                                </p>
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatQuantity(
                                        line.ordered_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatQuantity(
                                        line.allocated_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{
                                    formatQuantity(
                                        line.already_dispatched_quantity,
                                    )
                                }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white"
                            >
                                {{
                                    formatQuantity(
                                        line.remaining_dispatchable_quantity,
                                    )
                                }}
                            </td>

                            <td class="px-5 py-4">
                                <div
                                    class="flex min-w-72 items-center gap-2"
                                >
                                    <input
                                        v-model="
                                            form.lines[
                                                index
                                            ]
                                            .dispatched_quantity
                                        "
                                        :max="
                                            line.remaining_dispatchable_quantity
                                        "
                                        min="0"
                                        step="0.000001"
                                        type="number"
                                        class="h-11 w-40 rounded-lg border border-gray-300 bg-transparent px-3 text-right text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                                    />

                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                        @click="
                                            setMaximum(
                                                index,
                                            )
                                        "
                                    >
                                        Full
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                        @click="
                                            clearLine(
                                                index,
                                            )
                                        "
                                    >
                                        Zero
                                    </button>
                                </div>

                                <p
                                    v-if="
                                        fieldError(
                                            `lines.${index}.dispatched_quantity`,
                                        )
                                    "
                                    class="mt-1 text-xs text-error-500"
                                >
                                    {{
                                        fieldError(
                                            `lines.${index}.dispatched_quantity`,
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
            class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]"
        >
            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <label
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                    Internal Notes
                </label>

                <textarea
                    v-model="form.notes"
                    rows="5"
                    maxlength="4000"
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white"
                />
            </div>

            <div
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900 sm:p-6"
            >
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-white"
                >
                    Summary
                </h2>

                <dl
                    class="mt-5 space-y-4 text-sm"
                >
                    <div
                        class="flex justify-between gap-4"
                    >
                        <dt class="text-gray-500">
                            Selected Lines
                        </dt>

                        <dd
                            class="font-semibold text-gray-900 dark:text-white"
                        >
                            {{ selectedLineCount }}
                        </dd>
                    </div>

                    <div
                        class="flex justify-between gap-4"
                    >
                        <dt class="text-gray-500">
                            Total Quantity
                        </dt>

                        <dd
                            class="font-semibold text-brand-600 dark:text-brand-400"
                        >
                            {{
                                formatQuantity(
                                    totalQuantity,
                                )
                            }}
                        </dd>
                    </div>

                    <div
                        class="flex justify-between gap-4"
                    >
                        <dt class="text-gray-500">
                            Allocation Revision
                        </dt>

                        <dd
                            class="font-semibold text-gray-900 dark:text-white"
                        >
                            {{
                                salesOrder.allocation_revision
                            }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div
            class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
        >
            <Link
                :href="
                    dispatch
                        ? route(
                            'dispatches.show',
                            dispatch.id,
                        )
                        : route(
                            'dispatches.index',
                        )
                "
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800"
            >
                Cancel
            </Link>

            <button
                :disabled="
                    form.processing
                    || selectedLineCount
                        === 0
                "
                type="submit"
                class="inline-flex items-center justify-center rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
            >
                {{
                    form.processing
                        ? 'Saving...'
                        : isEditing
                            ? 'Update Dispatch Draft'
                            : 'Create Dispatch Draft'
                }}
            </button>
        </div>
    </form>
</template>