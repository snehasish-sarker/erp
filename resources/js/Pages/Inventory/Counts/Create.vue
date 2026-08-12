<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    InventoryStockCountStockOption,
    InventoryTransferWarehouseOption,
} from '@/Types/inventory';

defineOptions({ layout: ErpLayout });

const props = defineProps<{
    warehouseOptions: InventoryTransferWarehouseOption[];
    stockOptions: InventoryStockCountStockOption[];
    defaultCountDate: string;
}>();

interface CountLineForm {
    product_id: number | '';
    counted_quantity: string;
}

const form = useForm<{
    warehouse_id: number | '';
    count_date: string;
    notes: string;
    lines: CountLineForm[];
}>({
    warehouse_id: '',
    count_date: props.defaultCountDate,
    notes: '',
    lines: [
        {
            product_id: '',
            counted_quantity: '',
        },
    ],
});

const warehouseStockOptions = computed(() => {
    if (form.warehouse_id === '') return [];

    return props.stockOptions.filter(
        (option) => option.warehouse_id === form.warehouse_id,
    );
});

const productOption = (
    productId: number | '',
): InventoryStockCountStockOption | undefined => warehouseStockOptions.value.find(
    (option) => option.product_id === productId,
);

const addLine = (): void => {
    form.lines.push({
        product_id: '',
        counted_quantity: '',
    });
};

const removeLine = (index: number): void => {
    if (form.lines.length <= 1) return;
    form.lines.splice(index, 1);
};

const formatQuantity = (value: string): string => new Intl.NumberFormat(
    'en-US',
    { maximumFractionDigits: 6 },
).format(Number(value));

const variance = (line: CountLineForm): string => {
    const option = productOption(line.product_id);

    if (option === undefined || line.counted_quantity === '') return '—';

    return formatQuantity(
        String(Number(line.counted_quantity) - Number(option.quantity_on_hand)),
    );
};

const submit = (): void => {
    form.post(route('inventory.counts.store'));
};
</script>

<template>
    <Head title="New Stock Count" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">New Stock Count</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Capture physical quantities against a warehouse snapshot. Posting is blocked if stock or reservations change afterward.
                </p>
            </div>
            <Link :href="route('inventory.counts.index')" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">
                Back
            </Link>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] lg:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Warehouse</label>
                    <select v-model="form.warehouse_id" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                        <option value="">Select warehouse</option>
                        <option v-for="warehouse in warehouseOptions" :key="warehouse.id" :value="warehouse.id">
                            {{ warehouse.branch_name }} — {{ warehouse.name }} ({{ warehouse.code }})
                        </option>
                    </select>
                    <p v-if="form.errors.warehouse_id" class="mt-1 text-xs text-error-600">{{ form.errors.warehouse_id }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Count date</label>
                    <input v-model="form.count_date" type="date" class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                    <p v-if="form.errors.count_date" class="mt-1 text-xs text-error-600">{{ form.errors.count_date }}</p>
                </div>

                <div class="lg:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                    <textarea v-model="form.notes" rows="3" maxlength="2000" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90" />
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h2 class="font-semibold text-gray-800 dark:text-white/90">Count Lines</h2>
                        <p class="mt-1 text-xs text-gray-500">System and reserved quantities are captured again by the backend when the draft is created.</p>
                    </div>
                    <button type="button" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300" @click="addLine">
                        Add Line
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Product</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">System Qty</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Reserved</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Counted Qty</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Variance</th>
                                <th class="w-20 px-5 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr v-for="(line, index) in form.lines" :key="index">
                                <td class="min-w-80 px-5 py-4">
                                    <select v-model="line.product_id" class="h-10 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                                        <option value="">Select product</option>
                                        <option v-for="option in warehouseStockOptions" :key="option.product_id" :value="option.product_id">
                                            {{ option.product_name }} ({{ option.product_sku }})
                                        </option>
                                    </select>
                                    <p v-if="form.errors[`lines.${index}.product_id`]" class="mt-1 text-xs text-error-600">
                                        {{ form.errors[`lines.${index}.product_id`] }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-400">
                                    {{ productOption(line.product_id) ? formatQuantity(productOption(line.product_id)!.quantity_on_hand) : '—' }}
                                </td>
                                <td class="px-5 py-4 text-right text-sm text-gray-600 dark:text-gray-400">
                                    {{ productOption(line.product_id) ? formatQuantity(productOption(line.product_id)!.quantity_reserved) : '—' }}
                                </td>
                                <td class="px-5 py-4">
                                    <input v-model="line.counted_quantity" type="number" min="0" step="0.000001" class="h-10 w-40 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white/90">
                                    <p v-if="form.errors[`lines.${index}.counted_quantity`]" class="mt-1 text-xs text-error-600">
                                        {{ form.errors[`lines.${index}.counted_quantity`] }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-right text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ variance(line) }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <button type="button" class="text-sm font-medium text-error-600 disabled:opacity-40" :disabled="form.lines.length <= 1" @click="removeLine(index)">
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <Link :href="route('inventory.counts.index')" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300">
                    Cancel
                </Link>
                <button type="submit" class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-60" :disabled="form.processing">
                    Create Draft Count
                </button>
            </div>
        </form>
    </div>
</template>
