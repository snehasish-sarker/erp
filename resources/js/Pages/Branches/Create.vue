<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import BranchForm from '@/Pages/Branches/Partials/BranchForm.vue';
import type { BranchStatusOption } from '@/Types/branch';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    statusOptions: BranchStatusOption[];
}>();
</script>

<template>
    <Head title="Create Branch" />

    <BranchForm
        mode="create"
        :status-options="statusOptions"
    />
</template>