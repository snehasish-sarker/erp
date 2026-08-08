<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { OperationsDashboard } from '@/Types/operations';

defineOptions({ layout: ErpLayout });

const props = defineProps<{ dashboard: OperationsDashboard }>();

const refresh = (): void => {
    router.reload({ only: ['dashboard'] });
};

const bytes = (value: number | null): string => {
    if (value === null) return '—';
    if (value < 1024) return `${value} B`;
    if (value < 1024 ** 2) return `${(value / 1024).toFixed(1)} KB`;
    if (value < 1024 ** 3) return `${(value / 1024 ** 2).toFixed(1)} MB`;
    return `${(value / 1024 ** 3).toFixed(2)} GB`;
};
</script>

<template>
    <Head title="System Operations" />
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">System Operations</h1>
                <p class="text-sm text-gray-500">Live application, queue, scheduler, storage, backup, and database diagnostics.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Link :href="route('operations.preflight')" class="rounded-lg border px-3 py-2 text-sm">Deployment Preflight</Link>
                <button class="rounded-lg border px-3 py-2 text-sm" @click="refresh">Refresh Health</button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-7">
            <div class="rounded-xl border p-4 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-500">Health</p>
                <p class="mt-1 text-xl font-semibold" :class="props.dashboard.health.summary.healthy ? 'text-success-600' : 'text-error-600'">
                    {{ props.dashboard.health.summary.healthy ? 'Healthy' : 'Action Required' }}
                </p>
            </div>
            <div class="rounded-xl border p-4 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-500">Queued Jobs</p>
                <p class="mt-1 text-xl font-semibold">{{ props.dashboard.queue.queued }}</p>
            </div>
            <div class="rounded-xl border p-4 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-500">Failed Jobs</p>
                <p class="mt-1 text-xl font-semibold">{{ props.dashboard.queue.failed }}</p>
            </div>
            <div class="rounded-xl border p-4 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-500">Storage Used</p>
                <p class="mt-1 text-xl font-semibold">{{ props.dashboard.health.metrics.storage_used_percent ?? '—' }}<span v-if="props.dashboard.health.metrics.storage_used_percent !== null">%</span></p>
            </div>
            <div class="rounded-xl border p-4 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-500">Last Backup</p>
                <p class="mt-1 truncate text-sm font-semibold">{{ props.dashboard.backup?.filename ?? 'None' }}</p>
                <p class="text-xs text-gray-500">{{ props.dashboard.backup?.verification_status ?? 'Not available' }}</p>
            </div>
            <div class="rounded-xl border p-4 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-500">Acceptance</p>
                <p
                    class="mt-1 text-xl font-semibold"
                    :class="props.dashboard.acceptance?.status === 'passed' ? 'text-success-600' : 'text-error-600'"
                >
                    {{ props.dashboard.acceptance?.status ?? 'Not Run' }}
                </p>
                <p class="text-xs text-gray-500">{{ props.dashboard.acceptance ? `${props.dashboard.acceptance.blocking_failures} blocking` : 'Run before cutover' }}</p>
            </div>
            <div class="rounded-xl border p-4 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-500">Release Candidate</p>
                <p
                    class="mt-1 text-xl font-semibold"
                    :class="props.dashboard.release_candidate?.verification_status === 'matched' ? 'text-success-600' : 'text-error-600'"
                >
                    {{ props.dashboard.release_candidate?.version ?? 'Not Frozen' }}
                </p>
                <p class="text-xs text-gray-500">{{ props.dashboard.release_candidate?.verification_status ?? 'Freeze after acceptance' }}</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-5">
            <Link :href="route('operations.backups.index')" class="rounded-xl border p-4 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                <h2 class="font-semibold">Database Backups</h2>
                <p class="mt-1 text-sm text-gray-500">Review sanitized backup metadata and run integrity verification.</p>
            </Link>
            <Link :href="route('operations.failed-jobs.index')" class="rounded-xl border p-4 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                <h2 class="font-semibold">Failed Jobs</h2>
                <p class="mt-1 text-sm text-gray-500">Review sanitized failed-job metadata; retry and removal remain CLI-only operations.</p>
            </Link>
            <Link :href="route('management.production-readiness')" class="rounded-xl border p-4 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                <h2 class="font-semibold">ERP Readiness</h2>
                <p class="mt-1 text-sm text-gray-500">Review schema, accounting integrity, routes, configuration, and deployment blockers.</p>
            </Link>
            <Link :href="route('production-acceptance.index')" class="rounded-xl border p-4 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                <h2 class="font-semibold">Production Acceptance</h2>
                <p class="mt-1 text-sm text-gray-500">Run the persisted final tenant, accounting, inventory, permission, and operations acceptance gate.</p>
            </Link>
            <Link :href="route('release-candidates.index')" class="rounded-xl border p-4 transition hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/5">
                <h2 class="font-semibold">Release Candidates</h2>
                <p class="mt-1 text-sm text-gray-500">Freeze and verify the exact accepted source, routes, migrations, permissions, dependencies, and build.</p>
            </Link>
        </div>

        <div class="overflow-hidden rounded-xl border dark:border-gray-800">
            <div class="border-b p-4 dark:border-gray-800"><h2 class="font-semibold">Live Health Checks</h2></div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5">
                    <tr><th class="p-3">Check</th><th class="p-3">Status</th><th class="p-3">Severity</th><th class="p-3">Details</th></tr>
                </thead>
                <tbody>
                    <tr v-for="check in props.dashboard.health.checks" :key="check.key" class="border-t dark:border-gray-800">
                        <td class="p-3 font-medium">{{ check.label }}</td>
                        <td class="p-3" :class="check.status === 'passed' ? 'text-success-600' : 'text-error-600'">{{ check.status }}</td>
                        <td class="p-3">{{ check.critical ? 'Critical' : 'Advisory' }}</td>
                        <td class="p-3 text-gray-500">{{ check.message }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="overflow-hidden rounded-xl border dark:border-gray-800">
                <div class="border-b p-4 dark:border-gray-800">
                    <h2 class="font-semibold">Largest Database Tables</h2>
                    <p class="text-xs text-gray-500">Database size: {{ bytes(props.dashboard.performance.database_size_bytes) }}</p>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="p-3">Table</th><th class="p-3">Rows</th><th class="p-3">Data</th><th class="p-3">Indexes</th></tr></thead>
                    <tbody><tr v-for="row in props.dashboard.performance.top_tables" :key="row.table" class="border-t dark:border-gray-800"><td class="p-3 font-medium">{{ row.table }}</td><td class="p-3">{{ row.estimated_rows }}</td><td class="p-3">{{ bytes(row.data_bytes) }}</td><td class="p-3">{{ bytes(row.index_bytes) }}</td></tr></tbody>
                </table>
            </div>

            <div class="overflow-hidden rounded-xl border dark:border-gray-800">
                <div class="border-b p-4 dark:border-gray-800"><h2 class="font-semibold">Recent Operations Audit</h2></div>
                <div v-if="props.dashboard.recent_operations_audit.length === 0" class="p-4 text-sm text-gray-500">No operations audit events yet.</div>
                <div v-for="event in props.dashboard.recent_operations_audit" :key="event.id" class="border-t p-4 text-sm first:border-t-0 dark:border-gray-800">
                    <p class="font-medium">{{ event.event.replaceAll('_', ' ') }}</p>
                    <p class="text-xs text-gray-500">{{ event.actor_name ?? 'System' }} · {{ event.created_at ?? '—' }}</p>
                </div>
            </div>
        </div>
    </div>
</template>
