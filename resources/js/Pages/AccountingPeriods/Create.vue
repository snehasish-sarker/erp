<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import FiscalYearForm from '@/Pages/AccountingPeriods/Partials/FiscalYearForm.vue';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    timezone: string;
    suggestedStartDate: string;
}>();
</script>

<template>
    <Head title="Generate Fiscal Year" />

    <FiscalYearForm
        :timezone="timezone"
        :suggested-start-date="suggestedStartDate"
    />
</template>