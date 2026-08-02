<script setup lang="ts">
import {
    Head,
    Link,
    router,
} from '@inertiajs/vue3';
import {
    computed,
    reactive,
    ref,
} from 'vue';
import type {
    ComputedRef,
    Ref,
} from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import { useAuthorization } from '@/Composables/useAuthorization';
import type {
    ProductFilters,
    ProductOption,
    ProductPagination,
    ProductRecord,
    ProductRelationOption,
    ProductSort,
    ProductStatus,
    ProductType,
} from '@/Types/product';

defineOptions({
    layout: ErpLayout,
});

const props = defineProps<{
    products: ProductPagination;
    filters: ProductFilters;
    categoryOptions: ProductRelationOption[];
    brandOptions: ProductRelationOption[];
    productTypeOptions: ProductOption<ProductType>[];
    statusOptions: ProductOption<ProductStatus>[];
    canViewCost: boolean;
}>();

const { can } = useAuthorization();

const filters = reactive<ProductFilters>({
    search: props.filters.search,
    product_category_id:
        props.filters.product_category_id,
    brand_id: props.filters.brand_id,
    product_type: props.filters.product_type,
    status: props.filters.status,
    sort: props.filters.sort,
    direction: props.filters.direction,
    per_page: props.filters.per_page,
});

const deletingProductId: Ref<number | null> =
    ref(null);

const hasActiveFilters: ComputedRef<boolean> =
    computed(
        (): boolean =>
            filters.search !== ''
            || filters.product_category_id !== null
            || filters.brand_id !== null
            || filters.product_type !== ''
            || filters.status !== '',
    );

const tableColumnCount: ComputedRef<number> =
    computed(
        (): number => props.canViewCost ? 12 : 11,
    );

const navigate = (page = 1): void => {
    router.get(
        '/erp/products',
        {
            search: filters.search,
            product_category_id:
                filters.product_category_id,
            brand_id: filters.brand_id,
            product_type: filters.product_type,
            status: filters.status,
            sort: filters.sort,
            direction: filters.direction,
            per_page: filters.per_page,
            page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const applyFilters = (): void => {
    filters.search = filters.search.trim();
    navigate();
};

const resetFilters = (): void => {
    filters.search = '';
    filters.product_category_id = null;
    filters.brand_id = null;
    filters.product_type = '';
    filters.status = '';
    filters.sort = 'name';
    filters.direction = 'asc';
    filters.per_page = 25;

    navigate();
};

const sortBy = (
    column: ProductSort,
): void => {
    if (
        column === 'cost_price'
        && !props.canViewCost
    ) {
        return;
    }

    if (filters.sort === column) {
        filters.direction =
            filters.direction === 'asc'
                ? 'desc'
                : 'asc';
    } else {
        filters.sort = column;
        filters.direction = 'asc';
    }

    navigate();
};

const sortIndicator = (
    column: ProductSort,
): string => {
    if (filters.sort !== column) {
        return '';
    }

    return filters.direction === 'asc'
        ? '↑'
        : '↓';
};

const deleteProduct = (
    product: ProductRecord,
): void => {
    const confirmed = window.confirm(
        `Delete product “${product.name} (${product.sku})”? Its SKU, slug, and barcode will remain reserved.`,
    );

    if (!confirmed) {
        return;
    }

    deletingProductId.value = product.id;

    router.delete(
        `/erp/products/${product.id}`,
        {
            preserveScroll: true,

            onFinish: (): void => {
                deletingProductId.value = null;
            },
        },
    );
};

const statusBadgeClass = (
    status: ProductStatus,
): string =>
    status === 'active'
        ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400'
        : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400';

const typeBadgeClass = (
    productType: ProductType,
): string => {
    if (productType === 'stock') {
        return 'bg-brand-50 text-brand-700 dark:bg-brand-500/15 dark:text-brand-400';
    }

    if (productType === 'service') {
        return 'bg-purple-50 text-purple-700 dark:bg-purple-500/15 dark:text-purple-400';
    }

    return 'bg-warning-50 text-warning-700 dark:bg-warning-500/15 dark:text-warning-400';
};

const formatAmount = (
    value: string | null,
): string => {
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

const formatDate = (
    value: string | null,
): string => {
    if (value === null) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        'en-US',
        {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        },
    ).format(new Date(value));
};
</script>

<template>
    <Head title="Products" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-semibold text-gray-800 dark:text-white/90"
                >
                    Products
                </h1>

                <p
                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                >
                    Manage tenant-isolated catalogue products,
                    pricing, classifications, and status.
                </p>
            </div>

            <Link
                v-if="can('products.create')"
                href="/erp/products/create"
                class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600"
            >
                Create product
            </Link>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <form
                class="grid gap-4 border-b border-gray-200 p-5 dark:border-gray-800 sm:grid-cols-2 sm:p-6 xl:grid-cols-6"
                @submit.prevent="applyFilters"
            >
                <div class="sm:col-span-2 xl:col-span-2">
                    <label
                        for="product-search"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Search
                    </label>

                    <input
                        id="product-search"
                        v-model="filters.search"
                        type="search"
                        maxlength="160"
                        placeholder="Name, SKU, slug, barcode, or description"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    >
                </div>

                <div>
                    <label
                        for="product-category-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Category
                    </label>

                    <select
                        id="product-category-filter"
                        v-model="filters.product_category_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option :value="null">
                            All categories
                        </option>

                        <option
                            v-for="option in categoryOptions"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="product-brand-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Brand
                    </label>

                    <select
                        id="product-brand-filter"
                        v-model="filters.brand_id"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option :value="null">
                            All brands
                        </option>

                        <option
                            v-for="option in brandOptions"
                            :key="option.id"
                            :value="option.id"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="product-type-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Product type
                    </label>

                    <select
                        id="product-type-filter"
                        v-model="filters.product_type"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All types
                        </option>

                        <option
                            v-for="option in productTypeOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="product-status-filter"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Status
                    </label>

                    <select
                        id="product-status-filter"
                        v-model="filters.status"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option value="">
                            All statuses
                        </option>

                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div>
                    <label
                        for="product-per-page"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                    >
                        Per page
                    </label>

                    <select
                        id="product-per-page"
                        v-model.number="filters.per_page"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                </div>

                <div
                    class="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-6"
                >
                    <button
                        type="submit"
                        class="inline-flex h-10 items-center justify-center rounded-lg bg-brand-500 px-4 text-sm font-medium text-white transition hover:bg-brand-600"
                    >
                        Apply filters
                    </button>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]"
                    >
                        <tr>
                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    @click="sortBy('name')"
                                >
                                    Product
                                    {{ sortIndicator('name') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    @click="sortBy('sku')"
                                >
                                    SKU
                                    {{ sortIndicator('sku') }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Category
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Brand
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    @click="sortBy('product_type')"
                                >
                                    Type
                                    {{ sortIndicator('product_type') }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Unit
                            </th>

                            <th
                                v-if="canViewCost"
                                class="px-5 py-3 text-right"
                            >
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    @click="sortBy('cost_price')"
                                >
                                    Cost
                                    {{ sortIndicator('cost_price') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-right">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    @click="sortBy('selling_price')"
                                >
                                    Selling
                                    {{ sortIndicator('selling_price') }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Usage
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    @click="sortBy('status')"
                                >
                                    Status
                                    {{ sortIndicator('status') }}
                                </button>
                            </th>

                            <th class="px-5 py-3 text-left">
                                <button
                                    type="button"
                                    class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                                    @click="sortBy('created_at')"
                                >
                                    Created
                                    {{ sortIndicator('created_at') }}
                                </button>
                            </th>

                            <th
                                class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-800"
                    >
                        <tr
                            v-for="product in products.data"
                            :key="product.id"
                        >
                            <td class="px-5 py-4">
                                <p
                                    class="font-medium text-gray-800 dark:text-white/90"
                                >
                                    {{ product.name }}
                                </p>

                                <p
                                    v-if="product.barcode"
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Barcode: {{ product.barcode }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="rounded bg-gray-100 px-2 py-1 font-mono text-sm text-gray-700 dark:bg-white/10 dark:text-gray-300"
                                >
                                    {{ product.sku }}
                                </span>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ product.category_name }}
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ product.brand_name ?? '—' }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="typeBadgeClass(product.product_type)"
                                >
                                    {{ product.product_type_label }}
                                </span>
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ product.base_unit_name }}

                                <span
                                    v-if="product.base_unit_symbol"
                                    class="text-gray-500 dark:text-gray-400"
                                >
                                    ({{ product.base_unit_symbol }})
                                </span>
                            </td>

                            <td
                                v-if="canViewCost"
                                class="px-5 py-4 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300"
                            >
                                {{ formatAmount(product.cost_price) }}
                            </td>

                            <td
                                class="px-5 py-4 text-right text-sm tabular-nums text-gray-700 dark:text-gray-300"
                            >
                                {{ formatAmount(product.selling_price) }}
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-1.5">
                                    <span
                                        v-if="product.is_purchasable"
                                        class="rounded-full bg-blue-light-50 px-2 py-1 text-xs font-medium text-blue-light-700 dark:bg-blue-light-500/15 dark:text-blue-light-400"
                                    >
                                        Purchase
                                    </span>

                                    <span
                                        v-if="product.is_sellable"
                                        class="rounded-full bg-success-50 px-2 py-1 text-xs font-medium text-success-700 dark:bg-success-500/15 dark:text-success-400"
                                    >
                                        Sale
                                    </span>

                                    <span
                                        v-if="
                                            !product.is_purchasable
                                            && !product.is_sellable
                                        "
                                        class="text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        None
                                    </span>
                                </div>
                            </td>

                            <td class="px-5 py-4">
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
                            </td>

                            <td
                                class="px-5 py-4 text-sm text-gray-700 dark:text-gray-300"
                            >
                                {{ formatDate(product.created_at) }}
                            </td>

                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-3">
                                    <Link
                                        v-if="can('products.update')"
                                        :href="`/erp/products/${product.id}/locations`"
                                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                    >
                                        Locations
                                    </Link>
                                    <Link
                                        v-if="can('products.update')"
                                        :href="`/erp/products/${product.id}/edit`"
                                        class="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                    >
                                        Edit
                                    </Link>

                                    <button
                                        v-if="can('products.delete')"
                                        type="button"
                                        class="text-sm font-medium text-error-600 hover:text-error-700 disabled:cursor-not-allowed disabled:opacity-50"
                                        :disabled="
                                            deletingProductId
                                            === product.id
                                        "
                                        @click="deleteProduct(product)"
                                    >
                                        {{
                                            deletingProductId
                                                === product.id
                                                ? 'Deleting...'
                                                : 'Delete'
                                        }}
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="products.data.length === 0">
                            <td
                                :colspan="tableColumnCount"
                                class="px-5 py-12 text-center"
                            >
                                <p
                                    class="text-sm font-medium text-gray-700 dark:text-gray-300"
                                >
                                    No products found.
                                </p>

                                <p
                                    class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Create a product or adjust
                                    the current filters.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <p
                    class="text-sm text-gray-500 dark:text-gray-400"
                >
                    Showing {{ products.meta.from ?? 0 }}–{{
                        products.meta.to ?? 0
                    }} of {{ products.meta.total }}
                </p>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="products.meta.current_page <= 1"
                        @click="navigate(products.meta.current_page - 1)"
                    >
                        Previous
                    </button>

                    <span
                        class="px-2 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Page {{ products.meta.current_page }}
                        of {{ products.meta.last_page }}
                    </span>

                    <button
                        type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-gray-300 px-3 text-sm font-medium text-gray-700 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-700 dark:text-gray-300"
                        :disabled="
                            products.meta.current_page
                            >= products.meta.last_page
                        "
                        @click="navigate(products.meta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>