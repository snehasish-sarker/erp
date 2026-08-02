<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { useAuthorization } from '@/Composables/useAuthorization';
import type {
    AuditLogExportFilters,
    ExportRequestFormData,
} from '@/Types/export';

interface AuditLogPageFilters {
    search?: string;
    event?: string;
    subject_type?: string;
    actor?: string | number | null;
    date_from?: string;
    date_to?: string;
    direction?: 'asc' | 'desc';
}

const props = defineProps<{
    filters: AuditLogPageFilters;
}>();

const { can } = useAuthorization();

const form = useForm<ExportRequestFormData>({
    export_type: 'audit_logs',
    format: 'csv',

    filters: {
        search: '',
        event: '',
        subject_type: '',
        actor: '',
        date_from: '',
        date_to: '',
        direction: 'desc',
    },
});

const exportAuditLogs = (): void => {
    const filters: AuditLogExportFilters = {
        search:
            props.filters.search?.trim() ?? '',

        event:
            props.filters.event?.trim() ?? '',

        subject_type:
            props.filters.subject_type?.trim()
            ?? '',

        actor:
            props.filters.actor === null
            || props.filters.actor === undefined
                ? ''
                : String(props.filters.actor),

        date_from:
            props.filters.date_from ?? '',

        date_to:
            props.filters.date_to ?? '',

        direction:
            props.filters.direction === 'asc'
                ? 'asc'
                : 'desc',
    };

    form.filters = filters;

    form.post(
        '/erp/exports',
        {
            preserveScroll: true,
        },
    );
};
</script>

<template>
    <button
        v-if="can('exports.create')"
        type="button"
        class="inline-flex h-11 items-center justify-center rounded-lg border border-gray-300 bg-white px-5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]"
        :disabled="form.processing"
        @click="exportAuditLogs"
    >
        {{
            form.processing
                ? 'Queueing export...'
                : 'Export current results'
        }}
    </button>
</template>