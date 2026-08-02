<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import SupplierForm from '@/Pages/Suppliers/Partials/SupplierForm.vue';
import type {
    SupplierOption,
    SupplierRecord,
    SupplierStatus,
    SupplierType,
} from '@/Types/supplier';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    supplier: SupplierRecord;
    supplierTypeOptions:
        SupplierOption<SupplierType>[];
    statusOptions:
        SupplierOption<SupplierStatus>[];
}>();
</script>

<template>
    <Head :title="`Edit ${supplier.name}`" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Edit Supplier
            </h1>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Update {{ supplier.name }}
                ({{ supplier.code }}).
            </p>
        </div>

        <SupplierForm
            mode="edit"
            :supplier="supplier"
            :supplier-type-options="supplierTypeOptions"
            :status-options="statusOptions"
        />
    </div>
</template>