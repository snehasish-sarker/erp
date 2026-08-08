<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import type { PaginationMeta, ReleaseCandidateRow } from '@/Types/operations';

defineOptions({ layout: ErpLayout });

const props = defineProps<{
    candidates: { data: ReleaseCandidateRow[]; meta: PaginationMeta };
    can_create: boolean;
}>();

const form = useForm<{ version: string; notes: string }>({
    version: '',
    notes: '',
});

const freeze = (): void => {
    if (!window.confirm('Freeze this exact accepted code/build as the next release candidate?')) {
        return;
    }

    form.post(route('release-candidates.store'), {
        preserveScroll: true,
    });
};

const shortHash = (value: string | null): string => value === null ? '—' : value.slice(0, 12);
</script>

<template>
    <Head title="Release Candidates" />

    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Release Candidates</h1>
                <p class="text-sm text-gray-500">Freeze only a project fingerprint that exactly matches the latest passed production acceptance run.</p>
            </div>
            <div class="flex gap-2">
                <Link :href="route('production-acceptance.index')" class="rounded-lg border px-3 py-2 text-sm">Production Acceptance</Link>
                <Link :href="route('operations.preflight')" class="rounded-lg border px-3 py-2 text-sm">Preflight</Link>
            </div>
        </div>

        <form v-if="props.can_create" class="rounded-xl border p-4 dark:border-gray-800" @submit.prevent="freeze">
            <div class="grid gap-4 lg:grid-cols-[220px_1fr_auto] lg:items-end">
                <label class="block">
                    <span class="text-sm font-medium">Version</span>
                    <input v-model="form.version" required maxlength="64" placeholder="1.0.0-rc.1" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-900" />
                    <span v-if="form.errors.version" class="mt-1 block text-xs text-error-600">{{ form.errors.version }}</span>
                </label>
                <label class="block">
                    <span class="text-sm font-medium">Notes</span>
                    <input v-model="form.notes" maxlength="2000" placeholder="Release-candidate notes" class="mt-1 w-full rounded-lg border px-3 py-2 dark:border-gray-700 dark:bg-gray-900" />
                    <span v-if="form.errors.notes" class="mt-1 block text-xs text-error-600">{{ form.errors.notes }}</span>
                    <span v-if="form.errors.acceptance" class="mt-1 block text-xs text-error-600">{{ form.errors.acceptance }}</span>
                </label>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white disabled:opacity-50">
                    {{ form.processing ? 'Freezing…' : 'Freeze Candidate' }}
                </button>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border dark:border-gray-800">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-white/5">
                    <tr>
                        <th class="p-3">Version</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Verification</th>
                        <th class="p-3">Fingerprint</th>
                        <th class="p-3">Git</th>
                        <th class="p-3">Acceptance</th>
                        <th class="p-3">Frozen</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="candidate in props.candidates.data" :key="candidate.id" class="border-t dark:border-gray-800">
                        <td class="p-3 font-medium">
                            <Link :href="route('release-candidates.show', candidate.id)" class="text-brand-600 hover:underline">{{ candidate.version }}</Link>
                        </td>
                        <td class="p-3 capitalize">{{ candidate.status }}</td>
                        <td class="p-3 font-medium" :class="candidate.verification_status === 'matched' ? 'text-success-600' : 'text-error-600'">
                            {{ candidate.verification_status }}
                        </td>
                        <td class="p-3 font-mono text-xs">{{ shortHash(candidate.project_fingerprint) }}</td>
                        <td class="p-3 font-mono text-xs">{{ shortHash(candidate.git_commit) }}</td>
                        <td class="p-3">
                            <Link v-if="candidate.acceptance" :href="route('production-acceptance.show', candidate.acceptance.id)" class="text-brand-600 hover:underline">
                                #{{ candidate.acceptance.id }} · {{ candidate.acceptance.status }}
                            </Link>
                            <span v-else>—</span>
                        </td>
                        <td class="p-3 text-gray-500">{{ candidate.frozen_at ?? '—' }}</td>
                    </tr>
                    <tr v-if="props.candidates.data.length === 0">
                        <td colspan="7" class="p-6 text-center text-gray-500">No release candidate has been frozen yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-sm text-gray-500">
            {{ props.candidates.meta.total }} release candidate{{ props.candidates.meta.total === 1 ? '' : 's' }} recorded.
        </p>
    </div>
</template>
