<script setup lang="ts">
import {
    Link,
    useForm,
} from '@inertiajs/vue3';
import { watch } from 'vue';
import type {
    ProductFormData,
    ProductOption,
    ProductRecord,
    ProductRelationOption,
    ProductStatus,
    ProductType,
} from '@/Types/product';

const props = defineProps<{
    mode: 'create' | 'edit';
    product?: ProductRecord;
    categoryOptions: ProductRelationOption[];
    brandOptions: ProductRelationOption[];
    unitOptions: ProductRelationOption[];
    productTypeOptions: ProductOption<ProductType>[];
    statusOptions: ProductOption<ProductStatus>[];
    canViewCost: boolean;
}>();

const form = useForm<ProductFormData>({
    product_category_id:
        props.product?.product_category_id ?? null,
    brand_id: props.product?.brand_id ?? null,
    base_unit_id:
        props.product?.base_unit_id ?? null,
    name: props.product?.name ?? '',
    sku: props.product?.sku ?? '',
    slug: props.product?.slug ?? '',
    barcode: props.product?.barcode ?? '',
    product_type:
        props.product?.product_type ?? 'stock',
    description:
        props.product?.description ?? '',
    cost_price:
        props.product?.cost_price ?? '0.000000',
    selling_price:
        props.product?.selling_price ?? '0.000000',
    is_purchasable:
        props.product?.is_purchasable ?? true,
    is_sellable:
        props.product?.is_sellable ?? true,
    status: props.product?.status ?? 'active',
});

const relationOption = (
    options: ProductRelationOption[],
    id: number | null,
): ProductRelationOption | undefined =>
    options.find(
        (option: ProductRelationOption): boolean =>
            option.id === id,
    );

watch(
    (): ProductStatus => form.status,
    (status: ProductStatus): void => {
        if (status !== 'active') {
            return;
        }

        const category = relationOption(
            props.categoryOptions,
            form.product_category_id,
        );

        const brand = relationOption(
            props.brandOptions,
            form.brand_id,
        );

        const unit = relationOption(
            props.unitOptions,
            form.base_unit_id,
        );

        if (category?.status === 'inactive') {
            form.product_category_id = null;
        }

        if (brand?.status === 'inactive') {
            form.brand_id = null;
        }

        if (unit?.status === 'inactive') {
            form.base_unit_id = null;
        }
    },
);

const normalizeSlug = (value: string): string =>
    value
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

const submit = (): void => {
    form.name = form.name.trim();

    form.sku = form.sku
        .trim()
        .toUpperCase();

    form.slug = normalizeSlug(form.slug);
    form.barcode = form.barcode.trim();
    form.description = form.description.trim();
    form.cost_price = String(form.cost_price).trim();
    form.selling_price = String(form.selling_price).trim();

    if (
        props.mode === 'edit'
        && props.product !== undefined
    ) {
        form.put(
            `/erp/products/${props.product.id}`,
            {
                preserveScroll: true,
            },
        );

        return;
    }

    form.post(
        '/erp/products',
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <form
        class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        @submit.prevent="submit"
    >
        <div
            class="border-b border-gray-200 px-5 py-4 dark:border-gray-800 sm:px-6"
        >
            <h2
                class="text-lg font-semibold text-gray-800 dark:text-white/90"
            >
                Product information
            </h2>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Configure catalogue identity, classification,
                pricing, and operational controls.
            </p>
        </div>

        <div class="space-y-8 p-5 sm:p-6">
            <section class="space-y-5">
                <div>
                    <h3
                        class="text-base font-semibold text-gray-800 dark:text-white/90"
                    >
                        Identity
                    </h3>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Product identifiers remain reserved after deletion.
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="product-name"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Name
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="product-name"
                            v-model="form.name"
                            type="text"
                            maxlength="160"
                            placeholder="Wireless Keyboard"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            :class="form.errors.name
                                ? 'border-error-500'
                                : ''"
                        >

                        <p
                            v-if="form.errors.name"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="product-sku"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            SKU
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="product-sku"
                            v-model="form.sku"
                            type="text"
                            maxlength="80"
                            placeholder="KEYBOARD-001"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm uppercase text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            :class="form.errors.sku
                                ? 'border-error-500'
                                : ''"
                            @blur="
                                form.sku = form.sku
                                    .trim()
                                    .toUpperCase()
                            "
                        >

                        <p
                            class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Letters, numbers, periods, underscores,
                            slashes, and hyphens are supported.
                        </p>

                        <p
                            v-if="form.errors.sku"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.sku }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="product-slug"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Slug
                        </label>

                        <input
                            id="product-slug"
                            v-model="form.slug"
                            type="text"
                            maxlength="180"
                            placeholder="wireless-keyboard"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            :class="form.errors.slug
                                ? 'border-error-500'
                                : ''"
                            @blur="
                                form.slug = normalizeSlug(
                                    form.slug,
                                )
                            "
                        >

                        <p
                            class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Leave blank to generate it from the name.
                        </p>

                        <p
                            v-if="form.errors.slug"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.slug }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="product-barcode"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Barcode
                        </label>

                        <input
                            id="product-barcode"
                            v-model="form.barcode"
                            type="text"
                            maxlength="120"
                            placeholder="8901234567890"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                            :class="form.errors.barcode
                                ? 'border-error-500'
                                : ''"
                        >

                        <p
                            v-if="form.errors.barcode"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.barcode }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="space-y-5 border-t border-gray-200 pt-8 dark:border-gray-800"
            >
                <div>
                    <h3
                        class="text-base font-semibold text-gray-800 dark:text-white/90"
                    >
                        Classification
                    </h3>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Active products can only use active catalogue records.
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label
                            for="product-category"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Category
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="product-category"
                            v-model="form.product_category_id"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="form.errors.product_category_id
                                ? 'border-error-500'
                                : ''"
                        >
                            <option :value="null">
                                Select category
                            </option>

                            <option
                                v-for="option in categoryOptions"
                                :key="option.id"
                                :value="option.id"
                                :disabled="
                                    form.status === 'active'
                                    && option.status === 'inactive'
                                "
                            >
                                {{ option.label }}
                                {{
                                    option.status === 'inactive'
                                        ? ' — Inactive'
                                        : ''
                                }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.product_category_id"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.product_category_id }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="product-brand"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Brand
                        </label>

                        <select
                            id="product-brand"
                            v-model="form.brand_id"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="form.errors.brand_id
                                ? 'border-error-500'
                                : ''"
                        >
                            <option :value="null">
                                No brand
                            </option>

                            <option
                                v-for="option in brandOptions"
                                :key="option.id"
                                :value="option.id"
                                :disabled="
                                    form.status === 'active'
                                    && option.status === 'inactive'
                                "
                            >
                                {{ option.label }}
                                {{
                                    option.status === 'inactive'
                                        ? ' — Inactive'
                                        : ''
                                }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.brand_id"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.brand_id }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="product-unit"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Base unit
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="product-unit"
                            v-model="form.base_unit_id"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="form.errors.base_unit_id
                                ? 'border-error-500'
                                : ''"
                        >
                            <option :value="null">
                                Select unit
                            </option>

                            <option
                                v-for="option in unitOptions"
                                :key="option.id"
                                :value="option.id"
                                :disabled="
                                    form.status === 'active'
                                    && option.status === 'inactive'
                                "
                            >
                                {{ option.label }}
                                {{
                                    option.status === 'inactive'
                                        ? ' — Inactive'
                                        : ''
                                }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.base_unit_id"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.base_unit_id }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="product-type"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Product type
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="product-type"
                            v-model="form.product_type"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="form.errors.product_type
                                ? 'border-error-500'
                                : ''"
                        >
                            <option
                                v-for="option in productTypeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.product_type"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.product_type }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                class="space-y-5 border-t border-gray-200 pt-8 dark:border-gray-800"
            >
                <div>
                    <h3
                        class="text-base font-semibold text-gray-800 dark:text-white/90"
                    >
                        Pricing and status
                    </h3>

                    <p
                        class="mt-1 text-sm text-gray-500 dark:text-gray-400"
                    >
                        Prices support up to six decimal places.
                    </p>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div v-if="canViewCost">
                        <label
                            for="product-cost-price"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Default cost price
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="product-cost-price"
                            v-model="form.cost_price"
                            type="number"
                            min="0"
                            max="99999999999999.999999"
                            step="0.000001"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="form.errors.cost_price
                                ? 'border-error-500'
                                : ''"
                        >

                        <p
                            v-if="form.errors.cost_price"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.cost_price }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="product-selling-price"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Default selling price
                            <span class="text-error-500">*</span>
                        </label>

                        <input
                            id="product-selling-price"
                            v-model="form.selling_price"
                            type="number"
                            min="0"
                            max="99999999999999.999999"
                            step="0.000001"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="form.errors.selling_price
                                ? 'border-error-500'
                                : ''"
                        >

                        <p
                            v-if="form.errors.selling_price"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.selling_price }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="product-status"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400"
                        >
                            Status
                            <span class="text-error-500">*</span>
                        </label>

                        <select
                            id="product-status"
                            v-model="form.status"
                            class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 shadow-theme-xs focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:focus:border-brand-800"
                            :class="form.errors.status
                                ? 'border-error-500'
                                : ''"
                        >
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>

                        <p
                            v-if="form.errors.status"
                            class="mt-1.5 text-sm text-error-500"
                        >
                            {{ form.errors.status }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                    >
                        <input
                            v-model="form.is_purchasable"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
                        >

                        <span>
                            <span
                                class="block text-sm font-medium text-gray-800 dark:text-white/90"
                            >
                                Purchasable
                            </span>

                            <span
                                class="mt-1 block text-xs text-gray-500 dark:text-gray-400"
                            >
                                Allow this product on purchasing documents.
                            </span>
                        </span>
                    </label>

                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 p-4 dark:border-gray-800"
                    >
                        <input
                            v-model="form.is_sellable"
                            type="checkbox"
                            class="mt-0.5 size-4 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900"
                        >

                        <span>
                            <span
                                class="block text-sm font-medium text-gray-800 dark:text-white/90"
                            >
                                Sellable
                            </span>

                            <span
                                class="mt-1 block text-xs text-gray-500 dark:text-gray-400"
                            >
                                Allow this product on sales documents.
                            </span>
                        </span>
                    </label>
                </div>

                <p
                    v-if="form.errors.is_purchasable"
                    class="text-sm text-error-500"
                >
                    {{ form.errors.is_purchasable }}
                </p>

                <p
                    v-if="form.errors.is_sellable"
                    class="text-sm text-error-500"
                >
                    {{ form.errors.is_sellable }}
                </p>
            </section>

            <section
                class="space-y-3 border-t border-gray-200 pt-8 dark:border-gray-800"
            >
                <label
                    for="product-description"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-400"
                >
                    Description
                </label>

                <textarea
                    id="product-description"
                    v-model="form.description"
                    rows="6"
                    maxlength="4000"
                    placeholder="Optional internal product description"
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800"
                    :class="form.errors.description
                        ? 'border-error-500'
                        : ''"
                />

                <div
                    class="flex items-center justify-between gap-4"
                >
                    <p
                        v-if="form.errors.description"
                        class="text-sm text-error-500"
                    >
                        {{ form.errors.description }}
                    </p>

                    <span
                        class="ml-auto text-xs text-gray-500 dark:text-gray-400"
                    >
                        {{ form.description.length }}/4000
                    </span>
                </div>
            </section>
        </div>

        <div
            class="flex flex-col-reverse gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800 sm:flex-row sm:justify-end sm:px-6"
        >
            <Link
                href="/erp/products"
                class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.03]"
            >
                Cancel
            </Link>

            <button
                type="submit"
                class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="form.processing"
            >
                {{
                    form.processing
                        ? 'Saving...'
                        : mode === 'edit'
                            ? 'Update product'
                            : 'Create product'
                }}
            </button>
        </div>
    </form>
</template>