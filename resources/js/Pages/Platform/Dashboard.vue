<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';
import type {
    PlatformDashboardProps,
} from '@/Types/platform-admin';

defineOptions({
    layout: PlatformAdminLayout,
});

const props = defineProps<PlatformDashboardProps>();

const cards = [
    {
        label: 'Total tenants',
        value: props.metrics.tenants_total,
    },
    {
        label: 'Active tenants',
        value: props.metrics.tenants_active,
    },
    {
        label: 'Trial tenants',
        value: props.metrics.tenants_trial,
    },
    {
        label: 'Suspended tenants',
        value: props.metrics.tenants_suspended,
    },
    {
        label: 'Past-due tenants',
        value: props.metrics.tenants_past_due,
    },
    {
        label: 'Tenant users',
        value: props.metrics.tenant_users_total,
    },
];
</script>

<template>
    <Head title="Super Admin Dashboard" />

    <div class="space-y-6">
        <div>
            <p
                class="text-sm font-medium text-brand-600 dark:text-brand-400"
            >
                Platform control plane
            </p>

            <h1
                class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"
            >
                Super Admin Dashboard
            </h1>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Signed in as {{ platformAdmin.name }} ({{ platformAdmin.email }}).
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="card in cards"
                :key="card.label"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
            >
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ card.label }}
                </p>

                <p
                    class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white"
                >
                    {{ card.value }}
                </p>
            </div>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]"
        >
            <h2
                class="text-lg font-semibold text-gray-900 dark:text-white"
            >
                Foundation ready
            </h2>

            <p class="mt-2 text-sm leading-6 text-gray-500 dark:text-gray-400">
                This console is authenticated independently from tenant ERP users.
                Tenant management, SaaS plans, subscriptions, billing, and platform
                operations will be added as separate modules.
            </p>
        </div>
    </div>
</template>
