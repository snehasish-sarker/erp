<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import ProductCategoryForm from '@/Pages/ProductCategories/Partials/ProductCategoryForm.vue';
import type {
    ProductCategoryOption,
    ProductCategoryParentOption,
    ProductCategoryStatus,
} from '@/Types/product-category';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    parentOptions: ProductCategoryParentOption[];
    statusOptions:
        ProductCategoryOption<ProductCategoryStatus>[];
}>();
</script>

<template>
    <Head title="Create Product Category" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Create Product Category
            </h1>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Add a root or child category to the
                product catalogue hierarchy.
            </p>
        </div>

        <ProductCategoryForm
            mode="create"
            :parent-options="parentOptions"
            :status-options="statusOptions"
        />
    </div>
</template>