<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { CustomerCreditApplicationFormProps } from '@/Types/customer-settlement';
import CustomerCreditApplicationForm from './Partials/CustomerCreditApplicationForm.vue';
defineOptions({ layout: ErpLayout });
const props = defineProps<CustomerCreditApplicationFormProps>();
</script>
<template><Head title="Create Credit Application" /><div class="space-y-6"><div class="flex items-center justify-between"><div><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Create Customer Credit Application</h1><p class="mt-1 text-sm text-gray-500">Apply existing customer credits to outstanding invoices or debit adjustments.</p></div><Link :href="route('customer-credit-applications.index')" class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm dark:border-gray-700">Back</Link></div><CustomerCreditApplicationForm v-bind="props" /></div></template>
