<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import CustomerForm from '@/Pages/Customers/Partials/CustomerForm.vue';
import type {
    CustomerOption,
    CustomerRecord,
    CustomerStatus,
    CustomerType,
} from '@/Types/customer';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    customer: CustomerRecord;
    customerTypeOptions:
        CustomerOption<CustomerType>[];
    statusOptions:
        CustomerOption<CustomerStatus>[];
    canManageCreditLimit: boolean;
}>();
</script>

<template>
    <Head :title="`Edit ${customer.name}`" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Edit Customer
            </h1>

            <p
                class="mt-1 text-sm text-gray-500 dark:text-gray-400"
            >
                Update {{ customer.name }}
                ({{ customer.code }}).
            </p>
        </div>

        <CustomerForm
            mode="edit"
            :customer="customer"
            :customer-type-options="customerTypeOptions"
            :status-options="statusOptions"
            :can-manage-credit-limit="canManageCreditLimit"
        />
    </div>
</template>