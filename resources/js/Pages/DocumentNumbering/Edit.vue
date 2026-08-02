<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ErpLayout from '@/Layouts/ErpLayout.vue';
import DocumentSequenceForm from '@/Pages/DocumentNumbering/Partials/DocumentSequenceForm.vue';
import type {
    DocumentNumberPreviewContext,
    DocumentResetPolicy,
    DocumentSequenceBranch,
    DocumentSequenceOption,
    DocumentSequenceRecord,
    DocumentSequenceStatus,
    DocumentTypeOption,
} from '@/Types/document-numbering';

defineOptions({
    layout: ErpLayout,
});

defineProps<{
    documentSequence: DocumentSequenceRecord;
    branchOptions: DocumentSequenceBranch[];
    documentTypeOptions: DocumentTypeOption[];
    resetPolicyOptions: DocumentSequenceOption<DocumentResetPolicy>[];
    statusOptions: DocumentSequenceOption<DocumentSequenceStatus>[];
    previewContext: DocumentNumberPreviewContext;
}>();
</script>

<template>
    <Head :title="`Edit ${documentSequence.name}`" />

    <DocumentSequenceForm
        mode="edit"
        :document-sequence="documentSequence"
        :branch-options="branchOptions"
        :document-type-options="documentTypeOptions"
        :reset-policy-options="resetPolicyOptions"
        :status-options="statusOptions"
        :preview-context="previewContext"
    />
</template>