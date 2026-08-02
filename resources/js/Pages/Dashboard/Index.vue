<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';

defineOptions({
    layout: ErpLayout,
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold text-gray-800 dark:text-white/90"
            >
                Dashboard
            </h1>

            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Wholesale and Distribution ERP overview
            </p>
        </div>

        <div
            class="rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6"
        >
            <h2
                class="text-lg font-semibold text-gray-800 dark:text-white/90"
            >
                ERP foundation is ready
            </h2>

            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Laravel, Inertia.js, Vue 3, TypeScript, Tailwind CSS,
                and the TailAdmin application layout are connected.
            </p>
        </div>
    </div>
</template>