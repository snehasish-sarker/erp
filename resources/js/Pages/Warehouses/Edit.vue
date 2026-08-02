<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import WarehouseForm from '@/Pages/Warehouses/Partials/WarehouseForm.vue';
import type {
    WarehouseBranch,
    WarehouseRecord,
    WarehouseStatusOption,
    WarehouseTypeOption,
} from '@/Types/warehouse';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    warehouse: WarehouseRecord;
    branchOptions: WarehouseBranch[];
    typeOptions: WarehouseTypeOption[];
    statusOptions: WarehouseStatusOption[];
}>();
</script>

<template>
    <Head :title="`Edit ${warehouse.name}`" />

    <WarehouseForm
        mode="edit"
        :warehouse="warehouse"
        :branch-options="branchOptions"
        :type-options="typeOptions"
        :status-options="statusOptions"
    />
</template>