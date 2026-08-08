<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { FailedJobRow, PaginationMeta } from '@/Types/operations';

defineOptions({ layout: ErpLayout });

const props = defineProps<{ failedJobs: { data: FailedJobRow[]; meta: PaginationMeta } }>();

const goToPage = (page: number): void => {
    if (page < 1 || page > props.failedJobs.meta.last_page) return;
    router.get(route('operations.failed-jobs.index'), { page }, { preserveState: true, replace: true });
};
</script>

<template>
    <Head title="Failed Queue Jobs" />
    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Failed Queue Jobs</h1>
                <p class="text-sm text-gray-500">Read-only sanitized metadata. Payloads, exception messages, retry, and delete actions remain server-console only to preserve tenant isolation.</p>
            </div>
            <Link :href="route('operations.index')" class="rounded-lg border px-3 py-2 text-sm">Operations Dashboard</Link>
        </div>

        <div class="rounded-xl border p-4 text-sm dark:border-gray-800">
            <p class="font-medium">Trusted server commands</p>
            <code class="mt-2 block rounded bg-gray-100 p-3 text-xs dark:bg-white/5">php artisan erp:queue:retry &lt;failed-job-uuid&gt;</code>
            <code class="mt-2 block rounded bg-gray-100 p-3 text-xs dark:bg-white/5">php artisan erp:queue:forget &lt;failed-job-uuid&gt; --force</code>
        </div>

        <div class="overflow-hidden rounded-xl border dark:border-gray-800">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5">
                    <tr><th class="p-3">Job</th><th class="p-3">Queue</th><th class="p-3">Exception</th><th class="p-3">Failed At</th></tr>
                </thead>
                <tbody>
                    <tr v-for="job in failedJobs.data" :key="job.uuid" class="border-t dark:border-gray-800">
                        <td class="p-3"><p class="font-medium">{{ job.job }}</p><p class="font-mono text-xs text-gray-500">{{ job.uuid }}</p></td>
                        <td class="p-3">{{ job.connection }} / {{ job.queue }}</td>
                        <td class="p-3">{{ job.exception_class }}</td>
                        <td class="p-3">{{ job.failed_at }}</td>
                    </tr>
                    <tr v-if="failedJobs.data.length === 0"><td colspan="4" class="p-6 text-center text-gray-500">No failed jobs are currently recorded.</td></tr>
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between text-sm">
            <span class="text-gray-500">{{ failedJobs.meta.from ?? 0 }}–{{ failedJobs.meta.to ?? 0 }} of {{ failedJobs.meta.total }}</span>
            <div class="flex gap-2"><button class="rounded border px-3 py-1.5 disabled:opacity-40" :disabled="failedJobs.meta.current_page <= 1" @click="goToPage(failedJobs.meta.current_page - 1)">Previous</button><button class="rounded border px-3 py-1.5 disabled:opacity-40" :disabled="failedJobs.meta.current_page >= failedJobs.meta.last_page" @click="goToPage(failedJobs.meta.current_page + 1)">Next</button></div>
        </div>
    </div>
</template>
