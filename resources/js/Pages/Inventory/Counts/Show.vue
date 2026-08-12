<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { InventoryStockCountRecord } from '@/Types/inventory';

defineOptions({ layout: ErpLayout });

const props = defineProps<{
    stockCount: InventoryStockCountRecord;
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

const varianceClass = (value: string): string => {
    const numeric = Number(value);
    if (numeric > 0) return 'text-success-600 dark:text-success-400';
    if (numeric < 0) return 'text-error-600 dark:text-error-400';
    return 'text-gray-600 dark:text-gray-400';
};

const formatQuantity = (value: string): string => new Intl.NumberFormat(
    'en-US',
    { maximumFractionDigits: 6 },
).format(Number(value));

const formatMoney = (value: string | null): string => {
    if (value === null) return '—';

    try {
        return new Intl.NumberFormat(
            'en-US',
            { style: 'currency', currency: props.currencyCode },
        ).format(Number(value));
    } catch {
        return `${props.currencyCode} ${Number(value).toFixed(2)}`;
    }
};

const postCount = (): void => {
    if (!window.confirm('Post this stock count and update inventory to the counted quantities?')) return;

    router.post(
        route('inventory.counts.post', props.stockCount.id),
        {},
        { preserveScroll: true },
    );
};

const cancelCount = (): void => {
    const reason = window.prompt('Why are you cancelling this draft stock count?');

    if (reason === null || reason.trim() === '') return;

    router.post(
        route('inventory.counts.cancel', props.stockCount.id),
        { reason: reason.trim() },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head :title="stockCount.count_number ?? `Stock Count #${stockCount.id}`" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
                        {{ stockCount.count_number ?? `Draft Stock Count #${stockCount.id}` }}
                    </h1>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize" :class="statusClass(stockCount.status)">
                        {{ stockCount.status }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ stockCount.branch.name }} · {{ stockCount.warehouse.name }} · {{ stockCount.count_date }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <button v-if="stockCount.status === 'draft'" type="button" class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-medium text-white hover:bg-brand-600" @click="postCount">
                    Post Count
                </button>
                <button v-if="stockCount.status === 'draft'" type="button" class="h-10 rounded-lg border border-error-300 px-4 text-sm font-medium text-error-600 dark:border-error-800" @click="cancelCount">
                    Cancel Draft
                </button>
                <Link :href="route('inventory.counts.index')" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">
                    Back
                </Link>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Count Lines</p>
                <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ stockCount.total_lines }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Variance Lines</p>
                <p class="mt-2 text-2xl font-semibold text-gray-800 dark:text-white/90">{{ stockCount.variance_line_count }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Quantity Gain</p>
                <p class="mt-2 text-2xl font-semibold text-success-600 dark:text-success-400">{{ formatQuantity(stockCount.total_positive_variance) }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Quantity Loss</p>
                <p class="mt-2 text-2xl font-semibold text-error-600 dark:text-error-400">{{ formatQuantity(stockCount.total_negative_variance) }}</p>
            </div>
        </div>

        <div v-if="stockCount.notes || stockCount.cancellation_reason" class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div v-if="stockCount.notes">
                <h2 class="font-semibold text-gray-800 dark:text-white/90">Notes</h2>
                <p class="mt-2 whitespace-pre-line text-sm text-gray-600 dark:text-gray-400">{{ stockCount.notes }}</p>
            </div>
            <div v-if="stockCount.cancellation_reason" class="mt-4">
                <h2 class="font-semibold text-error-600">Cancellation reason</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ stockCount.cancellation_reason }}</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Product</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">System</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Reserved</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Counted</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Variance</th>
                            <th v-if="canViewCost" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Unit Cost</th>
                            <th v-if="canViewCost" class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Variance Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="line in stockCount.lines" :key="line.id">
                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                <div class="font-medium">{{ line.product_name }}</div>
                                <div class="text-xs text-gray-500">{{ line.product_sku }} · {{ line.unit_code }}</div>
                            </td>
                            <td class="px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatQuantity(line.system_quantity) }}</td>
                            <td class="px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatQuantity(line.reserved_quantity) }}</td>
                            <td class="px-5 py-4 text-right text-sm font-medium text-gray-800 dark:text-white/90">{{ formatQuantity(line.counted_quantity) }}</td>
                            <td class="px-5 py-4 text-right text-sm font-semibold" :class="varianceClass(line.variance_quantity)">{{ formatQuantity(line.variance_quantity) }}</td>
                            <td v-if="canViewCost" class="px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-400">{{ formatMoney(line.unit_cost) }}</td>
                            <td v-if="canViewCost" class="px-5 py-4 text-right text-sm font-medium" :class="varianceClass(line.variance_quantity)">{{ formatMoney(line.variance_value) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="canViewCost" class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-success-200 bg-success-50/60 p-5 dark:border-success-900 dark:bg-success-500/10">
                <p class="text-sm text-success-700 dark:text-success-400">Value Gain</p>
                <p class="mt-1 text-xl font-semibold text-success-700 dark:text-success-400">{{ formatMoney(stockCount.total_value_gain) }}</p>
            </div>
            <div class="rounded-2xl border border-error-200 bg-error-50/60 p-5 dark:border-error-900 dark:bg-error-500/10">
                <p class="text-sm text-error-700 dark:text-error-400">Value Loss</p>
                <p class="mt-1 text-xl font-semibold text-error-700 dark:text-error-400">{{ formatMoney(stockCount.total_value_loss) }}</p>
            </div>
        </div>
    </div>
</template>
