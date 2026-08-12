<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    InventoryTransferRecord,
} from '@/Types/inventory';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    transfer: InventoryTransferRecord;
    canViewCost: boolean;
    currencyCode: string;
}>();

const statusClass = (status: string): string => {
    if (status === 'posted') {
        return 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400';
    }

    if (status === 'cancelled') {
        return 'bg-error-50 text-error-700 dark:bg-error-500/15 dark:text-error-400';
    }

    return 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400';
};

const formatQuantity = (value: string): string => new Intl.NumberFormat(
    'en-US',
    {
        maximumFractionDigits: 6,
    },
).format(Number(value));

const formatMoney = (value: string | null): string => {
    if (value === null) {
        return '—';
    }

    try {
        return new Intl.NumberFormat(
            'en-US',
            {
                style: 'currency',
                currency: props.currencyCode,
            },
        ).format(Number(value));
    } catch {
        return `${props.currencyCode} ${Number(value).toFixed(2)}`;
    }
};

const postTransfer = (): void => {
    if (!window.confirm('Post this transfer and move stock now?')) {
        return;
    }

    router.post(
        route('inventory.transfers.post', props.transfer.id),
        {},
        {
            preserveScroll: true,
        },
    );
};

const cancelTransfer = (): void => {
    const reason = window.prompt(
        'Why are you cancelling this draft transfer?',
    );

    if (reason === null || reason.trim() === '') {
        return;
    }

    router.post(
        route('inventory.transfers.cancel', props.transfer.id),
        {
            reason: reason.trim(),
        },
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head :title="transfer.transfer_number ?? `Inventory Transfer #${transfer.id}`" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
                        {{ transfer.transfer_number ?? `Draft Transfer #${transfer.id}` }}
                    </h1>
                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize"
                        :class="statusClass(transfer.status)"
                    >
                        {{ transfer.status }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Transfer date: {{ transfer.transfer_date }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Link
                    :href="route('inventory.transfers.index')"
                    class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                >
                    Back
                </Link>
                <button
                    v-if="transfer.status === 'draft'"
                    type="button"
                    class="inline-flex h-10 items-center rounded-lg border border-error-300 px-4 text-sm font-medium text-error-600 dark:border-error-700 dark:text-error-400"
                    @click="cancelTransfer"
                >
                    Cancel Draft
                </button>
                <button
                    v-if="transfer.status === 'draft'"
                    type="button"
                    class="inline-flex h-10 items-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600"
                    @click="postTransfer"
                >
                    Post Transfer
                </button>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Source
                </p>
                <h2 class="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90">
                    {{ transfer.source_warehouse.name }} ({{ transfer.source_warehouse.code }})
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ transfer.source_branch.name }} ({{ transfer.source_branch.code }})
                </p>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Destination
                </p>
                <h2 class="mt-2 text-lg font-semibold text-gray-800 dark:text-white/90">
                    {{ transfer.destination_warehouse.name }} ({{ transfer.destination_warehouse.code }})
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ transfer.destination_branch.name }} ({{ transfer.destination_branch.code }})
                </p>
            </div>
        </div>

        <div
            v-if="transfer.notes"
            class="rounded-2xl border border-gray-200 bg-white p-5 text-sm text-gray-600 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400"
        >
            <span class="font-medium text-gray-800 dark:text-white/90">Notes:</span>
            {{ transfer.notes }}
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <h2 class="font-semibold text-gray-800 dark:text-white/90">
                    Transfer Lines
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">#</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Product</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Quantity</th>
                            <th v-if="canViewCost" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Unit Cost</th>
                            <th v-if="canViewCost" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Transfer Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr
                            v-for="line in transfer.lines"
                            :key="line.id"
                        >
                            <td class="px-5 py-4 text-sm text-gray-500">{{ line.line_number }}</td>
                            <td class="px-5 py-4">
                                <p class="text-sm font-medium text-gray-800 dark:text-white/90">
                                    {{ line.product_name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ line.product_sku }} · {{ line.unit_code }}
                                </p>
                            </td>
                            <td class="px-5 py-4 text-right text-sm font-semibold text-gray-800 dark:text-white/90">
                                {{ formatQuantity(line.quantity) }}
                            </td>
                            <td v-if="canViewCost" class="px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-400">
                                {{ formatMoney(line.unit_cost) }}
                            </td>
                            <td v-if="canViewCost" class="px-5 py-4 text-right text-sm font-medium text-gray-800 dark:text-white/90">
                                {{ formatMoney(line.transfer_value) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div
            v-if="transfer.status === 'cancelled'"
            class="rounded-2xl border border-error-200 bg-error-50 p-5 text-sm text-error-700 dark:border-error-900/50 dark:bg-error-500/10 dark:text-error-400"
        >
            <span class="font-semibold">Cancellation reason:</span>
            {{ transfer.cancellation_reason }}
        </div>

        <div
            v-if="transfer.status === 'posted'"
            class="rounded-2xl border border-success-200 bg-success-50 p-5 text-sm text-success-700 dark:border-success-900/50 dark:bg-success-500/10 dark:text-success-400"
        >
            Posted transfers are immutable. If stock must be returned, create a new transfer in the opposite direction so the audit trail remains complete.
        </div>
    </div>
</template>
