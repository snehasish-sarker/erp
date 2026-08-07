<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import type { SettlementPermissions } from '@/Types/customer-settlement';

interface Props {
    documentId: number;
    routeBase: string;
    permissions: SettlementPermissions;
}

const props = defineProps<Props>();

const processing = ref<string | null>(null);
const modalAction = ref<'cancel' | 'reverse' | null>(null);
const reasonForm = useForm({
    reason: '',
    posting_date: new Date().toISOString().slice(0, 10),
});

const perform = (action: 'submit' | 'return-to-draft' | 'approve' | 'post'): void => {
    const labels: Record<typeof action, string> = {
        submit: 'Submit this document for approval?',
        'return-to-draft': 'Return this document to draft?',
        approve: 'Approve this document?',
        post: 'Post this document to the ledgers? This action creates immutable accounting entries.',
    };

    if (!window.confirm(labels[action])) {
        return;
    }

    processing.value = action;
    router.post(
        route(`${props.routeBase}.${action}`, props.documentId),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                processing.value = null;
            },
        },
    );
};

const openReason = (action: 'cancel' | 'reverse'): void => {
    reasonForm.reset();
    reasonForm.clearErrors();
    reasonForm.posting_date = new Date().toISOString().slice(0, 10);
    modalAction.value = action;
};

const closeReason = (): void => {
    if (!reasonForm.processing) {
        modalAction.value = null;
    }
};

const submitReason = (): void => {
    const action = modalAction.value;
    if (!action) {
        return;
    }

    reasonForm.post(
        route(`${props.routeBase}.${action}`, props.documentId),
        {
            preserveScroll: true,
            onSuccess: () => {
                modalAction.value = null;
                reasonForm.reset();
            },
        },
    );
};
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-if="permissions.submit"
            :disabled="processing !== null"
            type="button"
            class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-60"
            @click="perform('submit')"
        >
            {{ processing === 'submit' ? 'Submitting...' : 'Submit' }}
        </button>

        <button
            v-if="permissions.return_to_draft"
            :disabled="processing !== null"
            type="button"
            class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-300"
            @click="perform('return-to-draft')"
        >
            Return to Draft
        </button>

        <button
            v-if="permissions.approve"
            :disabled="processing !== null"
            type="button"
            class="rounded-lg bg-success-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-success-700 disabled:opacity-60"
            @click="perform('approve')"
        >
            {{ processing === 'approve' ? 'Approving...' : 'Approve' }}
        </button>

        <button
            v-if="permissions.post"
            :disabled="processing !== null"
            type="button"
            class="rounded-lg bg-success-700 px-4 py-2.5 text-sm font-medium text-white hover:bg-success-800 disabled:opacity-60"
            @click="perform('post')"
        >
            {{ processing === 'post' ? 'Posting...' : 'Post' }}
        </button>

        <button
            v-if="permissions.cancel"
            type="button"
            class="rounded-lg border border-error-300 px-4 py-2.5 text-sm font-medium text-error-600 dark:border-error-900"
            @click="openReason('cancel')"
        >
            Cancel
        </button>

        <button
            v-if="permissions.reverse"
            type="button"
            class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-error-600"
            @click="openReason('reverse')"
        >
            Reverse
        </button>
    </div>

    <Teleport to="body">
        <div
            v-if="modalAction"
            class="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-950/60 p-4"
            role="dialog"
            aria-modal="true"
            @click.self="closeReason"
        >
            <form
                class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-gray-900"
                @submit.prevent="submitReason"
            >
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ modalAction === 'reverse' ? 'Reverse Document' : 'Cancel Document' }}
                </h2>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{
                        modalAction === 'reverse'
                            ? 'Reversal creates opposite immutable ledger entries.'
                            : 'Cancellation is available only before final posting.'
                    }}
                </p>

                <div v-if="modalAction === 'reverse'" class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Reversal Posting Date
                    </label>
                    <input
                        v-model="reasonForm.posting_date"
                        type="date"
                        class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm dark:border-gray-700 dark:text-white"
                    />
                    <p v-if="reasonForm.errors.posting_date" class="mt-1 text-xs text-error-500">
                        {{ reasonForm.errors.posting_date }}
                    </p>
                </div>

                <div class="mt-5">
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Reason
                    </label>
                    <textarea
                        v-model="reasonForm.reason"
                        rows="4"
                        maxlength="500"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm dark:border-gray-700 dark:text-white"
                        placeholder="Enter a clear audit reason"
                    />
                    <p v-if="reasonForm.errors.reason" class="mt-1 text-xs text-error-500">
                        {{ reasonForm.errors.reason }}
                    </p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        :disabled="reasonForm.processing"
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium dark:border-gray-700"
                        @click="closeReason"
                    >
                        Close
                    </button>
                    <button
                        :disabled="reasonForm.processing || reasonForm.reason.trim() === ''"
                        type="submit"
                        class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-medium text-white disabled:opacity-60"
                    >
                        {{ reasonForm.processing ? 'Processing...' : 'Confirm' }}
                    </button>
                </div>
            </form>
        </div>
    </Teleport>
</template>
