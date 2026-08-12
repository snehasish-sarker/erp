<script setup lang="ts">
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/vue3';
import {
    computed,
} from 'vue';
import type {
    ComputedRef,
} from 'vue';

import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    InventoryTransferStockOption,
    InventoryTransferWarehouseOption,
} from '@/Types/inventory';

defineOptions({
    layout: ErpLayout,
});

interface TransferLineForm {
    product_id: number | '';
    quantity: string;
}

const props = defineProps<{
    warehouseOptions: InventoryTransferWarehouseOption[];
    stockOptions: InventoryTransferStockOption[];
    today: string;
}>();

const form = useForm<{
    source_warehouse_id: number | '';
    destination_warehouse_id: number | '';
    transfer_date: string;
    notes: string;
    lines: TransferLineForm[];
}>({
    source_warehouse_id: '',
    destination_warehouse_id: '',
    transfer_date: props.today,
    notes: '',
    lines: [
        {
            product_id: '',
            quantity: '',
        },
    ],
});

const sourceStockOptions: ComputedRef<InventoryTransferStockOption[]> = computed(
    (): InventoryTransferStockOption[] => {
        if (form.source_warehouse_id === '') {
            return [];
        }

        return props.stockOptions.filter(
            (option: InventoryTransferStockOption): boolean =>
                option.warehouse_id === form.source_warehouse_id,
        );
    },
);

const destinationOptions: ComputedRef<InventoryTransferWarehouseOption[]> = computed(
    (): InventoryTransferWarehouseOption[] => props.warehouseOptions.filter(
        (warehouse: InventoryTransferWarehouseOption): boolean =>
            warehouse.id !== form.source_warehouse_id,
    ),
);

const productOption = (
    productId: number | '',
): InventoryTransferStockOption | undefined => sourceStockOptions.value.find(
    (option: InventoryTransferStockOption): boolean =>
        option.product_id === productId,
);

const addLine = (): void => {
    form.lines.push({
        product_id: '',
        quantity: '',
    });
};

const removeLine = (index: number): void => {
    if (form.lines.length === 1) {
        return;
    }

    form.lines.splice(index, 1);
};

const sourceChanged = (): void => {
    form.lines = [
        {
            product_id: '',
            quantity: '',
        },
    ];

    if (form.destination_warehouse_id === form.source_warehouse_id) {
        form.destination_warehouse_id = '';
    }
};

const submit = (): void => {
    form.post(
        route('inventory.transfers.store'),
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <Head title="New Inventory Transfer" />

    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-white/90">
                    New Inventory Transfer
                </h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Create a draft transfer. Stock moves only when the draft is posted.
                </p>
            </div>

            <Link
                :href="route('inventory.transfers.index')"
                class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
            >
                Back to Transfers
            </Link>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Source Warehouse
                    </label>
                    <select
                        v-model="form.source_warehouse_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90"
                        required
                        @change="sourceChanged"
                    >
                        <option value="">Select source</option>
                        <option
                            v-for="warehouse in warehouseOptions"
                            :key="warehouse.id"
                            :value="warehouse.id"
                        >
                            {{ warehouse.branch_name }} — {{ warehouse.name }} ({{ warehouse.code }})
                        </option>
                    </select>
                    <p v-if="form.errors.source_warehouse_id" class="mt-1 text-xs text-error-500">
                        {{ form.errors.source_warehouse_id }}
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Destination Warehouse
                    </label>
                    <select
                        v-model="form.destination_warehouse_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90"
                        required
                    >
                        <option value="">Select destination</option>
                        <option
                            v-for="warehouse in destinationOptions"
                            :key="warehouse.id"
                            :value="warehouse.id"
                        >
                            {{ warehouse.branch_name }} — {{ warehouse.name }} ({{ warehouse.code }})
                        </option>
                    </select>
                    <p v-if="form.errors.destination_warehouse_id" class="mt-1 text-xs text-error-500">
                        {{ form.errors.destination_warehouse_id }}
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Transfer Date
                    </label>
                    <input
                        v-model="form.transfer_date"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90"
                        required
                    >
                    <p v-if="form.errors.transfer_date" class="mt-1 text-xs text-error-500">
                        {{ form.errors.transfer_date }}
                    </p>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Notes
                    </label>
                    <input
                        v-model="form.notes"
                        type="text"
                        maxlength="2000"
                        placeholder="Optional transfer notes"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90"
                    >
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h2 class="font-semibold text-gray-800 dark:text-white/90">
                            Transfer Lines
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Available stock shown excludes reserved quantity.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"
                        @click="addLine"
                    >
                        Add Line
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-white/[0.02]">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Product
                                </th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Available
                                </th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Quantity
                                </th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <tr
                                v-for="(line, index) in form.lines"
                                :key="index"
                            >
                                <td class="min-w-[320px] px-5 py-4">
                                    <select
                                        v-model="line.product_id"
                                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90"
                                        required
                                    >
                                        <option value="">Select stock product</option>
                                        <option
                                            v-for="option in sourceStockOptions"
                                            :key="option.product_id"
                                            :value="option.product_id"
                                        >
                                            {{ option.product_name }} ({{ option.product_sku }})
                                        </option>
                                    </select>
                                    <p
                                        v-if="form.errors[`lines.${index}.product_id`]"
                                        class="mt-1 text-xs text-error-500"
                                    >
                                        {{ form.errors[`lines.${index}.product_id`] }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-400">
                                    <template v-if="productOption(line.product_id)">
                                        {{ productOption(line.product_id)?.quantity_available }}
                                        {{ productOption(line.product_id)?.unit_code }}
                                    </template>
                                    <span v-else>—</span>
                                </td>
                                <td class="min-w-[180px] px-5 py-4">
                                    <input
                                        v-model="line.quantity"
                                        type="number"
                                        min="0.000001"
                                        step="0.000001"
                                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90"
                                        required
                                    >
                                    <p
                                        v-if="form.errors[`lines.${index}.quantity`]"
                                        class="mt-1 text-xs text-error-500"
                                    >
                                        {{ form.errors[`lines.${index}.quantity`] }}
                                    </p>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <button
                                        type="button"
                                        class="text-sm font-medium text-error-600 disabled:opacity-40 dark:text-error-400"
                                        :disabled="form.lines.length === 1"
                                        @click="removeLine(index)"
                                    >
                                        Remove
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p v-if="form.errors.lines" class="px-5 py-3 text-sm text-error-500">
                    {{ form.errors.lines }}
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <Link
                    :href="route('inventory.transfers.index')"
                    class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex h-11 items-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-60"
                >
                    {{ form.processing ? 'Saving…' : 'Create Draft' }}
                </button>
            </div>
        </form>
    </div>
</template>
