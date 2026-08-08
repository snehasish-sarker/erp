<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { PaginationMeta, ProductionAcceptanceRunRow } from '@/Types/operations';

defineOptions({ layout: ErpLayout });

const props = defineProps<{
    runs: {
        data: ProductionAcceptanceRunRow[];
        meta: PaginationMeta;
    };
    can_run: boolean;
}>();

const running = ref(false);

const runAcceptance = (): void => {
    if (running.value || !props.can_run) {
        return;
    }

    if (!window.confirm('Run the full production acceptance audit now? This is read-only for business data and stores only the audit result.')) {
        return;
    }

    running.value = true;
    router.post(
        route('production-acceptance.store'),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                running.value = false;
            },
        },
    );
};

const pageUrl = (page: number): string => route('production-acceptance.index', { page });
</script>

<template>
    <Head title="Production Acceptance" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Production Acceptance</h1>
                <p class="text-sm text-gray-500">
                    Persisted final cross-module acceptance runs for accounting integrity, tenancy, routes, permissions, inventory, and operations health.
                </p>
            </div>

            <div class="flex gap-2">
                <Link :href="route('operations.preflight')" class="rounded-lg border px-3 py-2 text-sm">
                    Deployment Preflight
                </Link>
                <button
                    v-if="can_run"
                    type="button"
                    class="rounded-lg bg-brand-500 px-3 py-2 text-sm font-medium text-white disabled:opacity-50"
                    :disabled="running"
                    @click="runAcceptance"
                >
                    {{ running ? 'Running…' : 'Run Acceptance' }}
                </button>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border dark:border-gray-800">
            <div class="border-b p-4 dark:border-gray-800">
                <h2 class="font-semibold">Acceptance History</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/[0.02]">
                        <tr>
                            <th class="px-4 py-3">Run</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Checks</th>
                            <th class="px-4 py-3">Warnings</th>
                            <th class="px-4 py-3">Blocking</th>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3">Started</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="run in runs.data" :key="run.id" class="border-t dark:border-gray-800">
                            <td class="px-4 py-3 font-medium">#{{ run.id }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="font-medium"
                                    :class="run.status === 'passed' ? 'text-success-600' : run.status === 'running' ? 'text-warning-600' : 'text-error-600'"
                                >
                                    {{ run.status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ run.passed_checks }}/{{ run.total_checks }}</td>
                            <td class="px-4 py-3">{{ run.warning_checks }}</td>
                            <td class="px-4 py-3">{{ run.blocking_failures }}</td>
                            <td class="px-4 py-3">{{ run.source }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ run.started_at ?? '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('production-acceptance.show', run.id)" class="text-brand-600 hover:underline">
                                    View
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="runs.data.length === 0">
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">No acceptance runs have been recorded yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="runs.meta.last_page > 1" class="flex items-center justify-between text-sm">
            <Link
                v-if="runs.meta.current_page > 1"
                :href="pageUrl(runs.meta.current_page - 1)"
                class="rounded-lg border px-3 py-2"
            >
                Previous
            </Link>
            <span v-else></span>
            <span class="text-gray-500">Page {{ runs.meta.current_page }} of {{ runs.meta.last_page }}</span>
            <Link
                v-if="runs.meta.current_page < runs.meta.last_page"
                :href="pageUrl(runs.meta.current_page + 1)"
                class="rounded-lg border px-3 py-2"
            >
                Next
            </Link>
            <span v-else></span>
        </div>
    </div>
</template>
