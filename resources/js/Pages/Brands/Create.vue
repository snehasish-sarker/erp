<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import BrandForm from '@/Pages/Brands/Partials/BrandForm.vue';
import type {
    BrandOption,
    BrandStatus,
} from '@/Types/brand';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    statusOptions: BrandOption<BrandStatus>[];
}>();
</script>

<template>
    <Head title="Create Brand" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Create Brand
            </h1>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Add a manufacturer or commercial brand
                for future product catalogue records.
            </p>
        </div>

        <BrandForm
            mode="create"
            :status-options="statusOptions"
        />
    </div>
</template>