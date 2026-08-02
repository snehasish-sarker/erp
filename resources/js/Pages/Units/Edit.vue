<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import UnitForm from '@/Pages/Units/Partials/UnitForm.vue';
import type {
    UnitCategory,
    UnitOption,
    UnitRecord,
    UnitStatus,
} from '@/Types/unit';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    unit: UnitRecord;
    categoryOptions:
        UnitOption<UnitCategory>[];
    statusOptions:
        UnitOption<UnitStatus>[];
}>();
</script>

<template>
    <Head :title="`Edit ${unit.name}`" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Edit Unit
            </h1>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Update {{ unit.name }} ({{ unit.code }}).
            </p>
        </div>

        <UnitForm
            mode="edit"
            :unit="unit"
            :category-options="categoryOptions"
            :status-options="statusOptions"
        />
    </div>
</template>