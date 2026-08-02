<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import DocumentSequenceForm from '@/Pages/DocumentNumbering/Partials/DocumentSequenceForm.vue';
import type {
    DocumentNumberPreviewContext,
    DocumentResetPolicy,
    DocumentSequenceBranch,
    DocumentSequenceOption,
    DocumentSequenceStatus,
    DocumentTypeOption,
} from '@/Types/document-numbering';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    branchOptions: DocumentSequenceBranch[];
    documentTypeOptions: DocumentTypeOption[];
    resetPolicyOptions: DocumentSequenceOption<DocumentResetPolicy>[];
    statusOptions: DocumentSequenceOption<DocumentSequenceStatus>[];
    previewContext: DocumentNumberPreviewContext;
}>();
</script>

<template>
    <Head title="Create Document Sequence" />

    <DocumentSequenceForm
        mode="create"
        :branch-options="branchOptions"
        :document-type-options="documentTypeOptions"
        :reset-policy-options="resetPolicyOptions"
        :status-options="statusOptions"
        :preview-context="previewContext"
    />
</template>