<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { ProductionReadinessReport } from '@/Types/management';

defineOptions({ layout: ErpLayout });
defineProps<{ report: ProductionReadinessReport }>();
const refresh = (): void => router.reload({ only: ['report'] });
</script>
<template>
    <Head title="ERP Production Readiness" />
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="text-2xl font-semibold text-gray-900 dark:text-white">ERP Production Readiness</h1><p class="text-sm text-gray-500">Read-only configuration, schema, accounting-integrity, queue, and deployment checks.</p></div><button class="rounded-lg border px-3 py-2 text-sm" @click="refresh">Run checks again</button></div>
        <div class="grid gap-4 sm:grid-cols-4"><div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Overall</p><p class="mt-1 text-xl font-semibold" :class="report.summary.ready ? 'text-success-600' : 'text-error-600'">{{ report.summary.ready ? 'Ready' : 'Blocked' }}</p></div><div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Passed</p><p class="mt-1 text-xl font-semibold">{{ report.summary.passed }}/{{ report.summary.checks }}</p></div><div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Blocking failures</p><p class="mt-1 text-xl font-semibold">{{ report.summary.blocking_failures }}</p></div><div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Warnings</p><p class="mt-1 text-xl font-semibold">{{ report.summary.warnings }}</p></div></div>
        <div class="overflow-hidden rounded-xl border dark:border-gray-800"><table class="min-w-full text-sm"><thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="p-3">Check</th><th class="p-3">Status</th><th class="p-3">Severity</th><th class="p-3">Details</th></tr></thead><tbody><tr v-for="check in report.checks" :key="check.key" class="border-t dark:border-gray-800"><td class="p-3 font-medium">{{ check.label }}</td><td class="p-3"><span :class="check.status === 'passed' ? 'text-success-600' : 'text-error-600'">{{ check.status }}</span></td><td class="p-3">{{ check.blocking ? 'Blocking' : 'Advisory' }}</td><td class="p-3 text-gray-500">{{ check.message }}</td></tr></tbody></table></div>
        <div class="rounded-xl border p-4 dark:border-gray-800"><h2 class="mb-3 font-semibold">Deployment and acceptance checklist</h2><ol class="space-y-2 text-sm text-gray-600 dark:text-gray-300"><li v-for="(item, index) in report.deployment_checklist" :key="item" class="flex gap-3"><span class="font-medium text-gray-400">{{ index + 1 }}.</span><span>{{ item }}</span></li></ol></div>
        <p class="text-xs text-gray-500">Environment: {{ report.environment }} · Generated {{ report.generated_at }}</p>
    </div>
</template>
