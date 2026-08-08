<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { PaginationMeta, SystemBackupRow } from '@/Types/operations';

defineOptions({ layout: ErpLayout });

const props = defineProps<{
    backups: { data: SystemBackupRow[]; meta: PaginationMeta };
    configuration: { enabled: boolean; retention_days: number; minimum_keep: number; schedule_time: string };
}>();

const verify = (backup: SystemBackupRow): void => {
    if (!backup.can_verify || !window.confirm(`Verify backup ${backup.filename}?`)) return;
    router.post(route('operations.backups.verify', backup.id), {}, { preserveScroll: true });
};

const goToPage = (page: number): void => {
    if (page < 1 || page > props.backups.meta.last_page) return;
    router.get(route('operations.backups.index'), { page }, { preserveState: true, replace: true });
};

const bytes = (value: number | null): string => {
    if (value === null) return '—';
    if (value < 1024 ** 2) return `${(value / 1024).toFixed(1)} KB`;
    if (value < 1024 ** 3) return `${(value / 1024 ** 2).toFixed(1)} MB`;
    return `${(value / 1024 ** 3).toFixed(2)} GB`;
};
</script>

<template>
    <Head title="Database Backups" />
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h1 class="text-2xl font-semibold">Database Backups</h1><p class="text-sm text-gray-500">Backup creation and pruning are CLI-only; this screen exposes sanitized metadata and verification controls.</p></div>
            <Link :href="route('operations.index')" class="rounded-lg border px-3 py-2 text-sm">Operations Dashboard</Link>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Scheduled</p><p class="mt-1 font-semibold">{{ configuration.enabled ? 'Enabled' : 'Disabled' }}</p></div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Schedule</p><p class="mt-1 font-semibold">{{ configuration.schedule_time }}</p></div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Retention</p><p class="mt-1 font-semibold">{{ configuration.retention_days }} days</p></div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Minimum Keep</p><p class="mt-1 font-semibold">{{ configuration.minimum_keep }}</p></div>
        </div>

        <div class="rounded-xl border p-4 text-sm dark:border-gray-800">
            <p class="font-medium">Server commands</p>
            <code class="mt-2 block rounded bg-gray-100 p-3 text-xs dark:bg-white/5">php artisan erp:backup:create --verify</code>
            <code class="mt-2 block rounded bg-gray-100 p-3 text-xs dark:bg-white/5">php artisan erp:backup:prune</code>
        </div>

        <div class="overflow-hidden rounded-xl border dark:border-gray-800">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5"><tr><th class="p-3">Backup</th><th class="p-3">Size</th><th class="p-3">Status</th><th class="p-3">Verification</th><th class="p-3">Completed</th><th class="p-3 text-right">Action</th></tr></thead>
                <tbody>
                    <tr v-for="backup in backups.data" :key="backup.id" class="border-t dark:border-gray-800">
                        <td class="p-3"><p class="font-medium">{{ backup.filename }}</p><p class="text-xs text-gray-500">{{ backup.initiated_by }} · #{{ backup.id }}</p></td>
                        <td class="p-3">{{ bytes(backup.size_bytes) }}</td>
                        <td class="p-3">{{ backup.status }}</td>
                        <td class="p-3"><p>{{ backup.verification_status }}</p><p v-if="backup.verification_message" class="max-w-md text-xs text-gray-500">{{ backup.verification_message }}</p></td>
                        <td class="p-3">{{ backup.completed_at ?? '—' }}</td>
                        <td class="p-3 text-right"><button v-if="backup.can_verify" class="rounded border px-3 py-1.5 text-xs" @click="verify(backup)">Verify</button></td>
                    </tr>
                    <tr v-if="backups.data.length === 0"><td colspan="6" class="p-6 text-center text-gray-500">No backup metadata has been recorded.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500">{{ backups.meta.from ?? 0 }}–{{ backups.meta.to ?? 0 }} of {{ backups.meta.total }}</span>
            <div class="flex gap-2"><button class="rounded border px-3 py-1.5 disabled:opacity-40" :disabled="backups.meta.current_page <= 1" @click="goToPage(backups.meta.current_page - 1)">Previous</button><button class="rounded border px-3 py-1.5 disabled:opacity-40" :disabled="backups.meta.current_page >= backups.meta.last_page" @click="goToPage(backups.meta.current_page + 1)">Next</button></div>
        </div>
    </div>
</template>
