<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { ProductionAcceptanceCheckStatus, ProductionAcceptanceReport } from '@/Types/operations';

defineOptions({ layout: ErpLayout });

const props = defineProps<{ report: ProductionAcceptanceReport }>();
const filter = ref<'all' | ProductionAcceptanceCheckStatus>('all');
const filterOptions: Array<'all' | ProductionAcceptanceCheckStatus> = ['all', 'failed', 'warning', 'passed'];

const visibleChecks = computed(() => {
    if (filter.value === 'all') {
        return props.report.checks;
    }

    return props.report.checks.filter((check) => check.status === filter.value);
});

const groupedChecks = computed(() => {
    const groups: Record<string, typeof props.report.checks> = {};
    for (const check of visibleChecks.value) {
        (groups[check.category] ??= []).push(check);
    }

    return groups;
});

const statusClass = (status: ProductionAcceptanceCheckStatus): string => {
    if (status === 'passed') {
        return 'text-success-600';
    }

    if (status === 'warning') {
        return 'text-warning-600';
    }

    return 'text-error-600';
};
</script>

<template>
    <Head :title="`Production Acceptance #${report.id}`" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Production Acceptance #{{ report.id }}</h1>
                <p class="text-sm text-gray-500">{{ report.uuid }}</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('production-acceptance.index')" class="rounded-lg border px-3 py-2 text-sm">History</Link>
                <Link :href="route('operations.preflight')" class="rounded-lg border px-3 py-2 text-sm">Preflight</Link>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-5">
            <div class="rounded-xl border p-4 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-500">Acceptance</p>
                <p class="mt-1 text-xl font-semibold" :class="report.summary.ready ? 'text-success-600' : 'text-error-600'">
                    {{ report.summary.ready ? 'PASSED' : 'BLOCKED' }}
                </p>
            </div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Checks</p><p class="mt-1 text-xl font-semibold">{{ report.summary.checks }}</p></div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Passed</p><p class="mt-1 text-xl font-semibold text-success-600">{{ report.summary.passed }}</p></div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Warnings</p><p class="mt-1 text-xl font-semibold text-warning-600">{{ report.summary.warnings }}</p></div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Blocking</p><p class="mt-1 text-xl font-semibold text-error-600">{{ report.summary.blocking_failures }}</p></div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="option in filterOptions"
                :key="option"
                type="button"
                class="rounded-lg border px-3 py-2 text-sm capitalize"
                :class="filter === option ? 'bg-gray-100 dark:bg-white/10' : ''"
                @click="filter = option"
            >
                {{ option }}
            </button>
        </div>

        <div v-for="(checks, category) in groupedChecks" :key="category" class="overflow-hidden rounded-xl border dark:border-gray-800">
            <div class="border-b p-4 dark:border-gray-800">
                <h2 class="font-semibold capitalize">{{ category }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <tbody>
                        <tr v-for="check in checks" :key="check.key" class="border-t first:border-t-0 dark:border-gray-800">
                            <td class="w-12 px-4 py-3 text-gray-400">{{ check.sequence }}</td>
                            <td class="px-4 py-3 font-medium">{{ check.label }}</td>
                            <td class="px-4 py-3 font-medium uppercase" :class="statusClass(check.status)">{{ check.status }}</td>
                            <td class="px-4 py-3 text-gray-500">
                                <p>{{ check.message }}</p>
                                <ul v-if="check.remediation.length > 0" class="mt-2 list-disc space-y-1 pl-5 text-xs text-gray-500">
                                    <li v-for="item in check.remediation" :key="item">{{ item }}</li>
                                </ul>
                            </td>
                            <td class="px-4 py-3 text-xs" :class="check.blocking ? 'text-error-600' : 'text-gray-400'">
                                {{ check.blocking ? 'Blocking' : 'Advisory' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border p-4 text-sm dark:border-gray-800">
            <p><span class="font-medium">Environment:</span> {{ report.environment }}</p>
            <p><span class="font-medium">Source:</span> {{ report.source }}</p>
            <p class="break-all"><span class="font-medium">Project fingerprint:</span> <span class="font-mono text-xs">{{ report.project_fingerprint ?? 'Not captured' }}</span></p>
            <p><span class="font-medium">Started by:</span> {{ report.started_by?.name ?? 'CLI/System' }}</p>
            <p><span class="font-medium">Started:</span> {{ report.started_at ?? '—' }}</p>
            <p><span class="font-medium">Completed:</span> {{ report.completed_at ?? '—' }}</p>
        </div>
    </div>
</template>
