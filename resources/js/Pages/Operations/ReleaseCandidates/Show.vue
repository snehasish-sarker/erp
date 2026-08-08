<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { ReleaseCandidateReport } from '@/Types/operations';

defineOptions({ layout: ErpLayout });

const props = defineProps<{
    candidate: ReleaseCandidateReport;
    can_verify: boolean;
}>();

const verify = (): void => {
    if (!window.confirm('Recompute the current project fingerprint and compare it with this frozen candidate?')) {
        return;
    }

    router.post(route('release-candidates.verify', props.candidate.id), {}, { preserveScroll: true });
};

const shortHash = (value: string | null): string => value === null ? '—' : value.slice(0, 16);
</script>

<template>
    <Head :title="`Release Candidate ${candidate.version}`" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Release Candidate {{ candidate.version }}</h1>
                <p class="font-mono text-xs text-gray-500">{{ candidate.project_fingerprint }}</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('release-candidates.index')" class="rounded-lg border px-3 py-2 text-sm">History</Link>
                <button v-if="props.can_verify" type="button" class="rounded-lg border px-3 py-2 text-sm" @click="verify">Verify Fingerprint</button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-xl border p-4 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-500">Candidate</p>
                <p class="mt-1 text-xl font-semibold capitalize">{{ candidate.status }}</p>
            </div>
            <div class="rounded-xl border p-4 dark:border-gray-800">
                <p class="text-xs uppercase text-gray-500">Verification</p>
                <p class="mt-1 text-xl font-semibold" :class="candidate.verification_status === 'matched' ? 'text-success-600' : 'text-error-600'">{{ candidate.verification_status }}</p>
            </div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Environment</p><p class="mt-1 text-xl font-semibold">{{ candidate.environment }}</p></div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Source</p><p class="mt-1 text-xl font-semibold uppercase">{{ candidate.source }}</p></div>
            <div class="rounded-xl border p-4 dark:border-gray-800"><p class="text-xs uppercase text-gray-500">Git Commit</p><p class="mt-1 font-mono text-sm font-semibold">{{ shortHash(candidate.git_commit) }}</p></div>
        </div>

        <div v-if="candidate.verification_status === 'drifted'" class="rounded-xl border border-error-300 bg-error-50 p-4 dark:bg-error-950/20">
            <h2 class="font-semibold text-error-700 dark:text-error-400">Release candidate drift detected</h2>
            <p class="mt-1 text-sm text-error-700 dark:text-error-400">Do not deploy this candidate as-is. Rerun production acceptance against the new project state, then freeze a new version.</p>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-sm">
                <li v-for="artifact in candidate.verification_summary?.drifted_artifacts ?? []" :key="artifact.key">
                    {{ artifact.label }} — frozen {{ shortHash(artifact.frozen_sha256) }}, current {{ shortHash(artifact.current_sha256) }}
                </li>
            </ul>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border p-4 text-sm dark:border-gray-800">
                <h2 class="mb-3 font-semibold">Freeze Metadata</h2>
                <p><span class="font-medium">Frozen by:</span> {{ candidate.frozen_by?.name ?? 'CLI/System' }}</p>
                <p><span class="font-medium">Frozen at:</span> {{ candidate.frozen_at ?? '—' }}</p>
                <p><span class="font-medium">Last verified:</span> {{ candidate.verified_at ?? '—' }}</p>
                <p><span class="font-medium">Superseded:</span> {{ candidate.superseded_at ?? '—' }}</p>
                <p class="mt-3 whitespace-pre-wrap text-gray-500">{{ candidate.notes ?? 'No release notes.' }}</p>
            </div>

            <div class="rounded-xl border p-4 text-sm dark:border-gray-800">
                <h2 class="mb-3 font-semibold">Accepted Build</h2>
                <template v-if="candidate.acceptance">
                    <p><span class="font-medium">Acceptance run:</span> <Link :href="route('production-acceptance.show', candidate.acceptance.id)" class="text-brand-600 hover:underline">#{{ candidate.acceptance.id }}</Link></p>
                    <p><span class="font-medium">Status:</span> {{ candidate.acceptance.status }}</p>
                    <p><span class="font-medium">Blocking failures:</span> {{ candidate.acceptance.blocking_failures }}</p>
                    <p><span class="font-medium">Completed:</span> {{ candidate.acceptance.completed_at ?? '—' }}</p>
                </template>
                <p v-else class="text-gray-500">Acceptance metadata is unavailable.</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border dark:border-gray-800">
            <div class="border-b p-4 dark:border-gray-800">
                <h2 class="font-semibold">Frozen Artifacts</h2>
                <p class="text-xs text-gray-500">Each hash is part of the immutable release-candidate fingerprint.</p>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5">
                    <tr><th class="p-3">Artifact</th><th class="p-3">SHA-256</th></tr>
                </thead>
                <tbody>
                    <tr v-for="artifact in candidate.artifacts" :key="artifact.key" class="border-t dark:border-gray-800">
                        <td class="p-3 font-medium">{{ artifact.label }}</td>
                        <td class="p-3 break-all font-mono text-xs">{{ artifact.sha256 ?? 'Not available' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
