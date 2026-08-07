<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import type { LifecyclePermissions, TreasuryStatus } from '@/Types/treasury';

const props = defineProps<{
    documentId: number;
    routePrefix: 'treasury-transfers' | 'treasury-adjustments';
    status: TreasuryStatus;
    can: LifecyclePermissions;
}>();

const processing = ref<string | null>(null);

const act = (action: string, confirmation: string, payload: Record<string, string> = {}): void => {
    if (!window.confirm(confirmation)) return;
    processing.value = action;
    router.post(route(`${props.routePrefix}.${action}`, props.documentId), payload, {
        preserveScroll: true,
        onFinish: () => { processing.value = null; },
    });
};

const reasonAction = (action: 'cancel' | 'reverse'): void => {
    const reason = window.prompt(`Enter the reason to ${action} this document:`)?.trim() ?? '';
    if (reason === '') return;
    const payload: Record<string, string> = { reason };
    if (action === 'reverse') {
        payload.posting_date = new Date().toISOString().slice(0, 10);
    }
    act(action, `Confirm ${action} of this document?`, payload);
};
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button v-if="can.submit" :disabled="processing !== null" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" type="button" @click="act('submit', 'Submit this document for approval?')">Submit</button>
        <button v-if="can.return_to_draft" :disabled="processing !== null" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium dark:border-gray-700" type="button" @click="act('return-to-draft', 'Return this document to draft?')">Return to Draft</button>
        <button v-if="can.approve" :disabled="processing !== null" class="rounded-lg bg-success-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" type="button" @click="act('approve', 'Approve this document?')">Approve</button>
        <button v-if="can.post" :disabled="processing !== null" class="rounded-lg bg-success-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" type="button" @click="act('post', 'Post this document to the General Ledger?')">Post</button>
        <button v-if="can.cancel" :disabled="processing !== null" class="rounded-lg border border-error-300 px-4 py-2 text-sm font-medium text-error-600 disabled:opacity-50" type="button" @click="reasonAction('cancel')">Cancel</button>
        <button v-if="can.reverse" :disabled="processing !== null" class="rounded-lg bg-error-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50" type="button" @click="reasonAction('reverse')">Reverse</button>
    </div>
</template>
