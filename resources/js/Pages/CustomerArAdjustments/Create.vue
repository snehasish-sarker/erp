<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { CustomerArAdjustmentFormProps } from '@/Types/customer-settlement';
import CustomerArAdjustmentForm from './Partials/CustomerArAdjustmentForm.vue';
defineOptions({ layout: ErpLayout });
const props = defineProps<CustomerArAdjustmentFormProps>();
</script>
<template><Head title="Create AR Adjustment" /><div class="space-y-6"><div class="flex items-center justify-between"><div><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Create Customer AR Adjustment</h1><p class="mt-1 text-sm text-gray-500">Post controlled non-invoice receivable or customer-credit adjustments.</p></div><Link :href="route('customer-ar-adjustments.index')" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm dark:border-gray-700">Back</Link></div><CustomerArAdjustmentForm v-bind="props" /></div></template>
