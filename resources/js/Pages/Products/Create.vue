<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import ProductForm from '@/Pages/Products/Partials/ProductForm.vue';
import type {
    ProductOption,
    ProductRelationOption,
    ProductStatus,
    ProductType,
} from '@/Types/product';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    categoryOptions: ProductRelationOption[];
    brandOptions: ProductRelationOption[];
    unitOptions: ProductRelationOption[];
    productTypeOptions: ProductOption<ProductType>[];
    statusOptions: ProductOption<ProductStatus>[];
    canViewCost: boolean;
}>();
</script>

<template>
    <Head title="Create Product" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Create Product
            </h1>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Add a catalogue product for future purchasing,
                sales, and inventory configuration.
            </p>
        </div>

        <ProductForm
            mode="create"
            :category-options="categoryOptions"
            :brand-options="brandOptions"
            :unit-options="unitOptions"
            :product-type-options="productTypeOptions"
            :status-options="statusOptions"
            :can-view-cost="canViewCost"
        />
    </div>
</template>