<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import ProductCategoryForm from '@/Pages/ProductCategories/Partials/ProductCategoryForm.vue';
import type {
    ProductCategoryOption,
    ProductCategoryParentOption,
    ProductCategoryRecord,
    ProductCategoryStatus,
} from '@/Types/product-category';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    category: ProductCategoryRecord;
    parentOptions: ProductCategoryParentOption[];
    statusOptions:
        ProductCategoryOption<ProductCategoryStatus>[];
}>();
</script>

<template>
    <Head :title="`Edit ${category.name}`" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Edit Product Category
            </h1>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Update {{ category.name }}
                ({{ category.code }}).
            </p>
        </div>

        <ProductCategoryForm
            mode="edit"
            :category="category"
            :parent-options="parentOptions"
            :status-options="statusOptions"
        />
    </div>
</template>