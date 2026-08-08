<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { DeploymentPreflightReport } from '@/Types/operations';

defineOptions({ layout: ErpLayout });

defineProps<{ report: DeploymentPreflightReport }>();
const refresh = (): void => router.reload({ only: ['report'] });
</script>

<template>
    <Head title="Deployment Preflight" />
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h1 class="text-2xl font-semibold">Deployment Preflight</h1><p class="text-sm text-gray-500">Combined ERP readiness, live operations health, database diagnostics, cutover, and post-deployment controls.</p></div><div class="flex gap-2"><Link :href="route('operations.index')" class="rounded-lg border px-3 py-2 text-sm">Operations</Link><Link :href="route('production-acceptance.index')" class="rounded-lg border px-3 py-2 text-sm">Acceptance</Link><Link :href="route('release-candidates.index')" class="rounded-lg border px-3 py-2 text-sm">Release Candidates</Link><button class="rounded-lg border px-3 py-2 text-sm" @click="refresh">Run Again</button></div></div>
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Deployment</p><p class="mt-1 text-xl font-semibold" :class="report.ready ? 'text-success-600' : 'text-error-600'">{{ report.ready ? 'Ready' : 'Blocked' }}</p></div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Readiness Blocks</p><p class="mt-1 text-xl font-semibold">{{ report.production_readiness.summary.blocking_failures }}</p></div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Health Failures</p><p class="mt-1 text-xl font-semibold">{{ report.operations_health.summary.critical_failures }}</p></div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Warnings</p><p class="mt-1 text-xl font-semibold">{{ report.production_readiness.summary.warnings + report.operations_health.summary.warnings + report.security.summary.warnings }}</p></div>
        </div>
        <div class="grid gap-4 xl:grid-cols-2">
            <div class="overflow-hidden rounded-xl border dark:border-gray-800"><div class="border-b p-4 dark:border-gray-800"><h2 class="font-semibold">Production Readiness</h2></div><table class="min-w-full text-sm"><tbody><tr v-for="check in report.production_readiness.checks" :key="check.key" class="border-t dark:border-gray-800"><td class="p-3 font-medium">{{ check.label }}</td><td class="p-3" :class="check.status === 'passed' ? 'text-success-600' : 'text-error-600'">{{ check.status }}</td><td class="p-3 text-gray-500">{{ check.message }}</td></tr></tbody></table></div>
            <div class="overflow-hidden rounded-xl border dark:border-gray-800"><div class="border-b p-4 dark:border-gray-800"><h2 class="font-semibold">Operations Health</h2></div><table class="min-w-full text-sm"><tbody><tr v-for="check in report.operations_health.checks" :key="check.key" class="border-t dark:border-gray-800"><td class="p-3 font-medium">{{ check.label }}</td><td class="p-3" :class="check.status === 'passed' ? 'text-success-600' : 'text-error-600'">{{ check.status }}</td><td class="p-3 text-gray-500">{{ check.message }}</td></tr></tbody></table></div>
        </div>
        <div class="overflow-hidden rounded-xl border dark:border-gray-800"><div class="border-b p-4 dark:border-gray-800"><h2 class="font-semibold">Security Hardening</h2></div><table class="min-w-full text-sm"><tbody><tr v-for="check in report.security.checks" :key="check.key" class="border-t dark:border-gray-800"><td class="p-3 font-medium">{{ check.label }}</td><td class="p-3" :class="check.passed ? 'text-success-600' : 'text-error-600'">{{ check.passed ? 'passed' : 'failed' }}</td><td class="p-3 text-gray-500">{{ check.message }}</td></tr></tbody></table></div>
        <div class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-xl border p-4 dark:border-gray-800"><h2 class="mb-3 font-semibold">Production Cutover Checklist</h2><ol class="space-y-3 text-sm"><li v-for="(item, index) in report.cutover_checklist" :key="item.key" class="flex gap-3"><span class="text-gray-400">{{ index + 1 }}.</span><div><p>{{ item.label }}</p><p class="text-xs text-gray-500">Owner: {{ item.owner }}</p></div></li></ol></div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><h2 class="mb-3 font-semibold">Post-Deployment Verification</h2><ol class="space-y-3 text-sm"><li v-for="(item, index) in report.post_deployment_checklist" :key="item.key" class="flex gap-3"><span class="text-gray-400">{{ index + 1 }}.</span><div><p>{{ item.label }}</p><p class="text-xs text-gray-500">Owner: {{ item.owner }}</p></div></li></ol></div>
        </div>
        <div class="rounded-xl border p-4 text-sm dark:border-gray-800"><p class="font-medium">Recommended server sequence</p><code class="mt-2 block rounded bg-gray-100 p-3 text-xs dark:bg-white/5">php artisan erp:backup:create --verify<br>php artisan erp:deploy:preflight<br>php artisan erp:maintenance enable<br>php artisan migrate --force<br>npm run build<br>php artisan optimize<br>php artisan queue:restart<br>php artisan erp:maintenance disable<br>php artisan erp:health<br>php artisan erp:acceptance<br>php artisan erp:release:freeze 1.0.0-rc.1 --tenant=TENANT_ID<br>php artisan erp:release:verify --tenant=TENANT_ID</code></div>
        <p class="text-xs text-gray-500">Environment: {{ report.environment }} · Generated {{ report.generated_at }}</p>
    </div>
</template>
