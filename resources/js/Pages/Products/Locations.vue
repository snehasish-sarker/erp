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
    watch,
} from 'vue';
import type {
    ComputedRef,
    Ref,
} from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type {
    ProductBranchSettingFormData,
    ProductBranchSettingRecord,
    ProductLocationBranch,
    ProductLocationStatus,
    ProductLocationStatusOption,
    ProductLocationSummary,
    ProductLocationWarehouse,
    ProductWarehouseSettingFormData,
    ProductWarehouseSettingRecord,
} from '@/Types/product-location';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    product: ProductLocationSummary;
    branches: ProductLocationBranch[];
    warehouses: ProductLocationWarehouse[];
    branchSettings: ProductBranchSettingRecord[];
    warehouseSettings: ProductWarehouseSettingRecord[];
    statusOptions: ProductLocationStatusOption[];
}>();

const deletingBranchSettingId: Ref<number | null> =
    ref(null);

const deletingWarehouseSettingId: Ref<number | null> =
    ref(null);

const branchForm = useForm<ProductBranchSettingFormData>({
    branch_id: null,
    status: 'active',
    is_purchasable: props.product.is_purchasable,
    is_sellable: props.product.is_sellable,
    selling_price: '',
});

const warehouseForm = useForm<ProductWarehouseSettingFormData>({
    branch_id: null,
    warehouse_id: null,
    status: 'active',
    minimum_stock: '0.000000',
    reorder_level: '0.000000',
    maximum_stock: '',
    bin_location: '',
    allow_negative_stock: false,
});

const selectedBranch: ComputedRef<ProductLocationBranch | undefined> =
    computed(
        (): ProductLocationBranch | undefined =>
            props.branches.find(
                (branch: ProductLocationBranch): boolean =>
                    branch.id === branchForm.branch_id,
            ),
    );

const warehouseBranchOptions: ComputedRef<ProductLocationBranch[]> =
    computed(
        (): ProductLocationBranch[] =>
            props.branches.filter(
                (branch: ProductLocationBranch): boolean =>
                    props.branchSettings.some(
                        (setting: ProductBranchSettingRecord): boolean =>
                            setting.branch_id === branch.id,
                    ),
            ),
    );

const availableWarehouses: ComputedRef<ProductLocationWarehouse[]> =
    computed(
        (): ProductLocationWarehouse[] =>
            props.warehouses.filter(
                (warehouse: ProductLocationWarehouse): boolean =>
                    warehouse.branch_id === warehouseForm.branch_id,
            ),
    );

const selectedWarehouseBranch: ComputedRef<ProductLocationBranch | undefined> =
    computed(
        (): ProductLocationBranch | undefined =>
            props.branches.find(
                (branch: ProductLocationBranch): boolean =>
                    branch.id === warehouseForm.branch_id,
            ),
    );

const selectedWarehouse: ComputedRef<ProductLocationWarehouse | undefined> =
    computed(
        (): ProductLocationWarehouse | undefined =>
            props.warehouses.find(
                (warehouse: ProductLocationWarehouse): boolean =>
                    warehouse.id === warehouseForm.warehouse_id,
            ),
    );

const selectedWarehouseBranchSetting: ComputedRef<ProductBranchSettingRecord | undefined> =
    computed(
        (): ProductBranchSettingRecord | undefined =>
            props.branchSettings.find(
                (setting: ProductBranchSettingRecord): boolean =>
                    setting.branch_id === warehouseForm.branch_id,
            ),
    );

const canActivateBranchSetting: ComputedRef<boolean> =
    computed(
        (): boolean =>
            props.product.status === 'active'
            && selectedBranch.value?.status === 'active',
    );

const canActivateWarehouseSetting: ComputedRef<boolean> =
    computed(
        (): boolean =>
            props.product.product_type === 'stock'
            && props.product.status === 'active'
            && selectedWarehouseBranch.value?.status === 'active'
            && selectedWarehouse.value?.status === 'active'
            && selectedWarehouseBranchSetting.value?.status === 'active',
    );

watch(
    (): number | null => branchForm.branch_id,
    (branchId: number | null): void => {
        branchForm.clearErrors();

        const setting = props.branchSettings.find(
            (candidate: ProductBranchSettingRecord): boolean =>
                candidate.branch_id === branchId,
        );

        if (setting !== undefined) {
            branchForm.status = setting.status;
            branchForm.is_purchasable = setting.is_purchasable;
            branchForm.is_sellable = setting.is_sellable;
            branchForm.selling_price = setting.selling_price ?? '';

            return;
        }

        const branch = props.branches.find(
            (candidate: ProductLocationBranch): boolean =>
                candidate.id === branchId,
        );

        branchForm.status =
            props.product.status === 'active'
            && branch?.status === 'active'
                ? 'active'
                : 'inactive';

        branchForm.is_purchasable = props.product.is_purchasable;
        branchForm.is_sellable = props.product.is_sellable;
        branchForm.selling_price = '';
    },
);

watch(
    (): number | null => warehouseForm.branch_id,
    (branchId: number | null): void => {
        warehouseForm.clearErrors();

        const selected = props.warehouses.find(
            (warehouse: ProductLocationWarehouse): boolean =>
                warehouse.id === warehouseForm.warehouse_id,
        );

        if (selected?.branch_id !== branchId) {
            warehouseForm.warehouse_id = null;
        }
    },
);

watch(
    (): number | null => warehouseForm.warehouse_id,
    (warehouseId: number | null): void => {
        warehouseForm.clearErrors();

        const setting = props.warehouseSettings.find(
            (candidate: ProductWarehouseSettingRecord): boolean =>
                candidate.warehouse_id === warehouseId,
        );

        if (setting !== undefined) {
            warehouseForm.status = setting.status;
            warehouseForm.minimum_stock = setting.minimum_stock;
            warehouseForm.reorder_level = setting.reorder_level;
            warehouseForm.maximum_stock = setting.maximum_stock ?? '';
            warehouseForm.bin_location = setting.bin_location ?? '';
            warehouseForm.allow_negative_stock = setting.allow_negative_stock;

            return;
        }

        const warehouse = props.warehouses.find(
            (candidate: ProductLocationWarehouse): boolean =>
                candidate.id === warehouseId,
        );

        const branchSetting = props.branchSettings.find(
            (candidate: ProductBranchSettingRecord): boolean =>
                candidate.branch_id === warehouseForm.branch_id,
        );

        warehouseForm.status =
            props.product.status === 'active'
            && warehouse?.status === 'active'
            && branchSetting?.status === 'active'
                ? 'active'
                : 'inactive';

        warehouseForm.minimum_stock = '0.000000';
        warehouseForm.reorder_level = '0.000000';
        warehouseForm.maximum_stock = '';
        warehouseForm.bin_location = '';
        warehouseForm.allow_negative_stock = false;
    },
);

const resetBranchForm = (): void => {
    branchForm.reset();
    branchForm.clearErrors();
};

const resetWarehouseForm = (): void => {
    warehouseForm.reset();
    warehouseForm.clearErrors();
};

const submitBranchSetting = (): void => {
    branchForm.selling_price = branchForm.selling_price.trim();

    branchForm.put(
        `/erp/products/${props.product.id}/locations/branch`,
        {
            preserveScroll: true,
            onSuccess: resetBranchForm,
        },
    );
};

const submitWarehouseSetting = (): void => {
    warehouseForm.minimum_stock =
        warehouseForm.minimum_stock.trim();

    warehouseForm.reorder_level =
        warehouseForm.reorder_level.trim();

    warehouseForm.maximum_stock =
        warehouseForm.maximum_stock.trim();

    warehouseForm.bin_location =
        warehouseForm.bin_location.trim();

    warehouseForm.put(
        `/erp/products/${props.product.id}/locations/warehouse`,
        {
            preserveScroll: true,
            onSuccess: resetWarehouseForm,
        },
    );
};

const editBranchSetting = (
    setting: ProductBranchSettingRecord,
): void => {
    branchForm.branch_id = setting.branch_id;
    branchForm.status = setting.status;
    branchForm.is_purchasable = setting.is_purchasable;
    branchForm.is_sellable = setting.is_sellable;
    branchForm.selling_price = setting.selling_price ?? '';

    window.scrollTo({
        top: 0,
        behavior: 'smooth',
    });
};

const editWarehouseSetting = (
    setting: ProductWarehouseSettingRecord,
): void => {
    warehouseForm.branch_id = setting.branch_id;
    warehouseForm.warehouse_id = setting.warehouse_id;
    warehouseForm.status = setting.status;
    warehouseForm.minimum_stock = setting.minimum_stock;
    warehouseForm.reorder_level = setting.reorder_level;
    warehouseForm.maximum_stock = setting.maximum_stock ?? '';
    warehouseForm.bin_location = setting.bin_location ?? '';
    warehouseForm.allow_negative_stock = setting.allow_negative_stock;
};

const deleteBranchSetting = (
    setting: ProductBranchSettingRecord,
): void => {
    const confirmed = window.confirm(
        `Remove the branch configuration for ${setting.branch_name}?`,
    );

    if (!confirmed) {
        return;
    }

    deletingBranchSettingId.value = setting.id;

    router.delete(
        `/erp/products/${props.product.id}/locations/branches/${setting.id}`,
        {
            preserveScroll: true,
            onFinish: (): void => {
                deletingBranchSettingId.value = null;
            },
        },
    );
};

const deleteWarehouseSetting = (
    setting: ProductWarehouseSettingRecord,
): void => {
    const confirmed = window.confirm(
        `Remove the warehouse configuration for ${setting.warehouse_name}?`,
    );

    if (!confirmed) {
        return;
    }

    deletingWarehouseSettingId.value = setting.id;

    router.delete(
        `/erp/products/${props.product.id}/locations/warehouses/${setting.id}`,
        {
            preserveScroll: true,
            onFinish: (): void => {
                deletingWarehouseSettingId.value = null;
            },
        },
    );
};

const statusBadgeClass = (
    status: ProductLocationStatus,
): string =>
    status === 'active'
        ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
        : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400';

const formatAmount = (value: string | null): string => {
    if (value === null) {
        return '—';
    }

    return new Intl.NumberFormat(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 6,
        },
    ).format(Number(value));
};
</script>

<template>
    <Head :title="`${product.name} Locations`" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1
                        class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                    >
                        Product Locations
                    </h1>

                    <span
                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="statusBadgeClass(product.status)"
                    >
                        {{
                            product.status === 'active'
                                ? 'Active'
                                : 'Inactive'
                        }}
                    </span>
                </div>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Configure {{ product.name }}
                    ({{ product.sku }}) by accessible branch and warehouse.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <Link
                    :href="`/erp/products/${product.id}/edit`"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                >
                    Edit product
                </Link>

                <Link
                    href="/erp/products"
                    class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                >
                    Back to products
                </Link>
            </div>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6"
        >
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Product type
                    </p>
                    <p class="mt-1 font-medium capitalize text-gray-800 dark:text-white/90">
                        {{ product.product_type.replace('_', ' ') }}
                    </p>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Default selling price
                    </p>
                    <p class="mt-1 font-medium tabular-nums text-gray-800 dark:text-white/90">
                        {{ formatAmount(product.selling_price) }}
                    </p>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Purchasing
                    </p>
                    <p class="mt-1 font-medium text-gray-800 dark:text-white/90">
                        {{ product.is_purchasable ? 'Enabled' : 'Disabled' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        Selling
                    </p>
                    <p class="mt-1 font-medium text-gray-800 dark:text-white/90">
                        {{ product.is_sellable ? 'Enabled' : 'Disabled' }}
                    </p>
                </div>
            </div>
        </div>

        <section
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div
                class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
            >
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Branch configuration
                </h2>

                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Activate the product and optionally override its selling price by branch.
                </p>
            </div>

            <form
                class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-5"
                @submit.prevent="submitBranchSetting"
            >
                <div class="xl:col-span-2">
                    <label
                        for="product-location-branch"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Branch
                        <span class="text-error-500">*</span>
                    </label>

                    <select
                        id="product-location-branch"
                        v-model="branchForm.branch_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        :class="branchForm.errors.branch_id ? 'border-error-500' : ''"
                    >
                        <option :value="null">Select branch</option>
                        <option
                            v-for="branch in branches"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }} ({{ branch.code }})
                            {{ branch.status !== 'active' ? `— ${branch.status}` : '' }}
                        </option>
                    </select>

                    <p
                        v-if="branchForm.errors.branch_id"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ branchForm.errors.branch_id }}
                    </p>
                </div>

                <div>
                    <label
                        for="product-location-branch-status"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                    </label>

                    <select
                        id="product-location-branch-status"
                        v-model="branchForm.status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        :class="branchForm.errors.status ? 'border-error-500' : ''"
                    >
                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                            :disabled="option.value === 'active' && !canActivateBranchSetting"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <p
                        v-if="branchForm.errors.status"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ branchForm.errors.status }}
                    </p>
                </div>

                <div class="xl:col-span-2">
                    <label
                        for="product-location-selling-price"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Selling-price override
                    </label>

                    <input
                        id="product-location-selling-price"
                        v-model="branchForm.selling_price"
                        type="number"
                        min="0"
                        max="99999999999999.999999"
                        step="0.000001"
                        placeholder="Use product default"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        :class="branchForm.errors.selling_price ? 'border-error-500' : ''"
                    >

                    <p
                        v-if="branchForm.errors.selling_price"
                        class="mt-1.5 text-sm text-error-500"
                    >
                        {{ branchForm.errors.selling_price }}
                    </p>
                </div>

                <label
                    class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                >
                    <input
                        v-model="branchForm.is_purchasable"
                        type="checkbox"
                        :disabled="!product.is_purchasable"
                        class="mt-0.5 size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900"
                    >
                    <span>
                        <span class="block text-sm font-medium text-gray-800 dark:text-white/90">
                            Purchasable
                        </span>
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                            Enabled within this branch.
                        </span>
                    </span>
                </label>

                <label
                    class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                >
                    <input
                        v-model="branchForm.is_sellable"
                        type="checkbox"
                        :disabled="!product.is_sellable"
                        class="mt-0.5 size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 disabled:opacity-50 dark:border-gray-700 dark:bg-gray-900"
                    >
                    <span>
                        <span class="block text-sm font-medium text-gray-800 dark:text-white/90">
                            Sellable
                        </span>
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                            Enabled within this branch.
                        </span>
                    </span>
                </label>

                <div class="flex items-end gap-3 sm:col-span-2 xl:col-span-3 xl:justify-end">
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                        @click="resetBranchForm"
                    >
                        Clear
                    </button>

                    <button
                        type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="branchForm.processing || branchForm.branch_id === null"
                    >
                        {{ branchForm.processing ? 'Saving...' : 'Save branch setting' }}
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto border-t border-gray-200 dark:border-gray-800">
                <table class="min-w-full">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Branch
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Status
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Effective price
                            </th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Usage
                            </th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr
                            v-for="setting in branchSettings"
                            :key="setting.id"
                        >
                            <td class="px-5 py-4">
                                <p class="font-medium text-gray-800 dark:text-white/90">
                                    {{ setting.branch_name }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    {{ setting.branch_code }}
                                </p>
                            </td>
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="statusBadgeClass(setting.status)"
                                >
                                    {{ setting.status === 'active' ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300">
                                {{ formatAmount(setting.effective_selling_price) }}
                                <p
                                    v-if="setting.selling_price === null"
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Product default
                                </p>
                            </td>
                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">
                                {{ setting.is_purchasable ? 'Purchase' : 'No purchase' }} ·
                                {{ setting.is_sellable ? 'Sale' : 'No sale' }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-3">
                                    <button
                                        type="button"
                                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                        @click="editBranchSetting(setting)"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="text-sm font-medium text-error-600 hover:text-error-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="deletingBranchSettingId === setting.id"
                                        @click="deleteBranchSetting(setting)"
                                    >
                                        {{ deletingBranchSettingId === setting.id ? 'Removing...' : 'Remove' }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="branchSettings.length === 0">
                            <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                No branch configurations have been added.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section
            v-if="product.product_type === 'stock'"
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                    Warehouse configuration
                </h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Configure stock controls only after the Product has a branch configuration.
                </p>
            </div>

            <form
                class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6 xl:grid-cols-4"
                @submit.prevent="submitWarehouseSetting"
            >
                <div>
                    <label for="warehouse-setting-branch" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Branch
                    </label>
                    <select
                        id="warehouse-setting-branch"
                        v-model="warehouseForm.branch_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        :class="warehouseForm.errors.branch_id ? 'border-error-500' : ''"
                    >
                        <option :value="null">Select configured branch</option>
                        <option
                            v-for="branch in warehouseBranchOptions"
                            :key="branch.id"
                            :value="branch.id"
                        >
                            {{ branch.name }} ({{ branch.code }})
                        </option>
                    </select>
                    <p v-if="warehouseForm.errors.branch_id" class="mt-1.5 text-sm text-error-500">
                        {{ warehouseForm.errors.branch_id }}
                    </p>
                </div>

                <div>
                    <label for="warehouse-setting-warehouse" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Warehouse
                    </label>
                    <select
                        id="warehouse-setting-warehouse"
                        v-model="warehouseForm.warehouse_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        :class="warehouseForm.errors.warehouse_id ? 'border-error-500' : ''"
                    >
                        <option :value="null">Select warehouse</option>
                        <option
                            v-for="warehouse in availableWarehouses"
                            :key="warehouse.id"
                            :value="warehouse.id"
                        >
                            {{ warehouse.name }} ({{ warehouse.code }})
                            {{ warehouse.status !== 'active' ? `— ${warehouse.status}` : '' }}
                        </option>
                    </select>
                    <p v-if="warehouseForm.errors.warehouse_id" class="mt-1.5 text-sm text-error-500">
                        {{ warehouseForm.errors.warehouse_id }}
                    </p>
                </div>

                <div>
                    <label for="warehouse-setting-status" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Status
                    </label>
                    <select
                        id="warehouse-setting-status"
                        v-model="warehouseForm.status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        :class="warehouseForm.errors.status ? 'border-error-500' : ''"
                    >
                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                            :disabled="option.value === 'active' && !canActivateWarehouseSetting"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <p v-if="warehouseForm.errors.status" class="mt-1.5 text-sm text-error-500">
                        {{ warehouseForm.errors.status }}
                    </p>
                </div>

                <div>
                    <label for="warehouse-bin-location" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Bin location
                    </label>
                    <input
                        id="warehouse-bin-location"
                        v-model="warehouseForm.bin_location"
                        type="text"
                        maxlength="120"
                        placeholder="A-01-03"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        :class="warehouseForm.errors.bin_location ? 'border-error-500' : ''"
                    >
                    <p v-if="warehouseForm.errors.bin_location" class="mt-1.5 text-sm text-error-500">
                        {{ warehouseForm.errors.bin_location }}
                    </p>
                </div>

                <div>
                    <label for="warehouse-minimum-stock" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Minimum stock
                    </label>
                    <input
                        id="warehouse-minimum-stock"
                        v-model="warehouseForm.minimum_stock"
                        type="number"
                        min="0"
                        step="0.000001"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        :class="warehouseForm.errors.minimum_stock ? 'border-error-500' : ''"
                    >
                    <p v-if="warehouseForm.errors.minimum_stock" class="mt-1.5 text-sm text-error-500">
                        {{ warehouseForm.errors.minimum_stock }}
                    </p>
                </div>

                <div>
                    <label for="warehouse-reorder-level" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Reorder level
                    </label>
                    <input
                        id="warehouse-reorder-level"
                        v-model="warehouseForm.reorder_level"
                        type="number"
                        min="0"
                        step="0.000001"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                        :class="warehouseForm.errors.reorder_level ? 'border-error-500' : ''"
                    >
                    <p v-if="warehouseForm.errors.reorder_level" class="mt-1.5 text-sm text-error-500">
                        {{ warehouseForm.errors.reorder_level }}
                    </p>
                </div>

                <div>
                    <label for="warehouse-maximum-stock" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Maximum stock
                    </label>
                    <input
                        id="warehouse-maximum-stock"
                        v-model="warehouseForm.maximum_stock"
                        type="number"
                        min="0"
                        step="0.000001"
                        placeholder="No maximum"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                        :class="warehouseForm.errors.maximum_stock ? 'border-error-500' : ''"
                    >
                    <p v-if="warehouseForm.errors.maximum_stock" class="mt-1.5 text-sm text-error-500">
                        {{ warehouseForm.errors.maximum_stock }}
                    </p>
                </div>

                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    <input
                        v-model="warehouseForm.allow_negative_stock"
                        type="checkbox"
                        class="mt-0.5 size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
                    >
                    <span>
                        <span class="block text-sm font-medium text-gray-800 dark:text-white/90">
                            Allow negative stock
                        </span>
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                            Permit stock to fall below zero.
                        </span>
                    </span>
                </label>

                <div class="flex items-end gap-3 sm:col-span-2 xl:col-span-4 xl:justify-end">
                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                        @click="resetWarehouseForm"
                    >
                        Clear
                    </button>
                    <button
                        type="submit"
                        class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="warehouseForm.processing || warehouseForm.warehouse_id === null"
                    >
                        {{ warehouseForm.processing ? 'Saving...' : 'Save warehouse setting' }}
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto border-t border-gray-200 dark:border-gray-800">
                <table class="min-w-full">
                    <thead class="bg-gray-50 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Warehouse</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Minimum</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Reorder</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Maximum</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bin</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Negative</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="setting in warehouseSettings" :key="setting.id">
                            <td class="px-5 py-4">
                                <p class="font-medium text-gray-800 dark:text-white/90">{{ setting.warehouse_name }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ setting.branch_name }} · {{ setting.warehouse_code }}</p>
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass(setting.status)">
                                    {{ setting.status === 'active' ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300">{{ formatAmount(setting.minimum_stock) }}</td>
                            <td class="px-5 py-4 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300">{{ formatAmount(setting.reorder_level) }}</td>
                            <td class="px-5 py-4 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300">{{ formatAmount(setting.maximum_stock) }}</td>
                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">{{ setting.bin_location ?? '—' }}</td>
                            <td class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300">{{ setting.allow_negative_stock ? 'Allowed' : 'Blocked' }}</td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-3">
                                    <button type="button" class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400" @click="editWarehouseSetting(setting)">
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="text-sm font-medium text-error-600 hover:text-error-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="deletingWarehouseSettingId === setting.id"
                                        @click="deleteWarehouseSetting(setting)"
                                    >
                                        {{ deletingWarehouseSettingId === setting.id ? 'Removing...' : 'Remove' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="warehouseSettings.length === 0">
                            <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                No warehouse configurations have been added.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div
            v-else
            class="rounded-2xl border border-warning-200 bg-warning-50 p-5 text-sm text-warning-800 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-warning-300"
        >
            Warehouse configuration is available only for stock products.
        </div>
    </div>
</template>
