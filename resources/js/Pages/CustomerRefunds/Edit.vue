<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { CustomerRefundFormProps } from '@/Types/customer-settlement';
import CustomerRefundForm from './Partials/CustomerRefundForm.vue';
defineOptions({ layout: ErpLayout });
const props = defineProps<CustomerRefundFormProps>();
</script>
<template><Head title="Edit Customer Refund" /><div class="space-y-6"><div class="flex items-center justify-between"><div><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Edit Customer Refund</h1><p class="mt-1 text-sm text-gray-500">Revision {{ props.document?.revision ?? 1 }}</p></div><Link :href="route('customer-refunds.show', props.document?.id)" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm dark:border-gray-700">View Refund</Link></div><CustomerRefundForm v-bind="props" /></div></template>
