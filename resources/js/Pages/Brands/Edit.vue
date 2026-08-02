<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import BrandForm from '@/Pages/Brands/Partials/BrandForm.vue';
import type {
    BrandOption,
    BrandRecord,
    BrandStatus,
} from '@/Types/brand';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    brand: BrandRecord;
    statusOptions: BrandOption<BrandStatus>[];
}>();
</script>

<template>
    <Head :title="`Edit ${brand.name}`" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Edit Brand
            </h1>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Update {{ brand.name }}
                ({{ brand.code }}).
            </p>
        </div>

        <BrandForm
            mode="edit"
            :brand="brand"
            :status-options="statusOptions"
        />
    </div>
</template>