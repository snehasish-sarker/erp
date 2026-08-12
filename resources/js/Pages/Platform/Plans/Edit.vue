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

const props = defineProps<Required<PlatformSaasPlanFormProps>>();
</script>

<template>
    <Head :title="`Edit Plan - ${props.plan.name}`" />

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">
                Edit {{ props.plan.name }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Changes affect entitlement checks for every tenant currently assigned to this plan.
            </p>
        </div>

        <PlanForm
            mode="edit"
            :features="props.features"
            :plan="props.plan"
        />
    </div>
</template>
