<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import ProductForm from '@/Pages/Products/Partials/ProductForm.vue';
import type {
    ProductOption,
    ProductRecord,
    ProductRelationOption,
    ProductStatus,
    ProductType,
} from '@/Types/product';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    product: ProductRecord;
    categoryOptions: ProductRelationOption[];
    brandOptions: ProductRelationOption[];
    unitOptions: ProductRelationOption[];
    productTypeOptions: ProductOption<ProductType>[];
    statusOptions: ProductOption<ProductStatus>[];
    canViewCost: boolean;
}>();
</script>

<template>
    <Head :title="`Edit ${product.name}`" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Edit Product
            </h1>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Update {{ product.name }} ({{ product.sku }}).
            </p>
        </div>

        <ProductForm
            mode="edit"
            :product="product"
            :category-options="categoryOptions"
            :brand-options="brandOptions"
            :unit-options="unitOptions"
            :product-type-options="productTypeOptions"
            :status-options="statusOptions"
            :can-view-cost="canViewCost"
        />
    </div>
</template>