<script setup lang="ts">
import {
    Head,
} from '@inertiajs/vue3';
import PlatformAdminLayout from '@/Layouts/PlatformAdminLayout.vue';
import PlanForm from '@/Pages/Platform/Plans/Partials/PlanForm.vue';
import type {
    PlatformSaasPlanFormProps,
} from '@/Types/platform-admin';

defineOptions({
    layout: PlatformAdminLayout,
});

defineProps<PlatformSaasPlanFormProps>();
</script>

<template>
    <Head title="Create SaaS Plan" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                Create SaaS Plan
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Define a reusable plan and its module entitlements and limits.
            </p>
        </div>

        <PlanForm
            mode="create"
            :features="features"
        />
    </div>
</template>
