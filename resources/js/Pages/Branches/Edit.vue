<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import BranchForm from '@/Pages/Branches/Partials/BranchForm.vue';
import type {
    BranchRecord,
    BranchStatusOption,
} from '@/Types/branch';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    branch: BranchRecord;
    statusOptions: BranchStatusOption[];
}>();
</script>

<template>
    <Head :title="`Edit ${branch.name}`" />

    <BranchForm
        mode="edit"
        :branch="branch"
        :status-options="statusOptions"
    />
</template>